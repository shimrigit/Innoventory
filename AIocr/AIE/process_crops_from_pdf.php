<?php
/**
 * AI Enhancer - Process Saved Crops with OpenAI
 *
 * This file is called after crops are saved from the crop viewer
 * It retrieves the saved crop files from session and processes them with OpenAI
 */

session_start();

// Check if this is an AIE session
if (!isset($_SESSION['aie_mode']) || $_SESSION['aie_mode'] !== true) {
    die('Error: Invalid session - not in AIE mode');
}

// Check if we have saved crops
if (!isset($_SESSION['aie_crop_files']) || empty($_SESSION['aie_crop_files'])) {
    die('Error: No crops found in session. Please crop the PDF first.');
}

// Detect OCR engine mode (COE or GOE)
$ocrEngine = $_SESSION['aie_ocr_engine'] ?? 'coe';

// Load appropriate config file based on engine mode
$configFile = __DIR__ . '/' . ($ocrEngine === 'goe' ? 'GOE_config.json' : 'AIE_config.json');
if (!file_exists($configFile)) {
    die('Error: Config file not found: ' . basename($configFile));
}

$aieConfig = json_decode(file_get_contents($configFile), true);
$modelName = $aieConfig['model_settings']['model'] ?? 'OpenAI';

// Display loading screen immediately
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIE - Processing...</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .loading-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 60px 80px;
            text-align: center;
            max-width: 500px;
        }

        .spinner {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #2a5298;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        h2 {
            color: #1e3c72;
            margin: 0 0 20px 0;
            font-size: 28px;
        }

        .progress-text {
            color: #666;
            font-size: 16px;
            margin: 10px 0;
            line-height: 1.6;
        }

        .badge {
            background: #ff9800;
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }

        .timer {
            font-size: 24px;
            color: #2a5298;
            font-weight: bold;
            margin-top: 20px;
        }

        .dots {
            display: inline-block;
            width: 20px;
        }

        .result-container {
            display: none;
        }
    </style>
    <script>
        let startTime = Date.now();

        function updateTimer() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('timer').textContent = elapsed + 's';
        }

        // Update timer every second
        setInterval(updateTimer, 1000);

        // Animate dots
        let dotCount = 0;
        setInterval(() => {
            dotCount = (dotCount + 1) % 4;
            document.getElementById('dots').textContent = '.'.repeat(dotCount);
        }, 500);
    </script>
</head>
<body>
    <div class="loading-container">
        <div class="spinner"></div>
        <h2>🧪 AI Enhancer<span class="badge">EXPERIMENTAL</span></h2>
        <div class="progress-text">
            Processing invoice with OpenAI<span class="dots" id="dots">...</span>
        </div>
        <div class="progress-text">
            Please wait while <?php echo htmlspecialchars($modelName); ?> analyzes your cropped images
        </div>
        <div class="timer" id="timer">0s</div>
    </div>

    <div class="result-container" id="result">
<?php
flush(); // Send the loading screen to browser immediately

ob_start(); // Start output buffering for the rest

$cropFiles = $_SESSION['aie_crop_files'];
$cropNames = $_SESSION['aie_crop_names'];

// Config already loaded above for display
// Load API key from parent config
$apiConfigFile = __DIR__ . '/../config.json';
if (!file_exists($apiConfigFile)) {
    die('Error: config.json not found in parent directory');
}

$apiConfig = json_decode(file_get_contents($apiConfigFile), true);
$apiKey = $apiConfig['openai_api_key'] ?? '';

if (empty($apiKey)) {
    die('Error: OpenAI API key not configured');
}

// Start processing
$startTime = microtime(true);

// Get PDF basename from session
$pdfBaseName = $_SESSION['aie_pdf_basename'] ?? 'unknown';

// Organize files by type
$invoiceNoFile = null;
$dateFile = null;
$tableFiles = [];
$totalFile = null;

