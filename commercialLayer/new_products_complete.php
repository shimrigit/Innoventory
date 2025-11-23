<?php
/**
 * New Products Process Completion Page
 */

session_start();

$npFileName = $_GET['npFile'] ?? null;
$npComplete = $_SESSION['np_complete'] ?? false;

if (!$npFileName || !$npComplete) {
    header('Location: index.php');
    exit;
}

// Clear session flag
unset($_SESSION['np_complete']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>תהליך מוצרים חדשים הושלם</title>
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

        .completion-message {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="completion-card">
        <div class="success-icon">✓</div>

        <h1>תהליך המוצרים החדשים הושלם!</h1>

        <div class="message">
            קובץ המוצרים החדשים נשמר בהצלחה ומוכן ליישום במערכת
        </div>

        <div class="file-info">
            <p><strong>שם הקובץ:</strong> <?= htmlspecialchars($npFileName) ?></p>
            <p><strong>תאריך:</strong> <?= date('Y-m-d H:i:s') ?></p>
            <p><strong>מיקום:</strong> commercialLayer/commercial_invoice_files/</p>
        </div>

        <div class="buttons">
            <a href="index.php" class="button button-primary">חזרה לדף הראשי</a>
            <a href="verify_new_products.php?npFile=<?= urlencode($npFileName) ?>" class="button button-secondary">צפה בקובץ שוב</a>
        </div>

        <div class="completion-message">
            🎉 שלב ה-Commercial Layer הושלם בהצלחה!
        </div>
    </div>
</body>
</html>
