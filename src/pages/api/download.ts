import { NextApiRequest, NextApiResponse } from 'next';
import { exec } from 'child_process';
import { promisify } from 'util';
import fs from 'fs';
import path from 'path';
import { pipeline } from 'stream';

const execAsync = promisify(exec);
const pipelineAsync = promisify(pipeline);

// 一時ファイル保存用のディレクトリ
const TEMP_DIR = path.join(process.cwd(), 'tmp');

// 一時ディレクトリが存在しない場合は作成
if (!fs.existsSync(TEMP_DIR)) {
  fs.mkdirSync(TEMP_DIR, { recursive: true });
}

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  // GETリクエストのみ許可
  if (req.method !== 'GET') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  const { url, format } = req.query;

  if (!url || typeof url !== 'string') {
    return res.status(400).json({ error: 'URL is required' });
  }

  try {
    // 一時ファイル名を生成
    const timestamp = Date.now();
    const randomString = Math.random().toString(36).substring(2, 15);
    const tempFilePath = path.join(TEMP_DIR, `video-${timestamp}-${randomString}`);
    
    // yt-dlpコマンドのオプションを構築
    let args = ['-o', `${tempFilePath}.%(ext)s`];
    
    // フォーマットが指定されている場合は追加
    if (format && typeof format === 'string') {
      args.push('-f', format);
    }
    
    // URLを追加
    args.push(url);

    // yt-dlpを実行してファイルをダウンロード
    const { stdout, stderr } = await execAsync(`yt-dlp ${args.map(arg => `"${arg}"`).join(' ')}`, {
      env: { ...process.env, PATH: `${process.env.PATH}:/usr/local/bin:/usr/bin` }
    });
    
    if (stderr) {
      console.error('yt-dlp download error:', stderr);
      return res.status(500).json({ error: 'Failed to download video' });
    }

    // ダウンロードされたファイルを見つける
    const files = fs.readdirSync(TEMP_DIR);
    const downloadedFile = files.find(file => file.startsWith(`video-${timestamp}`));
    
    if (!downloadedFile) {
      return res.status(500).json({ error: 'Downloaded file not found' });
    }

    const filePath = path.join(TEMP_DIR, downloadedFile);
    const fileStats = fs.statSync(filePath);
    
    // ファイル情報をヘッダーに設定
    res.setHeader('Content-Disposition', `attachment; filename="${downloadedFile}"`);
    res.setHeader('Content-Length', fileStats.size);
    
    // ファイルの拡張子からContent-Typeを決定
    const ext = path.extname(downloadedFile).toLowerCase();
    let contentType = 'application/octet-stream';
    
    if (ext === '.mp4') contentType = 'video/mp4';
    else if (ext === '.webm') contentType = 'video/webm';
    else if (ext === '.mp3') contentType = 'audio/mpeg';
    else if (ext === '.m4a') contentType = 'audio/mp4';
    
    res.setHeader('Content-Type', contentType);

    // ファイルをストリーミング
    const fileStream = fs.createReadStream(filePath);
    await pipelineAsync(fileStream, res);
    
    // 一時ファイルを削除
    fs.unlinkSync(filePath);
  } catch (error) {
    console.error('Error downloading video:', error);
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    return res.status(500).json({ 
      error: 'Failed to download video',
      details: process.env.NODE_ENV === 'development' ? errorMessage : undefined
    });
  } finally {
    // 一時ファイルが残っていないか確認して削除
    try {
      const files = fs.readdirSync(TEMP_DIR);
      const oldFiles = files.filter(file => 
        file.startsWith('video-') && 
        Date.now() - parseInt(file.split('-')[1] || '0') > 3600000 // 1時間以上前のファイル
      );
      
      for (const file of oldFiles) {
        fs.unlinkSync(path.join(TEMP_DIR, file));
      }
    } catch (cleanupError) {
      console.error('Error during cleanup:', cleanupError);
    }
  }
}

// ファイルサイズが大きい場合に対応するための設定
export const config = {
  api: {
    responseLimit: false,
    bodyParser: false,
  },
};