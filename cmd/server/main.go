package main

import (
	"log"
	"net/http"

	"github.com/gorilla/mux"
	"sse-pdf-generator/internal/handler"
	"sse-pdf-generator/internal/service"
)

func main() {
	jobManager := service.NewJobManager()
	pdfService := service.NewPDFService("./generated")
	sseHandler := handler.NewSSEHandler(jobManager, pdfService)

	r := mux.NewRouter()
	
	r.HandleFunc("/api/job", sseHandler.CreateJob).Methods("POST")
	r.HandleFunc("/api/stream", sseHandler.StreamJob).Methods("GET")
	
	r.PathPrefix("/").Handler(http.FileServer(http.Dir("./static/")))

	log.Println("Server starting on :8080")
	if err := http.ListenAndServe(":8080", r); err != nil {
		log.Fatal("Server failed to start:", err)
	}
}