/**
 * YouTube ビューア (yt-dlp) - フロントエンド機能
 */

document.addEventListener('DOMContentLoaded', function() {
    // 要素の取得
    const urlInput = document.getElementById('url');
    const searchForm = document.querySelector('.search-section form');
    const messageDiv = document.querySelector('.message');
    
    // 検索フォームの送信イベント
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // 通常のフォーム送信を防止
            e.preventDefault();
            
            const url = urlInput.value.trim();
            if (!url) {
                showMessage('URLを入力してください', 'error');
                return;
            }
            
            // 動画情報を非同期で取得
            showMessage('動画情報を取得中...', 'info');
            fetchVideoInfo(url);
        });
    }
    
    // 動画情報を取得する関数
    function fetchVideoInfo(url) {
        fetch(`api.php?action=info&url=${encodeURIComponent(url)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('動画情報の取得に失敗しました');
                }
                return response.json();
            })
            .then(data => {
                // 動画情報を表示
                displayVideoInfo(data, url);
            })
            .catch(error => {
                showMessage(`エラー: ${error.message}`, 'error');
            });
    }
    
    // 動画情報を表示する関数
    function displayVideoInfo(videoInfo, url) {
        // 既存の動画情報セクションを削除
        const existingInfo = document.querySelector('.video-info');
        if (existingInfo) {
            existingInfo.remove();
        }
        
        // 新しい動画情報セクションを作成
        const videoInfoDiv = document.createElement('div');
        videoInfoDiv.className = 'video-info';
        
        // タイトル
        const title = document.createElement('h2');
        title.textContent = videoInfo.title;
        videoInfoDiv.appendChild(title);
        
        // 動画詳細
        const detailsDiv = document.createElement('div');
        detailsDiv.className = 'video-details';
        
        // サムネイル
        const thumbnailDiv = document.createElement('div');
        thumbnailDiv.className = 'thumbnail';
        if (videoInfo.thumbnail) {
            const img = document.createElement('img');
            img.src = videoInfo.thumbnail;
            img.alt = 'サムネイル';
            thumbnailDiv.appendChild(img);
        }
        detailsDiv.appendChild(thumbnailDiv);
        
        // 情報
        const infoDiv = document.createElement('div');
        infoDiv.className = 'info';
        
        const infoItems = [
            { label: 'チャンネル', value: videoInfo.uploader },
            { label: '再生回数', value: videoInfo.view_count ? videoInfo.view_count.toLocaleString() : 'N/A' },
            { label: '投稿日', value: formatDate(videoInfo.upload_date) },
            { label: '長さ', value: formatDuration(videoInfo.duration) }
        ];
        
        infoItems.forEach(item => {
            const p = document.createElement('p');
            const strong = document.createElement('strong');
            strong.textContent = `${item.label}: `;
            p.appendChild(strong);
            p.appendChild(document.createTextNode(item.value));
            infoDiv.appendChild(p);
        });
        
        detailsDiv.appendChild(infoDiv);
        videoInfoDiv.appendChild(detailsDiv);
        
        // フォーマット一覧
        const formatsDiv = document.createElement('div');
        formatsDiv.className = 'formats';
        
        const formatsTitle = document.createElement('h3');
        formatsTitle.textContent = 'ダウンロード可能なフォーマット';
        formatsDiv.appendChild(formatsTitle);
        
        const formatsForm = document.createElement('form');
        formatsForm.method = 'post';
        formatsForm.action = '';
        
        // URL入力を隠しフィールドとして追加
        const urlField = document.createElement('input');
        urlField.type = 'hidden';
        urlField.name = 'url';
        urlField.value = url;
        formatsForm.appendChild(urlField);
        
        // フォーマットテーブル
        const table = document.createElement('table');
        
        // テーブルヘッダー
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['選択', 'フォーマットID', '拡張子', '解像度', 'ファイルサイズ'].forEach(text => {
            const th = document.createElement('th');
            th.textContent = text;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);
        
        // テーブルボディ
        const tbody = document.createElement('tbody');
        
        if (videoInfo.formats && videoInfo.formats.length > 0) {
            videoInfo.formats.forEach(format => {
                if (format.format_id) {
                    const row = document.createElement('tr');
                    
                    // 選択ラジオボタン
                    const selectCell = document.createElement('td');
                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'format_id';
                    radio.value = format.format_id;
                    radio.required = true;
                    selectCell.appendChild(radio);
                    row.appendChild(selectCell);
                    
                    // フォーマットID
                    const idCell = document.createElement('td');
                    idCell.textContent = format.format_id;
                    row.appendChild(idCell);
                    
                    // 拡張子
                    const extCell = document.createElement('td');
                    extCell.textContent = format.ext || 'N/A';
                    row.appendChild(extCell);
                    
                    // 解像度
                    const resCell = document.createElement('td');
                    if (format.height && format.width) {
                        resCell.textContent = `${format.width}x${format.height}`;
                    } else if (format.height) {
                        resCell.textContent = `${format.height}p`;
                    } else {
                        resCell.textContent = 'N/A';
                    }
                    row.appendChild(resCell);
                    
                    // ファイルサイズ
                    const sizeCell = document.createElement('td');
                    if (format.filesize) {
                        sizeCell.textContent = `${(format.filesize / (1024 * 1024)).toFixed(2)} MB`;
                    } else {
                        sizeCell.textContent = 'N/A';
                    }
                    row.appendChild(sizeCell);
                    
                    tbody.appendChild(row);
                }
            });
        }
        
        table.appendChild(tbody);
        formatsForm.appendChild(table);
        
        // ダウンロードボタン
        const downloadBtn = document.createElement('button');
        downloadBtn.type = 'submit';
        downloadBtn.name = 'download';
        downloadBtn.className = 'download-btn';
        downloadBtn.textContent = 'ダウンロード';
        formatsForm.appendChild(downloadBtn);
        
        // フォームの送信イベント
        formatsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            downloadVideo(formData);
        });
        
        formatsDiv.appendChild(formatsForm);
        videoInfoDiv.appendChild(formatsDiv);
        
        // 動画情報を表示
        const searchSection = document.querySelector('.search-section');
        searchSection.after(videoInfoDiv);
        
        showMessage('動画情報を取得しました', 'success');
    }
    
    // 動画をダウンロードする関数
    function downloadVideo(formData) {
        showMessage('ダウンロードを開始しています...', 'info');
        
        // FormDataオブジェクトからURLとフォーマットIDを取得
        const url = formData.get('url');
        const formatId = formData.get('format_id');
        
        // APIにPOSTリクエストを送信
        fetch('api.php?action=download', {
            method: 'POST',
            body: JSON.stringify({
                url: url,
                format_id: formatId
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showMessage(`エラー: ${data.error}`, 'error');
            } else {
                showMessage('ダウンロードを開始しました。完了までしばらくお待ちください。', 'success');
                // 5秒後にファイル一覧を更新
                setTimeout(updateFileList, 5000);
            }
        })
        .catch(error => {
            showMessage(`エラー: ${error.message}`, 'error');
        });
    }
    
    // ファイル一覧を更新する関数
    function updateFileList() {
        fetch('api.php?action=list')
            .then(response => response.json())
            .then(data => {
                displayFileList(data.files);
            })
            .catch(error => {
                console.error('ファイル一覧の更新に失敗しました:', error);
            });
    }
    
    // ファイル一覧を表示する関数
    function displayFileList(files) {
        const filesSection = document.querySelector('.downloaded-files');
        if (!filesSection) return;
        
        // 既存のテーブルを削除
        const existingTable = filesSection.querySelector('table');
        const existingMessage = filesSection.querySelector('p');
        
        if (existingTable) existingTable.remove();
        if (existingMessage) existingMessage.remove();
        
        if (files.length === 0) {
            const message = document.createElement('p');
            message.textContent = 'ダウンロード済みファイルはありません。';
            filesSection.appendChild(message);
            return;
        }
        
        // 新しいテーブルを作成
        const table = document.createElement('table');
        
        // テーブルヘッダー
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['ファイル名', 'サイズ', 'ダウンロード日時', 'アクション'].forEach(text => {
            const th = document.createElement('th');
            th.textContent = text;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);
        
        // テーブルボディ
        const tbody = document.createElement('tbody');
        
        files.forEach(file => {
            const row = document.createElement('tr');
            
            // ファイル名
            const nameCell = document.createElement('td');
            nameCell.textContent = file.name;
            row.appendChild(nameCell);
            
            // サイズ
            const sizeCell = document.createElement('td');
            sizeCell.textContent = file.formatted_size;
            row.appendChild(sizeCell);
            
            // 日時
            const timeCell = document.createElement('td');
            timeCell.textContent = file.formatted_time;
            row.appendChild(timeCell);
            
            // アクション
            const actionCell = document.createElement('td');
            
            // ダウンロードリンク
            const downloadLink = document.createElement('a');
            downloadLink.href = file.path;
            downloadLink.download = file.name;
            downloadLink.textContent = 'ダウンロード';
            actionCell.appendChild(downloadLink);
            
            // 再生リンク
            const playLink = document.createElement('a');
            playLink.href = `player.php?file=${encodeURIComponent(file.name)}`;
            playLink.textContent = '再生';
            playLink.style.marginLeft = '10px';
            actionCell.appendChild(playLink);
            
            // 削除リンク
            const deleteLink = document.createElement('a');
            deleteLink.href = '#';
            deleteLink.textContent = '削除';
            deleteLink.style.marginLeft = '10px';
            deleteLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('本当に削除しますか？')) {
                    deleteFile(file.name);
                }
            });
            actionCell.appendChild(deleteLink);
            
            row.appendChild(actionCell);
            
            tbody.appendChild(row);
        });
        
        table.appendChild(tbody);
        filesSection.appendChild(table);
    }
    
    // ファイルを削除する関数
    function deleteFile(filename) {
        const formData = new FormData();
        formData.append('filename', filename);
        
        fetch('api.php?action=delete', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showMessage(`エラー: ${data.error}`, 'error');
            } else {
                showMessage('ファイルを削除しました', 'success');
                updateFileList();
            }
        })
        .catch(error => {
            showMessage(`エラー: ${error.message}`, 'error');
        });
    }
    
    // メッセージを表示する関数
    function showMessage(text, type = 'info') {
        if (!messageDiv) return;
        
        messageDiv.textContent = text;
        
        // メッセージタイプに応じたスタイル設定
        messageDiv.className = 'message';
        
        switch (type) {
            case 'error':
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.borderColor = '#f5c6cb';
                break;
            case 'success':
                messageDiv.style.backgroundColor = '#d4edda';
                messageDiv.style.color = '#155724';
                messageDiv.style.borderColor = '#c3e6cb';
                break;
            case 'info':
            default:
                messageDiv.style.backgroundColor = '#d1ecf1';
                messageDiv.style.color = '#0c5460';
                messageDiv.style.borderColor = '#bee5eb';
                break;
        }
        
        // メッセージを表示
        messageDiv.style.display = 'block';
    }
    
    // 日付フォーマット関数 (YYYYMMDD -> YYYY-MM-DD)
    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        
        // 数値の場合はUNIXタイムスタンプとして処理
        if (typeof dateStr === 'number') {
            const date = new Date(dateStr * 1000);
            return date.toISOString().split('T')[0];
        }
        
        // 文字列の場合はYYYYMMDD形式と仮定
        if (dateStr.length === 8) {
            return `${dateStr.substring(0, 4)}-${dateStr.substring(4, 6)}-${dateStr.substring(6, 8)}`;
        }
        
        return dateStr;
    }
    
    // 時間フォーマット関数 (秒 -> HH:MM:SS)
    function formatDuration(seconds) {
        if (!seconds && seconds !== 0) return 'N/A';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        return [
            hours.toString().padStart(2, '0'),
            minutes.toString().padStart(2, '0'),
            secs.toString().padStart(2, '0')
        ].join(':');
    }
    
    // 初期ロード時にファイル一覧を更新
    updateFileList();
});