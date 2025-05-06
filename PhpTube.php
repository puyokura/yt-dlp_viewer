<?php
/**
 * PhpTube - YouTube動画情報取得ライブラリ
 * 
 * このクラスはYouTube動画の情報を直接取得するための機能を提供します。
 * Invidiousインスタンスが利用できない場合のフォールバックとして使用できます。
 */

class PhpTube {
    /**
     * ユーザーエージェント
     */
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    /**
     * 動画情報をキャッシュする配列
     */
    private $cache = [];
    
    /**
     * キャッシュの有効期限（秒）
     */
    private $cacheExpiry = 3600; // 1時間
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // キャッシュディレクトリの作成
        $this->cacheDir = __DIR__ . '/cache';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }
    
    /**
     * YouTube動画IDから動画情報を取得
     * 
     * @param string $videoId YouTube動画ID
     * @return array|false 動画情報の配列、失敗した場合はfalse
     */
    public function getVideoInfo($videoId) {
        // キャッシュをチェック
        $cachedInfo = $this->getFromCache($videoId);
        if ($cachedInfo !== false) {
            return $cachedInfo;
        }
        
        // YouTube動画ページのURLを構築
        $url = "https://www.youtube.com/watch?v={$videoId}";
        
        // ページコンテンツを取得
        $html = $this->fetchUrl($url);
        if (!$html) {
            return false;
        }
        
        // 動画情報を抽出
        $videoInfo = $this->extractVideoInfo($html, $videoId);
        
        // 情報が取得できた場合はキャッシュに保存
        if ($videoInfo) {
            $this->saveToCache($videoId, $videoInfo);
        }
        
        return $videoInfo;
    }
    
    /**
     * URLからコンテンツを取得
     * 
     * @param string $url 取得するURL
     * @return string|false 取得したコンテンツ、失敗した場合はfalse
     */
    private function fetchUrl($url) {
        $options = [
            'http' => [
                'header' => "User-Agent: {$this->userAgent}\r\n",
                'method' => 'GET',
                'timeout' => 30,
            ]
        ];
        
        $context = stream_context_create($options);
        $content = @file_get_contents($url, false, $context);
        
        return $content;
    }
    
    /**
     * HTMLから動画情報を抽出
     * 
     * @param string $html YouTube動画ページのHTML
     * @param string $videoId 動画ID
     * @return array|false 動画情報の配列、失敗した場合はfalse
     */
    private function extractVideoInfo($html, $videoId) {
        // ytInitialPlayerResponseを抽出
        if (preg_match('/ytInitialPlayerResponse\s*=\s*(\{.+?\})\s*;/', $html, $matches)) {
            $jsonData = $matches[1];
            $data = json_decode($jsonData, true);
            
            if (!$data || !isset($data['videoDetails'])) {
                return false;
            }
            
            $videoDetails = $data['videoDetails'];
            $streamingData = isset($data['streamingData']) ? $data['streamingData'] : [];
            
            // サムネイル画像を取得
            $thumbnails = [];
            if (isset($videoDetails['thumbnail']['thumbnails'])) {
                $thumbnails = $videoDetails['thumbnail']['thumbnails'];
            }
            
            // フォーマットを取得
            $formats = [];
            
            // 適応ストリーミングフォーマット
            if (isset($streamingData['adaptiveFormats'])) {
                foreach ($streamingData['adaptiveFormats'] as $format) {
                    $formats[] = $this->parseFormat($format);
                }
            }
            
            // 通常フォーマット
            if (isset($streamingData['formats'])) {
                foreach ($streamingData['formats'] as $format) {
                    $formats[] = $this->parseFormat($format);
                }
            }
            
            // 動画情報を構築
            $videoInfo = [
                'id' => $videoId,
                'title' => $videoDetails['title'],
                'thumbnail' => !empty($thumbnails) ? end($thumbnails)['url'] : '',
                'uploader' => $videoDetails['author'],
                'upload_date' => time(), // 正確な日付は取得困難なため現在時刻を使用
                'view_count' => isset($videoDetails['viewCount']) ? (int)$videoDetails['viewCount'] : 0,
                'duration' => isset($videoDetails['lengthSeconds']) ? (int)$videoDetails['lengthSeconds'] : 0,
                'formats' => $formats
            ];
            
            return $videoInfo;
        }
        
        return false;
    }
    
    /**
     * フォーマット情報をパース
     * 
     * @param array $format YouTubeのフォーマット情報
     * @return array パースしたフォーマット情報
     */
    private function parseFormat($format) {
        $mimeType = isset($format['mimeType']) ? $format['mimeType'] : '';
        $ext = 'mp4'; // デフォルト
        
        // MIMEタイプから拡張子を抽出
        if (preg_match('/video\/([a-zA-Z0-9]+)/', $mimeType, $matches)) {
            $ext = $matches[1];
        }
        
        // 解像度情報
        $width = isset($format['width']) ? (int)$format['width'] : null;
        $height = isset($format['height']) ? (int)$format['height'] : null;
        $resolution = '';
        
        if ($width && $height) {
            $resolution = "{$width}x{$height}";
        } elseif ($height) {
            $resolution = "{$height}p";
        }
        
        // 品質ラベル
        $quality = isset($format['qualityLabel']) ? $format['qualityLabel'] : '';
        if (!$quality && isset($format['quality'])) {
            $quality = $format['quality'];
        }
        
        // URL
        $url = '';
        if (isset($format['url'])) {
            $url = $format['url'];
        } elseif (isset($format['signatureCipher'])) {
            // 署名付きURLの解析（簡易版）
            parse_str($format['signatureCipher'], $cipher);
            if (isset($cipher['url'])) {
                $url = $cipher['url'];
            }
        }
        
        return [
            'format_id' => isset($format['itag']) ? $format['itag'] : '',
            'ext' => $ext,
            'width' => $width,
            'height' => $height,
            'resolution' => $resolution,
            'quality' => $quality,
            'url' => $url,
            'filesize' => isset($format['contentLength']) ? (int)$format['contentLength'] : null
        ];
    }
    
    /**
     * キャッシュから動画情報を取得
     * 
     * @param string $videoId 動画ID
     * @return array|false キャッシュされた動画情報、存在しない場合はfalse
     */
    private function getFromCache($videoId) {
        $cacheFile = $this->cacheDir . '/' . $videoId . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            
            // キャッシュが有効期限内かチェック
            if (time() - $cacheTime < $this->cacheExpiry) {
                $cacheData = file_get_contents($cacheFile);
                return json_decode($cacheData, true);
            }
        }
        
        return false;
    }
    
    /**
     * 動画情報をキャッシュに保存
     * 
     * @param string $videoId 動画ID
     * @param array $videoInfo 動画情報
     * @return bool 保存に成功した場合はtrue
     */
    private function saveToCache($videoId, $videoInfo) {
        $cacheFile = $this->cacheDir . '/' . $videoId . '.json';
        return file_put_contents($cacheFile, json_encode($videoInfo)) !== false;
    }
    
    /**
     * YouTube動画IDを抽出する関数
     * 
     * @param string $url YouTubeのURL
     * @return string|false 動画ID、抽出できない場合はfalse
     */
    public static function extractVideoId($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
        return false;
    }
}