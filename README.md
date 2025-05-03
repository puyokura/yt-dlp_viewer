# yt-dlp Viewer

Vercelでデプロイして使えるyt-dlpを利用したYouTubeビデオビューアアプリケーションです。このアプリケーションを使用すると、YouTubeの動画情報を表示し、様々な形式でダウンロードすることができます。

## 機能

- YouTubeのURLから動画情報を取得
- サムネイル、タイトル、説明、アップロード日、再生回数などの表示
- 様々な形式（解像度、ファイル形式）でのダウンロード
- レスポンシブデザイン

## 技術スタック

- **フロントエンド**: Next.js, React, TypeScript, TailwindCSS
- **バックエンド**: Next.js API Routes
- **外部ツール**: yt-dlp (YouTubeダウンロードライブラリ)
- **デプロイ**: Vercel

## ローカル開発環境のセットアップ

### 前提条件

- Node.js (v14以上)
- npm または yarn
- Python 3
- yt-dlp (`pip install yt-dlp`)
- FFmpeg

### インストール手順

1. リポジトリをクローン

```bash
git clone https://github.com/yourusername/yt-dlp-viewer.git
cd yt-dlp-viewer
```

2. 依存関係をインストール

```bash
npm install
# または
yarn install
```

3. 開発サーバーを起動

```bash
npm run dev
# または
yarn dev
```

4. ブラウザで http://localhost:3000 にアクセス

## Vercelへのデプロイ

1. GitHubリポジトリにプロジェクトをプッシュ
2. Vercelダッシュボードで新しいプロジェクトを作成
3. GitHubリポジトリを選択
4. デプロイ設定はデフォルトのままで問題ありません（vercel.jsonが自動的に適用されます）
5. 「Deploy」ボタンをクリック

## 使用方法

1. アプリケーションにアクセス
2. YouTubeのURLを入力フォームに貼り付け
3. 「情報取得」ボタンをクリック
4. 動画情報が表示されたら、希望の形式の「ダウンロード」ボタンをクリック

## 注意事項

- このアプリケーションは個人的な使用目的のみを想定しています
- YouTubeの利用規約に従って使用してください
- 著作権で保護されたコンテンツのダウンロードには注意してください

## ライセンス

MIT