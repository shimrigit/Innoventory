<?php
/**
 * TOE Core Processor  (Items 2 – 5)
 * ===================================
 * Modular, path-agnostic engine.  Receives crop directory, invoice prefix,
 * run-time options, and output directories.  Returns a result array.
 * Has no HTML output and no hard-coded paths.
 *
 * Items implemented:
 *   2. ImageMagick pre-processing + Tesseract (PSM 6/11) → TSV  for table crops
 *   3. Parse TSV → OCRjson table rows
 *   4. ImageMagick pre-processing + Tesseract (PSM 7)    → TSV  for header crops
 *   5. Extract invoice_number / invoice_date / invoice_total → save OCRjson
 *
 * TSV files are kept in $tsv_dir for debugging.
 */

require_once __DIR__ . '/toe_tsv_parser.php';

// ---------------------------------------------------------------------------
// Helper: pre-process one image with ImageMagick
// ---------------------------------------------------------------------------

/**
 * @param string $magick_path    Full path to "magick" executable
 * @param string $input_path     Source PNG
 * @param string $output_path    Destination PNG
 * @param string $args           ImageMagick arguments (trusted config string)
 * @param array  &$log           Message log (passed by reference)
 * @return bool  True if magick exited 0 and output file exists
 */
function toe_preprocess_image(
    string $magick_path,
    string $input_path,
    string $output_path,
    string $args,
    array  &$log
): bool {
    $cmd = escapeshellarg($magick_path)
         . ' ' . escapeshellarg($input_path)
         . ' ' . $args
         . ' ' . escapeshellarg($output_path)
         . ' 2>&1';

    exec($cmd, $out, $code);

    if ($code !== 0 || !file_exists($output_path)) {
        $log[] = "  ⚠ ImageMagick failed (exit {$code}): " . implode(' | ', $out);
        return false;
    }
    return true;
}

// ---------------------------------------------------------------------------
// Helper: run Tesseract, produce TSV
// ---------------------------------------------------------------------------

/**
 * @param string      $tesseract_path  Full path to "tesseract" executable
 * @param string      $input_path      Source PNG
 * @param string      $output_base     Output base path (Tesseract appends ".tsv")
 * @param string      $lang            Language string e.g. "heb+eng"
 * @param int         $psm             Page segmentation mode
 * @param array       &$log            Message log
 * @param string|null $tessdata_dir    Explicit tessdata directory (avoids TESSDATA_PREFIX env var)
 * @return array  ['success', 'tsv_path', 'cmd', 'output']
 */
function toe_run_tesseract(
    string  $tesseract_path,
    string  $input_path,
    string  $output_base,
    string  $lang,
    int     $psm,
    array   &$log,
    ?string $tessdata_dir = null,
    ?int    $oem = null
): array {
    // $lang is alphanumeric + '+', safe to embed directly
    $lang_safe = preg_replace('/[^A-Za-z0-9+_]/', '', $lang);

    $tessdata_flag = '';
    if (!empty($tessdata_dir)) {
        $tessdata_flag = ' --tessdata-dir ' . escapeshellarg($tessdata_dir);
    }

    $oem_flag = ($oem !== null) ? ' --oem ' . $oem : '';

    $cmd = escapeshellarg($tesseract_path)
         . ' ' . escapeshellarg($input_path)
         . ' ' . escapeshellarg($output_base)
         . ' -l ' . $lang_safe
         . ' --psm ' . $psm
         . $oem_flag
         . $tessdata_flag
         . ' tsv'
         . ' 2>&1';

    exec($cmd, $out, $code);

    $log[] = "   CMD: " . $cmd;
    if (!empty($out)) {
        $log[] = "   OUT: " . implode(' | ', $out);
    }

    $tsv_path = $output_base . '.tsv';
    $success  = ($code === 0 && file_exists($tsv_path) && filesize($tsv_path) > 0);

    if (!$success) {
        $log[] = "  ⚠ Tesseract failed (exit {$code})";
    }

    return [
        'success'  => $success,
        'tsv_path' => $tsv_path,
        'cmd'      => $cmd,
        'output'   => $out,
    ];
}

// ---------------------------------------------------------------------------
// Helper: locate crop files for an invoice prefix
// ---------------------------------------------------------------------------

