<?php
// 設定ファイルを読み込み
require_once __DIR__ . '/config.php';

// 注: エラー表示設定とダウンロードディレクトリの設定はconfig.phpで行われます

// 注意: ダウンロード機能とファイル削除機能は削除されました

$message = '';
$video_info = null;
$formats = [];
$video_streams = [];

// URLが送信された場合
if (isset($_POST['url']) && !empty($_POST['url'])) {
    $url = $_POST['url'];
    
    // YouTube動画IDを抽出
    $video_id = '';
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $url, $match)) {
        $video_id = $match[1];
    }
    
    if (!empty($video_id)) {
        // Invidious APIを使用して動画情報を取得
        $api_url = "{$invidious_instance}/api/v1/videos/{$video_id}";
        $response = @file_get_contents($api_url);
        
        if ($response) {
            $api_data = json_decode($response, true);
            
            if ($api_data && isset($api_data['title'])) {
                // 動画情報を整形
                $video_info = [
                    'id' => $video_id,
                    'title' => $api_data['title'],
                    'thumbnail' => $api_data['videoThumbnails'][0]['url'],
                    'uploader' => $api_data['author'],
                    'upload_date' => $api_data['published'],
                    'view_count' => $api_data['viewCount'],
                    'duration' => $api_data['lengthSeconds'],
                ];
                
                // 利用可能なストリームフォーマットを取得
                $formats = [];
                if (isset($api_data['formatStreams']) && is_array($api_data['formatStreams'])) {
                    foreach ($api_data['formatStreams'] as $format) {
                        $formats[] = [
                            'format_id' => $format['itag'],
                            'ext' => $format['container'],
                            'width' => isset($format['width']) ? $format['width'] : null,
                            'height' => isset($format['height']) ? $format['height'] : null,
                            'resolution' => $format['resolution'],
                            'quality' => $format['quality'],
                            'url' => $format['url']
                        ];
                    }
                }
                
                // 動画ストリームURLを保存
                $video_streams = $formats;
            } else {
                $message = 'エラー: 動画情報を取得できませんでした。';
            }
        } else {
            $message = 'エラー: Invidious APIに接続できませんでした。';
        }
    } else {
        $message = 'エラー: 有効なYouTube URLを入力してください。';
    }
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

// 視聴履歴を取得
function getViewHistory() {
    if (isset($_COOKIE['view_history'])) {
        return json_decode($_COOKIE['view_history'], true);
    }
    return [];
}

// 視聴履歴を保存
function saveViewHistory($video_info) {
    $history = getViewHistory();
    
    // 同じ動画が既に履歴にある場合は削除
    foreach ($history as $key => $item) {
        if ($item['id'] === $video_info['id']) {
            unset($history[$key]);
            break;
        }
    }
    
    // 新しい履歴を先頭に追加
    array_unshift($history, [
        'id' => $video_info['id'],
        'title' => $video_info['title'],
        'thumbnail' => $video_info['thumbnail'],
        'timestamp' => time()
    ]);
    
    // 履歴を10件に制限
    $history = array_slice($history, 0, 10);
    
    // Cookieに保存（設定された日数有効）
    $history_days = get_app_config('history_days', 30);
    setcookie('view_history', json_encode($history), time() + 60 * 60 * 24 * $history_days, '/');
    
    return $history;
}

// 視聴履歴
$view_history = getViewHistory();

// 動画情報がある場合は視聴履歴に追加
if ($video_info) {
    $view_history = saveViewHistory($video_info);
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
    <title>Invidious Viewer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Invidious Viewer</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="search-section">
            <h2>動画を検索</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label for="url">YouTube URL:</label>
                    <input type="text" name="url" id="url" placeholder="https://www.youtube.com/watch?v=..." 
                           value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>" required>
                    <button type="submit">情報取得</button>
                </div>
            </form>
        </div>
        
        <?php if ($video_info): ?>
        <div class="video-info">
            <h2><?php echo htmlspecialchars($video_info['title']); ?></h2>
            
            <!-- 動画プレーヤー -->
            <div class="video-player">
                <?php if (!empty($video_streams)): ?>
                <video id="player" controls autoplay width="100%" height="480">
                    <?php foreach ($video_streams as $stream): ?>
                    <source src="<?php echo htmlspecialchars($stream['url']); ?>" type="video/<?php echo htmlspecialchars($stream['ext']); ?>" label="<?php echo htmlspecialchars($stream['resolution']); ?>">
                    <?php endforeach; ?>
                    お使いのブラウザはHTML5ビデオをサポートしていません。
                </video>
                <?php else: ?>
                <iframe width="100%" height="480" src="https://www.youtube.com/embed/<?php echo htmlspecialchars($video_info['id']); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <?php endif; ?>
            </div>
            
            <div class="video-details">
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
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formats as $index => $format): ?>
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
                                <td>
                                    <?php if (!empty($video_streams)): ?>
                                    <button onclick="changeSource(<?php echo $index; ?>)">この品質で再生</button>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($format['url']); ?>" target="_blank">直接視聴</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <tr>
                            <td>YouTube公式</td>
                            <td>-</td>
                            <td>最高画質</td>
                            <td>-</td>
                            <td><a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video_info['id']); ?>" target="_blank">YouTubeで視聴</a></td>
                        </tr>
                    </tbody>
                </table>
                <p class="note">※ 動画はInvidiousから取得したストリームを使用して再生されます</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 視聴履歴 -->
        <?php if (!empty($view_history)): ?>
        <div class="view-history">
            <h3>視聴履歴</h3>
            <div class="history-items">
                <?php foreach ($view_history as $item): ?>
                <div class="history-item">
                    <a href="?url=https://www.youtube.com/watch?v=<?php echo htmlspecialchars($item['id']); ?>">
                        <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" alt="サムネイル">
                        <div class="history-info">
                            <div class="history-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="history-date"><?php echo date('Y-m-d H:i', $item['timestamp']); ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <h3>視聴履歴</h3>
        <div class="history">
            <p>視聴履歴がありません</p>
        </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>Invidious Viewer - 個人利用目的のみでご使用ください。</p>
        <div class="instance-info">
            <p>現在のインスタンス: <a href="<?php echo htmlspecialchars($invidious_instance); ?>" target="_blank"><?php echo htmlspecialchars($invidious_instance); ?></a></p>
            <?php if (!empty(get_invidious_instances())): ?>
            <details>
                <summary>利用可能なインスタンス</summary>
                <ul class="instances-list">
                    <?php foreach (get_invidious_instances() as $instance): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($instance['url']); ?>" target="_blank"><?php echo htmlspecialchars($instance['name']); ?></a>
                        <?php if (isset($instance['region'])): ?>(<?php echo htmlspecialchars($instance['region']); ?>)<?php endif; ?>
                        <?php if (isset($instance['description'])): ?> - <?php echo htmlspecialchars($instance['description']); ?><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
    </footer>
    
    <script>
    // 動画ソースを変更する関数
    function changeSource(index) {
        const player = document.getElementById('player');
        const sources = player.getElementsByTagName('source');
        
        if (sources.length > 0 && index < sources.length) {
            const currentTime = player.currentTime;
            player.src = sources[index].src;
            player.load();
            player.currentTime = currentTime;
            player.play();
        }
    }
    
    // URLパラメータから自動的に動画を読み込む
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const videoUrl = urlParams.get('url');
        
        if (videoUrl) {
            document.getElementById('url').value = videoUrl;
            // フォームを自動送信
            if (!<?php echo isset($_POST['url']) ? 'true' : 'false'; ?>) {
                document.querySelector('form').submit();
            }
        }
    });
    </script>
</body>
</html>