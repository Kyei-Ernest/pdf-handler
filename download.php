<?php
// =============================================================================
// PDF Library Module — Secure File Download (download.php)
// =============================================================================
require_once __DIR__ . '/config.php';

$pdo = db_connect();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); exit('Invalid request.'); }

$stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = :id AND is_active = 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) { http_response_code(404); exit('Document not found.'); }

$filePath = UPLOAD_DIR . $doc['filename'];
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on server.'); }

// Increment download count
$pdo->prepare("UPDATE pdf_documents SET download_count = download_count + 1 WHERE id = :id")
    ->execute([':id' => $id]);

// Serve the file
$safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $doc['original_name']);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');
header('Pragma: no-cache');

readfile($filePath);
exit;
