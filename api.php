<?php
// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORSヘッダー設定
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエストの場合は早期リターン
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 設定
$ytdlp_path = 'yt-dlp'; // yt-dlpのパス（環境に合わせて変更）
$download_dir = './downloads'; // ダウンロードディレクトリ

// レスポンス関数
function sendResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// リクエストメソッドの確認
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

// エンドポイントの処理
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'info':
        // 動画情報を取得
        $url = isset($_GET['url']) ? $_GET['url'] : '';
        if (empty($url)) {
            sendResponse(400, ['error' => 'URL parameter is required']);
        }
        
        try {
            $command = escapeshellcmd("$ytdlp_path --dump-json " . escapeshellarg($url));
            $output = shell_exec($command);
            
            if ($output) {
                $video_info = json_decode($output, true);
                sendResponse(200, $video_info);
            } else {
                sendResponse(500, ['error' => 'Failed to get video information']);
            }
        } catch (Exception $e) {
            sendResponse(500, ['error' => $e->getMessage()]);
        }
        break;
        
    case 'download':
        // 動画をダウンロード
        $url = isset($_POST['url']) ? $_POST['url'] : '';
        $format_id = isset($_POST['format_id']) ? $_POST['format_id'] : '';
        
        if (empty($url) || empty($format_id)) {
            sendResponse(400, ['error' => 'URL and format_id parameters are required']);
        }
        
        try {
            $output_template = $download_dir . '/%(title)s.%(ext)s';
            $command = escapeshellcmd("$ytdlp_path -f " . escapeshellarg($format_id) . 
                      " -o " . escapeshellarg($output_template) . 
                      " " . escapeshellarg($url));
            
            // バックグラウンドで実行（Windowsの場合）
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen("start /B " . $command, "r"));
            } else {
                // Linuxの場合
                shell_exec($command . " > /dev/null 2>&1 &");
            }
            
            sendResponse(200, ['message' => 'Download started']);
        } catch (Exception $e) {
            sendResponse(500, ['error' => 'Download error: ' . $e->getMessage()]);
        }
        break;
        
    case 'list':
        // ダウンロード済みファイルの一覧を取得
        $downloaded_files = [];
        if (file_exists($download_dir)) {
            $files = scandir($download_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $file_path = $download_dir . '/' . $file;
                    $downloaded_files[] = [
                        'name' => $file,
                        'path' => $file_path,
                        'size' => filesize($file_path),
                        'time' => filemtime($file_path),
                        'formatted_size' => round(filesize($file_path) / (1024 * 1024), 2) . ' MB',
                        'formatted_time' => date('Y-m-d H:i:s', filemtime($file_path))
                    ];
                }
            }
            
            // 最新のファイルが先頭に来るようにソート
            usort($downloaded_files, function($a, $b) {
                return $b['time'] - $a['time'];
            });
        }
        
        sendResponse(200, ['files' => $downloaded_files]);
        break;
        
    case 'delete':
        // ファイルを削除
        $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
        
        if (empty($filename)) {
            sendResponse(400, ['error' => 'Filename parameter is required']);
        }
        
        $file_path = $download_dir . '/' . basename($filename);
        
        if (!file_exists($file_path)) {
            sendResponse(404, ['error' => 'File not found']);
        }
        
        if (unlink($file_path)) {
            sendResponse(200, ['message' => 'File deleted successfully']);
        } else {
            sendResponse(500, ['error' => 'Failed to delete file']);
        }
        break;
        
    default:
        sendResponse(400, ['error' => 'Invalid action']);
}