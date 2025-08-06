# PHP Client for SSE PDF Generator

GoのSSE PDF Generator APIを利用するPHPクライアントの実装です。

## 構成ファイル

### 1. CLI クライアント (`cli-client.php`)
コマンドラインから直接APIを利用するスタンドアロンクライアント。

**特徴:**
- ジョブ作成とSSEストリーミングを一つのスクリプトで処理
- カラフルなコンソール出力
- プログレス表示とリアルタイム更新
- エラーハンドリング

**使用方法:**
```bash
# デフォルト設定で実行
php cli-client.php

# カスタムIDsで実行
php cli-client.php --ids=doc001,doc002,doc003

# 別サーバーに接続
php cli-client.php --url=http://api.example.com --ids=test1,test2

# 詳細ログ表示
php cli-client.php -v --ids=doc001

# ヘルプ表示
php cli-client.php --help
```

**実装のポイント:**
```php
// SSE接続とストリーミング処理
$stream = fopen($url, 'r', false, $context);
while (!feof($stream)) {
    $line = fgets($stream);
    // SSEフォーマットのパース
    if (strpos($line, 'event: ') === 0) {
        $eventType = trim(substr($line, 7));
    }
    // ...
}
```

### 2. Web クライアント (`web-client.php`)
ブラウザベースのリッチなUIを持つクライアント。

**特徴:**
- モダンなUI/UXデザイン
- リアルタイムプログレスバー
- 統計情報の表示（処理済み、成功、経過時間）
- アニメーション効果
- エラーハンドリング

### 3. ジョブ作成プロキシ (`job-proxy.php`)
CORSやセキュリティ制限を回避するためのプロキシ。

**役割:**
- ブラウザからのPOSTリクエストを受信
- GoサーバーにリクエストをPHP側から転送
- CORS対応

**実装:**
```php
// GoサーバーへのPOSTリクエスト転送
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => ['Content-Type: application/json'],
        'content' => json_encode(['ids' => $ids])
    ]
]);
$response = file_get_contents($url, false, $context);
```

### 4. SSEプロキシ (`sse-proxy.php`)
SSEストリームをブラウザに転送するプロキシ。

**重要な設定:**
```php
// SSEに必要なヘッダー
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// バッファリング無効化（重要）
@ob_end_clean();
@ini_set('output_buffering', 'off');
ob_implicit_flush(1);
```

## セットアップ

### 1. PHPビルトインサーバーで実行

```bash
# php-clientディレクトリに移動
cd php-client

# PHPサーバー起動（ポート8081）
php -S localhost:8081

# ブラウザでアクセス
http://localhost:8081/web-client.php
```

### 2. Apache/Nginxで実行

php-clientディレクトリをDocumentRoot配下に配置。

**Nginx設定例:**
```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_buffering off;  # SSE用
    fastcgi_keep_conn on;   # 接続維持
}
```

## 技術的な実装詳細

### SSEストリーミングの処理

PHPでSSEを扱う際の重要なポイント：

1. **バッファリング無効化**
   ```php
   ob_implicit_flush(1);  // 自動フラッシュ有効
   @ini_set('output_buffering', 'off');
   ```

2. **SSEフォーマットのパース**
   ```php
   event: file
   data: {"id":"doc001","fileName":"..."}
   
   // パース処理
   if (strpos($line, 'event: ') === 0) {
       $eventType = substr($line, 7);
   } elseif (strpos($line, 'data: ') === 0) {
       $eventData = substr($line, 6);
   }
   ```

3. **接続管理**
   ```php
   // クライアント切断検知
   if (connection_aborted()) {
       break;
   }
   ```

### JavaScriptのEventSource API

```javascript
// SSE接続
eventSource = new EventSource('sse-proxy.php?jobId=' + jobId);

// イベントリスナー登録
eventSource.addEventListener('file', function(event) {
    const file = JSON.parse(event.data);
    // ファイル情報処理
});

eventSource.addEventListener('complete', function(event) {
    // 完了処理
    eventSource.close();
});
```

## 動作フロー

```
1. ユーザー → web-client.php
   ↓
2. AJAX POST → job-proxy.php
   ↓
3. HTTP POST → Go Server (/api/job)
   ↓
4. Job ID返却
   ↓
5. EventSource接続 → sse-proxy.php
   ↓
6. SSE Stream → Go Server (/api/stream)
   ↓
7. リアルタイム更新 → ユーザー
```

## トラブルシューティング

### SSEが動作しない場合

1. **PHPのバッファリング設定確認**
   ```bash
   php -i | grep output_buffering
   ```

2. **タイムアウト設定**
   ```php
   set_time_limit(0);  // スクリプトのタイムアウト無効化
   ```

3. **Webサーバー設定**
   - Nginx: `fastcgi_buffering off;`
   - Apache: `SetEnv no-gzip 1`

### CORS エラー

プロキシ経由でアクセスするか、Goサーバー側でCORS設定を追加：
```go
w.Header().Set("Access-Control-Allow-Origin", "*")
```

## パフォーマンス考慮事項

- **接続数制限**: PHPの同時実行数に注意
- **メモリ使用**: 長時間のSSE接続はメモリを消費
- **タイムアウト**: `max_execution_time`の設定確認

## まとめ

このPHPクライアント実装は、GoのSSE APIを様々な環境から利用可能にします。CLIツールとしても、Webアプリケーションとしても動作し、SSEの特性を活かしたリアルタイム通信を実現しています。