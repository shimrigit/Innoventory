<?php
/**
 * Geometry Engine for Invoice Tables
 *
 * Input: $raw (decoded JSON from model) with:
 *  - invoice_number, invoice_date, invoice_total
 *  - tables[]: each { z, image_width, image_height, tokens[]: {text,bbox[]} }
 *
 * Output: final JSON structure with table rows:
 *  - each row has "index" and columns "1".."N" with no gaps; empty cells = "".
 */

// -------------------------- Utilities --------------------------

function median(array $arr): float {
    $arr = array_values(array_filter($arr, fn($v) => is_numeric($v)));
    $n = count($arr);
    if ($n === 0) return 0.0;
    sort($arr);
    $mid = intdiv($n, 2);
    if ($n % 2 === 1) return (float)$arr[$mid];
    return ((float)$arr[$mid - 1] + (float)$arr[$mid]) / 2.0;
}

function mean(array $arr): float {
    $n = count($arr);
    if ($n === 0) return 0.0;
    return array_sum($arr) / $n;
}

function normalize_tokens(array $tokens): array {
    $out = [];
    foreach ($tokens as $t) {
        if (!isset($t['bbox']) || !is_array($t['bbox']) || count($t['bbox']) !== 4) continue;
        $text = isset($t['text']) ? trim((string)$t['text']) : '';
        if ($text === '') continue;

        [$x1,$y1,$x2,$y2] = $t['bbox'];
        $x1 = (float)$x1; $y1 = (float)$y1; $x2 = (float)$x2; $y2 = (float)$y2;

        $w = max(1.0, $x2 - $x1);
        $h = max(1.0, $y2 - $y1);

        $out[] = [
            'text' => $text,
            'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2,
            'cx' => ($x1 + $x2) / 2.0,
            'cy' => ($y1 + $y2) / 2.0,
            'w'  => $w,
            'h'  => $h,
        ];
    }
    return $out;
}

/**
 * Group tokens into rows by Y (center y) proximity.
 * Returns array of rows, each row = array of tokens.
 */
function group_into_rows(array $tokens): array {
    if (count($tokens) === 0) return [];

    usort($tokens, fn($a,$b) => $a['cy'] <=> $b['cy']);

    $medH = median(array_map(fn($t) => $t['h'], $tokens));
    $rowThreshold = max(2.0, 0.60 * $medH); // tune if needed

    $rows = [];
    $current = [];
    $currentMean = null;

    foreach ($tokens as $t) {
        if (count($current) === 0) {
            $current = [$t];
            $currentMean = $t['cy'];
            continue;
        }
        if (abs($t['cy'] - $currentMean) <= $rowThreshold) {
            $current[] = $t;
            $currentMean = mean(array_map(fn($x) => $x['cy'], $current));
        } else {
            $rows[] = $current;
            $current = [$t];
            $currentMean = $t['cy'];
        }
    }
    if (count($current)) $rows[] = $current;

    // sort each row left->right for stability
    foreach ($rows as &$r) {
        usort($r, fn($a,$b) => $a['cx'] <=> $b['cx']);
    }
    unset($r);

    return $rows;
}

/**
 * Detect column centers (cx clusters) from tokens.
 * Returns sorted array of column centers (floats).
 */
function detect_columns(array $tokens): array {
    if (count($tokens) === 0) return [];

    $cxs = array_map(fn($t) => $t['cx'], $tokens);
    sort($cxs);

    $medW = median(array_map(fn($t) => $t['w'], $tokens));
    $colThreshold = max(4.0, 0.80 * $medW); // tune if needed

    $centers = [];
    $cluster = [];

    foreach ($cxs as $x) {
        if (count($cluster) === 0) {
            $cluster = [$x];
            continue;
        }
        $m = mean($cluster);
        if (abs($x - $m) <= $colThreshold) {
            $cluster[] = $x;
        } else {
            $centers[] = mean($cluster);
            $cluster = [$x];
        }
    }
    if (count($cluster)) $centers[] = mean($cluster);

    sort($centers);

    // Optional: merge too-close centers to avoid "column explosion"
    $merged = [];
    foreach ($centers as $c) {
        if (count($merged) === 0) { $merged[] = $c; continue; }
        $last = $merged[count($merged)-1];
        if (abs($c - $last) < ($colThreshold * 0.55)) {
            // merge by averaging
            $merged[count($merged)-1] = ($last + $c) / 2.0;
        } else {
            $merged[] = $c;
        }
    }

    return $merged;
}

function nearest_col_index(float $cx, array $colCenters): int {
    $best = 0;
    $bestD = INF;
    foreach ($colCenters as $i => $c) {
        $d = abs($cx - $c);
        if ($d < $bestD) { $bestD = $d; $best = $i; }
    }
    return $best;
}

