<?php
// Serve an uploaded image from tmp/ only — no path traversal allowed.
$tmpDir = realpath(__DIR__ . '/tmp');

$requestedPath = $_GET['path'] ?? '';
if (empty($requestedPath)) { http_response_code(400); exit; }

$realPath = realpath($requestedPath);

if (!$realPath || !$tmpDir || strpos($realPath, $tmpDir) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

if (!file_exists($realPath)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=3600');
readfile($realPath);
