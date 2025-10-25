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

// Read metadata for total difference and sanity method
$totalDifference = 0;
$sanityMethod = 'Simple';
$metaSheet = $spreadsheet->getSheetByName('_Metadata');
if ($metaSheet !== null) {
    $totalDifference = (float)$metaSheet->getCell('B1')->getValue();
    $sanityMethod = $metaSheet->getCell('B2')->getValue();
    if (empty($sanityMethod)) {
        $sanityMethod = 'Simple';
    }
}

// Convert Excel data to array for JavaScript
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

$data = [];
$cellStyles = []; // Store cell background colors and borders
for ($row = 1; $row <= $highestRow; $row++) {
    $rowData = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);
        $rowData[] = $cell->getFormattedValue();

        $styleData = [
            'row' => $row - 1, // 0-indexed for Handsontable
            'col' => $col - 1
        ];
        $hasStyle = false;

        // Get cell background color
        $fill = $cell->getStyle()->getFill();
        $fillType = $fill->getFillType();
        if ($fillType !== \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE) {
            $bgColor = $fill->getStartColor()->getARGB();
            // Remove FF prefix if present and convert to hex
            if (strlen($bgColor) === 8 && substr($bgColor, 0, 2) === 'FF') {
                $bgColor = '#' . substr($bgColor, 2);
            } else {
                $bgColor = '#' . $bgColor;
            }
            $styleData['backgroundColor'] = $bgColor;
            $hasStyle = true;
        }

        // Get cell border style (check if any border is thick/bold)
        $borders = $cell->getStyle()->getBorders();
        $hasBoldBorder = false;

        if ($borders->getTop()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK ||
            $borders->getBottom()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK ||
            $borders->getLeft()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK ||
            $borders->getRight()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK) {
            $styleData['boldBorder'] = true;
            $hasStyle = true;
        }

        if ($hasStyle) {
            $cellStyles[] = $styleData;
        }
    }
    $data[] = $rowData;
}

