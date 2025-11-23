<?php
/**
 * Invoice Pre-Processing: Append and Rename PDFs
 *
 * This script combines multiple PDF pages into single invoices with proper naming.
 * Processed files remain in preProcessDir (no email sending).
 *
 * Output naming: "{SupplierEnglish} {dd-mm-yy} {Letter}.pdf"
 * Example: "Tayari 15-11-25 A.pdf"
 */

require_once '../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

$preProcessDir = realpath(__DIR__ . '/../preProcessDir');
$tempDir = $preProcessDir . '/TempPP';
$total = $_POST['totalFiles'] ?? 0;

if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Load suppliers.json to map Hebrew names to English
$suppliersMap = [];
$jsonPath = realpath(__DIR__ . '/../suppliers.json');
if (file_exists($jsonPath)) {
    $jsonData = json_decode(file_get_contents($jsonPath), true);
    foreach ($jsonData as $supplier) {
        $suppliersMap[$supplier['hebrewName']] = $supplier['supplierName'];
    }
}

$groups = [];
$discardedFiles = [];

// Group files by supplier, letter, and date
for ($i = 1; $i <= $total; $i++) {
    $fileName = $_POST["fileName_$i"] ?? '';
    $supplierHebrew = trim($_POST["hebrewName_$i"] ?? '');
    $letter = trim($_POST["englishLetter_$i"] ?? '');
    $pageNumber = intval($_POST["pageNumber_$i"] ?? 0);
    $date = $_POST["date_$i"] ?? '';
    $discard = $_POST["discard_$i"] ?? '';
    $filePath = $preProcessDir . DIRECTORY_SEPARATOR . $fileName;

    // Move discarded files to TempPP
    if ($discard === 'yes') {
        if (file_exists($filePath)) {
            rename($filePath, $tempDir . DIRECTORY_SEPARATOR . basename($filePath));
        }
        $discardedFiles[] = $fileName;
        continue;
    }

    // Map Hebrew name to English supplier name
    $supplierEnglish = $suppliersMap[$supplierHebrew] ?? $supplierHebrew;
    $groupKey = "{$supplierEnglish}|{$letter}|{$date}";

    $groups[$groupKey][] = [
        'filePath' => $filePath,
        'pageNumber' => $pageNumber,
        'originalName' => $fileName
    ];
}

/**
 * Merge PDFs using FPDI (primary method)
 * @throws CrossReferenceException if PDF uses unsupported compression
 */
function mergePDFsWithFPDI($pages, $outputPath, $tempDir) {
    $pdf = new FPDI();

    foreach ($pages as $pageInfo) {
        $pdf->setSourceFile($pageInfo['filePath']);
        $tpl = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        // Move the original file to TempPP
        $destPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
        rename($pageInfo['filePath'], $destPath);
    }

    $pdf->Output($outputPath, 'F');
}

/**
 * Fallback method using PDFtk (command line tool)
 * Requires PDFtk to be installed on the system
 */
function mergePDFsWithPDFtk($pages, $outputPath, $tempDir) {
    // Check if PDFtk is available
    $pdftkPath = 'pdftk'; // or full path like 'C:\Program Files\PDFtk\bin\pdftk.exe'

    exec("$pdftkPath --version 2>&1", $output, $returnCode);
    if ($returnCode !== 0) {
        throw new Exception("PDFtk is not installed. Please install PDFtk Server from: https://www.pdflabs.com/tools/pdftk-server/");
    }

    // Build the PDFtk command
    $inputFiles = array_map(function($page) {
        return escapeshellarg($page['filePath']);
    }, $pages);

    $command = "$pdftkPath " . implode(' ', $inputFiles) . " cat output " . escapeshellarg($outputPath);
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception("PDFtk merge failed: " . implode("\n", $output));
    }

    // Move original files to TempPP after successful merge
    foreach ($pages as $pageInfo) {
        $destPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
        rename($pageInfo['filePath'], $destPath);
    }
}

/**
 * Fallback method using Ghostscript (command line tool)
 * Requires Ghostscript to be installed on the system
 */
function mergePDFsWithGhostscript($pages, $outputPath, $tempDir) {
    // Check if Ghostscript is available
    $gsPath = 'gswin64c'; // or 'gs' on Linux/Mac, or full path

    exec("$gsPath --version 2>&1", $output, $returnCode);
    if ($returnCode !== 0) {
        throw new Exception("Ghostscript is not installed. Please install Ghostscript from: https://www.ghostscript.com/download/gsdnld.html");
    }

    // Build the Ghostscript command
    $inputFiles = array_map(function($page) {
        return escapeshellarg($page['filePath']);
    }, $pages);

    $command = "$gsPath -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($outputPath) . " " . implode(' ', $inputFiles);
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception("Ghostscript merge failed: " . implode("\n", $output));
    }

    // Move original files to TempPP after successful merge
    foreach ($pages as $pageInfo) {
        $destPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
        rename($pageInfo['filePath'], $destPath);
    }
}

// Process each group
$summary = [];
$errors = [];

