#!/usr/bin/env php
<?php
/**
 * Simple CLI Client
 * シンプルなfopen/fgets実装（デバッグ用）
 */

class SimpleSSEClient {
    private $apiUrl;
    
    public function __construct($apiUrl = 'http://localhost:8080') {
        $this->apiUrl = rtrim($apiUrl, '/');
    }
    
    public function createJob(array $ids) {
        echo "Creating job with IDs: " . implode(', ', $ids) . "\n";
        
        $data = json_encode(['ids' => $ids]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ],
                'content' => $data
            ]
        ]);
        
        $response = @file_get_contents($this->apiUrl . '/api/job', false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to create job");
        }
        
        $result = json_decode($response, true);
        echo "Job created with ID: " . $result['jobId'] . "\n";
        
        return $result['jobId'];
    }
    
    public function streamJob($jobId) {
        echo "Connecting to SSE stream for job: $jobId\n";
        
        $url = $this->apiUrl . '/api/stream?jobId=' . urlencode($jobId);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Cache-Control: no-cache',
                    'Accept: text/event-stream'
                ],
                'timeout' => 300
            ]
        ]);
        
        $stream = @fopen($url, 'r', false, $context);
        
        if (!$stream) {
            throw new Exception("Failed to connect to SSE stream");
        }
        
        echo "Connected to SSE stream\n\n";
        
        $eventType = '';
        $eventData = '';
        $fileCount = 0;
        $startTime = time();
        
        // ブロッキングモードで行単位読み込み
        while (!feof($stream)) {
            $line = fgets($stream);
            
            if ($line === false) {
                // タイムアウトやエラー
                break;
            }
            
            $line = rtrim($line, "\r\n");
            
            if (strpos($line, 'event: ') === 0) {
                $eventType = trim(substr($line, 7));
                echo "DEBUG: Event type: $eventType\n";
            } elseif (strpos($line, 'data: ') === 0) {
                $eventData = trim(substr($line, 6));
                echo "DEBUG: Event data: $eventData\n";
            } elseif ($line === '' && !empty($eventData)) {
                // イベント完了
                echo "DEBUG: Processing event: $eventType\n";
                $this->handleEvent($eventType, $eventData, $fileCount, $startTime);
                
                if ($eventType === 'file') {
                    $fileCount++;
                } elseif ($eventType === 'complete') {
                    fclose($stream);
                    return true;
                }
                
                $eventType = '';
                $eventData = '';
            }
        }
        
        fclose($stream);
        return false;
    }
    
    private function handleEvent($eventType, $eventData, &$fileCount, $startTime) {
        $data = json_decode($eventData, true);
        
        if ($eventType === 'file') {
            $fileCount++;
            $elapsed = time() - $startTime;
            
            echo "\033[32m✓\033[0m File #$fileCount generated\n";
            echo "  ID: {$data['id']}\n";
            echo "  File: {$data['fileName']}\n";
            echo "  Size: " . $this->formatBytes($data['fileSize']) . "\n";
            echo "  Status: {$data['status']}\n";
            echo "  Time: {$elapsed}s\n";
            echo "\n";
            
        } elseif ($eventType === 'complete') {
            $elapsed = time() - $startTime;
            echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
            echo "\033[32m✓ Job Completed!\033[0m\n";
            echo "  Total files: {$data['totalFiles']}\n";
            echo "  Status: {$data['status']}\n";
            echo "  Total time: {$elapsed}s\n";
            echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// メイン処理
if (php_sapi_name() === 'cli') {
    $url = $argv[1] ?? 'http://localhost:8080';
    $idsParam = $argv[2] ?? 'doc001,doc002,doc003';
    
    $ids = array_map('trim', explode(',', $idsParam));
    
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    echo "\033[1mSimple SSE PDF Generator Client\033[0m\n";
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    echo "Server: $url\n";
    echo "IDs: " . implode(', ', $ids) . "\n";
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";
    
    try {
        $client = new SimpleSSEClient($url);
        
        // ジョブ作成
        $jobId = $client->createJob($ids);
        
        echo "\nStarting SSE stream...\n";
        $client->streamJob($jobId);
        
    } catch (Exception $e) {
        echo "\033[31m✗ Error: " . $e->getMessage() . "\033[0m\n";
        exit(1);
    }
}