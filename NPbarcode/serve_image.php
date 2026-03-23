<?php
// Serve an image file from the NP directories (security: must be inside NP_BASE_DIR)
define('NP_BASE_DIR', 'C:/Users/Shimri-SAS/Dropbox (Personal)/PC/Documents/LS Consulting/Business/Retailomatics/InvoiceArchive/BernardYahudArchive/NewProducts');

$requestedPath = $_GET['path'] ?? '';
if (empty($requestedPath)) { http_response_code(400); exit; }

// Normalize and verify it's inside the allowed base
$realPath = realpath($requestedPath);
$realBase = realpath(NP_BASE_DIR);

if (!$realPath || !$realBase || strpos($realPath, $realBase) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

if (!file_exists($realPath)) {
    http_response_code(404);
    exit('Not found');
}

$ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mime = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : 'image/png';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=3600');
readfile($realPath);