foreach ($cropFiles as $index => $filePath) {
    $fileName = $cropNames[$index];
    $lowerName = strtolower($fileName);

    if (strpos($lowerName, 'invoiceno') !== false) {
        $invoiceNoFile = $filePath;
    } elseif (strpos($lowerName, 'date') !== false) {
        $dateFile = $filePath;
    } elseif (strpos($lowerName, 'total') !== false) {
        $totalFile = $filePath;
    } elseif (preg_match('/table\d+/i', $lowerName)) {
        $tableFiles[] = $filePath;
    }
}

// Sort table files
sort($tableFiles);

// Validate required files
if (!$invoiceNoFile || !$dateFile || empty($tableFiles) || !$totalFile) {
    die('Error: Missing required crop files. Need: InvoiceNo, Date, at least one Table, and Total');
}

// Build prompt from config
$promptConfig = $aieConfig['prompt'];
$tableCount = count($tableFiles);

// GOE uses simple single-part prompt, COE uses complex multi-part structure
if ($ocrEngine === 'goe') {
    $systemPrompt = $promptConfig['system_prompt'];
} else {
    // COE: Build multi-part prompt
    $systemPrompt = $promptConfig['system_prompt_part1'];

    // Add table description based on count
    if ($tableCount > 1) {
        $additional = '';
        if ($tableCount > 2) {
            $additional .= ', Table3';
        }
        if ($tableCount > 3) {
            $additional .= ', Table4';
        }
        if ($tableCount > 4) {
            $additional .= ', Table5';
        }

        $tableSection = str_replace('{tableCount}', $tableCount, $promptConfig['system_prompt_table_multiple_suffix']);
        $tableSection = str_replace('{additional}', $additional, $tableSection);
        $systemPrompt .= $promptConfig['system_prompt_table_multiple_prefix'] . ($tableCount + 2) . $tableSection;
    } else {
        $systemPrompt .= $promptConfig['system_prompt_table_single'];
    }

    $systemPrompt .= $promptConfig['system_prompt_part2'];
}

// Build user content array
// GOE expects simple labels, COE expects numbered labels
if ($ocrEngine === 'goe') {
    // GOE: Simple filenames for model to recognize
    $userContent = [
        [
            "type" => "text",
            "text" => "Cropped_Invoiceno.png:"
        ],
        [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . base64_encode(file_get_contents($invoiceNoFile))
            ]
        ],
        [
            "type" => "text",
            "text" => "Cropped_Date.png:"
        ],
        [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . base64_encode(file_get_contents($dateFile))
            ]
        ]
    ];

    // Add table images with GOE naming
    foreach ($tableFiles as $i => $tableFile) {
        $tableNum = $i + 1;
        $userContent[] = [
            "type" => "text",
            "text" => "Cropped_Table{$tableNum}.png:"
        ];
        $userContent[] = [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . base64_encode(file_get_contents($tableFile))
            ]
        ];
    }

    // Add total image
    $userContent[] = [
        "type" => "text",
        "text" => "Cropped_Total.png:"
    ];
    $userContent[] = [
        "type" => "image_url",
        "image_url" => [
            "url" => "data:image/png;base64," . base64_encode(file_get_contents($totalFile))
        ]
    ];
} else {
    // COE: Numbered image labels
    $userContent = [
        [
            "type" => "text",
            "text" => "Image 1 (InvoiceNo):"
        ],
        [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . base64_encode(file_get_contents($invoiceNoFile))
            ]
        ],
        [
            "type" => "text",
            "text" => "Image 2 (Date):"
        ],
        [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . base64_encode(file_get_contents($dateFile))
            ]
        ]
    ];

    // Add table images
    foreach ($tableFiles as $i => $tableFile) {
        $tableNum = $i + 1;
        $userContent[] = [
            "type" => "text",
            "text" => "Image " . ($i + 3) . " (Table{$tableNum}):"
        ];
        $userContent[] = [
            "type" => "image_url",
            "image_url" => [
                "url" => "data:image/png;base64," . base64_encode(file_get_contents($tableFile))
            ]
        ];
    }

    // Add total image
    $totalImageNum = count($tableFiles) + 3;
    $userContent[] = [
        "type" => "text",
        "text" => "Image {$totalImageNum} (Total):"
    ];
    $userContent[] = [
        "type" => "image_url",
        "image_url" => [
            "url" => "data:image/png;base64," . base64_encode(file_get_contents($totalFile))
        ]
    ];
}

