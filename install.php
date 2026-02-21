<?php
// =============================================================================
// PDF Library Module — Web Installer  (install.php)
// =============================================================================
// Run this page once to set up the database.
// DELETE or RENAME this file after setup is complete!
// =============================================================================

// ── Safety check ──────────────────────────────────────────────────────────────
$lockFile = __DIR__ . '/.installed';
if (file_exists($lockFile)) {
    die('<h2>Already installed.</h2><p>Delete <code>.installed</code> and <code>config.php</code> to re-run setup.</p>');
}

$step    = $_POST['step']    ?? 'form';
$errors  = [];
$success = false;
$log     = [];

// ── PHP requirement checks ────────────────────────────────────────────────────
$checks = [
    'PHP ≥ 8.0'        => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO extension'    => extension_loaded('pdo'),
    'PDO MySQL driver' => extension_loaded('pdo_mysql'),
    'fileinfo'         => extension_loaded('fileinfo'),
    'uploads/ writable'=> is_writable(__DIR__ . '/uploads/') || !file_exists(__DIR__ . '/uploads/'),
];
$allPass = !in_array(false, $checks, true);

// ── Run install ───────────────────────────────────────────────────────────────
if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect form values
    $dbHost    = trim($_POST['db_host']    ?? 'localhost');
    $dbPort    = trim($_POST['db_port']    ?? '3306');
    $dbName    = trim($_POST['db_name']    ?? 'pdf_library');
    $dbUser    = trim($_POST['db_user']    ?? '');
    $dbPass    = $_POST['db_pass']         ?? '';
    $uploadUrl = rtrim(trim($_POST['upload_url'] ?? '/pdf_handler'), '/');
    $moduleUrl = rtrim(trim($_POST['module_url'] ?? '/pdf_handler'), '/');
    $modName   = trim($_POST['module_name'] ?? 'PDF Library');
    $sessName  = trim($_POST['session_name'] ?? 'pdf_lib_session');

    if (!$dbUser) $errors[] = 'Database username is required.';

    if (empty($errors)) {
        // 1. Test DB connection
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $log[] = '✅ Database connection successful.';
        } catch (PDOException $e) {
            $errors[] = 'Cannot connect to MySQL: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // 2. Create database & tables
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pdf_documents (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    title         VARCHAR(255)  NOT NULL,
                    filename      VARCHAR(255)  NOT NULL,
                    original_name VARCHAR(255)  NOT NULL,
                    file_size     BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    page_count    INT UNSIGNED DEFAULT NULL,
                    description   TEXT          DEFAULT NULL,
                    category      VARCHAR(100)  DEFAULT NULL,
                    uploaded_by   VARCHAR(100)  DEFAULT 'admin',
                    uploaded_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    download_count INT UNSIGNED NOT NULL DEFAULT 0,
                    view_count    INT UNSIGNED NOT NULL DEFAULT 0,
                    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
                    INDEX idx_category   (category),
                    INDEX idx_uploaded_at (uploaded_at),
                    INDEX idx_is_active  (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pdf_categories (
                    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name  VARCHAR(100) NOT NULL UNIQUE,
                    color VARCHAR(7)   NOT NULL DEFAULT '#6c757d'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                INSERT IGNORE INTO pdf_categories (name, color) VALUES
                    ('Academic',  '#0d6efd'),
                    ('Research',  '#6610f2'),
                    ('Manuals',   '#0dcaf0'),
                    ('Reports',   '#198754'),
                    ('General',   '#6c757d')
            ");
            $log[] = "✅ Database <strong>$dbName</strong> and tables created.";
        } catch (PDOException $e) {
            $errors[] = 'SQL error: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // 3. Create uploads/ directory
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            if (mkdir($uploadDir, 0775, true)) {
                $log[] = '✅ <code>uploads/</code> directory created.';
            } else {
                $errors[] = 'Could not create uploads/ directory. Create it manually and chmod 775.';
            }
        } else {
            $log[] = '✅ <code>uploads/</code> directory already exists.';
        }
    }

    if (empty($errors)) {
        // 4. Write config.php
        $escapedPass = addslashes($dbPass);
        $config = <<<PHP
<?php
// Auto-generated by install.php — do not edit manually, use config.example.php as reference.
define('DB_HOST',    '$dbHost');
define('DB_PORT',    '$dbPort');
define('DB_NAME',    '$dbName');
define('DB_USER',    '$dbUser');
define('DB_PASS',    '$escapedPass');
define('DB_CHARSET', 'utf8mb4');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', '$uploadUrl/uploads/');
define('MAX_FILE_SIZE', 52428800);
define('ALLOWED_TYPES', ['application/pdf']);
define('MODULE_URL',  '$moduleUrl');
define('MODULE_NAME', '$modName');
define('SESSION_NAME','$sessName');
if (!function_exists('db_connect')) {
    function db_connect(): PDO {
        static \$pdo = null;
        if (\$pdo !== null) return \$pdo;
        \$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        \$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        try { \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$opts); }
        catch (PDOException \$e) { http_response_code(500); die(json_encode(['error' => 'DB connection failed: '.\$e->getMessage()])); }
        return \$pdo;
    }
}
PHP;
        if (file_put_contents(__DIR__ . '/config.php', $config) !== false) {
            $log[] = '✅ <code>config.php</code> written.';
        } else {
            $errors[] = 'Could not write config.php. Check directory permissions.';
        }
    }

    if (empty($errors)) {
        // 5. Write lock file
        file_put_contents($lockFile, date('c'));
        $log[] = '✅ Lock file <code>.installed</code> created.';
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PDF Library — Installer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body{background:#f0f4ff;font-family:'Segoe UI',system-ui,sans-serif;}
  .installer-card{max-width:640px;margin:3rem auto;border-radius:16px;box-shadow:0 4px 32px rgba(26,115,232,.12);}
  .installer-header{background:linear-gradient(135deg,#1a73e8,#0d47a1);color:#fff;border-radius:16px 16px 0 0;padding:2rem;}
  .check-item{display:flex;align-items:center;gap:.6rem;padding:.4rem 0;font-size:.9rem;}
  .check-item .bi-check-circle-fill{color:#198754;}
  .check-item .bi-x-circle-fill{color:#dc3545;}
</style>
</head>
<body>

<div class="installer-card card border-0">
  <div class="installer-header">
    <h4 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>PDF Library — Setup Wizard</h4>
    <p class="mb-0 opacity-75 small">One-time installer. Delete <code>install.php</code> after setup.</p>
  </div>
  <div class="card-body p-4">

  <?php if ($success): ?>
    <!-- ── Success ── -->
    <div class="text-center py-3">
      <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
      <h5 class="mt-3 fw-bold">Installation Complete!</h5>
      <p class="text-muted">Your PDF Library is ready to use.</p>
      <ul class="list-unstyled text-start small mb-3">
        <?php foreach ($log as $line): ?><li><?= $line ?></li><?php endforeach; ?>
      </ul>
      <div class="alert alert-warning text-start small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Security:</strong> Delete or rename <code>install.php</code> now to prevent re-installation.
      </div>
      <a href="index.php" class="btn btn-primary mt-1">
        <i class="bi bi-collection-fill me-1"></i>Open PDF Library
      </a>
    </div>

  <?php elseif ($step === 'install' && !empty($errors)): ?>
    <!-- ── Errors ── -->
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle me-2"></i><strong>Setup failed:</strong>
      <ul class="mb-0 mt-2 ps-3">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php if (!empty($log)): ?>
    <p class="small text-muted mb-3">Steps completed before error:</p>
    <ul class="list-unstyled small mb-3"><?php foreach ($log as $l): ?><li><?= $l ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <a href="install.php" class="btn btn-outline-secondary btn-sm">← Try Again</a>

  <?php else: ?>
    <!-- ── Setup form ── -->

    <!-- Requirements check -->
    <h6 class="fw-bold mb-2"><i class="bi bi-clipboard-check me-1 text-primary"></i>Requirements</h6>
    <?php foreach ($checks as $label => $pass): ?>
    <div class="check-item">
      <i class="bi <?= $pass ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
      <span><?= htmlspecialchars($label) ?></span>
      <?php if (!$pass && $label === 'uploads/ writable'): ?>
        <small class="text-muted ms-auto">Run: <code>mkdir uploads && chmod 775 uploads</code></small>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!$allPass): ?>
      <div class="alert alert-warning mt-3 small">Fix the failed requirements above, then refresh this page.</div>
    <?php else: ?>

    <hr class="my-3">
    <form method="post">
      <input type="hidden" name="step" value="install">

      <h6 class="fw-bold mb-3"><i class="bi bi-database me-1 text-primary"></i>Database</h6>
      <div class="row g-2 mb-3">
        <div class="col-8">
          <label class="form-label fw-semibold small">Host</label>
          <input type="text" name="db_host" class="form-control form-control-sm" value="localhost" required>
        </div>
        <div class="col-4">
          <label class="form-label fw-semibold small">Port</label>
          <input type="text" name="db_port" class="form-control form-control-sm" value="3306" required>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold small">Database name</label>
          <input type="text" name="db_name" class="form-control form-control-sm" value="pdf_library" required>
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold small">Username</label>
          <input type="text" name="db_user" class="form-control form-control-sm" placeholder="e.g. root" required>
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold small">Password</label>
          <input type="password" name="db_pass" class="form-control form-control-sm" placeholder="(leave blank if none)">
        </div>
      </div>

      <h6 class="fw-bold mb-3"><i class="bi bi-gear me-1 text-primary"></i>Module Settings</h6>
      <div class="mb-2">
        <label class="form-label fw-semibold small">Module URL (base path, no trailing slash)</label>
        <input type="text" name="module_url" class="form-control form-control-sm" value="/pdf_handler" required>
        <div class="form-text">URL prefix where this folder is served. e.g. <code>/pdf_handler</code> or <code>/docs/library</code></div>
      </div>
      <div class="mb-2">
        <label class="form-label fw-semibold small">Upload URL (usually same as Module URL)</label>
        <input type="text" name="upload_url" class="form-control form-control-sm" value="/pdf_handler" required>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-7">
          <label class="form-label fw-semibold small">Library name (navbar label)</label>
          <input type="text" name="module_name" class="form-control form-control-sm" value="PDF Library">
        </div>
        <div class="col-5">
          <label class="form-label fw-semibold small">Session name</label>
          <input type="text" name="session_name" class="form-control form-control-sm" value="pdf_lib_session">
        </div>
      </div>

      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        This will create <code>config.php</code>, the database, and the <code>uploads/</code> folder automatically.
      </div>

      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-rocket-takeoff me-1"></i>Run Installation
      </button>
    </form>
    <?php endif; ?>
  <?php endif; ?>

  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
