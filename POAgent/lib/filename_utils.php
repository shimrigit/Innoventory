<?php
// filename_utils.php — shared helpers for POAgent's filesystem-backed
// DataStore adapters (SupplierStore / POStore / future DNStore / VSStore).

/**
 * Sanitize a value before it's used as a filename segment: strips
 * whitespace, underscores (the filename field separator), and non-ASCII
 * characters (e.g. Hebrew names) so filenames stay unambiguous to parse
 * back apart. The raw value is still stored in the JSON body — never
 * reconstructed from the sanitized filename (spec §6.2).
 */
function poagent_sanitize_segment(string $value): string
{
    $value = preg_replace('/[^\x20-\x7E]/', '', $value); // strip non-ASCII
    $value = str_replace(['_', ' '], '', $value);          // strip separators
    $value = trim($value);
    return $value !== '' ? $value : 'x'; // guard against a fully-stripped value
}

/** Write JSON to $path via write-to-temp-then-rename (spec §8.6). */
function poagent_write_json_atomic(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $tmpPath = $path . '.tmp' . uniqid('', true);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($tmpPath, $json) === false) {
        throw new RuntimeException("Failed to write temp file: $tmpPath");
    }
    if (!rename($tmpPath, $path)) {
        @unlink($tmpPath);
        throw new RuntimeException("Failed to rename temp file into place: $path");
    }
}
