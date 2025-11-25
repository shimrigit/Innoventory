<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice_files'])) {

    // Start timing
    $startTime = microtime(true);

    // Capture supplier and process date information
    $processingOption = $_POST['processing_option'] ?? 'simple';
    $supplierName = 'N/A';
    $processDate = 'N/A';

    if ($processingOption === 'detailed') {
        // Get the Hebrew name from form
        $hebrewName = isset($_POST['supplier_name']) && !empty($_POST['supplier_name'])
            ? $_POST['supplier_name']
            : '';

        // Map Hebrew name to English supplierName
        if (!empty($hebrewName)) {
            $suppliersFile = __DIR__ . '/../suppliers.json';
            if (file_exists($suppliersFile)) {
                $suppliers = json_decode(file_get_contents($suppliersFile), true);
                foreach ($suppliers as $supplier) {
                    if ($supplier['hebrewName'] === $hebrewName) {
                        $supplierName = $supplier['supplierName'];
                        break;
                    }
                }
            }
            // If not found in mapping, keep as N/A
            if ($supplierName === 'N/A') {
                $supplierName = 'N/A';
            }
        }

        // Format date as dd-mm-yyyy
        $rawDate = isset($_POST['process_date']) && !empty($_POST['process_date'])
            ? $_POST['process_date']
            : '';
        if (!empty($rawDate)) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $rawDate);
            if ($dateObj) {
                $processDate = $dateObj->format('d-m-Y');
            }
        }
    }

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

    if ($fileCount < 4) {
        die("Error: Please select at least 4 PNG files (InvoiceNo, Date, Table1, Total). You selected {$fileCount} file(s).");
    }

    // Map files by their suffix
    $imageData = [];
    $tableFiles = []; // Array to store multiple table files in order
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

        // Check for InvoiceNo
        if (stripos($fileName, 'InvoiceNo') !== false) {
            $imageData['invoice_no'] = base64_encode(file_get_contents($tmpName));
            $foundFiles[] = 'InvoiceNo';
            $matched = true;
        }
        // Check for Date
        elseif (stripos($fileName, 'Date') !== false) {
            $imageData['date'] = base64_encode(file_get_contents($tmpName));
            $foundFiles[] = 'Date';
            $matched = true;
        }
        // Check for Table1, Table2, Table3, etc.
        elseif (preg_match('/Table(\d+)/i', $fileName, $matches)) {
            $tableNumber = (int)$matches[1];
            $tableFiles[$tableNumber] = base64_encode(file_get_contents($tmpName));
            $foundFiles[] = "Table{$tableNumber}";
            $matched = true;
        }
        // Check for Total
        elseif (stripos($fileName, 'Total') !== false) {
            $imageData['total'] = base64_encode(file_get_contents($tmpName));
            $foundFiles[] = 'Total';
            $matched = true;
        }

        if (!$matched) {
            die("Error: File '{$fileName}' does not match expected naming pattern. Files must contain: InvoiceNo, Date, Table1 (Table2, Table3...), or Total");
        }
    }

    // Sort table files by number to ensure correct order
    ksort($tableFiles);

    // Verify all required files are present
    if (!isset($imageData['invoice_no'])) {
        die("Error: Missing InvoiceNo file.");
    }
    if (!isset($imageData['date'])) {
        die("Error: Missing Date file.");
    }
    if (empty($tableFiles) || !isset($tableFiles[1])) {
        die("Error: Missing Table1 file. At least Table1 is required.");
    }
    if (!isset($imageData['total'])) {
        die("Error: Missing Total file.");
    }

    // Build system prompt - clear and specific
    $tableCount = count($tableFiles);
    $systemPrompt = <<<PROMPT
You are an OCR extraction system. Extract ALL data exactly as shown in the images.

