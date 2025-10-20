<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice_files'])) {

    // Load API key from config file
    $configFile = __DIR__ . '/config.json';
    if (!file_exists($configFile)) {
        die('Error: config.json not found. Please create it with your OpenAI API key.');
    }
    $config = json_decode(file_get_contents($configFile), true);
    $apiKey = $config['openai_api_key'] ?? null;
    if (empty($apiKey) || $apiKey === 'YOUR_NEW_API_KEY_HERE') {
        die('Error: Please set your OpenAI API key in config.json');
    }

    // Process multiple uploaded files
    $uploadedFiles = $_FILES['invoice_files'];
    $fileCount = count($uploadedFiles['name']);

    if ($fileCount !== 4) {
        die("Error: Please select exactly 4 PNG files. You selected {$fileCount} file(s).");
    }

    // Map files by their suffix
    $imageData = [];
    $fileMapping = [
        'InvoiceNo.png' => 'invoice_no',
        'Date.png' => 'date',
        'Table1.png' => 'table1',
        'Total.png' => 'total'
    ];

    $foundFiles = [];

    for ($i = 0; $i < $fileCount; $i++) {
        if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
            die("Error uploading file {$uploadedFiles['name'][$i]}: " . $uploadedFiles['error'][$i]);
        }

        $fileName = $uploadedFiles['name'][$i];
        $tmpName = $uploadedFiles['tmp_name'][$i];

        // Validate PNG format
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if ($mimeType !== 'image/png') {
            die("Error: {$fileName} must be a PNG file. Detected: {$mimeType}");
        }

        // Match file by suffix
        $matched = false;
        foreach ($fileMapping as $suffix => $key) {
            if (stripos($fileName, str_replace('.png', '', $suffix)) !== false) {
                $imageData[$key] = base64_encode(file_get_contents($tmpName));
                $foundFiles[] = $suffix;
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            die("Error: File '{$fileName}' does not match expected naming pattern. Files must contain: InvoiceNo, Date, Table1, or Total");
        }
    }

    // Verify all required files are present
    $requiredKeys = ['invoice_no', 'date', 'table1', 'total'];
    foreach ($requiredKeys as $key) {
        if (!isset($imageData[$key])) {
            die("Error: Missing required file. Please ensure you have files with names containing: InvoiceNo, Date, Table1, and Total");
        }
    }

    // Build system prompt - clear and specific
    $systemPrompt = <<<'PROMPT'
You are an OCR extraction system. Extract ALL data exactly as shown in the images.

IMAGES:
- Image 1: Invoice number
- Image 2: Invoice date
- Image 3: Table with rows and columns
- Image 4: Invoice total amount

OUTPUT JSON FORMAT:
{
  "invoice_number": "extracted text",
  "invoice_date": "extracted date",
  "invoice_total": numeric_value,
  "table": [
    {"index": 1, "1": "value", "2": "value", "3": "value", ...},
    {"index": 2, "1": "value", "2": "value", "3": "value", ...},
    ...continue for EVERY row
  ]
}

IMPORTANT: "index" property is YOUR row counter (1,2,3...) - this is separate from any row number columns in the image

CRITICAL RULE: ALWAYS LEFT-TO-RIGHT COLUMN NUMBERING (CONSISTENT FOR ALL INVOICES)

ABSOLUTE POSITIONING RULES:
1. Count ALL vertical columns strictly from LEFT to RIGHT across the page
2. Column "1" = LEFTMOST column (furthest left edge)
3. Column "2" = second from left
4. Column "3" = third from left, etc.
5. Extract EVERY column you see - do not skip any columns
6. ALWAYS use left-to-right numbering - this is MANDATORY for consistency
7. Column position is based ONLY on physical X-axis location, NOT content

CRITICAL: ROW NUMBER COLUMNS IN THE TABLE IMAGE - MUST EXTRACT AS DATA
⚠️ IMPORTANT: Tables often have a column showing row numbers 1,2,3,4... - this is DATA, NOT metadata
⚠️ The "index" property in JSON is YOUR counter (always set it: "index": 1, "index": 2, etc.)
⚠️ If TABLE IMAGE has a column with 1,2,3,4... extract it as a SEPARATE numbered column ("1", "2", "3", etc.)
⚠️ Common locations: leftmost column OR rightmost column
⚠️ Even if rightmost column shows 1,2,3,4... you MUST extract it as the highest column number
⚠️ Example: If table has 7 columns and rightmost shows "1","2","3", extract as column "7"
⚠️ DO NOT skip this column thinking it's redundant with "index" - they are different
⚠️ Your "index" property counts rows (1,2,3...), row number columns are just data in specific positions

DETECTION RULE FOR ROW NUMBER COLUMNS IN IMAGE:
- If you see a column with sequential numbers (1,2,3,4...), it's DATA
- Extract it like any other column based on its LEFT-TO-RIGHT position
- If it's the rightmost column with 7 total columns, it becomes column "7": "1", "7": "2", etc.
- If it's the leftmost column, it becomes column "1": "1", "1": "2", etc.
- The "index" property is always separate from these column numbers

