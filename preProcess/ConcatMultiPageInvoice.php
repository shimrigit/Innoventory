<?php
session_start();
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

for ($i = 1; $i <= $total; $i++) {
    $fileName = $_POST["fileName_$i"] ?? '';
    $supplierHebrew = trim($_POST["hebrewName_$i"] ?? '');
    $letter = trim($_POST["englishLetter_$i"] ?? '');
    $pageNumber = intval($_POST["pageNumber_$i"] ?? 0);
    $date = $_POST["date_$i"] ?? '';
    $discard = $_POST["discard_$i"] ?? '';
    $filePath = $preProcessDir . DIRECTORY_SEPARATOR . $fileName;

    // Always move discarded files
    if ($discard === 'yes') {
        if (file_exists($filePath)) {
            rename($filePath, $tempDir . DIRECTORY_SEPARATOR . basename($filePath));
        }
        $discardedFiles[] = $fileName;
        continue;
    }

    $supplierEnglish = $suppliersMap[$supplierHebrew] ?? $supplierHebrew; // fallback to Hebrew if mapping is missing
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

        // Move the original file
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

    // Move original files after successful merge
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

    // Move original files after successful merge
    foreach ($pages as $pageInfo) {
        $destPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
        rename($pageInfo['filePath'], $destPath);
    }
}

// Process each group
$summary = [];
$errors = [];

foreach ($groups as $key => $pages) {
    usort($pages, fn($a, $b) => $a['pageNumber'] <=> $b['pageNumber']);

    [$supplierEnglish, $letter, $date] = explode('|', $key);
    $dateFormatted = date('d-m-y', strtotime($date));
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

        // Restore files from tempDir back to preProcessDir for fallback attempt
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

// Output summary
echo "<h2>Documents Created:</h2>";
foreach ($summary as $doc) {
    $pageLabel = $doc['count'] === 1 ? 'page' : 'pages';
    $method = isset($doc['method']) ? " [Method: {$doc['method']}]" : '';
    echo "Document \"{$doc['name']}\" with {$doc['count']} $pageLabel$method<br>";
}

// Show errors if any
if (!empty($errors)) {
    echo "<h3 style='color: red;'>Failed Documents:</h3>";
    foreach ($errors as $error) {
        echo "<div style='margin-bottom: 15px; padding: 10px; background-color: #ffe6e6; border: 1px solid #ff0000;'>";
        echo "<strong>Document: \"{$error['name']}\"</strong><br>";
        if (isset($error['fpdiError'])) {
            echo "<em>FPDI Error:</em> {$error['fpdiError']}<br>";
            echo "<em>PDFtk Error:</em> {$error['pdftkError']}<br>";
            echo "<em>Ghostscript Error:</em> {$error['gsError']}<br>";
            echo "<br><strong>Solution:</strong> Install PDFtk or Ghostscript to handle compressed PDFs.<br>";
            echo "PDFtk: <a href='https://www.pdflabs.com/tools/pdftk-server/' target='_blank'>Download here</a><br>";
            echo "Ghostscript: <a href='https://www.ghostscript.com/download/gsdnld.html' target='_blank'>Download here</a>";
        } else {
            echo "<em>Error:</em> {$error['error']}";
        }
        echo "</div>";
    }
}

// Optionally show discarded files
if (!empty($discardedFiles)) {
    echo "<h3>Discarded Pages Moved:</h3>";
    foreach ($discardedFiles as $discarded) {
        echo htmlspecialchars($discarded) . "<br>";
    }
}

// Set session variables for sendToOCR.php
$_SESSION['preProcessDirectory'] = $preProcessDir;
$_SESSION['logFile'] = __DIR__ . '/../filelog.txt'; // Or wherever you keep logs
$_SESSION['preProcessFROMemailAddress'] = 'inno.ocr@gmail.com';
$_SESSION['preProcessDestinationEmailAddress'] = 'innoventory.tech@soluma.si';
$_SESSION['preProcessToSend'] = 1; // 0 = skip, 1 = draft, 2 = send

// Redirect to sendToOCR.php after processing
echo "<script>
  // Open sendToOCR.php in a new tab or window
  window.open('../gmailAccess/sendToOCR.php', '_blank');
</script>";

?>
