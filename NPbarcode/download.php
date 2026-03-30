<?php
$file = basename($_GET['file'] ?? '');
if (empty($file)) { http_response_code(400); exit; }

$path = __DIR__ . '/tmp/' . $file;
if (!file_exists($path)) { http_response_code(404); exit('File not found'); }

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ($ext === 'xlsx')
    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    : 'application/vnd.ms-excel';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="np_barcodes.' . $ext . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