/**
 * Build row objects { "1": "...", ..., "N": "..." } from row tokens using fixed col centers.
 * Joins multiple tokens in same cell with a space.
 */
function rows_to_grid(array $rows, array $colCenters): array {
    $N = count($colCenters);
    if ($N === 0) return [];

    $gridRows = [];
    foreach ($rows as $r) {
        $cells = array_fill(0, $N, []);
        foreach ($r as $t) {
            $ci = nearest_col_index($t['cx'], $colCenters);
            $cells[$ci][] = $t['text'];
        }
        $rowObj = [];
        for ($c=0; $c<$N; $c++) {
            $rowObj[(string)($c+1)] = count($cells[$c]) ? implode(' ', $cells[$c]) : "";
        }
        $gridRows[] = $rowObj;
    }
    return $gridRows;
}

/**
 * Compute a simple signature to detect duplicated overlap row between table parts.
 * (We assume last row of part Z may repeat as first row of part Z+1.)
 */
function row_signature(array $rowObj): string {
    // stable order: 1..N
    ksort($rowObj, SORT_NATURAL);
    return implode('|', array_map(fn($v) => trim((string)$v), $rowObj));
}

/**
 * Merge table parts sequentially, removing one-row overlap if duplicated.
 */
function merge_table_parts(array $partsGridRows): array {
    $merged = [];
    foreach ($partsGridRows as $partRows) {
        if (count($partRows) === 0) continue;

        if (count($merged) > 0) {
            $lastSig = row_signature($merged[count($merged)-1]);
            $firstSig = row_signature($partRows[0]);

            if ($lastSig === $firstSig) {
                // drop the overlapping first row of the new part
                array_shift($partRows);
            }
        }

        foreach ($partRows as $r) $merged[] = $r;
    }
    return $merged;
}

// -------------------------- Main builder --------------------------

/**
 * Build final invoice JSON (with geometry engine applied)
 */
function build_invoice_json(array $raw): array {
    $invoiceNumber = $raw['invoice_number'] ?? '';
    $invoiceDate   = $raw['invoice_date'] ?? '';
    $invoiceTotal  = $raw['invoice_total'] ?? null;

    $tables = $raw['tables'] ?? [];
    if (!is_array($tables)) $tables = [];

    // Sort by z ascending
    usort($tables, fn($a,$b) => ((int)($a['z'] ?? 0)) <=> ((int)($b['z'] ?? 0)));

    // Normalize tokens for each table part; also collect tokens from first part for column detection
    $normParts = [];
    foreach ($tables as $part) {
        $z = (int)($part['z'] ?? 0);
        $tokens = normalize_tokens($part['tokens'] ?? []);
        $normParts[] = ['z' => $z, 'tokens' => $tokens];
    }

    if (count($normParts) === 0) {
        return [
            'invoice_number' => (string)$invoiceNumber,
            'invoice_date'   => (string)$invoiceDate,
            'invoice_total'  => is_numeric($invoiceTotal) ? (float)$invoiceTotal : $invoiceTotal,
            'table'          => [],
        ];
    }

    // Detect columns from the FIRST table image tokens (most stable)
    // If first part is too sparse, fallback to all tokens.
    $colSourceTokens = $normParts[0]['tokens'];
    if (count($colSourceTokens) < 10) {
        $all = [];
        foreach ($normParts as $p) $all = array_merge($all, $p['tokens']);
        $colSourceTokens = $all;
    }
    $colCenters = detect_columns($colSourceTokens);
    $colCount = count($colCenters);

    // Build grid rows for each part using same columns
    $partsGrid = [];
    foreach ($normParts as $p) {
        $rows = group_into_rows($p['tokens']);
        $gridRows = rows_to_grid($rows, $colCenters);

        // Optional: remove obviously empty rows (all "" across columns)
        $gridRows = array_values(array_filter($gridRows, function($r) {
            foreach ($r as $v) {
                if (trim((string)$v) !== '') return true;
            }
            return false;
        }));

        $partsGrid[] = $gridRows;
    }

    // Merge parts; remove 1-row overlap if duplicated
    $mergedRows = merge_table_parts($partsGrid);

    // Add sequential "index"
    $finalTable = [];
    $idx = 1;
    foreach ($mergedRows as $r) {
        $rowObj = ['index' => $idx];
        // ensure all columns exist 1..N with no gaps
        for ($c=1; $c<=$colCount; $c++) {
            $k = (string)$c;
            $rowObj[$k] = isset($r[$k]) ? (string)$r[$k] : "";
        }
        $finalTable[] = $rowObj;
        $idx++;
    }

    return [
        'invoice_number' => (string)$invoiceNumber,
        'invoice_date'   => (string)$invoiceDate,
        'invoice_total'  => is_numeric($invoiceTotal) ? (float)$invoiceTotal : $invoiceTotal,
        'table'          => $finalTable,
    ];
}