/**
 * Scans $crops_dir for files whose name starts with $invoice_prefix followed
 * by a known suffix: _Date.png  _Invoiceno.png  _Total.png  _TableN.png
 * Versioned duplicates (_Date (1).png) are skipped automatically.
 *
 * @return array [
 *   'date'      => string|null,
 *   'invoiceno' => string|null,
 *   'total'     => string|null,
 *   'tables'    => string[]  (sorted by table number)
 * ]
 */
function toe_find_crops(string $crops_dir, string $invoice_prefix): array {
    $result = [
        'date'      => null,
        'invoiceno' => null,
        'total'     => null,
        'tables'    => [],
    ];

    $prefix_len = strlen($invoice_prefix);

    foreach (scandir($crops_dir) as $file) {
        if (substr($file, 0, $prefix_len) !== $invoice_prefix) continue;

        $suffix = substr($file, $prefix_len);

        if (strcasecmp($suffix, '_Date.png') === 0) {
            $result['date'] = $crops_dir . '/' . $file;
        } elseif (strcasecmp($suffix, '_Invoiceno.png') === 0) {
            $result['invoiceno'] = $crops_dir . '/' . $file;
        } elseif (strcasecmp($suffix, '_Total.png') === 0) {
            $result['total'] = $crops_dir . '/' . $file;
        } elseif (preg_match('/^_Table(\d+)\.png$/i', $suffix, $m)) {
            $result['tables'][(int)$m[1]] = $crops_dir . '/' . $file;
        }
        // Versioned duplicates like "_Date (1).png" do NOT match any case above → skipped
    }

    ksort($result['tables']);
    $result['tables'] = array_values($result['tables']);

    return $result;
}

// ---------------------------------------------------------------------------
// MAIN: process_invoice_toe()
// ---------------------------------------------------------------------------

/**
 * Core TOE processor.  Accepts any crop directory and output directory,
 * so it can be called from the test frame OR from the full Retailomatics
 * pipeline without modification.
 *
 * @param string $crops_dir       Directory that contains the PNG crop files
 * @param string $invoice_prefix  Invoice filename prefix  (e.g. "Tnuva_27122025_170237")
 * @param array  $options         Keys:
 *                                  tesseract_path         string
 *                                  magick_path            string
 *                                  tesseract_lang         string  (default "heb+eng")
 *                                  table_psm              int     (6 or 11, default 6)
 *                                  header_psm             int     (default 7)
 *                                  preprocess             bool
 *                                  preprocess_args_table  string  (ImageMagick args)
 *                                  preprocess_args_header string
 *                                  column_gap_px          int     (default 40)
 * @param string $ocr_dir         Directory where the OCRjson file will be saved
 * @param string $tsv_dir         Directory where TSV debug files will be saved
 *
 * @return array [
 *   'success'   => bool,
 *   'json_path' => string,   (absolute path)
 *   'json_file' => string,   (filename only)
 *   'data'      => array,    (the OCRjson array)
 *   'messages'  => string[], (info log)
 *   'errors'    => string[], (error log)
 *   'tsv_files' => string[], (TSV debug file paths)
 * ]
 */