IMAGES:
- Image 1: Invoice number
- Image 2: Invoice date
PROMPT;

    // Dynamically add table image descriptions
    if ($tableCount > 1) {
        $systemPrompt .= "\n- Images 3-" . ($tableCount + 2) . ": Table with rows and columns split across {$tableCount} images (Table1, Table2";
        if ($tableCount > 2) {
            $systemPrompt .= ", Table3";
        }
        if ($tableCount > 3) {
            for ($t = 4; $t <= $tableCount; $t++) {
                $systemPrompt .= ", Table{$t}";
            }
        }
        $systemPrompt .= "). These tables are CONTINUATIONS of the same table - combine all rows seamlessly.";
    } else {
        $systemPrompt .= "\n- Image 3: Table with rows and columns";
    }

    $systemPrompt .= "\n- Image " . ($tableCount + 3) . ": Invoice total amount\n\n";

    $systemPrompt .= <<<'PROMPT'
OUTPUT JSON FORMAT:
{
  "invoice_number": "extracted text",
  "invoice_date": "extracted date",
  "invoice_total": numeric_value,
  "table": [
    {"index": 1, "1": "value", "2": "value", "3": "value", ...},
    {"index": 2, "1": "value", "2": "value", "3": "value", ...},
    ...continue for EVERY row from ALL table images
  ]
}

IMPORTANT: "index" property is YOUR row counter (1,2,3...) - this is separate from any row number columns in the image

