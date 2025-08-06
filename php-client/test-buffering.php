<?php
/**
 * バッファリングテストスクリプト
 * PHPのSSEバッファリング問題を診断
 */

// バッファリング設定の診断
echo "=== PHP Buffering Configuration ===\n";
echo "output_buffering: " . ini_get('output_buffering') . "\n";
echo "zlib.output_compression: " . ini_get('zlib.output_compression') . "\n";
echo "implicit_flush: " . ini_get('implicit_flush') . "\n";
echo "ob_get_level: " . ob_get_level() . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "\n";

// Webサーバー情報
if (php_sapi_name() !== 'cli') {
    echo "=== Web Server Info ===\n";
    echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
    echo "\n";
}

// SSEテスト
if (php_sapi_name() !== 'cli') {
    // Webモード
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    
    // すべてのバッファをクリア
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    ob_implicit_flush(1);
    
    echo "event: test\n";
    echo "data: Starting SSE test...\n\n";
    flush();
    
    for ($i = 1; $i <= 5; $i++) {
        echo "event: progress\n";
        echo "data: {\"count\": $i, \"time\": \"" . date('H:i:s') . "\"}\n\n";
        
        // 様々なフラッシュ方法を試す
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        
        // パディング（一部の環境で必要）
        echo str_repeat(' ', 4096) . "\n";
        flush();
        
        sleep(1);
    }
    
    echo "event: complete\n";
    echo "data: Test completed\n\n";
    flush();
    
} else {
    // CLIモード
    echo "=== Testing SSE Stream ===\n";
    echo "This script should be run in a web browser for SSE testing.\n";
    echo "Access: http://localhost:8081/test-buffering.php\n";
}