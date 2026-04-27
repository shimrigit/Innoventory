<?php

/**
 * Auto-detects the JSON-to-Excel column mapping from invoice table data.
 *
 * Every step always runs regardless of whether earlier steps succeeded.
 * When a step cannot auto-detect a field, the value from $fallbackMapping
 * (suppliers.json jsonToOcrSanity) is used for that field only.
 *
 * $notices is populated with one line per field:
 *   ✅ detected automatically
 *   ⚠️ not detected — fallback column used
 *   ❌ not detected and no fallback configured
 *
 * Returns the best mapping that could be built (auto + fallback).
 * The caller is responsible for checking that all required fields are present.
 */
function detectJsonMapping(
    array   $tableData,
    string  $sanityMethod,
    array  &$notices       = [],
    ?array  $fallbackMapping = null
): array {
    $notices  = [];
    $mapping  = [];
    $assigned = [];

    if (count($tableData) < 2) {
        $notices[] = "❌ All steps skipped: too few data rows (" . count($tableData) . ")";
        return $mapping;
    }

    $valuesByKey = [];
    foreach ($tableData as $row) {
        foreach ($row as $k => $v) {
            if ($k === 'index') continue;
            $valuesByKey[(string)$k][] = (string)$v;
        }
    }

    if (empty($valuesByKey)) {
        $notices[] = "❌ All steps skipped: no column data found in table rows";
        return $mapping;
    }

    $allKeys   = array_keys($valuesByKey);
    $totalRows = count($tableData);

    // Use fallback for a field that could not be auto-detected.
    // Marks the fallback column as assigned so later steps don't reuse it.
    $applyFallback = function (string $field) use ($fallbackMapping, &$mapping, &$assigned, &$notices): void {
        if ($fallbackMapping !== null && isset($fallbackMapping[$field])) {
            $col = $fallbackMapping[$field];
            $mapping[$field]        = $col;
            $assigned[(string)$col] = $field;
            $notices[] = "⚠️ {$field}: not auto-detected — using fallback (column {$col})";
        } else {
            $notices[] = "❌ {$field}: not auto-detected and no fallback configured";
        }
    };

    // ── Step 1: ItemName ──────────────────────────────────────────────────────
    // Highest density of Unicode letters (Hebrew, Latin, etc.) wins.
    $scores = [];
    foreach ($allKeys as $k) {
        $totalLetters = 0; $totalChars = 0; $cnt = 0;
        foreach ($valuesByKey[$k] as $v) {
            if ($v === '') continue;
            $cnt++;
            $len = mb_strlen($v);
            $totalChars += $len;
            preg_match_all('/\p{L}/u', $v, $m);
            $totalLetters += count($m[0]);
        }
        $letterFraction = $totalChars > 0 ? $totalLetters / $totalChars : 0;
        $avgLen         = $cnt       > 0 ? $totalChars  / $cnt         : 0;
        $scores[$k] = $letterFraction * $avgLen;
    }
    arsort($scores);
    $best = array_key_first($scores);
    $totalLettersCheck = 0; $totalCharsCheck = 0;
    foreach ($valuesByKey[$best] as $v) {
        $totalCharsCheck += mb_strlen($v);
        preg_match_all('/\p{L}/u', $v, $m);
        $totalLettersCheck += count($m[0]);
    }
    if ($totalCharsCheck > 0 && ($totalLettersCheck / $totalCharsCheck) >= 0.3) {
        $assigned[$best]     = 'ItemName';
        $mapping['ItemName'] = (int)$best;
        $notices[] = "✅ ItemName: auto-detected (column {$best})";
    } else {
        $applyFallback('ItemName');
    }

    // ── Step 2: Barcode ───────────────────────────────────────────────────────
    // Longest pure-digit run per value; requires 6–14 digits and ≥30% row hit-rate.
    $scores = [];
    foreach ($allKeys as $k) {
        if (isset($assigned[$k])) continue;
        $hit = 0; $totalLen = 0;
        foreach ($valuesByKey[$k] as $v) {
            preg_match_all('/\d+/', $v, $runs);
            $longest = '';
            foreach ($runs[0] as $run) {
                if (strlen($run) > strlen($longest)) $longest = $run;
            }
            if (strlen($longest) >= 6 && strlen($longest) <= 14) {
                $hit++;
                $totalLen += strlen($longest);
            }
        }
        $scores[$k] = ($hit > 0) ? ($hit / $totalRows) * ($totalLen / $hit) : 0;
    }
    $barcodeDetected = false;
    if (!empty($scores)) {
        arsort($scores);
        $best     = array_key_first($scores);
        $hitCheck = 0;
        foreach ($valuesByKey[$best] as $v) {
            preg_match_all('/\d+/', $v, $runs);
            $longest = '';
            foreach ($runs[0] as $run) {
                if (strlen($run) > strlen($longest)) $longest = $run;
            }
            if (strlen($longest) >= 6 && strlen($longest) <= 14) $hitCheck++;
        }
        if ($totalRows > 0 && ($hitCheck / $totalRows) >= 0.3) {
            $assigned[$best]    = 'Barcode';
            $mapping['Barcode'] = (int)$best;
            $notices[] = "✅ Barcode: auto-detected (column {$best})";
            $barcodeDetected = true;
        }
    }
    if (!$barcodeDetected) {
        $applyFallback('Barcode');
    }

    // ── Step 3: Discount fields (Discount1 / Discount2 methods only) ──────────
    if (in_array($sanityMethod, ['Discount1', 'Discount2'], true)) {
        $discScores = [];
        foreach ($allKeys as $k) {
            if (isset($assigned[$k])) continue;
            $zero = 0; $inRange = 0; $hasPct = 0; $cnt = 0;
            foreach ($valuesByKey[$k] as $v) {
                if ($v === '') continue;
                $cnt++;
                if (strpos($v, '%') !== false) $hasPct++;
                $num = (float)str_replace(['%', ' '], '', $v);
                if (abs($num) < 0.01)         $zero++;
                if ($num >= 0 && $num <= 100) $inRange++;
            }
            if ($cnt === 0) continue;
            $discScores[$k] = 0.5 * ($zero / $cnt)
                            + 0.3 * ($inRange / $cnt)
                            + 0.2 * ($hasPct / $cnt);
        }
        arsort($discScores);
        $dKeys = array_keys($discScores);

        if (isset($dKeys[0]) && $discScores[$dKeys[0]] >= 0.3) {
            $assigned[$dKeys[0]]   = 'Discount1';
            $mapping['Discount1']  = (int)$dKeys[0];
            $notices[] = "✅ Discount1: auto-detected (column {$dKeys[0]})";
        } else {
            $applyFallback('Discount1');
        }

        if ($sanityMethod === 'Discount2') {
            if (isset($dKeys[1]) && $discScores[$dKeys[1]] >= 0.2) {
                $assigned[$dKeys[1]]  = 'Discount2';
                $mapping['Discount2'] = (int)$dKeys[1];
                $notices[] = "✅ Discount2: auto-detected (column {$dKeys[1]})";
            } else {
                $applyFallback('Discount2');
            }
        }
    }

    // ── Step 4 / 5: Qty, UnitPrice, LineTotal ─────────────────────────────────

    // Collect remaining columns that are mostly numeric.
    // preg_match handles values like "20.00 יח'" that start with a digit but
    // fail is_numeric() due to trailing Hebrew unit markers.
    $numKeys = [];
    foreach ($allKeys as $k) {
        if (isset($assigned[$k])) continue;
        $numericCount = 0;
        foreach ($valuesByKey[$k] as $v) {
            if (trim($v) !== '' && preg_match('/^-?\d/', trim($v))) $numericCount++;
        }
        if ($totalRows > 0 && ($numericCount / $totalRows) >= 0.5) {
            $numKeys[] = $k;
        }
    }

    if ($sanityMethod === 'Simple') {
        // ── Step 4 (Simple): most-integer column = Qty; next = UnitPrice ─────
        if (!empty($numKeys)) {
            $integrality = [];
            foreach ($numKeys as $k) {
                $intCount = 0; $cnt = 0;
                foreach ($valuesByKey[$k] as $v) {
                    $n = abs((float)$v); $cnt++;
                    if (abs($n - round($n)) < 0.01) $intCount++;
                }
                $integrality[$k] = $cnt > 0 ? $intCount / $cnt : 0;
            }
            arsort($integrality);
            $qtyK = array_key_first($integrality);
            $assigned[$qtyK] = 'Qty';
            $mapping['Qty']  = (int)$qtyK;
            $notices[] = "✅ Qty: auto-detected (column {$qtyK})";

            foreach ($numKeys as $k) {
                if (!isset($assigned[$k])) {
                    $assigned[$k]      = 'UnitPrice';
                    $mapping['UnitPrice'] = (int)$k;
                    $notices[] = "✅ UnitPrice: auto-detected (column {$k})";
                    break;
                }
            }
        } else {
            $applyFallback('Qty');
            // UnitPrice is optional for Simple
            if ($fallbackMapping !== null && isset($fallbackMapping['UnitPrice'])) {
                $col = $fallbackMapping['UnitPrice'];
                $mapping['UnitPrice']   = $col;
                $assigned[(string)$col] = 'UnitPrice';
                $notices[] = "⚠️ UnitPrice: not auto-detected — using fallback (column {$col})";
            }
        }

    } elseif (count($numKeys) >= 3) {
        // ── Step 5: find (ltK, qtyK, upK) where ltK ≈ qtyK × upK ────────────

        // Pre-filter: columns with <20% decimal-point values are treated as
        // code/sequence columns and excluded from the primary search.
        $decimalKeys = [];
        foreach ($numKeys as $k) {
            $decimalCount = 0;
            foreach ($valuesByKey[$k] as $v) {
                if (strpos($v, '.') !== false) $decimalCount++;
            }
            if ($totalRows > 0 && ($decimalCount / $totalRows) >= 0.20) {
                $decimalKeys[] = $k;
            }
        }

        $ltK = null; $qtyK = null; $upK = null; $bestScore = -1;

        $searchKeys = !empty($decimalKeys) ? $decimalKeys : $numKeys;
        if (count($searchKeys) >= 3) {
            $sk = count($searchKeys);
            $colMeans = [];
            foreach ($searchKeys as $k) {
                $sum = 0; $cnt = 0;
                foreach ($valuesByKey[$k] as $v) {
                    $sum += abs((float)str_replace([' ', '%'], '', $v)); $cnt++;
                }
                $colMeans[$k] = $cnt > 0 ? $sum / $cnt : 0;
            }
            for ($i = 0; $i < $sk; $i++) {
                for ($j = 0; $j < $sk; $j++) {
                    if ($j === $i) continue;
                    for ($l = 0; $l < $sk; $l++) {
                        if ($l === $i || $l === $j) continue;
                        $ltCand  = $searchKeys[$i];
                        $qtyCand = $searchKeys[$j];
                        $upCand  = $searchKeys[$l];
                        $matches = 0; $tested = 0;
                        foreach ($tableData as $row) {
                            $lt  = (float)($row[$ltCand]  ?? 0);
                            $qty = (float)($row[$qtyCand] ?? 0);
                            $up  = (float)($row[$upCand]  ?? 0);
                            if ($qty == 0 || $up == 0 || $lt == 0) continue;
                            $tested++;
                            if (abs($lt - $qty * $up) / max(abs($lt), 0.01) < 0.20) $matches++;
                        }
                        if ($tested === 0) continue;
                        $qtySmallest = ($colMeans[$qtyCand] <= $colMeans[$upCand] &&
                                        $colMeans[$qtyCand] <= $colMeans[$ltCand]) ? 1.0 : 0.8;
                        $score = ($matches / $tested) * $qtySmallest;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $ltK  = $ltCand;
                            $qtyK = $qtyCand;
                            $upK  = $upCand;
                        }
                    }
                }
            }
        }

        // Fallback search: include code columns (handles Qty without decimal points)
        if ($ltK === null || $bestScore < 0.4) {
            $nk = count($numKeys);
            for ($i = 0; $i < $nk; $i++) {
                for ($j = 0; $j < $nk; $j++) {
                    if ($j === $i) continue;
                    for ($l = 0; $l < $nk; $l++) {
                        if ($l === $i || $l === $j) continue;
                        $ltCand  = $numKeys[$i];
                        $qtyCand = $numKeys[$j];
                        $upCand  = $numKeys[$l];
                        $matches = 0; $tested = 0;
                        foreach ($tableData as $row) {
                            $lt  = (float)($row[$ltCand]  ?? 0);
                            $qty = (float)($row[$qtyCand] ?? 0);
                            $up  = (float)($row[$upCand]  ?? 0);
                            if ($qty == 0 || $up == 0 || $lt == 0) continue;
                            $tested++;
                            if (abs($lt - $qty * $up) / max(abs($lt), 0.01) < 0.20) $matches++;
                        }
                        if ($tested === 0) continue;
                        $score = $matches / $tested;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $ltK  = $ltCand;
                            $qtyK = $qtyCand;
                            $upK  = $upCand;
                        }
                    }
                }
            }
        }

        if ($ltK !== null && $qtyK !== null && $upK !== null && $bestScore >= 0.4) {
            // Qty vs UnitPrice disambiguation:
            // The column with more integer-like values (.0 / .00 / whole number) is Qty.
            // If both are ≤50% integer-like, fall back to smaller mean = Qty.
            $ifQ = 0; $ifU = 0; $cQ = 0; $cU = 0;
            foreach ($valuesByKey[$qtyK] as $v) {
                $n = abs((float)$v); $cQ++;
                if (abs($n - round($n)) < 0.01) $ifQ++;
            }
            foreach ($valuesByKey[$upK] as $v) {
                $n = abs((float)$v); $cU++;
                if (abs($n - round($n)) < 0.01) $ifU++;
            }
            $ifQ = $cQ > 0 ? $ifQ / $cQ : 0;
            $ifU = $cU > 0 ? $ifU / $cU : 0;
            $swapQU = false;
            if ($ifQ < 0.5 && $ifU < 0.5) {
                $sumQ = 0; $sumU = 0;
                foreach ($valuesByKey[$qtyK] as $v) $sumQ += abs((float)$v);
                foreach ($valuesByKey[$upK]  as $v) $sumU += abs((float)$v);
                if ($cQ > 0 && $cU > 0 && ($sumU / $cU) < ($sumQ / $cQ)) $swapQU = true;
            } elseif ($ifU > $ifQ) {
                $swapQU = true;
            }
            if ($swapQU) { $tmp = $qtyK; $qtyK = $upK; $upK = $tmp; }

            $assigned[$ltK]       = 'LineTotal';
            $mapping['LineTotal'] = (int)$ltK;
            $assigned[$qtyK]      = 'Qty';
            $mapping['Qty']       = (int)$qtyK;
            $assigned[$upK]       = 'UnitPrice';
            $mapping['UnitPrice'] = (int)$upK;
            $notices[] = "✅ Qty / UnitPrice / LineTotal: auto-detected (columns {$qtyK} / {$upK} / {$ltK})";
        } else {
            // Triple search failed — apply fallback for each field individually
            $applyFallback('Qty');
            $applyFallback('UnitPrice');
            $applyFallback('LineTotal');
        }

    } else {
        // Fewer than 3 numeric columns available — apply fallback for each
        $applyFallback('Qty');
        $applyFallback('UnitPrice');
        $applyFallback('LineTotal');
    }

    return $mapping;
}