CRITICAL - MULTIPLE TABLE IMAGES:
⚠️ If multiple table images are provided (Table1, Table2, Table3, etc.), they represent CONTINUATION of the SAME table
⚠️ Table2 rows come IMMEDIATELY AFTER Table1 rows (no gap in index numbers)
⚠️ Table3 rows come IMMEDIATELY AFTER Table2 rows, etc.
⚠️ Example: If Table1 ends at index 38, Table2 MUST start at index 39
⚠️ ALL table images must be combined into ONE "table" array with sequential index values
⚠️ The column structure is IDENTICAL across all table images (same columns, same order)
⚠️ Extract EVERY row from EVERY table image in order

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
    // Build user content array with all images
    $userContent = [
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
        ]
    ];

    // Add all table images dynamically
    foreach ($tableFiles as $tableNum => $tableImageData) {
        $userContent[] = [
            "type" => "text",
            "text" => "Table{$tableNum}:"
        ];
        $userContent[] = [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . $tableImageData
            ]
        ];
    }

    // Add total image
    $userContent[] = [
        "type" => "text",
        "text" => "Total:"
    ];
    $userContent[] = [
        "type" => "image_url",
        "image_url" => [
            "url" => "data:image/png;base64," . $imageData['total']
        ]
    ];

    $data = [
        "model" => "gpt-4.1-mini",
        "messages" => [
            [
                "role" => "system",
                "content" => $systemPrompt
            ],
            [
                "role" => "user",
                "content" => $userContent
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

    // Calculate elapsed time
    $endTime = microtime(true);
    $elapsedTime = round($endTime - $startTime, 2);
    $elapsedMinutes = floor($elapsedTime / 60);
    $elapsedSeconds = $elapsedTime - ($elapsedMinutes * 60);
    $elapsedFormatted = $elapsedMinutes > 0
        ? sprintf("%d min %.2f sec", $elapsedMinutes, $elapsedSeconds)
        : sprintf("%.2f seconds", $elapsedTime);

    // Save JSON response to file
    $savedFileName = '';
    $saveSuccess = false;

    // Parse the response to get the JSON content
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        // Parse the JSON content from OpenAI response
        $extractedData = json_decode($result['choices'][0]['message']['content'], true);

        if ($extractedData !== null) {
            // Re-encode with pretty print formatting (indented, like in the display)
            $jsonContent = json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Generate filename
            $supplierPart = ($supplierName !== 'N/A') ? $supplierName : 'NA';
            $datePart = ($processDate !== 'N/A') ? $processDate : 'NA';
            $timestamp = date('dmY_His'); // ddmmyyyy_hhmmss format

            $savedFileName = "OCRjson_{$supplierPart}_{$datePart}_{$timestamp}.json";
            $saveDirectory = __DIR__ . '/ocr_extracted/';

            // Create directory if it doesn't exist
            if (!file_exists($saveDirectory)) {
                mkdir($saveDirectory, 0755, true);
            }

            $fullPath = $saveDirectory . $savedFileName;

            // Save the JSON content with pretty formatting
            if (file_put_contents($fullPath, $jsonContent) !== false) {
                $saveSuccess = true;
            }
        }
    }

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
        .status p { margin: 5px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .timing { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class='container'>";

    echo "<h2>📄 OpenAI OCR Processing Results</h2>";

    // Display file save status
    if ($saveSuccess && !empty($savedFileName)) {
        echo "<div class='section'>";
        echo "<h3>💾 File Saved</h3>";
        echo "<div class='status success'>";
        echo "File <strong>" . htmlspecialchars($savedFileName) . "</strong> was saved successfully";
        echo "</div>";
        echo "</div>";
    } elseif (!empty($savedFileName)) {
        echo "<div class='section'>";
        echo "<h3>⚠️ File Save Error</h3>";
        echo "<div class='status error'>";
        echo "Failed to save file <strong>" . htmlspecialchars($savedFileName) . "</strong>";
        echo "</div>";
        echo "</div>";
    }

    echo "<div class='section'>";
    echo "<h3>📋 Processing Information</h3>";
    echo "<div class='status info'>";
    echo "<p><strong>Supplier Name:</strong> " . htmlspecialchars($supplierName) . "</p>";
    echo "<p><strong>Process Date:</strong> " . htmlspecialchars($processDate) . "</p>";
    echo "</div>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<h3>⏱️ Processing Time</h3>";
    echo "<div class='status timing'>";
    echo "Total Processing Time: <strong>{$elapsedFormatted}</strong>";
    echo "</div>";
    echo "</div>";

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
        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
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

        /* Loading overlay styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-width: 400px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-content h3 {
            color: #333;
            margin: 0 0 10px 0;
        }
        .loading-content p {
            color: #666;
            margin: 5px 0;
            font-size: 14px;
        }
        .loading-content .estimate {
            color: #007bff;
            font-weight: bold;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📄 Invoice OCR AI Processing</h2>

        <div class="info-box">
            <p><strong>ℹ️ Instructions:</strong></p>
            <p style="background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107; margin-bottom: 10px;">
                <strong>📁 Files Location:</strong> Navigate to <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">C:\xampp\htdocs\website\AIocr\crops</code> when selecting files
            </p>
            <p>Please select at least 4 PNG files from your directory. The files must be named with these suffixes:</p>
            <p>1. <strong>InvoiceNo</strong> - The invoice number image (e.g., "invoice123_InvoiceNo.png")</p>
            <p>2. <strong>Date</strong> - The invoice date image (e.g., "invoice123_Date.png")</p>
            <p>3. <strong>Table1</strong> - The first part of the items table (e.g., "invoice123_Table1.png")</p>
            <p style="margin-left: 20px; color: #0066cc;">
                <strong>Optional:</strong> <strong>Table2, Table3, ...</strong> - Additional table continuation images if the table spans multiple pages<br>
                (e.g., "invoice123_Table2.png", "invoice123_Table3.png"). These will be combined seamlessly with Table1.
            </p>
            <p>4. <strong>Total</strong> - The invoice total (e.g., "invoice123_Total.png")</p>
            <p><strong>Tip:</strong> Hold Ctrl (Windows/Linux) or Cmd (Mac) to select multiple files from the same folder.</p>
        </div>

        <form method="post" enctype="multipart/form-data">
            <!-- Radio Button Options -->
            <div class="form-group">
                <label><strong>📋 Processing Options:</strong></label>
                <div style="margin-top: 10px;">
                    <label style="display: block; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="processing_option" value="simple" id="option_simple" checked style="margin-right: 8px;">
                        Option 1: Process without supplier name and process date
                    </label>
                    <label style="display: block; cursor: pointer;">
                        <input type="radio" name="processing_option" value="detailed" id="option_detailed" style="margin-right: 8px;">
                        Option 2: Add supplier name and process date
                    </label>
                </div>
            </div>

            <!-- Supplier and Date Fields (Hidden by default) -->
            <div id="detailedFields" style="display: none;">
                <div class="form-group">
                    <label for="supplier_name">🏢 Supplier Name:</label>
                    <input type="text" id="supplier_name" name="supplier_name" list="supplierList"
                           placeholder="Start typing to search..."
                           style="width: 100%; padding: 10px; border: 2px solid #ccc; border-radius: 4px; font-size: 14px;">
                    <datalist id="supplierList">
                        <?php
                        // Load suppliers from JSON file
                        $suppliersFile = __DIR__ . '/../suppliers.json';
                        if (file_exists($suppliersFile)) {
                            $suppliers = json_decode(file_get_contents($suppliersFile), true);
                            // Sort by hebrewName
                            usort($suppliers, function($a, $b) {
                                return strcmp($a['hebrewName'], $b['hebrewName']);
                            });
                            // Generate options
                            foreach ($suppliers as $supplier) {
                                echo '<option value="' . htmlspecialchars($supplier['hebrewName']) . '" data-supplier="' . htmlspecialchars($supplier['supplierName']) . '">';
                            }
                        }
                        ?>
                    </datalist>
                    <small style="display:block; margin-top:5px; color:#666;">Select supplier from the dropdown list</small>
                </div>

                <div class="form-group">
                    <label for="process_date">📅 Process Date:</label>
                    <input type="date" id="process_date" name="process_date"
                           value="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%; padding: 10px; border: 2px solid #ccc; border-radius: 4px; font-size: 14px;">
                    <small style="display:block; margin-top:5px; color:#666;">Default is today's date</small>
                </div>
            </div>

            <div class="form-group">
                <label for="invoice_files">📁 Select Invoice PNG Files (Minimum 4, More if Multiple Tables)</label>
                <input type="file" id="invoice_files" name="invoice_files[]" accept=".png,image/png" multiple required>
                <small style="display:block; margin-top:10px; color:#666;">Select all required files at once (InvoiceNo, Date, Table1, Total, and optionally Table2, Table3, etc.)</small>
            </div>

            <button type="submit" id="submitBtn">🚀 Process Invoice with AI</button>
        </form>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Processing Invoice...</h3>
            <p>Analyzing images with OpenAI</p>
            <p>Extracting invoice data</p>
            <p class="estimate">Elapsed time: <span id="timeCounter">0 seconds</span></p>
            <p style="margin-top: 10px; color: #999; font-size: 13px;">Estimated: 1-2 minutes</p>
            <p style="margin-top: 10px; color: #999; font-size: 12px;">Please do not close this window</p>
        </div>
    </div>

    <script>
        // Toggle detailed fields based on radio selection
        document.getElementById('option_simple').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('detailedFields').style.display = 'none';
            }
        });

        document.getElementById('option_detailed').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('detailedFields').style.display = 'block';
            }
        });

        // Show loading overlay when form is submitted
        document.querySelector('form').addEventListener('submit', function(e) {
            // Validate that files are selected
            const fileInput = document.getElementById('invoice_files');
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please select files before submitting.');
                e.preventDefault();
                return false;
            }

            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');

            // Disable submit button
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Processing...';

            // Start time counter
            let seconds = 0;
            const timeCounter = document.getElementById('timeCounter');
            const timerInterval = setInterval(function() {
                seconds++;
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                if (minutes > 0) {
                    // Show as M:SS format without "seconds"
                    timeCounter.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                } else {
                    // Show as "X seconds" format
                    timeCounter.textContent = `${seconds} seconds`;
                }
            }, 1000);

            // Store interval ID in case we need to clear it (though page will reload anyway)
            window.processingTimer = timerInterval;

            // Allow form to submit normally
            return true;
        });
    </script>
</body>
</html>
