<?php
/**
 * TOE TSV Parser
 * Pure functions for parsing Tesseract TSV output into structured OCR data.
 * No I/O or HTML output - only data transformation.
 */

/**
 * Parse Tesseract TSV output into an array of word records.
 * Returns only level-5 (word-level) entries with valid text.
 *
 * @param string $tsv_content  Raw TSV string from Tesseract
 * @return array  Word records: [block_num, par_num, line_num, word_num,
 *                               left, top, width, height, conf, text, cx, cy]
 */
function parse_tsv_words(string $tsv_content): array {
    $lines = explode("\n", trim($tsv_content));
    if (count($lines) < 2) return [];

    // First line is header
    array_shift($lines);

    $words = [];
    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '') continue;

        $parts = explode("\t", $line);
        if (count($parts) < 12) continue;

        $level = (int)$parts[0];
        if ($level !== 5) continue;          // word level only

        $conf = (int)$parts[10];
        if ($conf < 0) continue;             // -1 = structure marker, not text

        // Text may contain tabs in theory; join anything beyond column 11
        $text = count($parts) > 12
            ? implode(' ', array_slice($parts, 11))
            : $parts[11];
        $text = trim($text);
        if ($text === '') continue;

        $left   = (int)$parts[6];
        $top    = (int)$parts[7];
        $width  = (int)$parts[8];
        $height = (int)$parts[9];

        $words[] = [
            'block_num' => (int)$parts[2],
            'par_num'   => (int)$parts[3],
            'line_num'  => (int)$parts[4],
            'word_num'  => (int)$parts[5],
            'left'      => $left,
            'top'       => $top,
            'width'     => $width,
            'height'    => $height,
            'conf'      => $conf,
            'text'      => $text,
            'cx'        => $left + intdiv($width, 2),
            'cy'        => $top  + intdiv($height, 2),
        ];
    }

    return $words;
}

/**
 * Extract a single text value from a header crop TSV.
 * Used for Date, InvoiceNo, and Total crops (PSM 7 - single line).
 * Words are sorted left-to-right and joined with a space.
 *
 * @param string $tsv_content  Raw TSV from Tesseract
 * @return string  Concatenated text value
 */
function parse_tsv_header_value(string $tsv_content): string {
    $words = parse_tsv_words($tsv_content);
    if (empty($words)) return '';

    usort($words, fn($a, $b) => $a['left'] <=> $b['left']);

    return implode(' ', array_column($words, 'text'));
}

/**
 * Detect columns as definitions {x_min, x_max, center}.
 *
 * Algorithm (ported from GOE geometry engine, extended with anchor filtering):
 *
 *  Phase A – Anchor filtering (removes false-boundary noise)
 *    1. Strip separator-character artifacts (|, —, –, =) – OCR noise from
 *       table lines that bridges gaps between real columns.
 *    2. Quantise each word's X-center into bands of (medW / 2) pixels.
 *    3. Count distinct Tesseract lines per band.  A band is an "anchor" when
 *       ≥ 25% of all lines have a word there (min 2).  This keeps column
 *       pillars (prices, quantities, "חפיסה") and drops product-name words
 *       that appear at a different X on every row, as well as inline numbers
 *       like "5%" in "גבינה 5%" which are also not positionally consistent.
 *
 *  Phase B – GOE-style median-width clustering (self-adaptive, no fixed px)
 *    4. Compute median word width of anchor words → medW.
 *    5. Sort anchor X-centers and cluster consecutive values within
 *       sameColThreshold = 0.6 × medW.  Each cluster becomes one column center.
 *    6. Detect "text column" to the RIGHT of the last anchor cluster:
 *       if ≥ 25% of lines have words past (last_center + medW), those words
 *       form an additional column (e.g. product-name column in RTL invoices).
 *
 *  Phase C – Column definitions with X-overlap boundaries (GOE approach)
 *    7. Convert cluster centers to {x_min, x_max, center} ranges; the first
 *       column starts at the global left edge and the last ends at the right edge.
 *
 * @param array $words       Word records from parse_tsv_words()
 * @param int   $min_gap_px  Floor: never merge columns closer than this (px)
 * @return array  [['x_min'=>float, 'x_max'=>float, 'center'=>float], ...]
 */
