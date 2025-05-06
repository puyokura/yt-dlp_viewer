<?php
// CORSヘッダー設定
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 設定ファイルを読み込み
require_once __DIR__ . '/config.php';

// OPTIONSリクエストの場合は早期リターン
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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
        
        $video_id = extractVideoId($url);
        
        if (!$video_id) {
            sendResponse(400, ['error' => 'Invalid YouTube URL']);
        }
        
        // YouTube Data APIを使用して動画情報を取得
        $api_url = "https://www.googleapis.com/youtube/v3/videos?id={$video_id}&key={$api_key}&part=snippet,contentDetails,statistics";
        $response = @file_get_contents($api_url);
        
        if ($response) {
            $api_data = json_decode($response, true);
            
            if (isset($api_data['items'][0])) {
                $item = $api_data['items'][0];
                
                // 動画情報を整形
                $video_info = [
                    'id' => $video_id,
                    'title' => $item['snippet']['title'],
                    'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                    'uploader' => $item['snippet']['channelTitle'],
                    'upload_date' => strtotime($item['snippet']['publishedAt']),
                    'view_count' => isset($item['statistics']['viewCount']) ? $item['statistics']['viewCount'] : 0,
                    'duration' => convertDuration($item['contentDetails']['duration']),
                    'formats' => [
                        ['format_id' => 'embed', 'ext' => 'mp4', 'width' => 1280, 'height' => 720, 'filesize' => null],
                    ]
                ];
                
                sendResponse(200, $video_info);
            } else {
                sendResponse(404, ['error' => 'Video not found']);
            }
        } else {
            sendResponse(500, ['error' => 'Failed to connect to YouTube API']);
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

// YouTube動画IDを抽出する関数
function extractVideoId($url) {
    $video_id = '';
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $url, $match)) {
        $video_id = $match[1];
    }
    return $video_id;
}

// ISO 8601形式の期間をセカンドに変換する関数
function convertDuration($duration) {
    $pattern = '/PT(?:([0-9]+)H)?(?:([0-9]+)M)?(?:([0-9]+)S)?/';
    preg_match($pattern, $duration, $matches);
    
    $hours = !empty($matches[1]) ? (int)$matches[1] : 0;
    $minutes = !empty($matches[2]) ? (int)$matches[2] : 0;
    $seconds = !empty($matches[3]) ? (int)$matches[3] : 0;
    
    return $hours * 3600 + $minutes * 60 + $seconds;
}