// Prepare API request
$modelSettings = $aieConfig['model_settings'];

$data = [
    "model" => $modelSettings['model'],
    "messages" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $userContent]
    ],
    "max_tokens" => $modelSettings['max_tokens'],
    "temperature" => $modelSettings['temperature'],
    "top_p" => $modelSettings['top_p'],
    "store" => $modelSettings['store'],
    "response_format" => $modelSettings['response_format']
];

// Make API call
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

$apiStartTime = microtime(true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$apiEndTime = microtime(true);

$endTime = microtime(true);
$processingTime = round($endTime - $startTime, 2);
$apiResponseTime = round($apiEndTime - $apiStartTime, 2);

// Parse response
$result = json_decode($response, true);

// Extract token usage and error information
$promptTokens = $result['usage']['prompt_tokens'] ?? 0;
$completionTokens = $result['usage']['completion_tokens'] ?? 0;
$totalTokens = $result['usage']['total_tokens'] ?? 0;
$apiError = null;

if ($httpCode !== 200 || !$result) {
    $apiError = 'HTTP Code: ' . $httpCode . ' - ' . ($result['error']['message'] ?? 'Unknown error');
    die('Error: OpenAI API request failed. ' . $apiError . '<br>Response: ' . htmlspecialchars($response));
}

if (isset($result['error'])) {
    $apiError = $result['error']['message'] ?? json_encode($result['error']);
}

// Extract JSON from response
$jsonResponse = $result['choices'][0]['message']['content'] ?? '';
$parsedJson = json_decode($jsonResponse, true);

// Apply geometry engine processing for GOE mode
if ($ocrEngine === 'goe') {
    // Include geometry engine
    require_once __DIR__ . '/geometry_engine.php';

    // Transform bbox tokens into structured table
    $parsedJson = build_invoice_json($parsedJson);
}

// Generate output filename with PDF basename
$timestamp = date('dmY_His');
$savedFileName = "AIE_OCRjson_{$pdfBaseName}_{$timestamp}.json";

// Save to ocr_extracted directory
$saveDirectory = __DIR__ . '/ocr_extracted/';
if (!file_exists($saveDirectory)) {
    mkdir($saveDirectory, 0755, true);
}

$savedFilePath = $saveDirectory . $savedFileName;
file_put_contents($savedFilePath, json_encode($parsedJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Clear session variables
unset($_SESSION['aie_crop_files']);
unset($_SESSION['aie_crop_names']);
unset($_SESSION['aie_pdf_basename']);
unset($_SESSION['aie_mode']);
unset($_SESSION['aie_ocr_engine']);

// Get the buffered results
$resultsHtml = ob_get_clean();

// Output the results HTML with updated styles
?>
    <style>
        .loading-container {
            display: none;
        }

        .result-container {
            display: block !important;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }

        .content {
            padding: 40px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2a5298;
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }

        .stat-box p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #2a5298;
        }

        .json-section {
            margin-top: 30px;
        }

        .json-section h2 {
            margin: 0 0 15px 0;
            color: #1e3c72;
        }

        pre {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
        }

        .table-section {
            margin-top: 30px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
            background: white;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .data-table th {
            background: #2a5298;
            color: white;
            font-weight: bold;
            text-align: center;
            position: sticky;
            top: 0;
        }

        .data-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .data-table tr:hover {
            background: #e3f2fd;
        }

        .data-table td {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .table-container {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .actions {
            margin-top: 30px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 0 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #2a5298;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3c72;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 82, 152, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .success-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status p {
            margin: 0;
        }
    </style>

    <script>
        // Hide loading screen and show results once processing is complete
        window.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.loading-container').style.display = 'none';
            document.getElementById('result').style.display = 'block';
        });
    </script>

    <div class="header">
        <div class="success-icon">✅</div>
        <h1>🧪 AI Enhancer Results<span class="badge">EXPERIMENTAL</span></h1>
        <p>OCR processing completed successfully</p>
    </div>

    <div class="content">
        <div class="stats">
            <div class="stat-box">
                <h3>Total Processing Time</h3>
                <p><?php echo $processingTime; ?>s</p>
            </div>
            <div class="stat-box">
                <h3>OpenAI Response Time</h3>
                <p><?php echo $apiResponseTime; ?>s</p>
            </div>
            <div class="stat-box">
                <h3>Model Used</h3>
                <p><?php echo htmlspecialchars($modelSettings['model']); ?></p>
            </div>
            <div class="stat-box">
                <h3>Crops Processed</h3>
                <p><?php echo count($cropFiles); ?></p>
            </div>
            <div class="stat-box">
                <h3>Tables Found</h3>
                <p><?php echo $tableCount; ?></p>
            </div>
            <div class="stat-box">
                <h3>Prompt Tokens</h3>
                <p><?php echo number_format($promptTokens); ?></p>
            </div>
            <div class="stat-box">
                <h3>Completion Tokens</h3>
                <p><?php echo number_format($completionTokens); ?></p>
            </div>
            <div class="stat-box">
                <h3>Total Tokens</h3>
                <p><?php echo number_format($totalTokens); ?></p>
            </div>
        </div>

        <?php if ($apiError): ?>
        <div class="json-section">
            <h2>⚠️ API Warning/Error</h2>
            <div class="status error">
                <p><?php echo htmlspecialchars($apiError); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="json-section">
            <h2>📄 Extracted JSON Output</h2>
            <p><strong>Saved to:</strong> <code><?php echo htmlspecialchars($savedFileName); ?></code></p>
            <pre><?php echo htmlspecialchars(json_encode($parsedJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        </div>

        <?php if (isset($parsedJson['table']) && is_array($parsedJson['table']) && count($parsedJson['table']) > 0): ?>
        <div class="table-section">
            <h2>📊 Table Data Visualization</h2>
            <p><strong>Rows:</strong> <?php echo count($parsedJson['table']); ?></p>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <?php
                            // Find the maximum number of columns across all rows
                            $maxColumns = 0;
                            foreach ($parsedJson['table'] as $row) {
                                // Get all numeric keys (column numbers)
                                $columnKeys = array_filter(array_keys($row), function($key) {
                                    return is_numeric($key) || ctype_digit($key);
                                });
                                $maxInRow = count($columnKeys) > 0 ? max(array_map('intval', $columnKeys)) : 0;
                                $maxColumns = max($maxColumns, $maxInRow);
                            }

                            // Generate column headers 1, 2, 3, etc.
                            for ($col = 1; $col <= $maxColumns; $col++) {
                                echo "<th>{$col}</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parsedJson['table'] as $row): ?>
                        <tr>
                            <td style="font-weight: bold; background: #f0f0f0; text-align: center;">
                                <?php echo isset($row['index']) ? htmlspecialchars($row['index']) : '—'; ?>
                            </td>
                            <?php
                            for ($col = 1; $col <= $maxColumns; $col++) {
                                $value = isset($row[(string)$col]) ? $row[(string)$col] : '';
                                echo '<td>' . htmlspecialchars($value) . '</td>';
                            }
                            ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="actions">
            <a href="enhancer_ai.php" class="btn btn-primary">🔄 Process Another Invoice</a>
            <a href="ocr_extracted/<?php echo urlencode($savedFileName); ?>" class="btn btn-secondary" download>💾 Download JSON</a>
        </div>
    </div>
    </div>
</body>
</html>
