<?php
// SupplierStore.php — DataStore adapter (spec §7) for supplier catalogs.
// Filesystem-backed today (SuppliersDB/supplier_<SupplierID>.xlsx); this is
// the only place that knows that detail, so swapping to a Priority API
// later touches only this file, not callers.

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

define('POAGENT_SUPPLIERS_DIR', __DIR__ . '/../SuppliersDB');

class SupplierStore
{
    /** List supplier IDs available in SuppliersDB/, derived from filenames. */
    public static function listSuppliers(): array
    {
        $ids = [];
        foreach (glob(POAGENT_SUPPLIERS_DIR . '/supplier_*.xlsx') as $path) {
            $base = basename($path, '.xlsx');
            $ids[] = substr($base, strlen('supplier_'));
        }
        sort($ids);
        return $ids;
    }

    /**
     * Read a supplier's item catalog.
     * Returns: [ ['barcode' => string, 'name' => string, 'price_agorot' => int], ... ]
     */
    public static function getCatalog(string $supplierId): array
    {
        $path = self::pathFor($supplierId);
        if (!file_exists($path)) {
            throw new RuntimeException("Unknown supplier: $supplierId");
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $items = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) { // row 1 = headers
            $barcode = trim((string)$sheet->getCell("A$row")->getValue());
            $name    = trim((string)$sheet->getCell("B$row")->getValue());
            $priceRaw = $sheet->getCell("C$row")->getValue();

            if ($barcode === '' && $name === '') {
                continue; // skip blank rows
            }

            $items[] = [
                'barcode'      => $barcode,
                'name'         => $name,
                // Price column is stored in whole currency units (shekels);
                // convert to integer agorot here so everything downstream
                // (PO, VS) works in exact integer agorot per spec §4.4/§8.3.
                'price_agorot' => (int) round(((float)$priceRaw) * 100),
            ];
        }

        return $items;
    }

    /** True if the supplier exists in the store. */
    public static function exists(string $supplierId): bool
    {
        return file_exists(self::pathFor($supplierId));
    }

    public static function pathFor(string $supplierId): string
    {
        return POAGENT_SUPPLIERS_DIR . '/supplier_' . $supplierId . '.xlsx';
    }
}
