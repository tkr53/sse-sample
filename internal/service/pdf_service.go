package service

import (
	"fmt"
	"math/rand"
	"os"
	"path/filepath"
	"time"

	"github.com/jung-kurt/gofpdf"
	"sse-pdf-generator/internal/model"
)

type PDFService struct {
	outputDir string
}

func NewPDFService(outputDir string) *PDFService {
	os.MkdirAll(outputDir, 0755)
	return &PDFService{
		outputDir: outputDir,
	}
}

func (ps *PDFService) GeneratePDF(id string) (model.FileInfo, error) {
	// 擬似的な処理時間を追加（1〜3秒のランダムな遅延）
	delay := time.Duration(rand.Intn(2000)+1000) * time.Millisecond
	time.Sleep(delay)
	
	pdf := gofpdf.New("P", "mm", "A4", "")
	pdf.AddPage()
	pdf.SetFont("Arial", "B", 16)
	
	pdf.Cell(40, 10, fmt.Sprintf("Document for ID: %s", id))
	pdf.Ln(20)
	
	pdf.SetFont("Arial", "", 12)
	pdf.MultiCell(0, 10, fmt.Sprintf("This is a sample PDF document generated for ID: %s\n\nGenerated at: %s\n\nSample Content:\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.", 
		id, 
		time.Now().Format("2006-01-02 15:04:05")), "", "", false)
	
	pdf.Ln(10)
	pdf.SetFont("Arial", "I", 10)
	pdf.Cell(0, 10, fmt.Sprintf("Page 1 of 1"))
	
	fileName := fmt.Sprintf("document_%s_%d.pdf", id, time.Now().Unix())
	filePath := filepath.Join(ps.outputDir, fileName)
	
	err := pdf.OutputFileAndClose(filePath)
	if err != nil {
		return model.FileInfo{}, fmt.Errorf("failed to generate PDF: %w", err)
	}
	
	fileInfo, err := os.Stat(filePath)
	if err != nil {
		return model.FileInfo{}, fmt.Errorf("failed to get file info: %w", err)
	}
	
	return model.FileInfo{
		ID:        id,
		FileName:  fileName,
		FilePath:  filePath,
		FileSize:  fileInfo.Size(),
		CreatedAt: time.Now(),
		Status:    "success",
	}, nil
}