<?php
// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ダウンロードディレクトリの設定
$download_dir = __DIR__ . '/downloads';
if (!file_exists($download_dir)) {
    mkdir($download_dir, 0777, true);
}

// yt-dlpのパス（環境に合わせて変更）
$ytdlp_path = 'yt-dlp';

// 注意: ダウンロード機能とファイル削除機能は削除されました

$message = '';
$video_info = null;
$formats = [];

// URLが送信された場合
if (isset($_POST['url']) && !empty($_POST['url'])) {
    $url = $_POST['url'];
    
    // 動画情報を取得
    try {
        $command = escapeshellcmd("$ytdlp_path --dump-json " . escapeshellarg($url));
        $output = shell_exec($command);
        
        if ($output) {
            $video_info = json_decode($output, true);
            
            // 利用可能なフォーマットを取得
            if (isset($video_info['formats'])) {
                $formats = $video_info['formats'];
            }
        } else {
            $message = 'エラー: 動画情報を取得できませんでした。';
        }
    } catch (Exception $e) {
        $message = 'エラー: ' . $e->getMessage();
    }
}

// ダウンロード機能は削除されました

// ダウンロード済みファイルの一覧を取得
$downloaded_files = [];
if (file_exists($download_dir)) {
    $files = scandir($download_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $downloaded_files[] = [
                'name' => $file,
                'path' => $download_dir . '/' . $file,
                'size' => filesize($download_dir . '/' . $file),
                'time' => filemtime($download_dir . '/' . $file)
            ];
        }
    }
    
    // 最新のファイルが先頭に来るようにソート
    usort($downloaded_files, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube ビューア (yt-dlp)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>YouTube ビューア (yt-dlp)</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="search-section">
            <h2>動画を検索</h2>
            <form method="post" action="">
                <div class="form-group">
                    <input type="text" name="url" id="url" placeholder="YouTube URL" 
                           value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>" required>
                    <button type="submit">情報取得</button>
                </div>
            </form>
        </div>
        
        <?php if ($video_info): ?>
        <div class="video-info">
            <h2><?php echo htmlspecialchars($video_info['title']); ?></h2>
            
            <div class="video-details">
                <div class="thumbnail">
                    <?php if (isset($video_info['thumbnail'])): ?>
                        <img src="<?php echo htmlspecialchars($video_info['thumbnail']); ?>" alt="サムネイル">
                    <?php endif; ?>
                </div>
                
                <div class="info">
                    <p><strong>チャンネル:</strong> <?php echo htmlspecialchars($video_info['uploader']); ?></p>
                    <p><strong>再生回数:</strong> <?php echo number_format($video_info['view_count']); ?></p>
                    <p><strong>投稿日:</strong> <?php echo date('Y-m-d', $video_info['upload_date']); ?></p>
                    <p><strong>長さ:</strong> <?php echo gmdate("H:i:s", $video_info['duration']); ?></p>
                </div>
            </div>
            
            <div class="formats">
                <h3>利用可能なフォーマット</h3>
                <table>
                    <thead>
                        <tr>
                            <th>フォーマットID</th>
                            <th>拡張子</th>
                            <th>解像度</th>
                            <th>ファイルサイズ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formats as $format): ?>
                            <?php if (isset($format['format_id'])): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($format['format_id']); ?></td>
                                <td><?php echo isset($format['ext']) ? htmlspecialchars($format['ext']) : 'N/A'; ?></td>
                                <td>
                                    <?php 
                                    if (isset($format['height']) && isset($format['width'])) {
                                        echo htmlspecialchars($format['width'] . 'x' . $format['height']);
                                    } elseif (isset($format['height'])) {
                                        echo htmlspecialchars($format['height'] . 'p');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($format['filesize'])) {
                                        echo htmlspecialchars(round($format['filesize'] / (1024 * 1024), 2) . ' MB');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="note">※ ダウンロード機能は無効化されています</p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="downloaded-files">
            <h2>保存済みファイル</h2>
            <?php if (empty($downloaded_files)): ?>
                <p>保存済みファイルはありません。</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ファイル名</th>
                            <th>サイズ</th>
                            <th>保存日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($downloaded_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['name']); ?></td>
                                <td><?php echo round($file['size'] / (1024 * 1024), 2); ?> MB</td>
                                <td><?php echo date('Y-m-d H:i:s', $file['time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="note">※ ファイル操作機能は無効化されています</p>
            <?php endif; ?>
        </div>
    </div>
    
    <footer>
        <p>Powered by <a href="https://github.com/yt-dlp/yt-dlp" target="_blank">yt-dlp</a> | &copy; <?php echo date('Y'); ?></p>
    </footer>
</body>
</html>