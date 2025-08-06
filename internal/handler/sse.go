package handler

import (
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"sse-pdf-generator/internal/model"
	"sse-pdf-generator/internal/service"
)

type SSEHandler struct {
	jobManager  *service.JobManager
	pdfService  *service.PDFService
}

func NewSSEHandler(jm *service.JobManager, ps *service.PDFService) *SSEHandler {
	return &SSEHandler{
		jobManager:  jm,
		pdfService:  ps,
	}
}

type CreateJobRequest struct {
	IDs []string `json:"ids"`
}

type CreateJobResponse struct {
	JobID string `json:"jobId"`
}

func (h *SSEHandler) CreateJob(w http.ResponseWriter, r *http.Request) {
	var req CreateJobRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if len(req.IDs) == 0 {
		http.Error(w, "IDs cannot be empty", http.StatusBadRequest)
		return
	}

	job := h.jobManager.CreateJob(req.IDs)
	
	go h.processJob(job)

	resp := CreateJobResponse{JobID: job.ID}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(resp)
}

func (h *SSEHandler) processJob(job *model.Job) {
	job.SetStatus(model.JobStatusProcessing)

	for _, id := range job.IDs {
		fileInfo, err := h.pdfService.GeneratePDF(id)
		if err != nil {
			fileInfo = model.FileInfo{
				ID:        id,
				Status:    "failed",
				CreatedAt: time.Now(),
			}
		}
		job.AddFile(fileInfo)
	}

	job.SetStatus(model.JobStatusCompleted)
}

func (h *SSEHandler) StreamJob(w http.ResponseWriter, r *http.Request) {
	jobID := r.URL.Query().Get("jobId")
	if jobID == "" {
		http.Error(w, "jobId parameter is required", http.StatusBadRequest)
		return
	}

	job, err := h.jobManager.GetJob(jobID)
	if err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, "Streaming unsupported", http.StatusInternalServerError)
		return
	}

	clientGone := r.Context().Done()
	ticker := time.NewTicker(500 * time.Millisecond)
	defer ticker.Stop()

	lastFileCount := 0

	for {
		select {
		case <-clientGone:
			return
		case <-ticker.C:
			files := job.GetFiles()
			status := job.GetStatus()

			if len(files) > lastFileCount {
				for i := lastFileCount; i < len(files); i++ {
					data, _ := json.Marshal(files[i])
					fmt.Fprintf(w, "event: file\ndata: %s\n\n", data)
				}
				lastFileCount = len(files)
				flusher.Flush()
			}

			if status == model.JobStatusCompleted || status == model.JobStatusFailed {
				fmt.Fprintf(w, "event: complete\ndata: {\"status\":\"%s\",\"totalFiles\":%d}\n\n", status, len(files))
				flusher.Flush()
				return
			}
		}
	}
}