CRITICAL: COLUMN POSITIONING - VISUAL EXAMPLE WITH HEBREW
When you see cells arranged like this from LEFT to RIGHT:
[418.40] [5.23] [80.00] [חלב קקאו] [7290001234567]
You MUST output:
"1": "418.40", "2": "5.23", "3": "80.00", "4": "חלב קקאו", "5": "7290001234567"

WRONG - DO NOT DO THIS:
"1": "418.40", "2": "5.23", "3": "80.00", "4": "7290001234567", "5": "חלב קקאו"

KEY RULE: The cell with Hebrew text is physically BEFORE (to the left of) the barcode cell
Therefore: Hebrew text = lower column number, Barcode = higher column number

Look at cell BORDERS/EDGES left-to-right, NOT at text direction inside cells

FORBIDDEN ACTIONS - DO NOT DO THESE:
❌ DO NOT skip row number columns (1,2,3...) - they are DATA not metadata
❌ DO NOT ignore rightmost column if it contains sequential numbers
❌ DO NOT ignore leftmost column if it contains sequential numbers
❌ DO NOT swap columns based on text reading direction
❌ DO NOT reorder Hebrew text based on how it reads
❌ DO NOT read columns right-to-left even if text is Hebrew/Arabic
❌ DO NOT put Hebrew text in different position than its visual column
❌ DO NOT move barcodes or numbers to different columns
❌ DO NOT reorganize columns by content type or language
❌ DO NOT skip column numbers - must be sequential 1,2,3,4...
❌ DO NOT move empty columns to the end

REQUIRED ACTIONS - MUST DO THESE (LEFT-TO-RIGHT):
✓ Count ALL columns by visual boundaries LEFT to RIGHT
✓ Extract row number column if it exists in the image
✓ LEFTMOST column = "1" ALWAYS (regardless of content)
✓ If cell is in 4th position from LEFT, it MUST be property "4"
✓ Empty column in position = empty string ""
✓ Physical left-to-right position determines column number ALWAYS
✓ Extract every visible column in the table

COLUMN NUMBERING LOGIC (ALWAYS LEFT TO RIGHT):
Leftmost edge position → "1": "content here"
2nd from left → "2": "content here"
3rd from left → "3": "content here"
4th from left → "4": "content here"
Position N from left → "N": "content here"

