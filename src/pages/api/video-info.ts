import { NextApiRequest, NextApiResponse } from 'next';
import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  const { url } = req.body;

  if (!url) {
    return res.status(400).json({ error: 'URL is required' });
  }

  try {
    // yt-dlpコマンドを実行して動画情報を取得
    const { stdout, stderr } = await execAsync('yt-dlp --dump-json', {
      env: { ...process.env, PATH: `${process.env.PATH}:/usr/local/bin:/usr/bin` },
      shell: true,
      input: url
    });
    
    if (stderr) {
      console.error('yt-dlp error:', stderr);
      return res.status(500).json({ error: 'Failed to fetch video info' });
    }

    // JSONをパース
    const videoInfo = JSON.parse(stdout);

    // 必要な情報だけを抽出
    const {
      title,
      thumbnail,
      description,
      uploader,
      upload_date,
      view_count,
      duration,
      formats,
    } = videoInfo;

    // フォーマット情報を整理
    const formattedFormats = formats
      .filter(format => format.format_id && format.ext)
      .map(format => ({
        format_id: format.format_id,
        format_note: format.format_note || `${format.height}p`,
        ext: format.ext,
        filesize: format.filesize,
        resolution: format.resolution || `${format.width}x${format.height}`,
      }));

    // 日付フォーマットを整形 (YYYYMMDD -> YYYY-MM-DD)
    const formattedDate = upload_date
      ? `${upload_date.slice(0, 4)}-${upload_date.slice(4, 6)}-${upload_date.slice(6, 8)}`
      : 'Unknown';

    // 時間フォーマットを整形 (秒 -> HH:MM:SS)
    const formatDuration = (seconds) => {
      if (!seconds) return 'Unknown';
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      const secs = Math.floor(seconds % 60);
      return [
        hours.toString().padStart(2, '0'),
        minutes.toString().padStart(2, '0'),
        secs.toString().padStart(2, '0')
      ].join(':');
    };

    // レスポンスを返す
    return res.status(200).json({
      title,
      thumbnail,
      description,
      uploader,
      upload_date: formattedDate,
      view_count,
      duration: formatDuration(duration),
      formats: formattedFormats,
    });
  } catch (error) {
    console.error('Error processing video info:', error);
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    return res.status(500).json({ 
      error: 'Failed to process video info', 
      details: process.env.NODE_ENV === 'development' ? errorMessage : undefined 
    });
  }
}