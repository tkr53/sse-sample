package model

import (
	"sync"
	"time"
)

type JobStatus string

const (
	JobStatusPending    JobStatus = "pending"
	JobStatusProcessing JobStatus = "processing"
	JobStatusCompleted  JobStatus = "completed"
	JobStatusFailed     JobStatus = "failed"
)

type FileInfo struct {
	ID        string    `json:"id"`
	FileName  string    `json:"fileName"`
	FilePath  string    `json:"filePath"`
	FileSize  int64     `json:"fileSize"`
	CreatedAt time.Time `json:"createdAt"`
	Status    string    `json:"status"`
}

type Job struct {
	ID        string
	IDs       []string
	Status    JobStatus
	Files     []FileInfo
	CreatedAt time.Time
	UpdatedAt time.Time
	mu        sync.RWMutex
}

func NewJob(id string, ids []string) *Job {
	return &Job{
		ID:        id,
		IDs:       ids,
		Status:    JobStatusPending,
		Files:     make([]FileInfo, 0),
		CreatedAt: time.Now(),
		UpdatedAt: time.Now(),
	}
}

func (j *Job) AddFile(file FileInfo) {
	j.mu.Lock()
	defer j.mu.Unlock()
	j.Files = append(j.Files, file)
	j.UpdatedAt = time.Now()
}

func (j *Job) SetStatus(status JobStatus) {
	j.mu.Lock()
	defer j.mu.Unlock()
	j.Status = status
	j.UpdatedAt = time.Now()
}

func (j *Job) GetStatus() JobStatus {
	j.mu.RLock()
	defer j.mu.RUnlock()
	return j.Status
}

func (j *Job) GetFiles() []FileInfo {
	j.mu.RLock()
	defer j.mu.RUnlock()
	return append([]FileInfo{}, j.Files...)
}