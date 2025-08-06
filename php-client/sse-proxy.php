<?php
/**
 * SSE Proxy
 * GoサーバーのSSEストリームをクライアントに転送
 */

// タイムアウト無制限
set_time_limit(0);

// SSEヘッダー設定
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no'); // nginx用

// PHPのすべてのバッファリングを完全に無効化
while (ob_get_level()) {
    ob_end_clean();
}
@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
ob_implicit_flush(1);

// Apache用の追加設定
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
    @apache_setenv('dont-vary', '1');
}

// パラメータ取得
$serverUrl = $_GET['serverUrl'] ?? 'http://localhost:8080';
$jobId = $_GET['jobId'] ?? '';

if (empty($jobId)) {
    echo "event: error\n";
    echo "data: {\"error\": \"jobId is required\"}\n\n";
    flush();
    exit;
}

// GoサーバーのSSEエンドポイントに接続
$url = rtrim($serverUrl, '/') . '/api/stream?jobId=' . urlencode($jobId);

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Cache-Control: no-cache',
            'Accept: text/event-stream'
        ],
        'timeout' => 300  // 5分のタイムアウト
    ]
]);

$stream = @fopen($url, 'r', false, $context);

if (!$stream) {
    echo "event: error\n";
    echo "data: {\"error\": \"Failed to connect to SSE stream\"}\n\n";
    flush();
    exit;
}

// ストリームを非ブロッキングモードに設定
stream_set_blocking($stream, false);

// ストリームを転送
$buffer = '';
while (!feof($stream) || !empty($buffer)) {
    // データ読み込み（非ブロッキング）
    $chunk = fread($stream, 1024);
    
    if ($chunk !== false && $chunk !== '') {
        $buffer .= $chunk;
    }
    
    // 改行で分割して処理
    while (($pos = strpos($buffer, "\n")) !== false) {
        $line = substr($buffer, 0, $pos + 1);
        $buffer = substr($buffer, $pos + 1);
        
        // クライアントに送信
        echo $line;
        
        // 空行の場合は即座にフラッシュ（イベント区切り）
        if (trim($line) === '') {
            // 強制的に出力をフラッシュ
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            
            // パディングは送らない（SSEフォーマットを崩す）
        }
    }
    
    // 接続チェック
    if (connection_aborted()) {
        break;
    }
    
    // CPU使用率を下げるための小休止
    usleep(10000); // 10ms
}

fclose($stream);