import { useState } from 'react';
import Head from 'next/head';

export default function Home() {
  const [url, setUrl] = useState('');
  const [videoInfo, setVideoInfo] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const fetchVideoInfo = async (e) => {
    e.preventDefault();
    if (!url) {
      setError('URLを入力してください');
      return;
    }

    setLoading(true);
    setError('');
    
    try {
      const response = await fetch('/api/video-info', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url }),
      });

      if (!response.ok) {
        throw new Error('動画情報の取得に失敗しました');
      }

      const data = await response.json();
      setVideoInfo(data);
    } catch (err) {
      setError(err.message || '予期せぬエラーが発生しました');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background text-white">
      <Head>
        <title>yt-dlp Viewer</title>
        <meta name="description" content="YouTubeビデオ情報を表示するビューア" />
        <link rel="icon" href="/favicon.ico" />
      </Head>

      <main className="container mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold text-primary mb-8 text-center">yt-dlp Viewer</h1>
        
        <form onSubmit={fetchVideoInfo} className="max-w-2xl mx-auto mb-8">
          <div className="flex flex-col sm:flex-row gap-4">
            <input
              type="text"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="YouTubeのURLを入力"
              className="flex-1 px-4 py-2 rounded bg-secondary text-white border border-gray-700 focus:outline-none focus:border-primary"
            />
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2 bg-primary text-white rounded hover:bg-red-700 transition disabled:opacity-50"
            >
              {loading ? '読み込み中...' : '情報取得'}
            </button>
          </div>
          {error && <p className="text-red-500 mt-2">{error}</p>}
        </form>

        {videoInfo && (
          <div className="max-w-4xl mx-auto bg-secondary rounded-lg p-6">
            <h2 className="text-2xl font-bold mb-4">{videoInfo.title}</h2>
            
            <div className="aspect-video mb-4 overflow-hidden rounded">
              <img 
                src={videoInfo.thumbnail} 
                alt={videoInfo.title} 
                className="w-full h-full object-cover"
              />
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 className="text-xl font-semibold mb-2">動画情報</h3>
                <ul className="space-y-2">
                  <li><span className="text-gray-400">チャンネル:</span> {videoInfo.uploader}</li>
                  <li><span className="text-gray-400">再生回数:</span> {videoInfo.view_count?.toLocaleString()}</li>
                  <li><span className="text-gray-400">投稿日:</span> {videoInfo.upload_date}</li>
                  <li><span className="text-gray-400">長さ:</span> {videoInfo.duration}</li>
                </ul>
              </div>
              
              <div>
                <h3 className="text-xl font-semibold mb-2">利用可能な形式</h3>
                <div className="space-y-2">
                  {videoInfo.formats?.map((format, index) => (
                    <div key={index} className="flex justify-between items-center p-2 bg-gray-800 rounded">
                      <span>{format.format_note} ({format.ext})</span>
                      <a 
                        href={`/api/download?url=${encodeURIComponent(url)}&format=${format.format_id}`}
                        className="px-3 py-1 bg-primary text-white text-sm rounded hover:bg-red-700 transition"
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        ダウンロード
                      </a>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            
            {videoInfo.description && (
              <div className="mt-6">
                <h3 className="text-xl font-semibold mb-2">説明</h3>
                <p className="whitespace-pre-line text-gray-300">{videoInfo.description}</p>
              </div>
            )}
          </div>
        )}
      </main>

      <footer className="py-6 text-center text-gray-400">
        <p>© {new Date().getFullYear()} yt-dlp Viewer</p>
      </footer>
    </div>
  );
}