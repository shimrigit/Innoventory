<?php
/**
 * TOE Test Frame  –  Tesseract OCR Engine
 *
 * Test frame items:
 *   Item 1 – Launch AIE for PNG cropping
 *   Item 6 – Display OCRjson as a formatted screen picture
 *
 * The core (Items 2-5) is handled by process_toe.php / toe_tsv_parser.php.
 */

// ── Load config ──────────────────────────────────────────────────────────────
$cfg_path = __DIR__ . '/toe_config.json';
if (!file_exists($cfg_path)) {
    die('<pre style="color:red">toe_config.json not found at ' . htmlspecialchars($cfg_path) . '</pre>');
}
$cfg = json_decode(file_get_contents($cfg_path), true);

// Resolve absolute paths for test directories
$crops_dir_abs = realpath(__DIR__ . '/' . $cfg['crops_dir_test']);
$ocr_dir_abs   = realpath(__DIR__ . '/' . $cfg['ocr_extracted_dir_test']);
$tsv_dir_abs   = __DIR__ . '/' . $cfg['tsv_debug_dir'];

// ── Collect available crop groups ────────────────────────────────────────────
function get_crop_groups(string $crops_dir): array {
    if (!is_dir($crops_dir)) return [];

    $groups = [];
    $suffix_re = '/(_Date|_Invoiceno|_Total|_Table\d+)(\.png)$/i';

    foreach (scandir($crops_dir) as $file) {
        if (!preg_match('/\.png$/i', $file)) continue;
        if (!preg_match($suffix_re, $file, $m)) continue;

        // Skip versioned duplicates:  "_Date (1).png"
        if (preg_match('/\s*\(\d+\)\.(png)$/i', $file)) continue;

        $prefix = preg_replace($suffix_re, '', $file);
        if ($prefix === '') continue;

        if (!isset($groups[$prefix])) {
            $groups[$prefix] = [
                'has_date'      => false,
                'has_invoiceno' => false,
                'has_total'     => false,
                'table_count'   => 0,
            ];
        }

        $suffix_lower = strtolower($m[1]);
        if ($suffix_lower === '_date')      $groups[$prefix]['has_date']      = true;
        elseif ($suffix_lower === '_invoiceno') $groups[$prefix]['has_invoiceno'] = true;
        elseif ($suffix_lower === '_total')     $groups[$prefix]['has_total']     = true;
        elseif (strpos($suffix_lower, '_table') === 0) $groups[$prefix]['table_count']++;
    }

    ksort($groups);
    return $groups;
}

$crop_groups = $crops_dir_abs ? get_crop_groups($crops_dir_abs) : [];

// ── Handle POST – run the core processor ────────────────────────────────────
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_prefix = trim($_POST['invoice_prefix'] ?? '');

    if ($invoice_prefix === '') {
        $result = ['success' => false, 'errors' => ['No invoice prefix selected.'], 'messages' => [], 'tsv_files' => []];
    } else {
        require_once __DIR__ . '/process_toe.php';

        $options = [
            'tesseract_path'         => $cfg['tesseract_path'],
            'magick_path'            => $cfg['magick_path'],
            'tesseract_lang'         => $cfg['tesseract_lang'],
            'tesseract_oem'          => isset($cfg['tesseract_oem']) ? (int)$cfg['tesseract_oem'] : null,
            'tessdata_dir'           => $cfg['tessdata_dir'] ?? null,
            'table_psm'              => (int)($_POST['table_psm']    ?? $cfg['table_psm_default']),
            'header_psm'             => (int)($cfg['header_psm']     ?? 7),
            'preprocess_headers'     => isset($_POST['preprocess_headers']),
            'preprocess_tables'      => isset($_POST['preprocess_tables']),
            'preprocess_args_table'  => $cfg['preprocess_args_table'],
            'preprocess_args_header' => $cfg['preprocess_args_header'],
            'column_gap_px'          => (int)($_POST['column_gap_px'] ?? $cfg['column_gap_px']),
        ];

        $result = process_invoice_toe(
            $crops_dir_abs,
            $invoice_prefix,
            $options,
            $ocr_dir_abs,
            $tsv_dir_abs
        );
    }
}

