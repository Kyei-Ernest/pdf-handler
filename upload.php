<?php
// =============================================================================
// PDF Library Module — Upload PDF (upload.php)
// =============================================================================
require_once __DIR__ . '/config.php';

// Optional auth hook
if (function_exists('pdf_lib_auth_guard')) pdf_lib_auth_guard();

session_name(SESSION_NAME);
session_start();

$errors   = [];
$success  = false;

// ── Categories ────────────────────────────────────────────────────────────────
$pdo        = db_connect();
$categories = $pdo->query("SELECT name FROM pdf_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category']    ?? '');
    $uploadedBy  = trim($_POST['uploaded_by'] ?? (function_exists('pdf_lib_current_user') ? (pdf_lib_current_user() ?? 'admin') : 'admin'));

    // Validate title
    if ($title === '') $errors[] = 'Document title is required.';

    // Validate file
    if (empty($_FILES['pdf_file']['name'])) {
        $errors[] = 'Please select a PDF file to upload.';
    } elseif ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error (code ' . $_FILES['pdf_file']['error'] . ').';
    } else {
        $file     = $_FILES['pdf_file'];
        $fsize    = $file['size'];
        $tmpPath  = $file['tmp_name'];
        $origName = basename($file['name']);

        // Size check
        if ($fsize > MAX_FILE_SIZE) {
            $errors[] = 'File exceeds maximum size of ' . round(MAX_FILE_SIZE / 1048576) . ' MB.';
        }

        // MIME check (read magic bytes — more reliable than browser type)
        $fh  = fopen($tmpPath, 'rb');
        $magic = fread($fh, 5);
        fclose($fh);
        if ($magic !== '%PDF-') {
            $errors[] = 'Uploaded file is not a valid PDF.';
        }
    }

    if (empty($errors)) {
        // Generate unique filename
        $ext       = 'pdf';
        $filename  = uniqid('pdf_', true) . '.' . $ext;
        $destPath  = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            $errors[] = 'Failed to save uploaded file. Check server permissions.';
        } else {
            // Store in DB
            $stmt = $pdo->prepare(
                "INSERT INTO pdf_documents (title, filename, original_name, file_size, description, category, uploaded_by)
                 VALUES (:title, :filename, :orig, :size, :desc, :cat, :by)"
            );
            $stmt->execute([
                ':title'    => $title,
                ':filename' => $filename,
                ':orig'     => $origName,
                ':size'     => $fsize,
                ':desc'     => $description ?: null,
                ':cat'      => $category    ?: null,
                ':by'       => $uploadedBy,
            ]);
            $newId = $pdo->lastInsertId();

            $_SESSION['flash'] = ['type' => 'success', 'msg' => "\"$title\" uploaded successfully."];
            header("Location: viewer.php?id=$newId");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload PDF — <?= htmlspecialchars(MODULE_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{--brand:#1a73e8;}
  body{background:#f8f9fa;font-family:'Segoe UI',system-ui,sans-serif;}
  .navbar{background:var(--brand)!important;}
  .navbar-brand,.nav-link{color:#fff!important;}
  .drop-zone{border:2.5px dashed #c5d9f7;border-radius:16px;background:#f0f6ff;padding:3rem 2rem;text-align:center;cursor:pointer;transition:background .2s,border-color .2s;}
  .drop-zone:hover,.drop-zone.dragover{background:#e0eefd;border-color:var(--brand);}
  .drop-zone i{font-size:3rem;color:var(--brand);}
  .drop-zone .hint{color:#6c757d;font-size:.85rem;}
  .file-preview{background:#fff;border-radius:8px;border:1px solid #dee2e6;padding:.75rem 1rem;}
  .upload-card{border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.08);}
</style>
</head>
<body>

<nav class="navbar py-2 shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">
      <i class="bi bi-collection-fill me-2"></i><?= htmlspecialchars(MODULE_NAME) ?>
    </a>
    <a href="index.php" class="btn btn-outline-light btn-sm ms-auto">
      <i class="bi bi-arrow-left me-1"></i>Back to Library
    </a>
  </div>
</nav>

<div class="container py-5" style="max-width:680px">
  <div class="card upload-card border-0 p-4">
    <h4 class="fw-bold mb-1"><i class="bi bi-cloud-upload text-primary me-2"></i>Upload PDF</h4>
    <p class="text-muted small mb-4">Add a new document to the digital library.</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="uploadForm" novalidate>

      <!-- Drop zone -->
      <div class="drop-zone mb-4" id="dropZone">
        <i class="bi bi-file-earmark-pdf d-block mb-2"></i>
        <p class="fw-semibold mb-1">Drag & drop your PDF here</p>
        <p class="hint mb-3">or click to browse files</p>
        <input type="file" name="pdf_file" id="pdfFile" accept=".pdf,application/pdf" class="d-none" required>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('pdfFile').click()">
          <i class="bi bi-folder2-open me-1"></i>Choose file
        </button>
      </div>
      <div id="filePreview" class="file-preview mb-4 d-none">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-pdf-fill text-danger fs-4"></i>
          <div>
            <div class="fw-semibold" id="fileName"></div>
            <div class="text-muted small" id="fileSize"></div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="clearFile">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </div>

      <!-- Metadata -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Document Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" placeholder="Enter document title"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Category</label>
          <select name="category" class="form-select">
            <option value="">— Select category —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"
                <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Uploaded by</label>
          <input type="text" name="uploaded_by" class="form-control"
          value="<?= htmlspecialchars($_POST['uploaded_by'] ?? (function_exists('pdf_lib_current_user') ? (pdf_lib_current_user() ?? 'admin') : 'admin')) ?>" placeholder="Your name">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="3"
                  placeholder="Optional: brief description of this document…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1" id="submitBtn">
          <i class="bi bi-cloud-upload me-1"></i>Upload Document
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>

    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('pdfFile');
const preview   = document.getElementById('filePreview');
const fnEl      = document.getElementById('fileName');
const fsEl      = document.getElementById('fileSize');
const clearBtn  = document.getElementById('clearFile');
const form      = document.getElementById('uploadForm');
const submitBtn = document.getElementById('submitBtn');

function showPreview(file) {
  if (!file) return;
  fnEl.textContent = file.name;
  fsEl.textContent = file.size > 1048576
    ? (file.size / 1048576).toFixed(1) + ' MB'
    : Math.round(file.size / 1024) + ' KB';
  preview.classList.remove('d-none');
  dropZone.classList.add('d-none');

  // Auto-fill title from filename
  const titleInput = document.querySelector('[name="title"]');
  if (!titleInput.value) {
    titleInput.value = file.name.replace(/\.pdf$/i, '').replace(/[_-]/g, ' ');
  }
}

fileInput.addEventListener('change', () => showPreview(fileInput.files[0]));

clearBtn.addEventListener('click', () => {
  fileInput.value = '';
  preview.classList.add('d-none');
  dropZone.classList.remove('d-none');
});

['dragover','dragenter'].forEach(e => dropZone.addEventListener(e, ev => {
  ev.preventDefault(); dropZone.classList.add('dragover');
}));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => {
  ev.preventDefault(); dropZone.classList.remove('dragover');
}));
dropZone.addEventListener('drop', ev => {
  const file = ev.dataTransfer.files[0];
  if (file && file.type === 'application/pdf') {
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    showPreview(file);
  } else {
    alert('Please drop a PDF file.');
  }
});
dropZone.addEventListener('click', () => fileInput.click());

form.addEventListener('submit', () => {
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading…';
});
</script>
</body>
</html>
