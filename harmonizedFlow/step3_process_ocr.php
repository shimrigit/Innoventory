<?php
/**
 * Step 3: Process OCR with OpenAI (with Sequence Identifier)
 *
 * This step:
 * 1. Receives crop images from AIocr/crops directory
 * 2. Sends them to OpenAI for OCR processing
 * 3. Generates OCRjson file with naming: OCRjson_XXXX_dd-mm-yyyy_Z_ddmmyy_hhmmss.json
 * 4. Redirects to Step 4 (OCR Sanity creation)
 */

session_start();

// Check if harmonized flow session exists
if (!isset($_SESSION['harmonizedFlow'])) {
    die('Error: Harmonized flow session not found. Please start from Step 1.');
}

$flowData = $_SESSION['harmonizedFlow'];
$supplierName = $flowData['supplierName'];
$processDateFull = $flowData['processDateFull']; // dd-mm-yyyy
$sequenceIdentifier = $flowData['sequenceIdentifier'];

// Start timing
$startTime = microtime(true);

// Load API key from config file
$configFile = __DIR__ . '/../AIocr/config.json';
if (!file_exists($configFile)) {
    die('Error: config.json not found. Please create it with your OpenAI API key.');
}
$config = json_decode(file_get_contents($configFile), true);
$apiKey = $config['openai_api_key'] ?? null;
if (empty($apiKey) || $apiKey === 'YOUR_NEW_API_KEY_HERE') {
    die('Error: Please set your OpenAI API key in config.json');
}

// Look for crop files in AIocr/crops directory
$cropsDir = __DIR__ . '/../AIocr/crops';

// Get all PNG files in crops directory
$allCropFiles = glob($cropsDir . '/*.png');

if (empty($allCropFiles)) {
    die('Error: No crop files found in crops directory. Please go back and mark crop regions.');
}

// Group files by their creation time (within 1 minute tolerance)
// This helps identify files that were created together in the same session
$fileGroups = [];
foreach ($allCropFiles as $file) {
    $mtime = filemtime($file);
    $fileName = basename($file);

    // Find a group with similar timestamp (within 60 seconds)
    $foundGroup = false;
    foreach ($fileGroups as $groupTime => &$group) {
        if (abs($mtime - $groupTime) <= 60) {
            $group[] = [
                'path' => $file,
                'name' => $fileName,
                'time' => $mtime
            ];
            $foundGroup = true;
            break;
        }
    }

    if (!$foundGroup) {
        $fileGroups[$mtime] = [[
            'path' => $file,
            'name' => $fileName,
            'time' => $mtime
        ]];
    }
}

// Sort groups by timestamp (newest first)
krsort($fileGroups);

// Get the most recent group of files
$latestGroup = reset($fileGroups);

if (empty($latestGroup)) {
    die('Error: Could not identify latest crop group. Please try again.');
}

// Extract just the file paths from the latest group
$cropFiles = array_column($latestGroup, 'path');

// Map files by their suffix
$imageData = [];
$tableFiles = [];
$foundFiles = [];

foreach ($cropFiles as $cropPath) {
    $fileName = basename($cropPath);

    // Read file content
    $fileContent = file_get_contents($cropPath);
    if ($fileContent === false) {
        die("Error reading crop file: {$fileName}");
    }

    // Encode to base64
    $base64Data = base64_encode($fileContent);

    // Match file by suffix
    $matched = false;

    // Check for InvoiceNo
    if (stripos($fileName, 'InvoiceNo') !== false) {
        $imageData['invoice_no'] = $base64Data;
        $foundFiles[] = 'InvoiceNo';
        $matched = true;
    }
    // Check for Date
    elseif (stripos($fileName, 'Date') !== false) {
        $imageData['date'] = $base64Data;
        $foundFiles[] = 'Date';
        $matched = true;
    }
    // Check for Table1, Table2, Table3, etc.
    elseif (preg_match('/Table(\d+)/i', $fileName, $matches)) {
        $tableNumber = (int)$matches[1];
        $tableFiles[$tableNumber] = $base64Data;
        $foundFiles[] = "Table{$tableNumber}";
        $matched = true;
    }
    // Check for Total
    elseif (stripos($fileName, 'Total') !== false) {
        $imageData['total'] = $base64Data;
        $foundFiles[] = 'Total';
        $matched = true;
    }
}

// Sort table files by number
ksort($tableFiles);

// Verify all required files are present
$errors = [];
if (!isset($imageData['invoice_no'])) {
    $errors[] = "Missing InvoiceNo crop";
}
if (!isset($imageData['date'])) {
    $errors[] = "Missing Date crop";
}
if (empty($tableFiles) || !isset($tableFiles[1])) {
    $errors[] = "Missing Table1 crop";
}
if (!isset($imageData['total'])) {
    $errors[] = "Missing Total crop";
}

if (!empty($errors)) {
    die('Error: Required crop files missing:<br>' . implode('<br>', $errors));
}

// Build system prompt - same as process_ocr_ai.php
$tableCount = count($tableFiles);
$systemPrompt = <<<PROMPT
You are an OCR extraction system. Extract ALL data exactly as shown in the images.

IMAGES:
- Image 1: Invoice number
- Image 2: Invoice date
PROMPT;

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

Each row MUST have ALL columns in sequential order 1,2,3,4... with no gaps.
Extract EVERY row. Cell values = exact text/number, no limit.
PROMPT;

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

// Add all table images
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

// Parse response
$result = json_decode($response, true);

if ($httpCode !== 200 || !isset($result['choices'][0]['message']['content'])) {
    $errorMsg = isset($result['error']) ? $result['error']['message'] : 'Unknown error';
    die("Error: OpenAI API request failed. HTTP Code: {$httpCode}. Error: {$errorMsg}");
}

// Extract JSON content
$extractedData = json_decode($result['choices'][0]['message']['content'], true);

if ($extractedData === null) {
    die('Error: Failed to parse OCR JSON response from OpenAI');
}

// Re-encode with pretty print
$jsonContent = json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Generate filename with sequence identifier
// Format: OCRjson_XXXX_dd-mm-yyyy_Z_ddmmyy_hhmmss.json
$timestamp = date('dmY_His'); // ddmmyyyy_hhmmss
$savedFileName = "OCRjson_{$supplierName}_{$processDateFull}_{$sequenceIdentifier}_{$timestamp}.json";

$saveDirectory = __DIR__ . '/../AIocr/ocr_extracted/';
if (!is_dir($saveDirectory)) {
    mkdir($saveDirectory, 0755, true);
}

$fullPath = $saveDirectory . $savedFileName;

// Save the JSON file
if (file_put_contents($fullPath, $jsonContent) === false) {
    die('Error: Failed to save OCR JSON file');
}

// Update session with OCR JSON filename
$_SESSION['harmonizedFlow']['ocrJsonFile'] = $savedFileName;
$_SESSION['harmonizedFlow']['ocrJsonPath'] = $fullPath;
$_SESSION['harmonizedFlow']['step'] = 3;

// Redirect directly to verify_ocrsanity.php in harmonized mode
// The verification screen will load the latest OCRjson file automatically
header("Location: step4_verify_ocr.php");
exit;
?>
