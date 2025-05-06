<?php
// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ファイルパスのパラメータを取得
$file = isset($_GET['file']) ? $_GET['file'] : '';
$download_dir = './downloads';
$file_path = $download_dir . '/' . basename($file);

// ファイルが存在するか確認
$file_exists = file_exists($file_path);
$file_type = '';

if ($file_exists) {
    // ファイルタイプを取得
    $file_info = pathinfo($file_path);
    $extension = strtolower($file_info['extension']);
    
    // 動画か音声かを判断
    $video_extensions = ['mp4', 'webm', 'ogg', 'mkv'];
    $audio_extensions = ['mp3', 'm4a', 'wav', 'ogg'];
    
    if (in_array($extension, $video_extensions)) {
        $file_type = 'video';
    } elseif (in_array($extension, $audio_extensions)) {
        $file_type = 'audio';
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $file_exists ? htmlspecialchars(basename($file)) : 'ファイルが見つかりません'; ?> - プレーヤー</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .player-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .media-player {
            width: 100%;
            max-width: 100%;
            margin: 20px 0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            background-color: #000;
        }
        
        .controls {
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .controls a {
            display: inline-block;
            padding: 10px 15px;
            background-color: #e62117;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 0;
        }
        
        .controls a:hover {
            background-color: #c81c0f;
        }
        
        .error-message {
            padding: 20px;
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 4px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="player-container">
        <h1>メディアプレーヤー</h1>
        
        <div class="controls">
            <a href="index.php">← 戻る</a>
            <?php if ($file_exists): ?>
                <a href="<?php echo htmlspecialchars($file_path); ?>" download>ダウンロード</a>
            <?php endif; ?>
        </div>
        
        <?php if ($file_exists && ($file_type == 'video' || $file_type == 'audio')): ?>
            <?php if ($file_type == 'video'): ?>
                <video class="media-player" controls autoplay>
                    <source src="<?php echo htmlspecialchars($file_path); ?>" type="video/<?php echo $extension; ?>">
                    お使いのブラウザは動画の再生に対応していません。
                </video>
            <?php else: ?>
                <audio class="media-player" controls autoplay>
                    <source src="<?php echo htmlspecialchars($file_path); ?>" type="audio/<?php echo $extension; ?>">
                    お使いのブラウザは音声の再生に対応していません。
                </audio>
                <div style="text-align: center;">
                    <img src="https://via.placeholder.com/800x450.png?text=Audio+Player" alt="Audio Player" style="max-width: 100%; border-radius: 4px;">
                </div>
            <?php endif; ?>
            
            <div class="file-info">
                <h2><?php echo htmlspecialchars(basename($file)); ?></h2>
                <p><strong>ファイルサイズ:</strong> <?php echo round(filesize($file_path) / (1024 * 1024), 2); ?> MB</p>
                <p><strong>最終更新日時:</strong> <?php echo date('Y-m-d H:i:s', filemtime($file_path)); ?></p>
            </div>
            
        <?php else: ?>
            <div class="error-message">
                <?php if (!$file_exists): ?>
                    <p>ファイルが見つかりません。</p>
                <?php else: ?>
                    <p>このファイル形式はプレーヤーで再生できません。</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>Powered by <a href="https://github.com/yt-dlp/yt-dlp" target="_blank">yt-dlp</a> | &copy; <?php echo date('Y'); ?></p>
    </footer>
</body>
</html>