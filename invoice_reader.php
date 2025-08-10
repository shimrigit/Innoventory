<?php
// Display file picker and handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice_file'])) {
    $apiKey = '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'; // 🔐 Replace with your OpenAI API key

    // Read file content
    $fileContent = file_get_contents($_FILES['invoice_file']['tmp_name']);
    $fileContent = mb_convert_encoding($fileContent, 'UTF-8');

    // Build system prompt
    $systemPrompt = <<<PROMPT
You receive a PDF invoice in Hebrew. Extract:
1. invoice_date (dd/mm/yyyy at top)
2. invoice_number (10-digit number one line below date)
3. total_before_vat (4 lines above the line containing “18.00%”)
Return only this JSON:
{
  "invoice_date": "...",
  "invoice_number": "...",
  "total_before_vat": "..."
}
PROMPT;

    // Construct messages
    $messages = [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => "The extracted text from the invoice is:\n\n" . $fileContent]
    ];

    $data = [
        "model" => "gpt-4o",
        "messages" => $messages,
        "temperature" => 0.0,
        "max_tokens" => 300
    ];

    // Send to OpenAI API
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

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        $content = json_decode($result['choices'][0]['message']['content'], true);
        echo "<h3>Extracted Invoice Data:</h3>";
        echo "📅 <strong>Invoice Date:</strong> " . htmlspecialchars($content['invoice_date'] ?? 'Not found') . "<br>";
        echo "🧾 <strong>Invoice Number:</strong> " . htmlspecialchars($content['invoice_number'] ?? 'Not found') . "<br>";
        echo "💵 <strong>Total Before VAT:</strong> " . htmlspecialchars($content['total_before_vat'] ?? 'Not found') . "<br>";
    } else {
        echo "<pre>API Error:\n" . htmlspecialchars($response) . "</pre>";
    }

    echo '<hr><a href="' . $_SERVER['PHP_SELF'] . '">Upload another file</a>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Reader</title>
</head>
<body>
    <h2>Upload Invoice PDF</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="invoice_file" accept=".pdf" required>
        <br><br>
        <button type="submit">Analyze Invoice</button>
    </form>
</body>
</html>
