<?php
// Verify OCR Sanity - Edit and Compare with PDF

if (!isset($_GET['excel']) || !isset($_GET['pdf'])) {
    die('Error: Missing excel or pdf parameter');
}

$excelFile = basename($_GET['excel']);
$pdfFile = basename($_GET['pdf']);

$excelPath = __DIR__ . '/sanity_files/' . $excelFile;
$pdfPath = __DIR__ . '/sanity_files/' . $pdfFile;

// Verify files exist
if (!file_exists($excelPath)) {
    die('Error: Excel file not found');
}

if (!file_exists($pdfPath)) {
    die('Error: PDF file not found');
}

// Load Excel data using PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load($excelPath);
$sheet = $spreadsheet->getActiveSheet();

// Convert Excel data to array for JavaScript
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

$data = [];
for ($row = 1; $row <= $highestRow; $row++) {
    $rowData = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $rowData[] = $cell->getFormattedValue();
    }
    $data[] = $rowData;
}

$jsonData = json_encode($data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OCR Sanity</title>

    <!-- Handsontable CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@14.0.0/dist/handsontable.full.min.css">

    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            overflow: hidden;
        }

        .header {
            background: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 20px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-save {
            background: #28a745;
            color: white;
        }

        .btn-save:hover {
            background: #218838;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .container {
            display: flex;
            height: calc(100vh - 140px);
            margin-bottom: 20px;
        }

        .left-panel {
            width: 50%;
            border-right: 2px solid #ccc;
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }

        .excel-container {
            flex: 1;
            padding: 10px;
            position: relative;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .right-panel {
            width: 50%;
            display: flex;
            flex-direction: column;
        }

        .pdf-controls {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .pdf-controls button {
            padding: 5px 10px;
            border: 1px solid #ccc;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }

        .pdf-controls button:hover {
            background: #e9ecef;
        }

        .pdf-container {
            flex: 1;
            overflow: auto;
            overflow-y: scroll;
            overflow-x: auto;
            background: #525659;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }

        .pdf-wrapper {
            display: inline-block;
            position: relative;
        }

        #pdf-canvas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            background: white;
            cursor: text;
        }

        .instructions {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }

        #hot-container {
            flex: 1;
            width: 100%;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 OCR Sanity Verification - <?php echo htmlspecialchars($excelFile); ?></h1>
        <div class="header-buttons">
            <button class="btn btn-save" id="saveBtn">💾 Save OCR Sanity</button>
            <button class="btn btn-cancel" onclick="window.close()">❌ Cancel</button>
        </div>
    </div>

    <div class="instructions">
        💡 <strong>Instructions:</strong> Edit cells in the spreadsheet on the left. Click on text in the PDF to copy it to the selected cell. Use arrow keys to navigate between cells.
    </div>

    <div class="container">
        <!-- Left Panel - Excel Editor -->
        <div class="left-panel">
            <div class="panel-header">📝 OCR Sanity Spreadsheet (Editable)</div>
            <div class="excel-container">
                <div id="hot-container"></div>
            </div>
        </div>

        <!-- Right Panel - PDF Viewer -->
        <div class="right-panel">
            <div class="panel-header">📄 Invoice PDF</div>
            <div class="pdf-controls">
                <button id="zoom-out">🔍 -</button>
                <button id="zoom-in">🔍 +</button>
                <button id="zoom-reset">↻ Reset</button>
                <span id="zoom-level">Zoom: 100%</span>
                <button id="prev-page" style="margin-left: auto;">← Previous</button>
                <span id="page-info">Page: 1 / 1</span>
                <button id="next-page">Next →</button>
            </div>
            <div class="pdf-container" id="pdf-scroll-container">
                <div class="pdf-wrapper">
                    <canvas id="pdf-canvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Handsontable JS -->
    <script src="https://cdn.jsdelivr.net/npm/handsontable@14.0.0/dist/handsontable.full.min.js"></script>

    <script>
        // Excel data from PHP
        const excelData = <?php echo $jsonData; ?>;
        const excelFileName = <?php echo json_encode($excelFile); ?>;

        // Initialize Handsontable with proper scrolling
        const container = document.getElementById('hot-container');

        const hot = new Handsontable(container, {
            data: excelData,
            colHeaders: true,
            rowHeaders: true,
            width: '100%',
            height: 'calc(100vh - 200px)',
            licenseKey: 'non-commercial-and-evaluation',
            contextMenu: ['row_above', 'row_below', 'col_left', 'col_right', 'remove_row', 'remove_col'],
            manualColumnResize: true,
            manualRowResize: true,
            stretchH: 'none',
            autoWrapRow: false,
            autoWrapCol: false,
            readOnly: false
        });

        // Track selected cell
        let selectedCell = { row: 0, col: 0 };

        hot.addHook('afterSelection', function(row, col) {
            selectedCell = { row, col };
        });

        // PDF.js setup
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;
        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas.getContext('2d');

        // Load PDF
        const pdfPath = 'sanity_files/<?php echo htmlspecialchars($pdfFile); ?>';

        pdfjsLib.getDocument(pdfPath).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('page-info').textContent = `Page: ${pageNum} / ${pdfDoc.numPages}`;
            renderPage(pageNum);
        });

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                const renderTask = page.render(renderContext);
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('page-info').textContent = `Page: ${num} / ${pdfDoc.numPages}`;
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        // PDF Navigation
        document.getElementById('prev-page').addEventListener('click', function() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        });

        document.getElementById('next-page').addEventListener('click', function() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        });

        // Zoom controls
        document.getElementById('zoom-in').addEventListener('click', function() {
            scale += 0.2;
            document.getElementById('zoom-level').textContent = `Zoom: ${Math.round(scale * 100)}%`;
            queueRenderPage(pageNum);
        });

        document.getElementById('zoom-out').addEventListener('click', function() {
            if (scale <= 0.5) return;
            scale -= 0.2;
            document.getElementById('zoom-level').textContent = `Zoom: ${Math.round(scale * 100)}%`;
            queueRenderPage(pageNum);
        });

        document.getElementById('zoom-reset').addEventListener('click', function() {
            scale = 1.5;
            document.getElementById('zoom-level').textContent = `Zoom: 100%`;
            queueRenderPage(pageNum);
        });

        // Save functionality
        document.getElementById('saveBtn').addEventListener('click', function() {
            if (!confirm('Save OCR Sanity file? This will close the verification window.')) {
                return;
            }

            const updatedData = hot.getData();

            // Disable save button
            document.getElementById('saveBtn').disabled = true;
            document.getElementById('saveBtn').textContent = '⏳ Saving...';

            // Send data to server to update Excel file
            fetch('save_ocrsanity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: excelFileName,
                    data: updatedData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success page
                    document.body.innerHTML = `
                        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5;">
                            <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; max-width: 600px;">
                                <div style="font-size: 64px; color: #28a745; margin-bottom: 20px;">✅</div>
                                <h2 style="color: #333; margin-bottom: 20px;">OCR Sanity File Saved Successfully!</h2>
                                <p style="color: #666; font-size: 16px; margin-bottom: 10px;">
                                    <strong>File:</strong> ${excelFileName}
                                </p>
                                <p style="color: #666; font-size: 16px;">
                                    <strong>Location:</strong> C:\\xampp\\htdocs\\website\\OCRsanity\\sanity_files
                                </p>
                            </div>
                        </div>
                    `;
                } else {
                    alert('❌ Error saving file: ' + data.error);
                    document.getElementById('saveBtn').disabled = false;
                    document.getElementById('saveBtn').textContent = '💾 Save OCR Sanity';
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
                document.getElementById('saveBtn').disabled = false;
                document.getElementById('saveBtn').textContent = '💾 Save OCR Sanity';
            });
        });
    </script>
</body>
</html>
