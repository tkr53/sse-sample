package service

import (
	"fmt"
	"sync"

	"github.com/google/uuid"
	"sse-pdf-generator/internal/model"
)

type JobManager struct {
	jobs map[string]*model.Job
	mu   sync.RWMutex
}

func NewJobManager() *JobManager {
	return &JobManager{
		jobs: make(map[string]*model.Job),
	}
}

func (jm *JobManager) CreateJob(ids []string) *model.Job {
	jm.mu.Lock()
	defer jm.mu.Unlock()

	jobID := uuid.New().String()
	job := model.NewJob(jobID, ids)
	jm.jobs[jobID] = job
	return job
}

func (jm *JobManager) GetJob(jobID string) (*model.Job, error) {
	jm.mu.RLock()
	defer jm.mu.RUnlock()

	job, exists := jm.jobs[jobID]
	if !exists {
		return nil, fmt.Errorf("job not found: %s", jobID)
	}
	return job, nil
}

func (jm *JobManager) DeleteJob(jobID string) {
	jm.mu.Lock()
	defer jm.mu.Unlock()
	delete(jm.jobs, jobID)
}