function detect_column_boundaries(array $words, int $min_gap_px = 10): array {
    if (count($words) < 2) return [];

    // ── Phase A-1: strip separator artifacts ─────────────────────────────────
    $words = array_values(array_filter($words, function ($w) {
        return !preg_match('/^[|—–_=]+$/', $w['text']);
    }));
    if (count($words) < 2) return [];

    // ── Phase A-2/3: band-coverage consistency map ────────────────────────────
    // Use a provisional band_width based on rough word width before knowing medW
    $widths_all = array_map(fn($w) => $w['width'], $words);
    sort($widths_all);
    $n = count($widths_all);
    $medW_all = ($n % 2 === 1)
        ? (float)$widths_all[intdiv($n, 2)]
        : ((float)$widths_all[intdiv($n, 2) - 1] + (float)$widths_all[intdiv($n, 2)]) / 2.0;
    $band_width = max(1, (int)round($medW_all / 2.0));

    $band_lines = [];
    foreach ($words as $w) {
        $band     = (int)round($w['cx'] / $band_width) * $band_width;
        $line_key = "{$w['block_num']}_{$w['par_num']}_{$w['line_num']}";
        $band_lines[$band][$line_key] = true;
    }

    $all_line_keys = [];
    foreach ($words as $w) {
        $all_line_keys["{$w['block_num']}_{$w['par_num']}_{$w['line_num']}"] = true;
    }
    $num_lines = count($all_line_keys);
    $min_count = max(2, (int)ceil($num_lines * 0.25));

    $anchor_words = array_values(array_filter($words, function ($w) use ($band_width, $band_lines, $min_count) {
        $band = (int)round($w['cx'] / $band_width) * $band_width;
        return count($band_lines[$band] ?? []) >= $min_count;
    }));

    if (empty($anchor_words)) return [];

    // ── Phase B: GOE-style median-width clustering ────────────────────────────
    $anchor_widths = array_map(fn($w) => $w['width'], $anchor_words);
    sort($anchor_widths);
    $na = count($anchor_widths);
    $medW = ($na % 2 === 1)
        ? (float)$anchor_widths[intdiv($na, 2)]
        : ((float)$anchor_widths[intdiv($na, 2) - 1] + (float)$anchor_widths[intdiv($na, 2)]) / 2.0;

    $sameColThreshold = max((float)$min_gap_px, 0.6 * $medW);

    $sorted_cx = array_column($anchor_words, 'cx');
    sort($sorted_cx);

    $clusters = [];
    $cluster  = [$sorted_cx[0]];
    for ($i = 1; $i < count($sorted_cx); $i++) {
        if (($sorted_cx[$i] - $sorted_cx[$i - 1]) <= $sameColThreshold) {
            $cluster[] = $sorted_cx[$i];
        } else {
            $clusters[] = $cluster;
            $cluster = [$sorted_cx[$i]];
        }
    }
    $clusters[] = $cluster;

    $column_centers = array_map(fn($c) => array_sum($c) / count($c), $clusters);

    // ── Phase B-6: detect unanchored text column to the right ────────────────
    // (e.g. product-name column in RTL invoices: words spread across varying X
    // positions so they fail the consistency check, yet they cover many rows)
    $last_center    = max($column_centers);
    $right_threshold = $last_center + max($sameColThreshold, $medW);

    $right_line_keys = [];
    $right_cx_vals   = [];
    foreach ($words as $w) {
        if ($w['cx'] > $right_threshold) {
            $lk = "{$w['block_num']}_{$w['par_num']}_{$w['line_num']}";
            $right_line_keys[$lk] = true;
            $right_cx_vals[]      = $w['cx'];
        }
    }
    if (count($right_line_keys) >= $min_count && !empty($right_cx_vals)) {
        $column_centers[] = array_sum($right_cx_vals) / count($right_cx_vals);
    }

    sort($column_centers);

    // ── Phase C: build column definitions {x_min, x_max, center} ─────────────
    $global_x1 = min(array_map(fn($w) => $w['left'], $words));
    $global_x2 = max(array_map(fn($w) => $w['left'] + $w['width'], $words));

    $columns = [];
    for ($i = 0; $i < count($column_centers); $i++) {
        $center = $column_centers[$i];
        $xMin   = ($i === 0)
            ? $global_x1
            : ($column_centers[$i - 1] + $center) / 2.0;
        $xMax   = ($i === count($column_centers) - 1)
            ? $global_x2
            : ($center + $column_centers[$i + 1]) / 2.0;
        $columns[] = ['x_min' => $xMin, 'x_max' => $xMax, 'center' => $center];
    }

    return $columns;
}