function process_invoice_toe(
    string $crops_dir,
    string $invoice_prefix,
    array  $options,
    string $ocr_dir,
    string $tsv_dir
): array {
    $log      = [];
    $errors   = [];
    $tsv_files = [];

    // ── Options with defaults ────────────────────────────────────────────────
    $tesseract_path         = $options['tesseract_path'];
    $magick_path            = $options['magick_path'];
    $lang                   = $options['tesseract_lang']         ?? 'heb+eng';
    $oem                    = isset($options['tesseract_oem']) ? (int)$options['tesseract_oem'] : null;
    $tessdata_dir           = $options['tessdata_dir']           ?? null;
    $table_psm              = (int)($options['table_psm']        ?? 6);
    $header_psm             = (int)($options['header_psm']       ?? 7);
    $preprocess             = !empty($options['preprocess']);
    $args_table             = $options['preprocess_args_table']  ?? '-type Grayscale -normalize';
    $args_header            = $options['preprocess_args_header'] ?? '-type Grayscale -normalize -resize 200%';
    $gap_px                 = (int)($options['column_gap_px']    ?? 40);

    // ── Ensure directories exist ─────────────────────────────────────────────
    foreach ([$ocr_dir, $tsv_dir] as $dir) {
        if (!file_exists($dir)) mkdir($dir, 0777, true);
    }
    $temp_dir = $tsv_dir . '/temp';
    if ($preprocess && !file_exists($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }

    // ── Find crop files ──────────────────────────────────────────────────────
    $crops = toe_find_crops($crops_dir, $invoice_prefix);

    $log[] = "Crops found:"
           . " Date="      . ($crops['date']      ? 'YES' : 'NO')
           . " InvoiceNo=" . ($crops['invoiceno'] ? 'YES' : 'NO')
           . " Total="     . ($crops['total']     ? 'YES' : 'NO')
           . " Tables="    . count($crops['tables']);

    if (empty($crops['tables'])) {
        return [
            'success' => false,
            'errors'  => ["No table crops found for prefix: {$invoice_prefix}"],
            'messages'=> $log,
            'tsv_files'=> [],
        ];
    }

    // Timestamp and safe prefix for unique file naming
    $timestamp   = date('dmYHis');
    $safe_prefix = preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoice_prefix);

    // ════════════════════════════════════════════════════════════════════════
    // ITEMS 2 & 3 – Table crops → Tesseract TSV → table rows
    // ════════════════════════════════════════════════════════════════════════

    $table_tsv_contents = [];

    foreach ($crops['tables'] as $t_idx => $crop_path) {
        $table_num = $t_idx + 1;
        $log[] = "── Table{$table_num}: " . basename($crop_path);

        // Item 2a – ImageMagick pre-processing
        $ocr_input = $crop_path;
        if ($preprocess) {
            $pre_path = $temp_dir . '/' . $safe_prefix . "_Table{$table_num}_pre.png";
            $ok = toe_preprocess_image($magick_path, $crop_path, $pre_path, $args_table, $log);
            $ocr_input = $ok ? $pre_path : $crop_path;
            $log[] = $ok ? "   Pre-processed OK" : "   Using original (pre-process failed)";
        }

        // Item 2b – Tesseract → TSV
        $tsv_base   = $tsv_dir . '/' . $safe_prefix . "_Table{$table_num}_psm{$table_psm}_{$timestamp}";
        $tess       = toe_run_tesseract($tesseract_path, $ocr_input, $tsv_base, $lang, $table_psm, $log, $tessdata_dir, $oem);

        if (!$tess['success']) {
            $errors[] = "Tesseract failed for Table{$table_num}";
            continue;
        }

        $tsv_files[] = $tess['tsv_path'];
        $log[] = "   TSV → " . basename($tess['tsv_path']);

        $table_tsv_contents[] = file_get_contents($tess['tsv_path']);
    }

    // Item 3 – Parse TSV into table rows
    $table = parse_tsv_tables($table_tsv_contents, $gap_px);
    $num_cols = empty($table) ? 0 : (count($table[0]) - 1);
    $log[] = "Table built: " . count($table) . " rows × {$num_cols} columns  (gap_px={$gap_px})";

    // ════════════════════════════════════════════════════════════════════════
    // ITEMS 4 & 5 – Header crops → Tesseract TSV → scalar fields
    // ════════════════════════════════════════════════════════════════════════

    $invoice_number = '';
    $invoice_date   = '';
    $invoice_total  = null;

    $header_defs = [
        'invoiceno' => ['label' => 'InvoiceNo', 'path' => $crops['invoiceno']],
        'date'      => ['label' => 'Date',      'path' => $crops['date']],
        'total'     => ['label' => 'Total',     'path' => $crops['total']],
    ];

    foreach ($header_defs as $field => $info) {
        if (empty($info['path'])) {
            $log[] = "── {$info['label']}: no crop found – skipped";
            continue;
        }
        $log[] = "── {$info['label']}: " . basename($info['path']);

        // Item 4a – ImageMagick pre-processing
        $ocr_input = $info['path'];
        if ($preprocess) {
            $pre_path = $temp_dir . '/' . $safe_prefix . "_{$info['label']}_pre.png";
            $ok = toe_preprocess_image($magick_path, $info['path'], $pre_path, $args_header, $log);
            $ocr_input = $ok ? $pre_path : $info['path'];
            $log[] = $ok ? "   Pre-processed OK" : "   Using original (pre-process failed)";
        }

        // Item 4b – Tesseract → TSV  (PSM 7 = single text line)
        $tsv_base = $tsv_dir . '/' . $safe_prefix . "_{$info['label']}_psm{$header_psm}_{$timestamp}";
        $tess     = toe_run_tesseract($tesseract_path, $ocr_input, $tsv_base, $lang, $header_psm, $log, $tessdata_dir, $oem);

        if (!$tess['success']) {
            $errors[] = "Tesseract failed for {$info['label']}";
            continue;
        }

        $tsv_files[] = $tess['tsv_path'];
        $log[] = "   TSV → " . basename($tess['tsv_path']);

        // Item 5 – Extract value from TSV
        $raw = parse_tsv_header_value(file_get_contents($tess['tsv_path']));
        $log[] = "   Raw text: \"{$raw}\"";

        if ($field === 'invoiceno') {
            $invoice_number = $raw;
        } elseif ($field === 'date') {
            $invoice_date = $raw;
        } elseif ($field === 'total') {
            // Strip everything except digits, commas, and dots; treat comma as decimal separator
            $clean = preg_replace('/[^\d.,]/', '', $raw);
            // If both ',' and '.' present, assume '.' is thousands separator → remove it
            if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '.', $clean);
            }
            $invoice_total = $clean !== '' ? (float)$clean : null;
        }
    }

    // ── Build OCRjson ────────────────────────────────────────────────────────
    $ocr_data = [
        'invoice_number' => $invoice_number,
        'invoice_date'   => $invoice_date,
        'invoice_total'  => $invoice_total,
        'table'          => $table,
    ];

    // ── Save OCRjson ─────────────────────────────────────────────────────────
    $json_filename = 'TOE_OCRjson_' . $safe_prefix . '_' . $timestamp . '.json';
    $json_path     = rtrim($ocr_dir, '/\\') . '/' . $json_filename;

    $json_content = json_encode($ocr_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($json_path, $json_content);

    $log[] = "OCRjson saved → " . $json_filename;

    return [
        'success'   => true,
        'json_path' => $json_path,
        'json_file' => $json_filename,
        'data'      => $ocr_data,
        'messages'  => $log,
        'errors'    => $errors,
        'tsv_files' => $tsv_files,
    ];
}

