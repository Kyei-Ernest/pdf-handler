<?php
// =============================================================================
// PDF Library Module — Delete Document (delete.php)
// =============================================================================
require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_start();

$pdo = db_connect();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = :id AND is_active = 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Document not found.'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
    // Soft-delete: mark inactive
    $pdo->prepare("UPDATE pdf_documents SET is_active = 0 WHERE id = :id")
        ->execute([':id' => $id]);

    // Optionally delete file — comment out to keep files on disk
    $filePath = UPLOAD_DIR . $doc['filename'];
    if (file_exists($filePath)) @unlink($filePath);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => '"' . $doc['title'] . '" was deleted.'];
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delete Document — PDF Library</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>:root{--brand:#1a73e8;}body{background:#f8f9fa;}</style>
</head>
<body>
<nav class="navbar py-2" style="background:var(--brand)">
  <div class="container">
    <a class="navbar-brand text-white fw-bold" href="index.php"><i class="bi bi-collection-fill me-2"></i>PDF Library</a>
  </div>
</nav>
<div class="container py-5" style="max-width:500px">
  <div class="card border-0 shadow-sm rounded-3 p-4 text-center">
    <i class="bi bi-trash3-fill text-danger" style="font-size:3rem"></i>
    <h5 class="mt-3 fw-bold">Delete Document?</h5>
    <p class="text-muted mb-1">You are about to permanently delete:</p>
    <p class="fw-semibold"><?= htmlspecialchars($doc['title']) ?></p>
    <p class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>This action cannot be undone.</p>
    <form method="post" class="d-flex gap-2 justify-content-center mt-2">
      <input type="hidden" name="confirm" value="yes">
      <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
      <a href="viewer.php?id=<?= $doc['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
