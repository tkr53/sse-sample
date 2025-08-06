<?php
/**
 * SSE Direct Client - 改善版
 * バッファリング問題を解決した直接接続クライアント
 */

// 完全なバッファリング無効化
set_time_limit(0);
ignore_user_abort(true);

// すべての出力バッファをクリア
while (ob_get_level()) {
    ob_end_clean();
}

// PHPとWebサーバーのバッファリングを無効化
@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');

// Apache特有の設定
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
    @apache_setenv('dont-vary', '1');
}

// SSEヘッダー
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no'); // Nginx

// 初期パディング送信（一部のブラウザ/プロキシ対策）
echo ':' . str_repeat(' ', 2048) . "\n\n";
flush();

// パラメータ取得
$serverUrl = $_GET['serverUrl'] ?? 'http://localhost:8080';
$jobId = $_GET['jobId'] ?? '';

if (empty($jobId)) {
    echo "event: error\ndata: {\"error\": \"jobId is required\"}\n\n";
    flush();
    exit;
}

// cURLを使用してより細かい制御を行う
$url = rtrim($serverUrl, '/') . '/api/stream?jobId=' . urlencode($jobId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // 小さなバッファサイズ
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cache-Control: no-cache',
    'Accept: text/event-stream'
]);

// ストリーミングデータの処理
$eventBuffer = '';
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$eventBuffer) {
    $eventBuffer .= $data;
    
    // イベントごとに処理
    while (strpos($eventBuffer, "\n\n") !== false) {
        $pos = strpos($eventBuffer, "\n\n");
        $event = substr($eventBuffer, 0, $pos + 2);
        $eventBuffer = substr($eventBuffer, $pos + 2);
        
        // クライアントに送信
        echo $event;
        
        // 強制フラッシュ
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    return strlen($data);
});

// 実行
curl_exec($ch);

// エラーチェック
if (curl_errno($ch)) {
    echo "event: error\ndata: {\"error\": \"" . curl_error($ch) . "\"}\n\n";
    flush();
}

curl_close($ch);