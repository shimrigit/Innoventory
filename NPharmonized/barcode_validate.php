<?php
// barcode_validate.php — standard mod-10 checksum validation for
// EAN-13 (13 digits), UPC-A (12 digits) and EAN-8 (8 digits).
//
// EAN-13:            odd positions (1st,3rd,5th...) weight 1, even positions weight 3
// UPC-A / EAN-8:      odd positions weight 3, even positions weight 1
// Check digit = (10 - (sum mod 10)) mod 10, compared against the barcode's last digit.
//
// Returns false for anything that isn't a pure 8/12/13-digit string, or
// whose check digit doesn't match.
function validateBarcode($code) {
    $code = trim((string)$code);
    if ($code === '' || !ctype_digit($code)) return false;

    $len = strlen($code);
    if (!in_array($len, [8, 12, 13], true)) return false;

    $digits     = array_map('intval', str_split($code));
    $checkDigit = array_pop($digits); // last digit is the check digit

    $sum = 0;
    foreach ($digits as $i => $d) {
        $position = $i + 1; // 1-based position among the remaining digits
        if ($len === 13) {
            // EAN-13: odd positions x1, even positions x3
            $sum += ($position % 2 === 1) ? $d * 1 : $d * 3;
        } else {
            // UPC-A / EAN-8: odd positions x3, even positions x1
            $sum += ($position % 2 === 1) ? $d * 3 : $d * 1;
        }
    }

    $calculatedCheck = (10 - ($sum % 10)) % 10;
    return $calculatedCheck === $checkDigit;
}
