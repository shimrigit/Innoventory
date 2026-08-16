<?php
// seed_demo_suppliers.php — one-off/dev tool: writes placeholder supplier
// catalogs into SuppliersDB/ so the PO flow can be exercised end-to-end
// before real supplier data exists.
//
// Run from CLI: php POAgent/tools/seed_demo_suppliers.php
//
// Supplier IDs reused from the existing root suppliers.json (Dansell/Osem/Tnuva)
// so IDs stay consistent with the rest of the codebase. Items/prices are
// fictional placeholders — NOT real catalog data. Max 10 items/supplier.
// Safe to re-run: overwrites the same 3 files each time.

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$suppliersDir = __DIR__ . '/../SuppliersDB';
if (!is_dir($suppliersDir)) {
    mkdir($suppliersDir, 0777, true);
}

// [SupplierID => [ [barcode, itemName, price], ... ]]  (price in whole shekels, decimals allowed)
$demoCatalogs = [
    'Osem' => [
        ['7290000066317', 'אסם פסטה פנה 500 גרם', 6.90],
        ['7290000066324', 'אסם קוסקוס 500 גרם', 7.50],
        ['7290000066331', 'אסם קטשופ 500 מ"ל', 9.90],
        ['7290000066348', 'אסם מרק עוף בקוביות', 5.20],
        ['7290000066355', 'אסם קורנפלקס 500 גרם', 14.90],
        ['7290000066362', 'אסם ביסלי גריל', 4.50],
        ['7290000066379', 'אסם במבה 80 גרם', 5.90],
        ['7290000066386', 'אסם עוגיות פטיפור', 8.90],
        ['7290000066393', 'אסם רסק עגבניות', 4.20],
        ['7290000066409', 'אסם צנימי מלוח', 6.50],
    ],
    'Tnuva' => [
        ['7290000077317', 'תנובה חלב 3% 1 ליטר', 6.20],
        ['7290000077324', 'תנובה גבינה לבנה 5% 250 גרם', 6.90],
        ['7290000077331', 'תנובה קוטג\' 5% 250 גרם', 5.50],
        ['7290000077348', 'תנובה יוגורט טבעי 3%', 4.90],
        ['7290000077355', 'תנובה שמנת חמוצה 15%', 6.10],
        ['7290000077362', 'תנובה חמאה 200 גרם', 8.90],
        ['7290000077379', 'תנובה גבינה צהובה פרוסה', 12.90],
        ['7290000077386', 'תנובה אשל וניל', 4.30],
    ],
    'Dansell' => [
        ['7290000088317', 'דנסל נייר טואלט 32 גלילים', 34.90],
        ['7290000088324', 'דנסל מגבות נייר 4 יח\'', 16.90],
        ['7290000088331', 'דנסל סבון כלים 750 מ"ל', 9.50],
        ['7290000088348', 'דנסל אקונומיקה 2 ליטר', 8.90],
        ['7290000088355', 'דנסל שקיות אשפה 30 יח\'', 11.90],
        ['7290000088362', 'דנסל מגבונים לחים 80 יח\'', 7.90],
    ],
];

foreach ($demoCatalogs as $supplierId => $items) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Barcode');
    $sheet->setCellValue('B1', 'ItemName');
    $sheet->setCellValue('C1', 'Price');
    $sheet->getStyle('A1:C1')->getFont()->setBold(true);

    $row = 2;
    foreach ($items as [$barcode, $name, $price]) {
        $sheet->setCellValueExplicit("A$row", $barcode, DataType::TYPE_STRING); // keep leading-safe, avoid sci-notation
        $sheet->setCellValue("B$row", $name);
        $sheet->setCellValue("C$row", $price);
        $row++;
    }

    foreach (['A', 'B', 'C'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $outPath = $suppliersDir . "/supplier_{$supplierId}.xlsx";
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($outPath);
    echo "Wrote $outPath (" . count($items) . " items)\n";
}

echo "Done.\n";
