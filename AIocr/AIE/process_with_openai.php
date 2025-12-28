<?php
/**
 * AI Enhancer - Process with OpenAI
 *
 * Reads configuration from AIE_config.json
 * Sends crops to OpenAI for OCR processing
 * Displays results and saves to ocr_extracted directory
 */

function processWithOpenAI($uploadedFiles) {
    // Start timing
    $startTime = microtime(true);

    // Get PDF basename from session (if from PDF cropping) or extract from uploaded files
    $pdfBaseName = $_SESSION['aie_pdf_basename'] ?? null;

    // Load AIE configuration
    $configFile = __DIR__ . '/AIE_config.json';
    if (!file_exists($configFile)) {
        die('Error: AIE_config.json not found. Configuration file is required.');
    }

    $aieConfig = json_decode(file_get_contents($configFile), true);
    if (!$aieConfig) {
        die('Error: Failed to parse AIE_config.json - invalid JSON format');
    }

    // Load OpenAI API key from parent config
    $apiConfigFile = __DIR__ . '/../config.json';
    if (!file_exists($apiConfigFile)) {
        die('Error: config.json not found. Please create it with your OpenAI API key.');
    }

    $apiConfig = json_decode(file_get_contents($apiConfigFile), true);
    $apiKey = $apiConfig['openai_api_key'] ?? null;

    if (empty($apiKey) || $apiKey === 'YOUR_NEW_API_KEY_HERE') {
        die('Error: Please set your OpenAI API key in AIocr/config.json');
    }

    // Process uploaded files
    $fileCount = count($uploadedFiles['name']);

    if ($fileCount < 4) {
        die("Error: Please select at least 4 PNG files (InvoiceNo, Date, Table1, Total). You selected {$fileCount} file(s).");
    }

    // Map files by their suffix
    $imageData = [];
    $tableFiles = []; // Array to store multiple table files in order
    $foundFiles = [];

    // Extract basename from first uploaded file if not already set from PDF cropping
    if (!$pdfBaseName && $fileCount > 0) {
        $firstFileName = $uploadedFiles['name'][0];
        // Try to extract prefix before the first underscore + suffix pattern
        // Expected: "XXXX_dd-mm-yy_Z_InvoiceNo.png" or similar
        if (preg_match('/^(.+?)_(InvoiceNo|Date|Table\d+|Total)\.png$/i', $firstFileName, $matches)) {
            $pdfBaseName = $matches[1];
        } else {
            // Fallback: use filename without suffix
            $pdfBaseName = pathinfo($firstFileName, PATHINFO_FILENAME);
            $pdfBaseName = preg_replace('/_(InvoiceNo|Date|Table\d+|Total)$/i', '', $pdfBaseName);
        }
    }

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

    // Build system prompt from AIE config
    $promptConfig = $aieConfig['prompt'];
    $tableCount = count($tableFiles);

    $systemPrompt = $promptConfig['system_prompt_part1'];

    // Add table description
    if ($tableCount > 1) {
        $additional = '';
        if ($tableCount > 2) {
            $additional .= ', Table3';
        }
        if ($tableCount > 3) {
            for ($t = 4; $t <= $tableCount; $t++) {
                $additional .= ", Table{$t}";
            }
        }

        $tableSection = str_replace('{tableCount}', $tableCount, $promptConfig['system_prompt_table_multiple_suffix']);
        $tableSection = str_replace('{additional}', $additional, $tableSection);

        $systemPrompt .= $promptConfig['system_prompt_table_multiple_prefix'] . ($tableCount + 2) . $tableSection;
    } else {
        $systemPrompt .= $promptConfig['system_prompt_table_single'];
    }

    // Add total section
    $totalSection = str_replace('{totalImageNumber}', ($tableCount + 3), $promptConfig['system_prompt_total']);
    $systemPrompt .= $totalSection;

    // Add part 2 (rules and examples)
    $systemPrompt .= $promptConfig['system_prompt_part2'];

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

    // Build API request using settings from config
    $modelSettings = $aieConfig['model_settings'];

    $data = [
        "model" => $modelSettings['model'],
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
        "max_tokens" => $modelSettings['max_tokens'],
        "temperature" => $modelSettings['temperature'],
        "top_p" => $modelSettings['top_p'],
        "store" => $modelSettings['store'],
        "response_format" => $modelSettings['response_format']
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
            // Re-encode with pretty print formatting
            $jsonContent = json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Generate filename with PDF basename
            $timestamp = date('dmY_His'); // ddmmyyyy_hhmmss format

            // Include PDF basename in filename: AIE_OCRjson_XXXX_dd-mm-yy_Z_ddmmyyyy_hhmmss.json
            if ($pdfBaseName) {
                $savedFileName = "AIE_OCRjson_{$pdfBaseName}_{$timestamp}.json";
            } else {
                $savedFileName = "AIE_OCRjson_{$timestamp}.json";
            }

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
    displayResults($response, $httpCode, $elapsedFormatted, $savedFileName, $saveSuccess, $aieConfig);
}

function displayResults($response, $httpCode, $elapsedFormatted, $savedFileName, $saveSuccess, $aieConfig) {
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>AIE - OCR Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #1e3c72; padding-bottom: 10px; }
        .badge { background: #ff9800; color: white; padding: 5px 12px; border-radius: 15px; font-size: 12px; margin-left: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 14px; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .status p { margin: 5px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .timing { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .config-info { background: #e3f2fd; color: #0d47a1; border: 1px solid #90caf9; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #1e3c72; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #2a5298; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table th, table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        table th { background: #1e3c72; color: white; }
    </style>
</head>
<body>
<div class='container'>";

    echo "<h2>🧪 AI Enhancer - OCR Results<span class='badge'>EXPERIMENTAL</span></h2>";

    // Display config info
    echo "<div class='section'>";
    echo "<h3>⚙️ Configuration Used</h3>";
    echo "<div class='status config-info'>";
    echo "<p><strong>Model:</strong> " . htmlspecialchars($aieConfig['model_settings']['model']) . "</p>";
    echo "<p><strong>Temperature:</strong> " . $aieConfig['model_settings']['temperature'] . "</p>";
    echo "<p><strong>Max Tokens:</strong> " . $aieConfig['model_settings']['max_tokens'] . "</p>";
    echo "<p><strong>Config File:</strong> AIE_config.json</p>";
    echo "</div>";
    echo "</div>";

    // Display file save status
    if ($saveSuccess && !empty($savedFileName)) {
        echo "<div class='section'>";
        echo "<h3>💾 File Saved</h3>";
        echo "<div class='status success'>";
        echo "<p>File <strong>" . htmlspecialchars($savedFileName) . "</strong> was saved successfully to <code>AIE/ocr_extracted/</code></p>";
        echo "</div>";
        echo "</div>";
    } elseif (!empty($savedFileName)) {
        echo "<div class='section'>";
        echo "<h3>⚠️ File Save Error</h3>";
        echo "<div class='status error'>";
        echo "<p>Failed to save file <strong>" . htmlspecialchars($savedFileName) . "</strong></p>";
        echo "</div>";
        echo "</div>";
    }

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
                    echo "<table border='1' cellpadding='8' cellspacing='0' style='width:100%;'>";
                    echo "<thead><tr style='background:#1e3c72; color:white;'><th>Index</th><th>Data</th></tr></thead>";
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
    } else {
        echo "<div class='section'>";
        echo "<div class='status error'>";
        echo "<strong>❌ Error:</strong> No valid response content received from OpenAI API.";
        echo "</div>";
        echo "</div>";

        // Show full raw response for debugging
        echo "<div class='section'>";
        echo "<h3>📦 Full API Response</h3>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo "</div>";
    }

    echo "<a href='enhancer_ai.php' class='back-link'>← Process Another Invoice</a>";
    echo "</div></body></html>";
}
?>
