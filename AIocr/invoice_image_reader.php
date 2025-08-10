<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice_image'])) {
    $apiKey = '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'; // 🔐 Replace with your OpenAI API key

    // Read image file content
    $imagePath = $_FILES['invoice_image']['tmp_name'];
    $imageData = base64_encode(file_get_contents($imagePath));

    // Build system prompt (Hebrew)
    $systemPrompt = <<<PROMPT
 הצג את שורות המוצרים  מהתמונה, מבלי לכלול שורות סיכום כמו שם הספק . כל שורה תכיל : שם מוצר, כמות, מחיר, סה״כ.
החזר בפורמט JSON
וודא סהכ כמות 230
PROMPT;

    // Step 1: Use GPT-4o vision model to analyze the image
    $data = [
        "model" => "gpt-4o",
        "messages" => [
            [
                "role" => "system",
                "content" => $systemPrompt
            ],
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "להלן תמונת החשבונית:"
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/png;base64," . $imageData
                        ]
                    ]
                ]
            ]
        ],
        "max_tokens" => 1000,
        "temperature" => 0
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

// Full debug output of raw API response
echo "<h3>📋 שורות המוצרים שהתקבלו:</h3>";
echo "<h4>📦 Raw API Response:</h4>";
echo "<pre style='background:#f4f4f4; border:1px solid #ccc; padding:10px; font-size:13px; overflow:auto;'>"
   . htmlspecialchars($response) .
   "</pre>";

// Decode full response
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $rawContent = $result['choices'][0]['message']['content'];
        echo "<h4>📄 GPT Message Content:</h4>";
        echo "<pre style='background:#eef; border:1px solid #99c; padding:10px;'>"
            . htmlspecialchars($rawContent) . "</pre>";

        // Try to decode content as JSON
        $products = json_decode($rawContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<p style='color:red;'>❌ JSON decode error: " . json_last_error_msg() . "</p>";
        }

        if (is_array($products)) {
            echo "<table border='1' cellpadding='8'><tr><th>שם מוצר</th><th>כמות</th><th>יחידה</th><th>מחיר</th><th>סה\"כ</th></tr>";
            foreach ($products as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['שם מוצר'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['כמות'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['יחידה'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['מחיר'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['סה\"כ'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p><strong>⚠️ GPT did not return valid JSON. See content above.</strong></p>";
        }
    } elseif (isset($result['error']['message'])) {
        echo "<p style='color:red;'><strong>❌ API Error:</strong> "
            . htmlspecialchars($result['error']['message']) . "</p>";
    } else {
        echo "<p style='color:red;'>❌ No valid response from OpenAI API.</p>";
    }

        echo '<hr><a href="' . $_SERVER['PHP_SELF'] . '">העלה תמונה נוספת</a>';
        exit;
    }
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>קריאת חשבונית מתמונה</title>
</head>
<body>
  <h2>העלה תמונת חשבונית (PNG בלבד)</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="invoice_image" accept=".png" required>
    <br><br>
    <button type="submit">נתח את החשבונית</button>
  </form>
</body>
</html>
