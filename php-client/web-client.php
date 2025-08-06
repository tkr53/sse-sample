<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSE PDF Generator - PHP Web Client</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .status-bar {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .status-text {
            font-weight: 500;
            color: #666;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-idle { background: #e0e0e0; color: #666; }
        .badge-processing { background: #fff3cd; color: #856404; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 10px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
            width: 0%;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .file-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
        }
        
        .file-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .file-item.success {
            border-left: 4px solid #28a745;
        }
        
        .file-item.failed {
            border-left: 4px solid #dc3545;
        }
        
        .file-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .file-name {
            font-weight: 600;
            color: #333;
        }
        
        .file-status {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .file-details {
            font-size: 13px;
            color: #666;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 SSE PDF Generator</h1>
            <p>PHP Web Client Implementation</p>
        </div>
        
        <div class="content">
            <div class="form-group">
                <label for="server-url">Server URL:</label>
                <input type="text" id="server-url" value="http://localhost:8080" placeholder="http://localhost:8080">
            </div>
            
            <div class="form-group">
                <label for="ids">Document IDs (カンマ区切り):</label>
                <input type="text" id="ids" value="doc001, doc002, doc003, doc004, doc005" placeholder="doc001, doc002, doc003">
            </div>
            
            <div class="button-group">
                <button id="start-btn" class="btn-primary" onclick="startGeneration()">
                    PDF生成開始
                </button>
                <button class="btn-secondary" onclick="clearResults()">
                    クリア
                </button>
            </div>
            
            <div class="status-bar">
                <span class="status-text" id="status-text">待機中</span>
                <span class="status-badge badge-idle" id="status-badge">IDLE</span>
            </div>
            
            <div class="progress-bar" id="progress-bar" style="display: none;">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            
            <div class="stats" id="stats" style="display: none;">
                <div class="stat-card">
                    <div class="stat-value" id="processed-count">0</div>
                    <div class="stat-label">処理済み</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="total-count">0</div>
                    <div class="stat-label">合計</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="success-count">0</div>
                    <div class="stat-label">成功</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="elapsed-time">0s</div>
                    <div class="stat-label">経過時間</div>
                </div>
            </div>
            
            <div id="error-container"></div>
            
            <div class="file-list" id="file-list" style="display: none;"></div>
        </div>
    </div>

    <script>
        let currentJobId = null;
        let eventSource = null;
        let startTime = null;
        let elapsedInterval = null;
        let processedFiles = 0;
        let successFiles = 0;
        let totalFiles = 0;

        async function startGeneration() {
            const serverUrl = document.getElementById('server-url').value.trim();
            const idsInput = document.getElementById('ids').value.trim();
            
            if (!idsInput) {
                showError('IDを入力してください');
                return;
            }
            
            const ids = idsInput.split(',').map(id => id.trim()).filter(id => id);
            totalFiles = ids.length;
            
            // UIリセット
            resetUI();
            updateStatus('ジョブ作成中...', 'processing');
            document.getElementById('start-btn').disabled = true;
            
            try {
                // ジョブ作成（PHPプロキシ経由）
                const response = await fetch('job-proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'create',
                        serverUrl: serverUrl,
                        ids: ids
                    })
                });
                
                if (!response.ok) {
                    throw new Error('ジョブの作成に失敗しました');
                }
                
                const data = await response.json();
                currentJobId = data.jobId;
                
                // SSE接続
                connectSSE(serverUrl, currentJobId);
                
            } catch (error) {
                showError(error.message);
                updateStatus('エラー', 'error');
                document.getElementById('start-btn').disabled = false;
            }
        }
        
        function connectSSE(serverUrl, jobId) {
            updateStatus('PDF生成中...', 'processing');
            startTimer();
            
            // 統計表示
            document.getElementById('stats').style.display = 'grid';
            document.getElementById('progress-bar').style.display = 'block';
            document.getElementById('file-list').style.display = 'block';
            document.getElementById('total-count').textContent = totalFiles;
            
            // SSE接続（改善版のsse-directを使用）
            eventSource = new EventSource(`sse-direct.php?serverUrl=${encodeURIComponent(serverUrl)}&jobId=${jobId}`);
            
            eventSource.addEventListener('file', function(event) {
                const file = JSON.parse(event.data);
                processedFiles++;
                if (file.status === 'success') {
                    successFiles++;
                }
                
                updateProgress();
                addFileToList(file);
            });
            
            eventSource.addEventListener('complete', function(event) {
                const data = JSON.parse(event.data);
                stopTimer();
                updateStatus(`完了: ${data.totalFiles}個のPDFを生成しました`, 'completed');
                document.getElementById('start-btn').disabled = false;
                eventSource.close();
            });
            
            eventSource.onerror = function(error) {
                console.error('SSE Error:', error);
                stopTimer();
                showError('接続エラーが発生しました');
                updateStatus('エラー', 'error');
                document.getElementById('start-btn').disabled = false;
                if (eventSource) {
                    eventSource.close();
                }
            };
        }
        
        function updateProgress() {
            const percentage = (processedFiles / totalFiles) * 100;
            document.getElementById('progress-fill').style.width = percentage + '%';
            document.getElementById('processed-count').textContent = processedFiles;
            document.getElementById('success-count').textContent = successFiles;
        }
        
        function addFileToList(file) {
            const fileList = document.getElementById('file-list');
            const fileDiv = document.createElement('div');
            fileDiv.className = `file-item ${file.status}`;
            
            const createdAt = new Date(file.createdAt).toLocaleString('ja-JP');
            const fileSizeKB = file.fileSize ? (file.fileSize / 1024).toFixed(2) : '0';
            
            fileDiv.innerHTML = `
                <div class="file-header">
                    <span class="file-name">📄 ${file.fileName || 'N/A'}</span>
                    <span class="file-status status-${file.status}">${file.status === 'success' ? '成功' : '失敗'}</span>
                </div>
                <div class="file-details">
                    <div>ID: ${file.id}</div>
                    <div>サイズ: ${fileSizeKB} KB</div>
                    <div>作成: ${createdAt}</div>
                </div>
            `;
            
            fileList.insertBefore(fileDiv, fileList.firstChild);
        }
        
        function updateStatus(text, badge) {
            document.getElementById('status-text').textContent = text;
            const badgeEl = document.getElementById('status-badge');
            badgeEl.className = `status-badge badge-${badge}`;
            
            const badgeTexts = {
                'idle': 'IDLE',
                'processing': 'PROCESSING',
                'completed': 'COMPLETED',
                'error': 'ERROR'
            };
            badgeEl.textContent = badgeTexts[badge] || badge.toUpperCase();
        }
        
        function startTimer() {
            startTime = Date.now();
            elapsedInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                document.getElementById('elapsed-time').textContent = elapsed + 's';
            }, 1000);
        }
        
        function stopTimer() {
            if (elapsedInterval) {
                clearInterval(elapsedInterval);
                elapsedInterval = null;
            }
        }
        
        function resetUI() {
            processedFiles = 0;
            successFiles = 0;
            totalFiles = 0;
            document.getElementById('file-list').innerHTML = '';
            document.getElementById('error-container').innerHTML = '';
            document.getElementById('progress-fill').style.width = '0%';
            document.getElementById('processed-count').textContent = '0';
            document.getElementById('success-count').textContent = '0';
            document.getElementById('elapsed-time').textContent = '0s';
        }
        
        function clearResults() {
            if (eventSource) {
                eventSource.close();
            }
            stopTimer();
            resetUI();
            updateStatus('待機中', 'idle');
            document.getElementById('stats').style.display = 'none';
            document.getElementById('progress-bar').style.display = 'none';
            document.getElementById('file-list').style.display = 'none';
            document.getElementById('start-btn').disabled = false;
        }
        
        function showError(message) {
            const errorContainer = document.getElementById('error-container');
            errorContainer.innerHTML = `<div class="error-message">⚠️ ${message}</div>`;
        }
    </script>
</body>
</html>