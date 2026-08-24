<?php
// VSStore.php — DataStore adapter (spec §7) for Variance Statements.
// Delivery-index counters live in POcounter/ — the same directory as the
// PO##### counter (POStore's POAGENT_COUNTER_DIR), per explicit request
// that every POAgent counter live in one place rather than spreading a new
// directory per entity. Filenames are prefixed ("vs_...") to stay distinct
// from POStore's own ".po_counter". DNdir/ stays disposable/regenerable
// without losing per-PO delivery sequencing either way (POAgentNotes.md Fix 4).

require_once __DIR__ . '/filename_utils.php';
require_once __DIR__ . '/DNStore.php'; // POAGENT_DNDIR
require_once __DIR__ . '/POStore.php'; // POAGENT_COUNTER_DIR

class VSStore
{
    /** Atomically allocate the next zero-based delivery index for a PO (spec §6.3). */
    public static function nextDeliveryIndex(string $poCoreName): int
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $poCoreName)) {
            throw new InvalidArgumentException('Invalid PO core name');
        }
        if (!is_dir(POAGENT_COUNTER_DIR)) {
            mkdir(POAGENT_COUNTER_DIR, 0777, true);
        }

        $counterFile = POAGENT_COUNTER_DIR . "/vs_{$poCoreName}.count";
        $fh = fopen($counterFile, 'c+');
        if ($fh === false) {
            throw new RuntimeException('Cannot open VS delivery-index counter file');
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                throw new RuntimeException('Cannot lock VS delivery-index counter file');
            }
            $current = trim((string) stream_get_contents($fh));
            $next = ($current === '') ? 0 : ((int) $current) + 1;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) $next);
            fflush($fh);
            flock($fh, LOCK_UN);
        } finally {
            fclose($fh);
        }

        return $next;
    }

    /** Write a VS JSON record to DNdir/ (spec §6.3 filename convention). Never overwritten, never skipped. */
    public static function write(string $poCoreName, int $deliveryIndex, array $vs): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $poCoreName)) {
            throw new InvalidArgumentException('Invalid PO core name');
        }
        $timestamp = date('dmy-His');
        $indexPadded = str_pad((string) $deliveryIndex, 2, '0', STR_PAD_LEFT);
        $path = POAGENT_DNDIR . "/{$poCoreName}_VS_{$timestamp}_{$indexPadded}.json";
        poagent_write_json_atomic($path, $vs);
        return $path;
    }

    /** All VS records for a PO, ordered by delivery_index (spec §5 — index order, not filename timestamp). */
    public static function listForPo(string $poCoreName): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $poCoreName)) {
            return [];
        }
        $records = [];
        foreach (glob(POAGENT_DNDIR . "/{$poCoreName}_VS_*.json") as $path) {
            $data = json_decode(file_get_contents($path), true);
            if ($data !== null) {
                $records[] = $data;
            }
        }
        usort($records, fn($a, $b) => ($a['delivery_index'] ?? 0) <=> ($b['delivery_index'] ?? 0));
        return $records;
    }
}
