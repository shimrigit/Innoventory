<?php
// Commercial Layer Verification - Side-by-side CL Excel and Invoice PDF view

session_start();
$isDemoMode = !empty($_SESSION['harmonizedFlow']['demoMode']) && $_SESSION['harmonizedFlow']['demoMode'] === true;

$clFileName = $_GET['cl'] ?? '';
$pdfFileName = $_GET['pdf'] ?? '';
$shopName = $_GET['shop'] ?? '';

if (empty($clFileName) || empty($pdfFileName)) {
    die("Error: Missing CL file or PDF file parameter");
}

$clFilePath = __DIR__ . '/commercial_invoice_files/' . basename($clFileName);
$pdfFilePath = __DIR__ . '/commercial_invoice_files/' . basename($pdfFileName);

if (!file_exists($clFilePath) || !file_exists($pdfFilePath)) {
    die("Error: CL file or PDF file not found");
}

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Load Excel file and convert to array
$spreadsheet = IOFactory::load($clFilePath);
$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();

// Convert Excel data to array for Handsontable
$excelData = [];
for ($row = 1; $row <= $highestRow; $row++) {
    $rowData = [];
    $columnIterator = $sheet->getRowIterator($row)->current()->getCellIterator();
    $columnIterator->setIterateOnlyExistingCells(false);

    foreach ($columnIterator as $cell) {
        $rowData[] = $cell->getFormattedValue();
    }
    $excelData[] = $rowData;
}

// Collect cell styles (background colors and borders from conditional formatting)
$cellStyles = [];
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