// ── Collect source PNGs for the result panel ─────────────────────────────────
$result_pngs    = [];
$crops_url_base = '';
if (!empty($result['success']) && $crops_dir_abs && !empty($invoice_prefix)) {
    $suffix_re  = '/(_Date|_Invoiceno|_Total|_Table(\d+))(\.png)$/i';
    $header_map = [];
    $tables_map = [];
    foreach (scandir($crops_dir_abs) as $f) {
        if (!preg_match('/\.png$/i', $f)) continue;
        if (preg_match('/\s*\(\d+\)\.png$/i', $f)) continue; // skip versioned duplicates
        if (strpos($f, $invoice_prefix) !== 0) continue;
        if (!preg_match($suffix_re, $f, $m)) continue;
        $sl = strtolower($m[1]);
        if (in_array($sl, ['_date', '_invoiceno', '_total'])) {
            $header_map[$sl] = $f;
        } elseif (isset($m[2])) {
            $tables_map[(int)$m[2]] = $f;
        }
    }
    ksort($tables_map);
    foreach (['_date' => 'Date', '_invoiceno' => 'Invoice No', '_total' => 'Total'] as $k => $lbl) {
        if (isset($header_map[$k])) $result_pngs[] = ['label' => $lbl, 'file' => $header_map[$k], 'type' => 'header'];
    }
    foreach ($tables_map as $idx => $f) {
        $result_pngs[] = ['label' => "Table {$idx}", 'file' => $f, 'type' => 'table'];
    }
    $doc_root       = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $crops_url_base = rtrim(str_replace('\\', '/', $crops_dir_abs), '/');
    $crops_url_base = str_replace($doc_root, '', $crops_url_base) . '/';
}

