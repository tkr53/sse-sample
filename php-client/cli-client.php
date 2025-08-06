#!/usr/bin/env php
<?php
/**
 * SSE PDF Generator - PHP CLI Client
 * コマンドラインからSSE APIを利用するクライアント
 */

class SSEClient {
    private $apiUrl;
    private $verbose;
    
    public function __construct($apiUrl = 'http://localhost:8080', $verbose = false) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->verbose = $verbose;
    }
    
    /**
     * ジョブを作成
     */
    public function createJob(array $ids) {
        $this->log("Creating job with IDs: " . implode(', ', $ids));
        
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
        $this->log("Job created with ID: " . $result['jobId']);
        
        return $result['jobId'];
    }
    
    /**
     * SSEストリームを購読（改良版）
     */
    public function streamJob($jobId) {
        $this->log("Connecting to SSE stream for job: $jobId");
        
        $url = $this->apiUrl . '/api/stream?jobId=' . urlencode($jobId);
        
        // cURLを使用してより細かい制御
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // 小さなバッファ
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cache-Control: no-cache',
            'Accept: text/event-stream',
            'Connection: keep-alive'
        ]);
        
        $this->log("Connected to SSE stream");
        echo "\n";
        
        $eventType = '';
        $eventData = '';
        $fileCount = 0;
        $startTime = time();
        $buffer = '';
        
        // 終了フラグ
        $completed = false;
        
        // ストリーミングデータの処理
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$eventType, &$eventData, &$fileCount, &$startTime, &$buffer, &$completed) {
            // 既に完了している場合は処理を停止
            if ($completed) {
                return 0;
            }
            
            $dataLength = strlen($data);
            $buffer .= $data;
            
            // 改行で分割して処理
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                if (strpos($line, 'event: ') === 0) {
                    $eventType = trim(substr($line, 7));
                } elseif (strpos($line, 'data: ') === 0) {
                    $eventData = trim(substr($line, 6));
                } elseif (trim($line) === '' && !empty($eventData)) {
                    try {
                        // イベント処理
                        $this->handleEvent($eventType, $eventData, $fileCount, $startTime);
                        
                        if ($eventType === 'file') {
                            $fileCount++;
                        } elseif ($eventType === 'complete') {
                            $completed = true;
                            return 0; // 正常終了
                        }
                        
                        $eventType = '';
                        $eventData = '';
                    } catch (Exception $e) {
                        // イベント処理エラーでも継続
                        echo "\033[33mWarning: " . $e->getMessage() . "\033[0m\n";
                        $eventType = '';
                        $eventData = '';
                    }
                }
            }
            
            // 正常に処理したデータ長を返す（重要）
            return $dataLength;
        });
        
        // 実行
        $result = curl_exec($ch);
        $curlError = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        // エラーチェック
        if ($curlError !== 0) {
            throw new Exception("cURL error ($curlError): $curlErrorMsg");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP error: $httpCode");
        }
        
        return $result !== false;
    }
    
    /**
     * file_get_contents版のフォールバック（デバッグ用）
     */
    public function streamJobFallback($jobId) {
        $this->log("Using fallback method for job: $jobId");
        
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
        
        // 非ブロッキングモードに設定
        stream_set_blocking($stream, false);
        
        $this->log("Connected to SSE stream (fallback mode)");
        echo "\n";
        
        $eventType = '';
        $eventData = '';
        $fileCount = 0;
        $startTime = time();
        $buffer = '';
        $lastActivity = time();
        
        while (!feof($stream)) {
            $chunk = fread($stream, 1024);
            
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                $lastActivity = time();
                
                // 改行で分割して処理
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    
                    if (strpos($line, 'event: ') === 0) {
                        $eventType = trim(substr($line, 7));
                    } elseif (strpos($line, 'data: ') === 0) {
                        $eventData = trim(substr($line, 6));
                    } elseif (trim($line) === '' && !empty($eventData)) {
                        // イベント処理
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
            } else {
                // データがない場合は少し待機
                usleep(100000); // 100ms
                
                // タイムアウトチェック（5分間データなし）
                if (time() - $lastActivity > 300) {
                    echo "\033[33m⚠ Connection timeout\033[0m\n";
                    break;
                }
            }
        }
        
        fclose($stream);
        return false;
    }
    
    /**
     * イベント処理
     */
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
    
    /**
     * バイト数をフォーマット
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * ログ出力
     */
    private function log($message) {
        if ($this->verbose) {
            echo "[" . date('Y-m-d H:i:s') . "] $message\n";
        }
    }
}

/**
 * メイン処理
 */
function main($argv) {
    // コマンドライン引数の解析
    $options = getopt('h::v', ['help::', 'verbose', 'url:', 'ids:', 'fallback']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showHelp();
        exit(0);
    }
    
    $verbose = isset($options['v']) || isset($options['verbose']);
    $url = $options['url'] ?? 'http://localhost:8080';
    $idsParam = $options['ids'] ?? null;
    $forceFallback = isset($options['fallback']);
    
    // IDsの処理
    if ($idsParam) {
        $ids = array_map('trim', explode(',', $idsParam));
    } else {
        // デフォルトのIDs
        $ids = ['doc001', 'doc002', 'doc003', 'doc004', 'doc005'];
        echo "Using default IDs: " . implode(', ', $ids) . "\n";
    }
    
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    echo "\033[1mSSE PDF Generator - PHP Client\033[0m\n";
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    echo "Server: $url\n";
    echo "IDs: " . implode(', ', $ids) . "\n";
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";
    
    try {
        $client = new SSEClient($url, $verbose);
        
        // ジョブ作成
        echo "Creating job...\n";
        $jobId = $client->createJob($ids);
        echo "Job ID: \033[36m$jobId\033[0m\n\n";
        
        // SSEストリーミング
        echo "Starting SSE stream...\n";
        
        // ストリーミング方法の選択
        if ($forceFallback || !function_exists('curl_init')) {
            if ($forceFallback) {
                echo "\033[33m⚠ Using fallback method by request\033[0m\n";
            } else {
                echo "\033[33m⚠ cURL not available, using fallback method\033[0m\n";
            }
            $client->streamJobFallback($jobId);
        } else {
            $client->streamJob($jobId);
        }
        
    } catch (Exception $e) {
        echo "\033[31m✗ Error: " . $e->getMessage() . "\033[0m\n";
        exit(1);
    }
}

function showHelp() {
    echo <<<HELP
SSE PDF Generator - PHP CLI Client

Usage: php cli-client.php [options]

Options:
    -h, --help      Show this help message
    -v, --verbose   Enable verbose logging
    --url=URL       API server URL (default: http://localhost:8080)
    --ids=IDS       Comma-separated list of IDs (default: doc001,doc002,doc003,doc004,doc005)
    --fallback      Force use of fallback method (file_get_contents instead of cURL)

Examples:
    php cli-client.php
    php cli-client.php --ids=doc001,doc002,doc003
    php cli-client.php --url=http://api.example.com --ids=test1,test2
    php cli-client.php -v --ids=doc001
    php cli-client.php --fallback --ids=doc001,doc002

HELP;
}

// スクリプト実行
if (php_sapi_name() === 'cli') {
    main($argv);
}