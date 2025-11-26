<?php
/**
 * CL Process Completion Page (Harmonized Flow)
 * Used when user concludes CL stage without generating PC/NP files
 */

session_start();

$clFileName = $_GET['cl'] ?? null;
$shopName = $_GET['shop'] ?? null;

if (!$clFileName) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>תהליך Commercial Layer הושלם</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .completion-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
        }

        .success-icon {
            font-size: 5em;
            color: #28a745;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        .message {
            font-size: 1.3em;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .file-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-right: 5px solid #28a745;
        }

        .file-info p {
            font-size: 1.1em;
            margin: 10px 0;
            color: #333;
        }

        .file-info strong {
            color: #667eea;
        }

        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .button {
            padding: 15px 30px;
            font-size: 1.1em;
            font-weight: bold;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .button-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .button-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .button-secondary {
            background: #6c757d;
            color: white;
        }

        .button-secondary:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        .info-note {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-radius: 10px;
            border-right: 5px solid #ffc107;
        }

        .info-note h2 {
            color: #856404;
            font-size: 1.3em;
            margin-bottom: 10px;
        }

        .info-note p {
            color: #856404;
            font-size: 1em;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="completion-card">
        <div class="success-icon">✓</div>

        <h1>תהליך ה-Commercial Layer הושלם!</h1>

        <div class="message">
            קובץ ה-CL נשמר בהצלחה<br>
            לא בוצעה בדיקת שינויי מחירים ומוצרים חדשים
        </div>

        <div class="file-info">
            <p><strong>קובץ CL:</strong> <?= htmlspecialchars($clFileName) ?></p>
            <?php if ($shopName): ?>
                <p><strong>חנות:</strong> <?= htmlspecialchars($shopName) ?></p>
            <?php endif; ?>
            <p><strong>תאריך:</strong> <?= date('Y-m-d H:i:s') ?></p>
            <p><strong>מיקום:</strong> commercialLayer/commercial_invoice_files/</p>
        </div>

        <div class="buttons">
            <button id="cleanupBtn" class="button button-primary">Cleanup and continue to next invoice</button>
        </div>

        <div class="info-note">
            <h2>ℹ️ שים לב</h2>
            <p>
                קובץ ה-CL נשמר אך לא נוצרו קבצי PC (שינוי מחירים) ו-NP (מוצרים חדשים).<br>
                אם תרצה לבצע בדיקת שינויי מחירים או מוצרים חדשים, חזור לקובץ ה-CL ולחץ על כפתור
                "📊 Generate Price Change and New Products Files".
            </p>
        </div>
    </div>

    <script>
        document.getElementById('cleanupBtn').addEventListener('click', function() {
            // Disable button
            this.disabled = true;
            this.textContent = '⏳ Cleaning up...';

            // Get file information from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const clFile = urlParams.get('cl');
            const shopName = urlParams.get('shop');

            // Build cleanup URL with file parameters
            const cleanupUrl = '/website/harmonizedFlow/cleanup_process.php?' +
                'clFile=' + encodeURIComponent(clFile || '');

            // Call cleanup process
            fetch(cleanupUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to cleanup results screen
                        const resultsJson = encodeURIComponent(JSON.stringify(data));
                        window.location.href = '/website/harmonizedFlow/cleanup_results.php?results=' + resultsJson;
                    } else {
                        alert('❌ Cleanup error: ' + data.error);
                        this.disabled = false;
                        this.textContent = 'Cleanup and continue to next invoice';
                    }
                })
                .catch(error => {
                    alert('❌ Error: ' + error);
                    this.disabled = false;
                    this.textContent = 'Cleanup and continue to next invoice';
                });
        });
    </script>
</body>
</html>
