#!/usr/bin/env php
<?php
/**
 * CLI Client Test Script
 * 異なる方法でのSSEストリーミングをテスト
 */

require_once 'cli-client.php';

function testSSEMethods() {
    $serverUrl = 'http://localhost:8080';
    $testIds = ['test001', 'test002'];
    
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    echo "\033[1mSSE Methods Comparison Test\033[0m\n";
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    
    $client = new SSEClient($serverUrl, true);
    
    try {
        // ジョブ作成
        echo "\n\033[32m1. Creating test job...\033[0m\n";
        $jobId = $client->createJob($testIds);
        echo "Job ID: \033[36m$jobId\033[0m\n";
        
        // 方法1: cURLベース
        if (function_exists('curl_init')) {
            echo "\n\033[32m2. Testing cURL-based streaming...\033[0m\n";
            $startTime = microtime(true);
            $client->streamJob($jobId);
            $curlTime = microtime(true) - $startTime;
            echo "\033[36mcURL method completed in: " . round($curlTime, 2) . "s\033[0m\n";
        } else {
            echo "\n\033[31m2. cURL not available\033[0m\n";
        }
        
    } catch (Exception $e) {
        echo "\033[31m✗ Error: " . $e->getMessage() . "\033[0m\n";
    }
    
    echo "\n\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
    echo "\033[32mTest completed!\033[0m\n";
    echo "\033[34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
}

function benchmarkMethods() {
    echo "\n\033[33mBenchmarking different SSE methods...\033[0m\n";
    
    $methods = [
        'cURL' => function_exists('curl_init'),
        'file_get_contents' => function_exists('file_get_contents'),
        'fopen/fgets' => function_exists('fopen')
    ];
    
    foreach ($methods as $method => $available) {
        $status = $available ? "\033[32m✓ Available\033[0m" : "\033[31m✗ Not available\033[0m";
        echo "  $method: $status\n";
    }
    
    echo "\n\033[36mRecommendation:\033[0m\n";
    echo "  - Use cURL for best performance and control\n";
    echo "  - Use fallback for environments without cURL\n";
}

// CLI実行時のメイン処理
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'test';
    
    switch ($command) {
        case 'test':
            testSSEMethods();
            break;
        case 'benchmark':
            benchmarkMethods();
            break;
        case 'help':
            echo "Usage: php cli-test.php [test|benchmark|help]\n";
            echo "  test      - Run SSE streaming test\n";
            echo "  benchmark - Show available methods\n";
            echo "  help      - Show this help\n";
            break;
        default:
            echo "Unknown command: $command\n";
            echo "Use 'php cli-test.php help' for usage information.\n";
    }
}