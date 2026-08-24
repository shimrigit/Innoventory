<?php
// DiffEngine.php — Stage 4 (spec §9 step 6, §4.4/§5): fused quantity+price
// comparison, cumulative across a PO's delivery history. ALWAYS produces a
// VS body — no skip-if-matched branch (spec §4.4/§8.8). The caller always
// calls compare() and always writes the result via VSStore::write(),
// even when every diff comes out zero.

class DiffEngine
{
    /**
     * $po: PO record (POStore shape).
     * $finalizedDn: DN record (DNStore::finalize() shape) — items already
     *   numeric-coerced by DNSanity::check().
     * $priorVs: VSStore::listForPo($po['core_name']) taken BEFORE this
     *   delivery's VS is written — every VS already on record for this PO,
     *   used for the cumulative remaining-quantity math (spec §5).
     */
    public static function compare(array $po, array $finalizedDn, int $deliveryIndex, array $priorVs): array
    {
        // PO items indexed by barcode.
        $poItems = [];
        foreach ($po['items'] ?? [] as $item) {
            $poItems[$item['barcode']] = $item;
        }

        // Cumulative qty already received per barcode, from every prior VS's line_items.
        $receivedSoFar = [];
        foreach ($priorVs as $vs) {
            foreach ($vs['line_items'] ?? [] as $line) {
                $barcode = $line['barcode'] ?? '';
                $receivedSoFar[$barcode] = ($receivedSoFar[$barcode] ?? 0) + (int) ($line['dn_qty'] ?? 0);
            }
        }

        $lineItems = [];
        $unmatched = [];
        $coveredBarcodes = []; // PO barcodes this DN actually mentions (i.e. present in the DN's own items)
        $dnComputedTotalAgorot = 0; // sum of ALL DN rows (matched + unmatched) — what the
                                    // document's own printed total is supposed to add up to

        foreach ($finalizedDn['items'] ?? [] as $dnItem) {
            $barcode = (string) ($dnItem['barcode'] ?? '');
            $dnQty = (int) round((float) ($dnItem['qty'] ?? 0));
            $dnPriceAgorot = (int) round(((float) ($dnItem['unit_price'] ?? 0)) * 100);
            $dnComputedTotalAgorot += $dnQty * $dnPriceAgorot;

            if (isset($poItems[$barcode])) {
                $poItem = $poItems[$barcode];
                $poQtyTotal = (int) ($poItem['qty'] ?? 0);
                $poPriceAgorot = (int) ($poItem['unit_price_agorot'] ?? 0);
                $alreadyReceived = $receivedSoFar[$barcode] ?? 0;
                $remainingBefore = $poQtyTotal - $alreadyReceived;

                $qtyDiff = $dnQty - $remainingBefore;
                $priceDiff = $dnPriceAgorot - $poPriceAgorot;

                $coveredBarcodes[$barcode] = true;
                $lineItems[] = [
                    'barcode'                 => $barcode,
                    'po_qty_remaining_before' => $remainingBefore,
                    'dn_qty'                  => $dnQty,
                    'qty_diff'                => $qtyDiff,
                    'qty_flagged'             => $qtyDiff !== 0,
                    'po_price_agorot'         => $poPriceAgorot,
                    'dn_price_agorot'         => $dnPriceAgorot,
                    'price_diff_agorot'       => $priceDiff,
                    'price_flagged'           => $priceDiff !== 0,
                    'not_delivered'           => false,
                ];
            } else {
                $unmatched[] = [
                    'barcode' => $barcode,
                    'dn_qty'  => $dnQty,
                    'note'    => 'לא ברשימת ההזמנה — נמסר שלא הוזמן',
                ];
            }
        }

        // PO items still owed (remaining > 0 going into this delivery) that
        // this DN doesn't mention AT ALL — added as explicit zero-delivery
        // line items rather than left silently absent. An under-delivery
        // must be exactly as visible as an over-delivery (no tolerance,
        // no gap hidden — spec §4.4). Skipped when remaining is already 0
        // (fully settled by a PRIOR delivery) — that's not this delivery's
        // business to re-report every time.
        foreach ($poItems as $barcode => $poItem) {
            // PHP silently casts purely-numeric array keys to int — re-cast
            // back to string so this loop's 'barcode' field always matches
            // the type the DN-item loop above emits (explicit (string) cast
            // there), not just its value.
            $barcode = (string) $barcode;
            if (isset($coveredBarcodes[$barcode])) {
                continue;
            }
            $poQtyTotal = (int) ($poItem['qty'] ?? 0);
            $poPriceAgorot = (int) ($poItem['unit_price_agorot'] ?? 0);
            $alreadyReceived = $receivedSoFar[$barcode] ?? 0;
            $remainingBefore = $poQtyTotal - $alreadyReceived;

            if ($remainingBefore <= 0) {
                continue;
            }

            $lineItems[] = [
                'barcode'                 => $barcode,
                'po_qty_remaining_before' => $remainingBefore,
                'dn_qty'                  => 0,
                'qty_diff'                => -$remainingBefore,
                'qty_flagged'             => true,
                'po_price_agorot'         => $poPriceAgorot,
                'dn_price_agorot'         => $poPriceAgorot, // nothing delivered — no DN price to compare against
                'price_diff_agorot'       => 0,
                'price_flagged'           => false,
                'not_delivered'           => true,
            ];
        }

        // Total verification (explicit request): does the DN's own declared
        // total agree with (a) what this delivery was expected to be worth —
        // the remaining PO value for every item still owed, whether this DN
        // addressed it or not (that's exactly what the "not delivered"
        // entries above contribute here) — and (b) the DN's own row-by-row
        // sum (which only reflects what was actually itemized as delivered).
        // An unordered item still isn't counted into "expected" — that
        // divergence is exactly what this check exists to surface, not hide.
        $poExpectedTotalAgorot = array_sum(array_map(
            fn($l) => $l['po_qty_remaining_before'] * $l['po_price_agorot'],
            $lineItems
        ));
        $dnDeclaredTotalRaw = $finalizedDn['dn_total'] ?? null;
        $dnDeclaredTotalAgorot = $dnDeclaredTotalRaw !== null ? (int) round($dnDeclaredTotalRaw * 100) : null;

        $poVsDeclaredDiff = $dnDeclaredTotalAgorot !== null ? $dnDeclaredTotalAgorot - $poExpectedTotalAgorot : null;
        $declaredVsComputedDiff = $dnDeclaredTotalAgorot !== null ? $dnDeclaredTotalAgorot - $dnComputedTotalAgorot : null;

        $totalCheck = [
            'po_expected_total_agorot'          => $poExpectedTotalAgorot,
            'dn_declared_total_agorot'           => $dnDeclaredTotalAgorot, // null = no total visible on the document
            'dn_computed_total_agorot'            => $dnComputedTotalAgorot,
            'po_vs_declared_diff_agorot'          => $poVsDeclaredDiff,
            'po_vs_declared_flagged'              => $poVsDeclaredDiff !== null && $poVsDeclaredDiff !== 0,
            'declared_vs_computed_diff_agorot'    => $declaredVsComputedDiff,
            'declared_vs_computed_flagged'        => $declaredVsComputedDiff !== null && $declaredVsComputedDiff !== 0,
        ];

        $hasVariance = $unmatched !== []
            || $totalCheck['po_vs_declared_flagged']
            || $totalCheck['declared_vs_computed_flagged']
            || array_reduce(
                $lineItems,
                fn($carry, $l) => $carry || $l['qty_flagged'] || $l['price_flagged'],
                false
            );

        return [
            'po_core_name'       => $po['core_name'],
            'delivery_index'     => $deliveryIndex,
            'dn_reference'       => $finalizedDn['dn_core_name'] ?? '',
            'generated_at'       => date('c'),
            'status'             => $hasVariance ? 'variance' : 'matched',
            'line_items'         => $lineItems,
            'unmatched_dn_items' => $unmatched,
            'total_check'        => $totalCheck,
        ];
    }
}