$jsonData = json_encode($data);
$cellStylesData = json_encode($cellStyles);
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
            gap: 15px;
            align-items: center;
        }

        .total-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 8px 15px;
            border-radius: 4px;
            color: #333;
        }

        .indicator-label {
            font-size: 14px;
            font-weight: bold;
        }

        .indicator-value {
            display: inline-block;
            min-width: 50px;
            padding: 6px 12px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            border: 2px solid;
        }

        .indicator-green {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .indicator-orange {
            background-color: #ff9800;
            border-color: #ff9800;
            color: white;
        }

        .indicator-red {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
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

        #text-layer {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: auto;
        }

        .word-span {
            position: absolute;
            cursor: pointer;
            padding: 0 2px;
            user-select: none;
        }

        .word-span:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 OCR Sanity Verification - <?php echo htmlspecialchars($excelFile); ?></h1>
        <div class="header-buttons">
            <?php if ($sanityMethod === 'LineTotal'): ?>
            <div id="totalIndicator" class="total-indicator">
                <span class="indicator-label">Total Equality Indicator:</span>
                <span class="indicator-value" id="indicatorValue">0</span>
            </div>
            <?php endif; ?>
            <button class="btn btn-save" id="saveBtn">💾 Save OCR Sanity</button>
            <button class="btn btn-cancel" id="reverifyBtn">🔄 Re-verify</button>
        </div>
    </div>

    <div class="instructions">
        💡 <strong>Instructions:</strong> Edit cells in the spreadsheet on the left. Scroll through the PDF on the right to view all pages. Click on highlighted text in the PDF (numbers shown in green) to copy it to the selected cell. Use arrow keys to navigate between cells. Toggle overlay to show/hide clickable text.
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
                <button id="toggle-overlay">👁 Toggle Overlay</button>
                <span id="zoom-level">Zoom: 100%</span>
                <span id="page-info" style="margin-left: auto;">Total Pages: 0</span>
            </div>
            <div class="pdf-container" id="pdf-scroll-container">
                <div class="pdf-wrapper">
                    <canvas id="pdf-canvas"></canvas>
                    <div id="text-layer"></div>
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
        const cellStyles = <?php echo $cellStylesData; ?>;
        let totalDifference = <?php echo $totalDifference; ?>;
        const sanityMethod = <?php echo json_encode($sanityMethod); ?>;

        // Create a map for quick lookup of cell styles
        const styleMap = new Map();
        cellStyles.forEach(style => {
            const key = `${style.row}-${style.col}`;
            styleMap.set(key, style);
        });

        // Function to update total indicator
        function updateTotalIndicator(difference) {
            const indicatorValue = document.getElementById('indicatorValue');
            if (!indicatorValue) return; // Only for LineTotal method

            totalDifference = difference;
            indicatorValue.textContent = difference.toFixed(2);

            // Remove existing color classes
            indicatorValue.classList.remove('indicator-green', 'indicator-orange', 'indicator-red');

            // Apply color based on difference
            if (difference === 0) {
                indicatorValue.classList.add('indicator-green');
            } else if (Math.abs(difference) <= 3) {
                indicatorValue.classList.add('indicator-orange');
            } else {
                indicatorValue.classList.add('indicator-red');
            }
        }

        // Initialize indicator on page load (if LineTotal method)
        if (sanityMethod === 'LineTotal') {
            updateTotalIndicator(totalDifference);
        }

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
            readOnly: false,
            cells: function(row, col) {
                const cellProperties = {};
                const key = `${row}-${col}`;
                if (styleMap.has(key)) {
                    cellProperties.renderer = function(instance, td, row, col, prop, value, cellProperties) {
                        Handsontable.renderers.TextRenderer.apply(this, arguments);
                        const styleData = styleMap.get(`${row}-${col}`);

                        // Safety check - make sure styleData exists
                        if (styleData) {
                            // Apply background color if present
                            if (styleData.backgroundColor) {
                                td.style.backgroundColor = styleData.backgroundColor;
                            }

                            // Apply bold border if present
                            if (styleData.boldBorder) {
                                td.style.border = '3px solid #000';
                            }
                        }
                    };
                }
                return cellProperties;
            }
        });

        // Track selected cell
        let selectedCell = { row: 0, col: 0 };

        hot.addHook('afterSelection', function(row, col) {
            selectedCell = { row, col };
        });

        // PDF.js setup
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let scale = 1.5;
        let overlayVisible = true;
        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas.getContext('2d');

        // Load PDF
        const pdfPath = 'sanity_files/<?php echo htmlspecialchars($pdfFile); ?>';

        pdfjsLib.getDocument(pdfPath).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('page-info').textContent = `Total Pages: ${pdfDoc.numPages}`;
            renderAllPages();
        });

        async function renderAllPages() {
            if (!pdfDoc) return;

            // Get all pages and their viewports
            const numPages = pdfDoc.numPages;
            const pages = [];
            const viewports = [];

            for (let i = 1; i <= numPages; i++) {
                const page = await pdfDoc.getPage(i);
                const viewport = page.getViewport({ scale: scale });
                pages.push(page);
                viewports.push(viewport);
            }

            // Calculate total canvas dimensions
            const maxWidth = Math.max(...viewports.map(v => v.width));
            const totalHeight = viewports.reduce((sum, v) => sum + v.height, 0);

            // Set canvas size
            canvas.width = maxWidth;
            canvas.height = totalHeight;

            // Clear and setup text layer
            const textLayerDiv = document.getElementById('text-layer');
            textLayerDiv.innerHTML = '';
            textLayerDiv.style.width = `${maxWidth}px`;
            textLayerDiv.style.height = `${totalHeight}px`;

            // Render all pages vertically stacked
            let currentY = 0;
            for (let i = 0; i < pages.length; i++) {
                const page = pages[i];
                const viewport = viewports[i];

                // Save context and translate for this page
                ctx.save();
                ctx.translate(0, currentY);

                // Render page
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                await page.render(renderContext).promise;

                // Extract and render text content
                const textContent = await page.getTextContent();
                textContent.items.forEach(item => {
                    const span = document.createElement('span');
                    const word = item.str.trim();

                    if (!word) return; // Skip empty strings

                    span.textContent = word;
                    span.classList.add('word-span');

                    // Calculate position using PDF.js transform
                    const transform = pdfjsLib.Util.transform(viewport.transform, item.transform);
                    const x = transform[4];
                    const y = transform[5] + currentY; // Offset by current Y position
                    const fontSize = Math.sqrt(transform[0] ** 2 + transform[1] ** 2);

                    span.style.left = `${x}px`;
                    span.style.top = `${y - fontSize}px`;
                    span.style.fontSize = `${fontSize}px`;

                    // Highlight numbers with light green background
                    const hasDigits = /[\d]/.test(word);
                    if (hasDigits) {
                        span.style.backgroundColor = 'rgba(144, 238, 144, 0.3)';
                    }

                    // Click handler - copy to selected Excel cell
                    span.onclick = function() {
                        span.style.backgroundColor = 'orange';
                        if (selectedCell) {
                            const { row, col } = selectedCell;
                            hot.setDataAtCell(row, col, word);
                            hot.selectCell(row, col);
                            console.log(`✔ Inserted "${word}" into cell at row ${row}, column ${col}`);
                        } else {
                            console.warn('⚠ No cell selected!');
                        }
                    };

                    // Only add span if overlay is visible
                    if (overlayVisible) {
                        textLayerDiv.appendChild(span);
                    }
                });

                // Restore context
                ctx.restore();

                // Move Y position for next page
                currentY += viewport.height;
            }
        }

        // Zoom controls
        document.getElementById('zoom-in').addEventListener('click', function() {
            scale += 0.2;
            document.getElementById('zoom-level').textContent = `Zoom: ${Math.round(scale * 100)}%`;
            renderAllPages();
        });

        document.getElementById('zoom-out').addEventListener('click', function() {
            if (scale <= 0.5) return;
            scale -= 0.2;
            document.getElementById('zoom-level').textContent = `Zoom: ${Math.round(scale * 100)}%`;
            renderAllPages();
        });

        document.getElementById('zoom-reset').addEventListener('click', function() {
            scale = 1.5;
            document.getElementById('zoom-level').textContent = `Zoom: 100%`;
            renderAllPages();
        });

        // Toggle overlay
        document.getElementById('toggle-overlay').addEventListener('click', function() {
            overlayVisible = !overlayVisible;
            renderAllPages();
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

        // Re-verify functionality
        document.getElementById('reverifyBtn').addEventListener('click', function() {
            const updatedData = hot.getData();

            // Disable re-verify button
            document.getElementById('reverifyBtn').disabled = true;
            document.getElementById('reverifyBtn').textContent = '⏳ Re-verifying...';

            // Send data to server to re-verify
            fetch('reverify_ocrsanity.php', {
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
                console.log('Re-verify response:', data); // Debug log
                if (data.success) {
                    // Update styleMap with new cell styles
                    styleMap.clear();
                    data.cellStyles.forEach(style => {
                        const key = `${style.row}-${style.col}`;
                        styleMap.set(key, style);
                    });

                    // Update total indicator if LineTotal method
                    if (sanityMethod === 'LineTotal' && data.totalDifference !== undefined) {
                        console.log('Updating indicator with:', data.totalDifference); // Debug log
                        updateTotalIndicator(data.totalDifference);
                    }

                    // Force Handsontable to re-render all cells with new styles
                    hot.render();

                    // Re-enable button
                    document.getElementById('reverifyBtn').disabled = false;
                    document.getElementById('reverifyBtn').textContent = '🔄 Re-verify';
                } else {
                    alert('❌ Error re-verifying file: ' + data.error);
                    document.getElementById('reverifyBtn').disabled = false;
                    document.getElementById('reverifyBtn').textContent = '🔄 Re-verify';
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
                document.getElementById('reverifyBtn').disabled = false;
                document.getElementById('reverifyBtn').textContent = '🔄 Re-verify';
            });
        });
    </script>
</body>
</html>
