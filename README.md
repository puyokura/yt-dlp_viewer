# YouTube ビューア (yt-dlp)

PHPを使用したシンプルなYouTubeビューアアプリケーションです。[yt-dlp](https://github.com/yt-dlp/yt-dlp)を利用して、YouTube動画の情報取得、ダウンロード、再生を行うことができます。

## 機能

- YouTube URLから動画情報を取得
- 様々な品質・フォーマットでの動画ダウンロード
- ダウンロードした動画のブラウザ内再生
- ダウンロード済みファイルの管理

## 必要条件

- PHP 7.0以上
- yt-dlp（最新版を推奨）
- FFmpeg（オプション、一部の機能に必要）

## インストール方法

1. このリポジトリをクローンまたはダウンロードします
   ```
   git clone https://github.com/yourusername/yt-dlp_viewer.git
   ```

2. yt-dlpをインストールします
   - Windows: [yt-dlp公式リポジトリ](https://github.com/yt-dlp/yt-dlp/releases)からダウンロード
   - Linux/Mac: `pip install yt-dlp`

3. FFmpeg（オプション）をインストールします
   - Windows: [FFmpeg公式サイト](https://ffmpeg.org/download.html)からダウンロード
   - Linux: `sudo apt install ffmpeg`
   - Mac: `brew install ffmpeg`

4. `index.php`の設定を環境に合わせて変更します
   ```php
   $ytdlp_path = 'yt-dlp'; // yt-dlpのパス（環境に合わせて変更）
   $download_dir = './downloads'; // ダウンロードディレクトリ
   ```

5. PHPサーバーを起動します
   ```
   php -S localhost:8000
   ```

6. ブラウザで `http://localhost:8000` にアクセスします

## レンタルサーバーでの利用

多くのレンタルサーバーではシェルコマンドの実行が制限されている場合があります。以下の点に注意してください：

1. PHPの `shell_exec` 関数が有効になっているか確認してください
2. yt-dlpをサーバーにアップロードし、実行権限を付与してください
3. FFmpegがサーバーにインストールされているか確認してください
4. サーバーのリソース制限（CPU、メモリ、ディスク容量）に注意してください

## 使い方

1. トップページでYouTube動画のURLを入力します
2. 「情報取得」ボタンをクリックして動画情報を取得します
3. 利用可能なフォーマットから希望のものを選択します
4. 「ダウンロード」ボタンをクリックしてダウンロードを開始します
5. ダウンロード完了後、ファイル一覧から再生またはダウンロードできます

## 注意事項

- このアプリケーションは個人的な使用を目的としています
- 著作権を侵害する目的での使用は避けてください
- 大量のダウンロードはサーバーに負荷をかける可能性があります

## ライセンス

MITライセンスの下で公開されています。詳細は[LICENSE](LICENSE)ファイルを参照してください。

## 謝辞

- [yt-dlp](https://github.com/yt-dlp/yt-dlp) - 動画ダウンロードライブラリ
- [FFmpeg](https://ffmpeg.org/) - 動画処理ライブラリ