/**
 * Assign a word to a column using X-overlap (GOE approach).
 * More robust than midpoint comparison for words near column edges.
 * Falls back to closest column center when no overlap exists.
 *
 * @param array $word     Word record (needs 'left', 'width', 'cx')
 * @param array $columns  Column definitions from detect_column_boundaries()
 * @return int  1-based column number
 */
function assign_word_to_column(array $word, array $columns): int {
    $x1 = (float)$word['left'];
    $x2 = (float)($word['left'] + $word['width']);

    $best_col  = 1;
    $max_overlap = -1.0;

    foreach ($columns as $i => $col) {
        $overlap = max(0.0, min($x2, $col['x_max']) - max($x1, $col['x_min']));
        if ($overlap > $max_overlap) {
            $max_overlap = $overlap;
            $best_col    = $i + 1;
        }
    }

    // Fallback: no overlap → use closest column center
    if ($max_overlap <= 0.0) {
        $cx       = $word['cx'];
        $best_dist = PHP_FLOAT_MAX;
        foreach ($columns as $i => $col) {
            $dist = abs($cx - $col['center']);
            if ($dist < $best_dist) {
                $best_dist = $dist;
                $best_col  = $i + 1;
            }
        }
    }

    return $best_col;
}

/**
 * Build OCRjson table rows from words using detected column definitions.
 * Words are grouped by Tesseract line key (block+par+line), sorted top-to-bottom,
 * then each word is assigned to a column by X-overlap.
 *
 * @param array $words        Word records from parse_tsv_words()
 * @param array $columns      Column definitions from detect_column_boundaries()
 * @param int   $start_index  Row index to start from (for multi-table continuity)
 * @return array  [{"index": 1, "1": "...", "2": "...", ...}, ...]
 */
function build_table_rows(array $words, array $columns, int $start_index = 1): array {
    if (empty($words)) return [];

    $num_cols = count($columns);

    // Group words by Tesseract line identity
    $row_groups = [];
    foreach ($words as $word) {
        $key = "{$word['block_num']}_{$word['par_num']}_{$word['line_num']}";
        if (!isset($row_groups[$key])) {
            $row_groups[$key] = ['top' => $word['top'], 'words' => []];
        } else {
            $row_groups[$key]['top'] = min($row_groups[$key]['top'], $word['top']);
        }
        $row_groups[$key]['words'][] = $word;
    }

    // Sort row groups top-to-bottom
    uasort($row_groups, fn($a, $b) => $a['top'] <=> $b['top']);

    $rows = [];
    $idx  = $start_index;

    foreach ($row_groups as $rg) {
        // Sort words left-to-right within the row
        $rw = $rg['words'];
        usort($rw, fn($a, $b) => $a['left'] <=> $b['left']);

        // Initialize row with empty columns
        $row = ['index' => $idx];
        for ($c = 1; $c <= $num_cols; $c++) {
            $row[(string)$c] = '';
        }

        // Assign each word to its column by X-overlap
        foreach ($rw as $word) {
            $col = assign_word_to_column($word, $columns);
            $key = (string)$col;
            $row[$key] = $row[$key] === ''
                ? $word['text']
                : $row[$key] . ' ' . $word['text'];
        }

        $rows[] = $row;
        $idx++;
    }

    return $rows;
}

/**
 * Parse one or more table TSV strings into a combined OCRjson table array.
 *
 * Column definitions are detected from ALL table words combined so that
 * column numbering stays consistent across multiple table images.
 *
 * @param array $tsv_contents  Ordered TSV strings [Table1, Table2, ...]
 * @param int   $min_gap_px    Floor gap: never merge columns closer than this
 * @return array  Combined table rows with continuous indexing
 */
function parse_tsv_tables(array $tsv_contents, int $min_gap_px = 10): array {
    if (empty($tsv_contents)) return [];

    // First pass: parse all word sets
    $word_sets  = [];
    $all_words  = [];
    foreach ($tsv_contents as $tsv) {
        $ws          = parse_tsv_words($tsv);
        $word_sets[] = $ws;
        $all_words   = array_merge($all_words, $ws);
    }

    if (empty($all_words)) return [];

    // Detect columns from all tables combined
    $columns = detect_column_boundaries($all_words, $min_gap_px);

    // Second pass: build rows with continuous indexing
    $combined = [];
    $next_idx = 1;
    foreach ($word_sets as $ws) {
        if (empty($ws)) continue;
        $rows      = build_table_rows($ws, $columns, $next_idx);
        $combined  = array_merge($combined, $rows);
        $next_idx += count($rows);
    }

    return $combined;
}