for ($row = 1; $row <= $highestRow; $row++) {
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cell = $sheet->getCellByColumnAndRow($col, $row);

        $styleData = [
            'row' => $row - 1,
            'col' => $col - 1
        ];
        $hasStyle = false;

        // Get background color
        $fill = $cell->getStyle()->getFill();
        $fillType = $fill->getFillType();
        if ($fillType !== \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE) {
            $bgColor = $fill->getStartColor()->getARGB();
            if (strlen($bgColor) === 8 && substr($bgColor, 0, 2) === 'FF') {
                $bgColor = '#' . substr($bgColor, 2);
            } else {
                $bgColor = '#' . $bgColor;
            }
            $styleData['backgroundColor'] = $bgColor;
            $hasStyle = true;
        }

        // Get border styles - check if any border is THICK (bold)
        $borders = $cell->getStyle()->getBorders();
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commercial Layer Verification</title>

    <!-- Handsontable CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@12.3.1/dist/handsontable.full.min.css">

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
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .header h1 {
            font-size: 20px;
            font-weight: normal;
        }

        .header-info {
            font-size: 14px;
            opacity: 0.9;
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-save {
            background: #27ae60;
            color: white;
        }

        .btn-save:hover {
            background: #229954;
        }

        .btn-retrieve {
            background: #3498db;
            color: white;
        }

        .btn-retrieve:hover {
            background: #2980b9;
        }

        .btn-generate {
            background: #e67e22;
            color: white;
        }

        .btn-generate:hover {
            background: #d35400;
        }

        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .instructions {
            background: #ecf0f1;
            padding: 10px 20px;
            font-size: 13px;
            border-bottom: 1px solid #bdc3c7;
        }

        .container {
            display: flex;
            height: calc(100vh - 120px);
        }

        .left-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 2px solid #34495e;
        }

        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            background: #34495e;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
        }

        .excel-container {
            flex: 1;
            overflow: auto;
            background: white;
            padding: 10px;
        }

        #hot-container {
            width: 100%;
            height: auto;
            overflow: hidden;
        }

        .pdf-controls {
            background: #ecf0f1;
            padding: 10px 15px;
            display: flex;
            gap: 10px;
            align-items: center;
            border-bottom: 1px solid #bdc3c7;
        }

        .pdf-controls button {
            padding: 6px 12px;
            border: 1px solid #95a5a6;
            background: white;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
        }

        .pdf-controls button:hover {
            background: #f8f9fa;
        }

        .pdf-container {
            flex: 1;
            overflow: auto;
            background: #525252;
            position: relative;
        }

        .pdf-wrapper {
            display: inline-block;
            position: relative;
        }

        #pdf-canvas {
            display: block;
            margin: 20px auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
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
            color: transparent;
            user-select: none;
        }

        .word-span:hover {
            background-color: rgba(255, 255, 0, 0.3);
            color: rgba(0, 0, 0, 0.1);
        }

        .word-span.clicked {
            background-color: rgba(0, 255, 0, 0.4);
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🏪 Commercial Layer Verification</h1>
            <div class="header-info">
                Shop: <?php echo htmlspecialchars($shopName); ?> |
                File: <?php echo htmlspecialchars($clFileName); ?>
            </div>
        </div>
        <div class="button-group">
            <button class="btn btn-retrieve" id="retrieveBtn">🔄 Retrieve Again Commercial Data</button>
            <button class="btn btn-generate" id="generateBtn">📊 Generate Price Change and New Products Files</button>
            <button class="btn btn-save" id="saveBtn">✅ Conclude CL Stage - no price change and new products</button>
        </div>
    </div>

    <div class="instructions">
        💡 <strong>Instructions:</strong> Edit cells in the CL spreadsheet on the left. Click on highlighted text in the PDF (right) to copy it to the selected cell. Use arrow keys to navigate between cells.
    </div>

    <div class="container">
        <!-- Left Panel - CL Excel Editor -->
        <div class="left-panel">
            <div class="panel-header">📝 Commercial Layer Spreadsheet (Editable)</div>
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
    <script src="https://cdn.jsdelivr.net/npm/handsontable@12.3.1/dist/handsontable.full.min.js"></script>

    <script>
        // Excel data and configuration
        const excelData = <?php echo json_encode($excelData); ?>;
        const cellStylesData = <?php echo json_encode($cellStyles); ?>;
        const clFileName = <?php echo json_encode($clFileName); ?>;
        const pdfFileName = <?php echo json_encode($pdfFileName); ?>;
        const shopName = <?php echo json_encode($shopName); ?>;
        const isDemoMode = <?php echo json_encode($isDemoMode); ?>;

        // Create styleMap for Handsontable
        const styleMap = new Map();
        cellStylesData.forEach(style => {
            const key = `${style.row}-${style.col}`;
            styleMap.set(key, style);
        });

        // Initialize Handsontable
        function hotHeight() {
            const top = document.getElementById('hot-container').getBoundingClientRect().top;
            return Math.floor(window.innerHeight - top - 12);
        }

        const container = document.getElementById('hot-container');
        const hot = new Handsontable(container, {
            data: excelData,
            colHeaders: true,
            rowHeaders: true,
            width: '100%',
            height: hotHeight(),
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
                const style = styleMap.get(key);

                // Always define a renderer to handle both adding AND removing styles
                cellProperties.renderer = function(instance, td, row, col, prop, value, cellProperties) {
                    Handsontable.renderers.TextRenderer.apply(this, arguments);

                    // Apply background color if present, otherwise clear it
                    if (style && style.backgroundColor) {
                        td.style.background = style.backgroundColor;
                    } else {
                        td.style.background = '';
                    }

                    // Apply bold border if present, otherwise clear it
                    if (style && style.boldBorder) {
                        td.style.border = '3px solid #000';
                    } else {
                        td.style.border = '';
                    }
                };

                return cellProperties;
            }
        });

        window.addEventListener('resize', () => hot.updateSettings({ height: hotHeight() }));

        // Track selected cell for PDF click-to-copy
        let selectedCell = { row: 0, col: 0 };

        hot.addHook('afterSelection', function(row, col) {
            selectedCell = { row, col };
            console.log(`Selected cell: row ${row}, col ${col}`);
        });

        // Save CL file functionality
        document.getElementById('saveBtn').addEventListener('click', function() {
            const updatedData = hot.getData();

            // Check if we're in harmonized mode
            const urlParams = new URLSearchParams(window.location.search);
            const harmonizedMode = urlParams.get('harmonized') === 'true';

            // Disable save button
            document.getElementById('saveBtn').disabled = true;
            document.getElementById('saveBtn').textContent = '⏳ Saving...';

            // Send data to server to save
            fetch('save_commercial_layer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: clFileName,
                    data: updatedData,
                    shop: shopName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ CL file saved successfully!');

                    // If harmonized mode, redirect to harmonized completion page
                    if (harmonizedMode) {
                        window.location.href = 'cl_complete.php?cl=' + encodeURIComponent(clFileName) + '&shop=' + encodeURIComponent(shopName);
                    } else {
                        // Non-harmonized mode - redirect to process page
                        window.location.href = 'process_commercial_layer.php';
                    }
                } else {
                    alert('❌ Error saving file: ' + data.error);
                    document.getElementById('saveBtn').disabled = false;
                    document.getElementById('saveBtn').textContent = '✅ Conclude CL Stage - no price change and new products';
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
                document.getElementById('saveBtn').disabled = false;
                document.getElementById('saveBtn').textContent = '✅ Conclude CL Stage - no price change and new products';
            });
        });

        // Retrieve again functionality - re-match barcodes with price list
        document.getElementById('retrieveBtn').addEventListener('click', function() {
            if (!confirm('🔄 This will re-match all barcodes in column D with the price list and update columns L-P. Continue?')) {
                return;
            }

            // Disable button during processing
            document.getElementById('retrieveBtn').disabled = true;
            document.getElementById('retrieveBtn').textContent = '⏳ Retrieving...';

            const currentData = hot.getData();

            fetch('retrieve_commercial_layer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    filename: clFileName,
                    shop: shopName,
                    data: currentData,
                    demoMode: isDemoMode
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cells with retrieved data
                    if (data.updatedCells && data.updatedCells.length > 0) {
                        // First, reload the Excel file to get updated border styles
                        fetch('get_cell_styles.php?filename=' + encodeURIComponent(clFileName))
                            .then(response => response.json())
                            .then(stylesData => {
                                // Update styleMap with new styles from Excel
                                if (stylesData.success && stylesData.styles) {
                                    // Clear existing styleMap
                                    styleMap.clear();

                                    // Rebuild styleMap with new styles
                                    stylesData.styles.forEach(style => {
                                        const key = `${style.row}-${style.col}`;
                                        styleMap.set(key, style);
                                    });
                                }

                                // Update cell values
                                data.updatedCells.forEach(cell => {
                                    // Format value for display
                                    let displayValue = cell.value;

                                    // Column P (15) - add % sign for numeric values
                                    if (cell.col === 15 && typeof cell.value === 'number') {
                                        displayValue = cell.value.toFixed(2) + '%';
                                    }
                                    // Columns L and O (11, 14) - format as currency
                                    else if ((cell.col === 11 || cell.col === 14) && cell.value !== '') {
                                        displayValue = cell.value;
                                    }

                                    hot.setDataAtCell(cell.row, cell.col, displayValue);
                                });

                                // Force re-render with updated styles
                                hot.render();

                                alert('✅ ' + data.message);
                                document.getElementById('retrieveBtn').disabled = false;
                                document.getElementById('retrieveBtn').textContent = '🔄 Retrieve Again Commercial Data';
                            })
                            .catch(error => {
                                console.error('Error loading cell styles:', error);
                                // Still update cell values even if styles fail
                                data.updatedCells.forEach(cell => {
                                    let displayValue = cell.value;
                                    if (cell.col === 15 && typeof cell.value === 'number') {
                                        displayValue = cell.value.toFixed(2) + '%';
                                    } else if ((cell.col === 11 || cell.col === 14) && cell.value !== '') {
                                        displayValue = cell.value;
                                    }
                                    hot.setDataAtCell(cell.row, cell.col, displayValue);
                                });
                                hot.render();

                                alert('✅ ' + data.message);
                                document.getElementById('retrieveBtn').disabled = false;
                                document.getElementById('retrieveBtn').textContent = '🔄 Retrieve Again Commercial Data';
                            });
                    } else {
                        alert('✅ ' + data.message);
                        document.getElementById('retrieveBtn').disabled = false;
                        document.getElementById('retrieveBtn').textContent = '🔄 Retrieve Again Commercial Data';
                    }
                } else {
                    alert('❌ Error retrieving data: ' + data.error);
                    document.getElementById('retrieveBtn').disabled = false;
                    document.getElementById('retrieveBtn').textContent = '🔄 Retrieve Again Commercial Data';
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
                document.getElementById('retrieveBtn').disabled = false;
                document.getElementById('retrieveBtn').textContent = '🔄 Retrieve Again Commercial Data';
            });
        });

        // Generate Price Change and New Products files functionality
        document.getElementById('generateBtn').addEventListener('click', function() {
            if (!confirm('📊 This will save the current CL file and generate Price Change and New Products files. Continue?')) {
                return;
            }

            // Disable button during processing
            document.getElementById('generateBtn').disabled = true;
            document.getElementById('generateBtn').textContent = '⏳ Generating...';

            const currentData = hot.getData();

            fetch('generate_pc_np_files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    filename: clFileName,
                    shop: shopName,
                    data: currentData
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response from generate_pc_np_files.php:', data);

                if (data.success) {
                    // Check if PC file was skipped
                    if (data.skipPriceChange) {
                        alert('ℹ️ ' + data.message);

                        console.log('skipPriceChange detected. generateNP flag:', data.generateNP);
                        console.log('hasNewProducts:', data.hasNewProducts);
                        console.log('redirectUrl:', data.redirectUrl);

                        // Check if we need to generate NP file (no PC but has NP)
                        if (data.generateNP) {
                            console.log('Calling generate_np_file.php with:', {
                                clFileName: data.clFileName,
                                shopName: data.shopName
                            });
                            // Call generate_np_file.php to create NP file
                            fetch('generate_np_file.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    clFileName: data.clFileName,
                                    shopName: data.shopName
                                })
                            })
                            .then(response => response.json())
                            .then(npData => {
                                console.log('Response from generate_np_file.php:', npData);

                                if (npData.success && npData.hasNewProducts) {
                                    alert('✅ New Products file created!\n\n' +
                                          'Products found: ' + npData.newProductCount + '\n' +
                                          'Supplier: ' + npData.supplierHebrewName + '\n\n' +
                                          'Redirecting to New Product verification...');
                                    window.location.href = npData.redirectUrl;
                                } else {
                                    console.log('NP generation failed or no new products. npData:', npData);
                                    alert('❌ Error: ' + (npData.error || 'Failed to create NP file'));
                                    document.getElementById('generateBtn').disabled = false;
                                    document.getElementById('generateBtn').textContent = '📊 Generate Price Change and New Products Files';
                                }
                            })
                            .catch(error => {
                                console.error('Error calling generate_np_file.php:', error);
                                alert('❌ Error generating NP file: ' + error);
                                document.getElementById('generateBtn').disabled = false;
                                document.getElementById('generateBtn').textContent = '📊 Generate Price Change and New Products Files';
                            });
                        } else if (data.redirectUrl) {
                            console.log('Redirecting directly to:', data.redirectUrl);
                            // Direct redirect (no PC, no NP - go to completion)
                            window.location.href = data.redirectUrl;
                        } else {
                            document.getElementById('generateBtn').disabled = false;
                            document.getElementById('generateBtn').textContent = '📊 Generate Price Change and New Products Files';
                        }
                    } else {
                        alert('✅ ' + data.message + '\n\n' +
                              'Price Change file: ' + data.pcFileName + '\n' +
                              'Total rows: ' + data.pcRowCount + '\n' +
                              'Items with recommendations: ' + data.recommendationCount + '\n\n' +
                              'Redirecting to verification page...');

                        // Redirect to verification page
                        if (data.redirectUrl) {
                            window.location.href = data.redirectUrl;
                        } else {
                            document.getElementById('generateBtn').disabled = false;
                            document.getElementById('generateBtn').textContent = '📊 Generate Price Change and New Products Files';
                        }
                    }
                } else {
                    alert('❌ Error generating files: ' + data.error);
                    document.getElementById('generateBtn').disabled = false;
                    document.getElementById('generateBtn').textContent = '📊 Generate Price Change and New Products Files';
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
                document.getElementById('generateBtn').disabled = false;
                document.getElementById('generateBtn').textContent = '📊 Generate Price Change and New Products Files';
            });
        });

        // PDF.js Setup
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let currentScale = 1.5;
        let overlayVisible = true;

        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas.getContext('2d');
        const textLayerDiv = document.getElementById('text-layer');
        const pdfContainer = document.getElementById('pdf-scroll-container');

        // Load PDF
        const pdfPath = 'commercial_invoice_files/' + encodeURIComponent(pdfFileName);

        pdfjsLib.getDocument(pdfPath).promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('page-info').textContent = 'Total Pages: ' + pdf.numPages;
            renderAllPages();
        }).catch(function(error) {
            console.error('Error loading PDF:', error);
            alert('Error loading PDF: ' + error.message);
        });

        async function renderAllPages() {
            if (!pdfDoc) return;

            // Clear previous content
            textLayerDiv.innerHTML = '';

            // Load all pages
            const pages = [];
            const viewports = [];
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                const page = await pdfDoc.getPage(i);
                const viewport = page.getViewport({ scale: currentScale });
                pages.push(page);
                viewports.push(viewport);
            }

            // Calculate total height and max width
            const totalHeight = viewports.reduce((sum, vp) => sum + vp.height, 0);
            const maxWidth = Math.max(...viewports.map(vp => vp.width));

            // Set canvas size
            canvas.width = maxWidth;
            canvas.height = totalHeight;

            // Clear and setup text layer
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
                    const tx = pdfjsLib.Util.transform(viewport.transform, item.transform);
                    const fontSize = Math.sqrt(tx[2] * tx[2] + tx[3] * tx[3]);

                    span.style.left = `${tx[4]}px`;
                    span.style.top = `${currentY + tx[5]}px`;
                    span.style.fontSize = `${fontSize}px`;
                    span.style.fontFamily = item.fontName || 'sans-serif';

                    // Click handler to copy to selected cell
                    span.onclick = function() {
                        span.style.backgroundColor = 'orange';
                        if (selectedCell) {
                            const { row, col } = selectedCell;
                            hot.setDataAtCell(row, col, word);
                            hot.selectCell(row, col);
                            console.log(`✔ Inserted "${word}" into cell at row ${row}, column ${col}`);

                            // Visual feedback
                            setTimeout(() => {
                                span.style.backgroundColor = '';
                            }, 500);
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
            currentScale += 0.1;
            updateZoom();
        });

        document.getElementById('zoom-out').addEventListener('click', function() {
            currentScale = Math.max(0.5, currentScale - 0.1);
            updateZoom();
        });

        document.getElementById('zoom-reset').addEventListener('click', function() {
            currentScale = 1.0;
            updateZoom();
        });

        function updateZoom() {
            document.getElementById('zoom-level').textContent = 'Zoom: ' + Math.round(currentScale * 100) + '%';
            renderAllPages();
        }

        // Toggle overlay
        document.getElementById('toggle-overlay').addEventListener('click', function() {
            overlayVisible = !overlayVisible;
            if (overlayVisible) {
                renderAllPages();
            } else {
                textLayerDiv.innerHTML = '';
            }
        });
    </script>
</body>
</html>
