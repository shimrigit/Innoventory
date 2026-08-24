<?php
// DNSanity.php — Stage 2 (spec §9 step 4): lightweight validation gate,
// equivalent in spirit to OCRsanity/process_ocrsanity.php's
// applyDataTypeVerification()/applyBarcodeSanityVerification(), but
// reimplemented directly against the OCR JSON instead of an xlsx — no
// crop/xlsx machinery reused here (see POAgentNotes.md for why).

class DNSanity
{
    /**
     * Flags each extracted item's fields and coerces qty/unit_price to
     * numbers (0 when invalid, so downstream code never has to re-check
     * is_numeric()). Passes dn_total through unchanged (DNOcr::extract()
     * already normalized it to a float-or-null — a missing total isn't a
     * sanity failure, just "nothing to verify against", so it never affects
     * $ok). Returns:
     * ['items' => [...], 'ok' => bool, 'dn_total' => float|null]
     */
    public static function check(array $extracted): array
    {
        $items = [];
        $ok = true;

        foreach ($extracted['items'] ?? [] as $raw) {
            $barcode = trim((string) ($raw['barcode'] ?? ''));
            $qty = $raw['qty'] ?? null;
            $unitPrice = $raw['unit_price'] ?? null;

            $barcodeInvalid = $barcode === '' || !ctype_digit($barcode) || strlen($barcode) > 13;
            $qtyInvalid = !is_numeric($qty);
            $priceInvalid = !is_numeric($unitPrice);

            if ($barcodeInvalid || $qtyInvalid || $priceInvalid) {
                $ok = false;
            }

            $items[] = [
                'barcode'         => $barcode,
                'name'            => (string) ($raw['name'] ?? ''),
                'qty'             => $qtyInvalid ? 0 : (float) $qty,
                'unit_price'      => $priceInvalid ? 0.0 : (float) $unitPrice,
                'barcode_invalid' => $barcodeInvalid,
                'qty_invalid'     => $qtyInvalid,
                'price_invalid'   => $priceInvalid,
            ];
        }

        return ['items' => $items, 'ok' => $ok, 'dn_total' => $extracted['dn_total'] ?? null];
    }
}
