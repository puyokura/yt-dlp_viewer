<?php
// 設定ファイルを読み込み
require_once __DIR__ . '/config.php';

// Invidiousインスタンスを取得（健全性を考慮）
$invidious_instance = get_current_invidious_instance();

// 動画IDとフォーマットの取得
$video_id = isset($_GET['id']) ? $_GET['id'] : '';
$format_id = isset($_GET['format']) ? $_GET['format'] : '';

// 動画IDが存在しない場合はエラー
if (empty($video_id)) {
    header('HTTP/1.1 400 Bad Request');
    echo '動画IDが指定されていません';
    exit;
}

// Invidious APIを使用して動画情報を取得
$api_url = "{$invidious_instance}/api/v1/videos/{$video_id}";
$api_response = @file_get_contents($api_url);

if (!$api_response) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Invidious APIに接続できませんでした';
    exit;
}

$api_data = json_decode($api_response, true);

if (!$api_data || !isset($api_data['title'])) {
    header('HTTP/1.1 404 Not Found');
    echo '動画情報を取得できませんでした';
    exit;
}

// 動画情報を取得
$video_title = $api_data['title'];
$video_author = $api_data['author'];

// 利用可能なストリームフォーマットを取得
$formats = [];
$selected_format = null;

if (isset($api_data['formatStreams']) && is_array($api_data['formatStreams'])) {
    foreach ($api_data['formatStreams'] as $format) {
        $formats[] = $format;
        
        // 指定されたフォーマットIDと一致するか、指定がなければ最高画質を選択
        if ((!empty($format_id) && $format['itag'] == $format_id) || 
            (empty($format_id) && (!$selected_format || $format['height'] > $selected_format['height']))) {
            $selected_format = $format;
        }
    }
}

// 再生可能なフォーマットが見つからない場合はエラー
if (!$selected_format) {
    header('HTTP/1.1 404 Not Found');
    echo '再生可能なフォーマットが見つかりませんでした';
    exit;
}

// コンテンツタイプの設定
$content_type = 'video/' . $selected_format['container'];
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