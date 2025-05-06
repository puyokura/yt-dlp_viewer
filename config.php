<?php
/**
 * Invidious Viewer 設定ファイル読み込み
 * 
 * config.yamlファイルから設定を読み込み、アプリケーション全体で使用できるようにします。
 */

// Composerがインストールされていない環境でも動作するように、シンプルなYAML解析関数を定義
if (!function_exists('yaml_parse_file') && !function_exists('parse_yaml_file')) {
    /**
     * シンプルなYAML解析関数
     * 基本的なYAML構造のみをサポート
     */
    function parse_yaml_file($file) {
        if (!file_exists($file)) {
            return false;
        }
        
        $yaml_text = file_get_contents($file);
        return parse_yaml_text($yaml_text);
    }
    
    function parse_yaml_text($yaml_text) {
        $lines = explode("\n", $yaml_text);
        $result = [];
        $current = &$result;
        $parents = [];
        $prev_indent = 0;
        
        foreach ($lines as $line) {
            // コメントや空行をスキップ
            if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // インデントレベルを計算
            preg_match('/^(\s*)(.*)$/', $line, $matches);
            $indent = strlen($matches[1]);
            $content = trim($matches[2]);
            
            // キーと値を分離
            if (strpos($content, ':') !== false) {
                list($key, $value) = explode(':', $content, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 値が空の場合は配列として扱う
                if ($value === '') {
                    // インデントが増えた場合、新しい階層に移動
                    if ($indent > $prev_indent) {
                        $parents[] = &$current;
                        $current[$key] = [];
                        $current = &$current[$key];
                    } 
                    // インデントが減った場合、親階層に戻る
                    else if ($indent < $prev_indent) {
                        $steps_back = ($prev_indent - $indent) / 2;
                        for ($i = 0; $i < $steps_back; $i++) {
                            $current = &$parents[count($parents) - 1];
                            array_pop($parents);
                        }
                        $parents[] = &$current;
                        $current[$key] = [];
                        $current = &$current[$key];
                    }
                    // 同じインデントレベルの場合
                    else {
                        $current = &$parents[count($parents) - 1];
                        $current[$key] = [];
                        $current = &$current[$key];
                    }
                } 
                // 値がある場合は直接代入
                else {
                    // 文字列の引用符を削除
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    // 数値に変換
                    if (is_numeric($value)) {
                        $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                    }
                    // 真偽値に変換
                    else if (strtolower($value) === 'true') {
                        $value = true;
                    }
                    else if (strtolower($value) === 'false') {
                        $value = false;
                    }
                    
                    $current[$key] = $value;
                }
            }
            // リスト項目の処理
            else if (strpos($content, '-') === 0) {
                $item = trim(substr($content, 1));
                
                // 項目が「キー: 値」の形式の場合
                if (strpos($item, ':') !== false) {
                    list($key, $value) = explode(':', $item, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // 値が空の場合は配列として扱う
                    if ($value === '') {
                        $current[] = [$key => []];
                        $parents[] = &$current;
                        $current = &$current[count($current) - 1][$key];
                    } 
                    // 値がある場合は直接代入
                    else {
                        // 文字列の引用符を削除
                        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                            $value = substr($value, 1, -1);
                        }
                        
                        // 数値に変換
                        if (is_numeric($value)) {
                            $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                        }
                        // 真偽値に変換
                        else if (strtolower($value) === 'true') {
                            $value = true;
                        }
                        else if (strtolower($value) === 'false') {
                            $value = false;
                        }
                        
                        $current[] = [$key => $value];
                    }
                }
                // 単純な値の場合
                else {
                    $current[] = $item;
                }
            }
            
            $prev_indent = $indent;
        }
        
        return $result;
    }
}

// 設定ファイルのパス
$config_file = __DIR__ . '/config.yaml';

// 設定の読み込み
$config = function_exists('yaml_parse_file') ? 
    yaml_parse_file($config_file) : 
    parse_yaml_file($config_file);

// 設定が読み込めない場合はデフォルト値を使用
if (!$config) {
    $config = [
        'invidious' => [
            'default_instance' => 'https://invidious.snopyta.org',
            'instances' => [
                ['name' => 'Snopyta', 'url' => 'https://invidious.snopyta.org', 'region' => 'EU']
            ]
        ],
        'app' => [
            'history_days' => 30,
            'download_dir' => 'downloads',
            'default_quality' => '720p',
            'display_errors' => true
        ]
    ];
}

/**
 * 利用可能なInvidiousインスタンスを取得
 * 
 * @return array 利用可能なインスタンスの配列
 */
function get_invidious_instances() {
    global $config;
    return isset($config['invidious']['instances']) ? $config['invidious']['instances'] : [];
}

/**
 * 動作するInvidiousインスタンスを取得
 * 
 * @return string 動作するインスタンスのURL、見つからない場合はデフォルトインスタンス
 */
function get_working_invidious_instance() {
    global $config;
    
    // デフォルトインスタンス
    $default_instance = isset($config['invidious']['default_instance']) ? 
        $config['invidious']['default_instance'] : 
        'https://invidious.snopyta.org';
    
    // インスタンスリストが設定されていない場合はデフォルトを返す
    if (!isset($config['invidious']['instances']) || empty($config['invidious']['instances'])) {
        return $default_instance;
    }
    
    // 各インスタンスをテスト
    foreach ($config['invidious']['instances'] as $instance) {
        $url = $instance['url'];
        
        // インスタンスが応答するかテスト
        $context = stream_context_create([
            'http' => [
                'timeout' => 2 // 2秒のタイムアウト
            ]
        ]);
        
        $response = @file_get_contents($url . '/api/v1/stats', false, $context);
        
        if ($response !== false) {
            return $url;
        }
    }
    
    // 動作するインスタンスが見つからない場合はデフォルトを返す
    return $default_instance;
}

/**
 * アプリケーション設定を取得
 * 
 * @param string $key 設定キー
 * @param mixed $default デフォルト値
 * @return mixed 設定値
 */
function get_app_config($key, $default = null) {
    global $config;
    return isset($config['app'][$key]) ? $config['app'][$key] : $default;
}

// エラー表示設定
ini_set('display_errors', get_app_config('display_errors', true) ? 1 : 0);
ini_set('display_startup_errors', get_app_config('display_errors', true) ? 1 : 0);
error_reporting(E_ALL);

// ダウンロードディレクトリの設定
$download_dir = __DIR__ . '/' . get_app_config('download_dir', 'downloads');
if (!file_exists($download_dir)) {
    mkdir($download_dir, 0777, true);
}

// 動作するInvidiousインスタンスを取得
$invidious_instance = get_working_invidious_instance();