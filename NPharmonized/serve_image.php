<?php
// Serves a JPEG from the NP working directory. Validates path to prevent traversal.
$npDir = $_GET['np_dir'] ?? '';
$fname = basename($_GET['fname'] ?? '');

if (empty($npDir) || empty($fname)) { http_response_code(400); exit; }

$fullPath = $npDir . '/' . $fname;
$realPath = realpath($fullPath);
$realDir  = realpath($npDir);

if (!$realPath || !$realDir || strpos($realPath, $realDir) !== 0 || !file_exists($realPath)) {
    http_response_code(404); exit('Not found');
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=3600');
readfile($realPath);
