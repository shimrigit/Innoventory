<?php
// xlsx_retry.php — loads an xlsx with a wait + retry, for Flow mode.
//
// A stage sometimes loads a file the *previous* stage (a separate HTTP
// request) only just finished saving a moment earlier. On some filesystems
// (network/cloud-synced drives, antivirus real-time scanning newly-written
// files) there can be a brief window right after save() returns where the
// file isn't fully readable yet, and PhpSpreadsheet throws
// "Unable to identify a reader for this file".
//
// Waits 3 seconds before each retry and gives up after 3 retries (4 attempts
// total), then throws with a message that makes the cause obvious instead of
// the raw PhpSpreadsheet error.
//
// The fast path is unaffected: if the file is already fully readable (the
// common case), it loads on the first attempt with no added delay.
function loadSpreadsheetWithRetry(string $path, int $maxAttempts = 4, int $delayMs = 3000) {
    $lastError = null;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Throwable $e) {
            $lastError = $e;
            if ($attempt < $maxAttempts) {
                usleep($delayMs * 1000);
            }
        }
    }
    throw new \Exception(
        "נכשל בטעינת הקובץ אחרי {$maxAttempts} ניסיונות (כנראה בעיית סנכרון עם מערכת הקבצים — נסה שוב בעוד רגע): "
        . $lastError->getMessage(),
        0,
        $lastError
    );
}