Each row MUST have ALL columns in sequential order 1,2,3,4... with no gaps.
Extract EVERY row. Cell values = exact text/number, no limit.
PROMPT;

    // Build API request
    $data = [
        "model" => "gpt-4.1-mini",
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
                        "text" => "Invoice#:"
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/png;base64," . $imageData['invoice_no']
                        ]
                    ],
                    [
                        "type" => "text",
                        "text" => "Date:"
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/png;base64," . $imageData['date']
                        ]
                    ],
                    [
                        "type" => "text",
                        "text" => "Table:"
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/png;base64," . $imageData['table1']
                        ]
                    ],
                    [
                        "type" => "text",
                        "text" => "Total:"
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/png;base64," . $imageData['total']
                        ]
                    ]
                ]
            ]
        ],
        "max_tokens" => 16000,
        "temperature" => 0.0,
        "top_p" => 1.00,
        "store" => true,
        "response_format" => [
            "type" => "json_object"
        ]
    ];

    // Make API request
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Display results
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>OCR AI Processing Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 14px; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class='container'>";

    echo "<h2>📄 OpenAI OCR Processing Results</h2>";

    echo "<div class='section'>";
    echo "<h3>📊 HTTP Status Code</h3>";
    echo "<div class='status " . ($httpCode == 200 ? 'success' : 'error') . "'>";
    echo "HTTP Response Code: <strong>{$httpCode}</strong>";
    echo "</div>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<h3>📦 Full API Response</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo "</div>";

    // Parse and display structured response
    $result = json_decode($response, true);

    if (isset($result['error'])) {
        echo "<div class='section'>";
        echo "<h3>❌ API Error</h3>";
        echo "<div class='status error'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($result['error']['message'] ?? 'Unknown error');
        if (isset($result['error']['type'])) {
            echo "<br><strong>Type:</strong> " . htmlspecialchars($result['error']['type']);
        }
        echo "</div>";
        echo "</div>";
    } elseif (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];

        echo "<div class='section'>";
        echo "<h3>💬 OpenAI Response Content</h3>";
        echo "<pre>" . htmlspecialchars($content) . "</pre>";
        echo "</div>";

        // Try to parse as JSON
        $extractedData = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($extractedData)) {
            echo "<div class='section'>";
            echo "<h3>✅ Extracted Invoice Data (Parsed JSON)</h3>";
            echo "<pre>" . htmlspecialchars(json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
            echo "</div>";

            // Display in a more readable format
            if (isset($extractedData['invoice_number']) || isset($extractedData['invoice_date']) || isset($extractedData['invoice_total'])) {
                echo "<div class='section'>";
                echo "<h3>📋 Invoice Summary</h3>";
                echo "<div class='info'>";
                if (isset($extractedData['invoice_number'])) {
                    echo "<p><strong>Invoice Number:</strong> " . htmlspecialchars($extractedData['invoice_number']) . "</p>";
                }
                if (isset($extractedData['invoice_date'])) {
                    echo "<p><strong>Invoice Date:</strong> " . htmlspecialchars($extractedData['invoice_date']) . "</p>";
                }
                if (isset($extractedData['invoice_total'])) {
                    echo "<p><strong>Invoice Total:</strong> " . htmlspecialchars($extractedData['invoice_total']) . "</p>";
                }
                echo "</div>";
                echo "</div>";

                // Display table data
                if (isset($extractedData['table']) && is_array($extractedData['table'])) {
                    echo "<div class='section'>";
                    echo "<h3>📊 Invoice Table Data</h3>";
                    echo "<table border='1' cellpadding='8' cellspacing='0' style='width:100%; border-collapse: collapse;'>";
                    echo "<thead><tr style='background:#007bff; color:white;'><th>Index</th><th>Data</th></tr></thead>";
                    echo "<tbody>";
                    foreach ($extractedData['table'] as $row) {
                        echo "<tr>";
                        echo "<td><strong>" . htmlspecialchars($row['index'] ?? $row['row'] ?? 'N/A') . "</strong></td>";
                        echo "<td><pre style='margin:0;'>" . htmlspecialchars(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                }
            }
        } else {
            echo "<div class='section'>";
            echo "<div class='status error'>";
            echo "<strong>⚠️ Note:</strong> The response content is not valid JSON or could not be parsed.";
            echo "<br><strong>JSON Error:</strong> " . json_last_error_msg();
            echo "</div>";
            echo "</div>";
        }

        // Display usage information if available
        if (isset($result['usage'])) {
            echo "<div class='section'>";
            echo "<h3>📈 API Usage Statistics</h3>";
            echo "<div class='info'>";
            echo "<p><strong>Prompt Tokens:</strong> " . htmlspecialchars($result['usage']['prompt_tokens'] ?? 'N/A') . "</p>";
            echo "<p><strong>Completion Tokens:</strong> " . htmlspecialchars($result['usage']['completion_tokens'] ?? 'N/A') . "</p>";
            echo "<p><strong>Total Tokens:</strong> " . htmlspecialchars($result['usage']['total_tokens'] ?? 'N/A') . "</p>";
            echo "</div>";
            echo "</div>";
        }

        // Display other comments/metadata
        if (isset($result['model'])) {
            echo "<div class='section'>";
            echo "<h3>🤖 Model Information</h3>";
            echo "<div class='info'>";
            echo "<p><strong>Model Used:</strong> " . htmlspecialchars($result['model']) . "</p>";
            if (isset($result['id'])) {
                echo "<p><strong>Request ID:</strong> " . htmlspecialchars($result['id']) . "</p>";
            }
            if (isset($result['created'])) {
                echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $result['created']) . " (" . htmlspecialchars($result['created']) . ")</p>";
            }
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div class='section'>";
        echo "<div class='status error'>";
        echo "<strong>❌ Error:</strong> No valid response content received from OpenAI API.";
        echo "</div>";
        echo "</div>";
    }

    echo "<a href='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' class='back-link'>⬅️ Process Another Invoice</a>";
    echo "</div></body></html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice OCR AI Processing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .form-group {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #555;
        }
        input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            border: 2px dashed #ccc;
            border-radius: 4px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        input[type="file"]:hover {
            border-color: #007bff;
        }
        button {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: background 0.3s;
        }
        button:hover {
            background: #0056b3;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📄 Invoice OCR AI Processing</h2>

        <div class="info-box">
            <p><strong>ℹ️ Instructions:</strong></p>
            <p>Please select all 4 PNG files at once from your directory. The files must be named with these suffixes:</p>
            <p>1. <strong>InvoiceNo</strong> - The invoice number image (e.g., "invoice123_InvoiceNo.png")</p>
            <p>2. <strong>Date</strong> - The invoice date image (e.g., "invoice123_Date.png")</p>
            <p>3. <strong>Table1</strong> - The items table (e.g., "invoice123_Table1.png")</p>
            <p>4. <strong>Total</strong> - The invoice total (e.g., "invoice123_Total.png")</p>
            <p><strong>Tip:</strong> Hold Ctrl (Windows/Linux) or Cmd (Mac) to select multiple files from the same folder.</p>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="invoice_files">📁 Select All 4 Invoice PNG Files</label>
                <input type="file" id="invoice_files" name="invoice_files[]" accept=".png,image/png" multiple required>
                <small style="display:block; margin-top:10px; color:#666;">Click to browse and select all 4 files at once</small>
            </div>

            <button type="submit">🚀 Process Invoice with AI</button>
        </form>
    </div>
</body>
</html>
