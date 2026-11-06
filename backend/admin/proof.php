<?php
// Streams a proof image to logged-in admins only (storage/ is not public).
require __DIR__ . '/_boot.php';
require_admin();

$path = val('SELECT proof_path FROM submissions WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$file = $path ? realpath(STORAGE_PATH . '/' . $path) : false;
if (!$file || !str_starts_with($file, realpath(STORAGE_PATH . '/proofs'))) { http_response_code(404); exit('Not found'); }

header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file));
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=86400');
readfile($file);