// ── Helpers for display ──────────────────────────────────────────────────────
function badge(bool $ok, string $yes = 'YES', string $no = 'NO'): string {
    return $ok
        ? "<span style='background:#28a745;color:#fff;padding:2px 7px;border-radius:3px;font-size:.8em'>{$yes}</span>"
        : "<span style='background:#ccc;color:#555;padding:2px 7px;border-radius:3px;font-size:.8em'>{$no}</span>";
}
?>
<!DOCTYPE html>
<html lang="he" dir="ltr">
<head>
<meta charset="UTF-8">
<title>TOE – Tesseract OCR Engine</title>
<style>
  body        { font-family: Arial, sans-serif; font-size: 14px; background: #f5f5f5; margin: 0; padding: 16px; }
  h1          { font-size: 1.5em; margin-bottom: 4px; }
  h2          { font-size: 1.15em; margin: 20px 0 8px; border-bottom: 2px solid #007bff; padding-bottom: 4px; color: #007bff; }
  .card       { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
  .btn        { display: inline-block; padding: 7px 18px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
  .btn-primary{ background: #007bff; color: #fff; }
  .btn-secondary{ background: #6c757d; color: #fff; text-decoration: none; }
  .btn-success{ background: #28a745; color: #fff; }
  .btn:hover  { opacity: .85; }
  label       { display: inline-block; margin-right: 16px; cursor: pointer; }
  select, input[type=number] { padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
  .group-list { max-height: 340px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 4px; }
  .group-item { padding: 6px 10px; cursor: pointer; border-radius: 3px; display: flex; align-items: center; gap: 8px; }
  .group-item:hover { background: #e8f4ff; }
  .group-item.selected { background: #cce5ff; font-weight: bold; }
  .group-item input[type=radio] { margin: 0; }
  .badges     { display: flex; gap: 4px; flex-shrink: 0; }
  .group-name { flex: 1; font-family: monospace; font-size: 13px; direction: ltr; }
  /* ── Result section ── */
  .invoice-box{ display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; }
  .inv-field  { background: #f0f7ff; border: 1px solid #b8d8f5; border-radius: 5px; padding: 10px 18px; min-width: 160px; }
  .inv-label  { font-size: .75em; color: #555; text-transform: uppercase; letter-spacing: .5px; }
  .inv-value  { font-size: 1.3em; font-weight: bold; color: #003070; margin-top: 3px; direction: ltr; }
  .tbl-wrap   { overflow-x: auto; }
  table.ocr   { border-collapse: collapse; width: 100%; font-size: 13px; }
  table.ocr th{ background: #343a40; color: #fff; padding: 6px 10px; text-align: center; white-space: nowrap; }
  table.ocr td{ border: 1px solid #ddd; padding: 5px 8px; white-space: nowrap; }
  table.ocr tr:nth-child(even) td { background: #f8f8f8; }
  table.ocr td[dir=auto]:not(:first-child) { text-align: right; }
  .log-box    { background: #1e1e1e; color: #ccc; font-family: monospace; font-size: 12px;
                border-radius: 4px; padding: 12px; max-height: 260px; overflow-y: auto; white-space: pre-wrap; }
  .log-err    { color: #f88; }
  .alert-ok   { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px 14px; border-radius: 4px; margin-bottom: 10px; }
  .alert-err  { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px 14px; border-radius: 4px; margin-bottom: 10px; }
  .tsv-list   { margin: 0; padding-left: 18px; }
  .tsv-list li{ font-family: monospace; font-size: 12px; color: #555; }
  summary     { cursor: pointer; font-weight: bold; margin-bottom: 6px; }
  details     { margin-top: 10px; }
  /* ── Split result layout ── */
  .result-split { display: flex; gap: 20px; align-items: flex-start; }
  .result-left  { flex: 1; min-width: 0; }
  .result-right { flex: 1; min-width: 0; position: sticky; top: 16px; max-height: 92vh; display: flex; flex-direction: column; }
  /* ── PNG viewer ── */
  .png-panel    { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 12px; display: flex; flex-direction: column; max-height: 92vh; }
  .png-panel-title { font-weight: bold; font-size: .95em; margin-bottom: 8px; color: #333; }
  .png-zoom-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
  .png-zoom-bar input[type=range] { flex: 1; }
  .zoom-btn     { background: #6c757d; color: #fff; border: none; border-radius: 4px; padding: 2px 9px; font-size: 15px; cursor: pointer; line-height: 1.4; }
  .zoom-btn:hover { background: #545b62; }
  .zoom-lbl     { font-size: 12px; min-width: 38px; text-align: right; color: #555; }
  .png-scroll   { overflow-y: auto; flex: 1; }
  /* header PNGs (Date/InvoiceNo/Total) in one row */
  .png-headers-row { display: flex; gap: 8px; margin-bottom: 14px; }
  .png-header-item { flex: 1; min-width: 0; }
  /* table PNGs stacked full width */
  .png-entry    { margin-bottom: 14px; }
  .png-entry-label { font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 3px; }
  .png-entry-wrap  { overflow-x: auto; }
  .png-img      { display: block; width: 100%; border: 1px solid #ddd; border-radius: 3px; cursor: zoom-in; transition: width .15s; }
</style>
</head>
<body>

<h1>🔬 TOE – Tesseract OCR Engine</h1>
<p style="color:#555;margin-top:0">Local Tesseract OCR test frame &nbsp;|&nbsp;
   <a href="/website/AIocr/AIE/enhancer_ai.php">→ Open AIE (OpenAI engine)</a></p>
<hr>

<?php if ($result !== null): ?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     ITEM 6 – Result display
     ═══════════════════════════════════════════════════════════════════════════ -->
<h2>Result</h2>
<div class="result-split">
<div class="result-left">

<?php if (!$result['success']): ?>
  <div class="alert-err">
    <strong>Processing failed.</strong><br>
    <?php foreach ($result['errors'] as $e): ?>
      <?= htmlspecialchars($e) ?><br>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="alert-ok">
    ✅ OCRjson saved: <strong><?= htmlspecialchars($result['json_file']) ?></strong>
  </div>

  <?php $data = $result['data']; ?>

  <!-- Invoice header fields -->
  <div class="invoice-box">
    <div class="inv-field">
      <div class="inv-label">Invoice Number</div>
      <div class="inv-value"><?= htmlspecialchars($data['invoice_number'] ?: '—') ?></div>
    </div>
    <div class="inv-field">
      <div class="inv-label">Invoice Date</div>
      <div class="inv-value"><?= htmlspecialchars($data['invoice_date'] ?: '—') ?></div>
    </div>
    <div class="inv-field">
      <div class="inv-label">Invoice Total</div>
      <div class="inv-value">
        <?= $data['invoice_total'] !== null ? number_format((float)$data['invoice_total'], 2) : '—' ?>
      </div>
    </div>
    <div class="inv-field">
      <div class="inv-label">Table Rows</div>
      <div class="inv-value"><?= count($data['table']) ?></div>
    </div>
  </div>

  <!-- Data table -->
  <?php if (!empty($data['table'])): ?>
    <?php
      // Determine number of columns from first row (all rows have same structure)
      $first_row = $data['table'][0];
      $num_cols  = count($first_row) - 1; // subtract 'index'
    ?>
    <div class="tbl-wrap card" style="padding:0">
      <table class="ocr">
        <thead>
          <tr>
            <th>#</th>
            <?php for ($c = 1; $c <= $num_cols; $c++): ?>
              <th><?= $c ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data['table'] as $row): ?>
          <tr>
            <td style="color:#888;text-align:center"><?= (int)$row['index'] ?></td>
            <?php for ($c = 1; $c <= $num_cols; $c++): ?>
              <td dir="auto"><?= htmlspecialchars($row[(string)$c] ?? '') ?></td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p style="color:#888">No table rows extracted.</p>
  <?php endif; ?>

  <!-- TSV debug files -->
  <?php if (!empty($result['tsv_files'])): ?>
  <details>
    <summary>TSV Debug Files (<?= count($result['tsv_files']) ?>)</summary>
    <ul class="tsv-list">
      <?php foreach ($result['tsv_files'] as $tsv): ?>
        <li><?= htmlspecialchars(basename($tsv)) ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="font-size:12px;color:#777">Files saved in: <?= htmlspecialchars($tsv_dir_abs) ?></p>
  </details>
  <?php endif; ?>

<?php endif; ?>

<!-- Processing log -->
<details>
  <summary>Processing Log</summary>
  <div class="log-box"><?php
    foreach ($result['messages'] as $m) {
        echo htmlspecialchars($m) . "\n";
    }
    foreach ($result['errors'] as $e) {
        echo '<span class="log-err">' . htmlspecialchars($e) . "</span>\n";
    }
  ?></div>
</details>

<p style="margin-top:14px">
  <a href="toe_start.php" class="btn btn-secondary">← Process Another Invoice</a>
</p>

</div><!-- .result-left -->

<?php if (!empty($result_pngs)): ?>
<div class="result-right">
  <div class="png-panel">
    <div class="png-panel-title">📸 Source PNGs (<?= count($result_pngs) ?>)</div>
    <div class="png-zoom-bar">
      <button class="zoom-btn" onclick="changeZoom(-25)">−</button>
      <input type="range" id="zoom-slider" min="30" max="300" value="100" oninput="setZoom(this.value)">
      <button class="zoom-btn" onclick="changeZoom(+25)">+</button>
      <span class="zoom-lbl" id="zoom-lbl">100%</span>
    </div>
    <div class="png-scroll">

      <?php
        $header_pngs = array_filter($result_pngs, fn($p) => $p['type'] === 'header');
        $table_pngs  = array_filter($result_pngs, fn($p) => $p['type'] === 'table');
      ?>

      <?php if (!empty($header_pngs)): ?>
      <div class="png-headers-row">
        <?php foreach ($header_pngs as $png): ?>
        <div class="png-header-item">
          <div class="png-entry-label"><?= htmlspecialchars($png['label']) ?></div>
          <div class="png-entry-wrap">
            <img src="<?= htmlspecialchars($crops_url_base . $png['file']) ?>"
                 class="png-img"
                 alt="<?= htmlspecialchars($png['label']) ?>"
                 title="Click to toggle full size"
                 onclick="toggleFull(this)">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php foreach ($table_pngs as $png): ?>
      <div class="png-entry">
        <div class="png-entry-label"><?= htmlspecialchars($png['label']) ?></div>
        <div class="png-entry-wrap">
          <img src="<?= htmlspecialchars($crops_url_base . $png['file']) ?>"
               class="png-img"
               alt="<?= htmlspecialchars($png['label']) ?>"
               title="Click to toggle full size"
               onclick="toggleFull(this)">
        </div>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</div><!-- .result-right -->
<?php endif; ?>

</div><!-- .result-split -->
<hr>
<?php endif; ?>

<script>
let zoom = 100;
function setZoom(val) {
    zoom = Math.min(300, Math.max(30, parseInt(val)));
    document.querySelectorAll('.png-img').forEach(img => img.style.width = zoom + '%');
    document.getElementById('zoom-lbl').textContent = zoom + '%';
    document.getElementById('zoom-slider').value = zoom;
}
function changeZoom(delta) { setZoom(zoom + delta); }
function toggleFull(img) {
    if (img.dataset.full === '1') {
        img.style.width = zoom + '%';
        img.style.cursor = 'zoom-in';
        img.dataset.full = '0';
    } else {
        img.style.width = '100%';
        img.style.cursor = 'zoom-out';
        img.dataset.full = '1';
        // temporarily override zoom panel for this img only
    }
}
</script>

<!-- ═══════════════════════════════════════════════════════════════════════════
     ITEM 1 – Step 1: Launch AIE for cropping
     ═══════════════════════════════════════════════════════════════════════════ -->
<h2>Step 1 – Crop an Invoice (AIE)</h2>
<div class="card">
  <p style="margin:0 0 10px">
    Use the AIE tool to draw crop rectangles on a PDF invoice.
    Crops are automatically saved to:<br>
    <code><?= htmlspecialchars($crops_dir_abs ?: 'path not found') ?></code>
  </p>
  <a href="/website/AIocr/AIE/enhancer_ai.php" class="btn btn-primary" target="_blank">
    Open AIE Crop Tool ↗
  </a>
  &nbsp;
  <span style="color:#777;font-size:13px">
    After cropping, refresh this page and select the new invoice below.
  </span>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     ITEMS 2-5 – Step 2: Select crops and process
     ═══════════════════════════════════════════════════════════════════════════ -->
<h2>Step 2 – Run Tesseract OCR</h2>
<div class="card">

<?php if (empty($crop_groups)): ?>
  <p style="color:#888">No crop groups found in:<br>
    <code><?= htmlspecialchars($crops_dir_abs ?: 'directory not found') ?></code>
  </p>
<?php else: ?>

<form method="POST" action="toe_start.php">

  <!-- Invoice selection -->
  <p style="margin:0 0 6px"><strong>Select invoice crops:</strong>
     <span style="color:#777;font-size:12px">(<?= count($crop_groups) ?> groups available)</span>
  </p>
  <div class="group-list" id="groupList">
    <?php foreach ($crop_groups as $prefix => $info): ?>
    <label class="group-item" onclick="selectGroup(this)">
      <input type="radio" name="invoice_prefix" value="<?= htmlspecialchars($prefix) ?>" required>
      <span class="group-name"><?= htmlspecialchars($prefix) ?></span>
      <span class="badges">
        <?= badge($info['has_date'],      'Date',  'Date?') ?>
        <?= badge($info['has_invoiceno'], 'InvNo', 'InvNo?') ?>
        <?= badge($info['has_total'],     'Total', 'Total?') ?>
        <?php if ($info['table_count'] > 0): ?>
          <span style='background:#0066cc;color:#fff;padding:2px 7px;border-radius:3px;font-size:.8em'>
            Tables:<?= $info['table_count'] ?>
          </span>
        <?php else: ?>
          <span style='background:#dc3545;color:#fff;padding:2px 7px;border-radius:3px;font-size:.8em'>No Table!</span>
        <?php endif; ?>
      </span>
    </label>
    <?php endforeach; ?>
  </div>

  <br>

  <!-- OCR options -->
  <p style="margin:0 0 8px"><strong>Options:</strong></p>
  <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">

    <div>
      <label style="font-weight:bold;display:block;margin-bottom:4px">Table PSM:</label>
      <label><input type="radio" name="table_psm" value="6"
             <?= (($cfg['table_psm_default'] ?? 6) == 6 ? 'checked' : '') ?>>
        PSM 6 – uniform block</label>
      <label><input type="radio" name="table_psm" value="11"
             <?= (($cfg['table_psm_default'] ?? 6) == 11 ? 'checked' : '') ?>>
        PSM 11 – sparse text</label>
    </div>

    <div>
      <label style="font-weight:bold;display:block;margin-bottom:4px">Column gap (px):</label>
      <input type="number" name="column_gap_px"
             value="<?= (int)($cfg['column_gap_px'] ?? 40) ?>"
             min="10" max="200" style="width:80px">
      <span style="color:#777;font-size:12px">Increase if columns merge; decrease if they split.</span>
    </div>

    <div>
      <label style="font-weight:bold;display:block;margin-bottom:4px">Pre-processing:</label>
      <label style="display:block;margin-bottom:3px">
        <input type="checkbox" name="preprocess_headers" value="1"
               <?= !empty($cfg['preprocess_headers_enabled']) ? 'checked' : '' ?>>
        Headers (Date / InvoiceNo / Total) — grayscale + normalize + 200% resize
      </label>
      <label style="display:block">
        <input type="checkbox" name="preprocess_tables" value="1"
               <?= !empty($cfg['preprocess_tables_enabled']) ? 'checked' : '' ?>>
        Tables — grayscale + normalize + unsharp
      </label>
    </div>

  </div>

  <br>
  <button type="submit" class="btn btn-success" style="font-size:15px;padding:9px 28px">
    ▶ Run Tesseract OCR
  </button>

</form>
<?php endif; ?>

</div><!-- /card -->

<!-- ── Software info ── -->
<div class="card" style="background:#f9f9f9;font-size:13px">
  <strong>Configuration:</strong><br>
  Tesseract: <code><?= htmlspecialchars($cfg['tesseract_path']) ?></code><br>
  ImageMagick: <code><?= htmlspecialchars($cfg['magick_path']) ?></code><br>
  Language: <code><?= htmlspecialchars($cfg['tesseract_lang']) ?></code>
  &nbsp;|&nbsp; Header PSM: <code><?= (int)($cfg['header_psm'] ?? 7) ?></code>
  &nbsp;|&nbsp; TSV debug: <code><?= htmlspecialchars($tsv_dir_abs ?: $cfg['tsv_debug_dir']) ?></code>
</div>

<script>
function selectGroup(el) {
    document.querySelectorAll('.group-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
}
// Pre-select any already-checked radio (on back/reload)
document.querySelectorAll('input[name=invoice_prefix]').forEach(r => {
    if (r.checked) r.closest('.group-item').classList.add('selected');
});
</script>
</body>
</html>
