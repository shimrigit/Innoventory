<?php
/**
 * Price Change Process Completion Page
 */

session_start();

$pcFileName = $_GET['pcFile'] ?? null;
$verificationComplete = $_SESSION['verification_complete'] ?? false;

if (!$pcFileName || !$verificationComplete) {
    header('Location: index.php');
    exit;
}

// Clear session flag
unset($_SESSION['verification_complete']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>תהליך שינוי מחירים הושלם</title>
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

        .next-steps {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }

        .next-steps h2 {
            color: #667eea;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .next-steps ul {
            text-align: right;
            list-style: none;
            padding: 0;
        }

        .next-steps li {
            background: #f8f9fa;
            margin: 10px 0;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 1.1em;
            color: #333;
        }

        .next-steps li::before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="completion-card">
        <div class="success-icon">✓</div>

        <h1>תהליך שינוי המחירים הושלם!</h1>

        <div class="message">
            קובץ שינויי המחירים נשמר בהצלחה ומוכן ליישום במערכת
        </div>

        <div class="file-info">
            <p><strong>שם הקובץ:</strong> <?= htmlspecialchars($pcFileName) ?></p>
            <p><strong>תאריך:</strong> <?= date('Y-m-d H:i:s') ?></p>
            <p><strong>מיקום:</strong> commercialLayer/PC/</p>
        </div>

        <div class="buttons">
            <a href="index.php" class="button button-primary">חזרה לדף הראשי</a>
            <a href="verify_price_changes.php?pcFile=<?= urlencode($pcFileName) ?>" class="button button-secondary">צפה בקובץ שוב</a>
        </div>

        <div class="next-steps">
            <h2>השלבים הבאים:</h2>
            <ul>
                <li>יבוא קובץ השינויים למערכת ה-ERP</li>
                <li>עדכון מחירי המכירה בסניפים</li>
                <li>בדיקת עדכון תוויות המדף</li>
                <li>מעקב אחר השפעת שינויי המחירים על המכירות</li>
            </ul>
        </div>
    </div>
</body>
</html>