// ---------------------------------------------------------------------------
// Standalone mode: when called directly as an HTTP endpoint (AJAX / curl)
// Reads POST params, loads toe_config.json, calls process_invoice_toe()
// ---------------------------------------------------------------------------
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $invoice_prefix = trim($_POST['invoice_prefix'] ?? '');
    if ($invoice_prefix === '') {
        echo json_encode(['error' => 'invoice_prefix is required']);
        exit;
    }

    $cfg_path = __DIR__ . '/toe_config.json';
    if (!file_exists($cfg_path)) {
        echo json_encode(['error' => 'toe_config.json not found']);
        exit;
    }
    $cfg = json_decode(file_get_contents($cfg_path), true);

    $crops_dir = realpath(__DIR__ . '/' . $cfg['crops_dir_test']);
    $ocr_dir   = realpath(__DIR__ . '/' . $cfg['ocr_extracted_dir_test']);
    $tsv_dir   = __DIR__ . '/' . $cfg['tsv_debug_dir'];

    $options = [
        'tesseract_path'         => $cfg['tesseract_path'],
        'magick_path'            => $cfg['magick_path'],
        'tesseract_lang'         => $cfg['tesseract_lang'],
        'tesseract_oem'          => isset($cfg['tesseract_oem']) ? (int)$cfg['tesseract_oem'] : null,
        'tessdata_dir'           => $cfg['tessdata_dir'] ?? null,
        'table_psm'              => (int)($_POST['table_psm']   ?? $cfg['table_psm_default']),
        'header_psm'             => (int)($cfg['header_psm']    ?? 7),
        'preprocess'             => (bool)($_POST['preprocess'] ?? $cfg['preprocess_enabled']),
        'preprocess_args_table'  => $cfg['preprocess_args_table'],
        'preprocess_args_header' => $cfg['preprocess_args_header'],
        'column_gap_px'          => (int)($_POST['column_gap_px'] ?? $cfg['column_gap_px']),
    ];

    $result = process_invoice_toe($crops_dir, $invoice_prefix, $options, $ocr_dir, $tsv_dir);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