foreach ($groups as $key => $pages) {
    // Sort pages by page number
    usort($pages, fn($a, $b) => $a['pageNumber'] <=> $b['pageNumber']);

    [$supplierEnglish, $letter, $date] = explode('|', $key);
    $dateFormatted = date('d-m-y', strtotime($date));

    // Output naming: "{SupplierEnglish} {dd-mm-yy} {Letter}.pdf"
    $outputName = "{$supplierEnglish} {$dateFormatted} {$letter}.pdf";
    $outputPath = $preProcessDir . DIRECTORY_SEPARATOR . $outputName;

    try {
        // Try FPDI first
        mergePDFsWithFPDI($pages, $outputPath, $tempDir);

        $summary[] = [
            'name' => $outputName,
            'count' => count($pages),
            'pages' => array_column($pages, 'pageNumber'),
            'method' => 'FPDI'
        ];
    } catch (CrossReferenceException $e) {
        // FPDI failed due to compression - try fallback methods
        error_log("FPDI failed for $outputName: " . $e->getMessage());

        // Restore files from TempPP back to preProcessDir for fallback attempt
        foreach ($pages as $pageInfo) {
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
            if (file_exists($tempPath)) {
                rename($tempPath, $pageInfo['filePath']);
            }
        }

        try {
            // Try PDFtk
            mergePDFsWithPDFtk($pages, $outputPath, $tempDir);
            $summary[] = [
                'name' => $outputName,
                'count' => count($pages),
                'pages' => array_column($pages, 'pageNumber'),
                'method' => 'PDFtk'
            ];
        } catch (Exception $pdftkError) {
            error_log("PDFtk failed for $outputName: " . $pdftkError->getMessage());

            // Restore files again for Ghostscript attempt
            foreach ($pages as $pageInfo) {
                $tempPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
                if (file_exists($tempPath)) {
                    rename($tempPath, $pageInfo['filePath']);
                }
            }

            try {
                // Try Ghostscript
                mergePDFsWithGhostscript($pages, $outputPath, $tempDir);
                $summary[] = [
                    'name' => $outputName,
                    'count' => count($pages),
                    'pages' => array_column($pages, 'pageNumber'),
                    'method' => 'Ghostscript'
                ];
            } catch (Exception $gsError) {
                // All methods failed
                $errors[] = [
                    'name' => $outputName,
                    'pages' => $pages,
                    'fpdiError' => $e->getMessage(),
                    'pdftkError' => $pdftkError->getMessage(),
                    'gsError' => $gsError->getMessage()
                ];
            }
        }
    } catch (Exception $e) {
        // Other FPDI errors
        $errors[] = [
            'name' => $outputName,
            'pages' => $pages,
            'error' => $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing Results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        h3 {
            color: #f44336;
            border-bottom: 2px solid #f44336;
            padding-bottom: 10px;
        }
        .success-item {
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .error-item {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .discard-item {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .method-badge {
            display: inline-block;
            background-color: #2196F3;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .back-button:hover {
            background-color: #45a049;
        }
        code {
            background-color: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>✅ Processing Complete</h2>

        <?php if (!empty($summary)): ?>
            <h3 style="color: #4CAF50; border-color: #4CAF50;">Documents Created:</h3>
            <?php foreach ($summary as $doc): ?>
                <div class="success-item">
                    <strong><?= htmlspecialchars($doc['name']) ?></strong>
                    <span class="method-badge"><?= htmlspecialchars($doc['method']) ?></span>
                    <br>
                    <small>
                        <?= $doc['count'] ?> page<?= $doc['count'] === 1 ? '' : 's' ?>
                        (Page numbers: <?= implode(', ', $doc['pages']) ?>)
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <h3>❌ Failed Documents</h3>
            <?php foreach ($errors as $error): ?>
                <div class="error-item">
                    <strong>Document: "<?= htmlspecialchars($error['name']) ?>"</strong><br>
                    <?php if (isset($error['fpdiError'])): ?>
                        <em>FPDI Error:</em> <?= htmlspecialchars($error['fpdiError']) ?><br>
                        <em>PDFtk Error:</em> <?= htmlspecialchars($error['pdftkError']) ?><br>
                        <em>Ghostscript Error:</em> <?= htmlspecialchars($error['gsError']) ?><br>
                        <br>
                        <strong>Solution:</strong> Install PDFtk or Ghostscript to handle compressed PDFs.<br>
                        PDFtk: <a href='https://www.pdflabs.com/tools/pdftk-server/' target='_blank'>Download here</a><br>
                        Ghostscript: <a href='https://www.ghostscript.com/download/gsdnld.html' target='_blank'>Download here</a>
                    <?php else: ?>
                        <em>Error:</em> <?= htmlspecialchars($error['error']) ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($discardedFiles)): ?>
            <h3 style="color: #ff9800; border-color: #ff9800;">🗑️ Discarded Files</h3>
            <p style="color: #666;">The following files were moved to <code>TempPP</code>:</p>
            <?php foreach ($discardedFiles as $discarded): ?>
                <div class="discard-item">
                    <?= htmlspecialchars($discarded) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($summary) && empty($errors) && empty($discardedFiles)): ?>
            <p style="color: #666; text-align: center; padding: 20px;">
                No files were processed. All PDFs may have been discarded.
            </p>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="ProcessInvoices.php" class="back-button">Process More Invoices</a>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <strong>Note:</strong> Processed PDF files remain in <code>preProcessDir</code> for the next stage (OCR and Analysis).
            Original page files have been moved to <code>TempPP</code>.
        </div>
    </div>
</body>
</html>
