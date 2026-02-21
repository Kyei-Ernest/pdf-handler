<?php
// =============================================================================
// PDF Library Module — Document Listing (index.php)
// =============================================================================
require_once __DIR__ . '/config.php';

// Start session early so auth guard can access $_SESSION
session_name(SESSION_NAME);
session_start();

// Optional auth hook — define pdf_lib_auth_guard() in your app or config.php
if (function_exists('pdf_lib_auth_guard')) pdf_lib_auth_guard();

$pdo = db_connect();

// ── Filters ──────────────────────────────────────────────────────────────────
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = in_array($_GET['sort'] ?? '', ['title','uploaded_at','view_count','file_size'])
            ? $_GET['sort'] : 'uploaded_at';
$order    = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ['is_active = 1'];
$params = [];

if ($search !== '') {
    $where[]         = '(title LIKE :search OR description LIKE :search2)';
    $params[':search']  = "%$search%";
    $params[':search2'] = "%$search%";
}
if ($category !== '') {
    $where[]           = 'category = :cat';
    $params[':cat']    = $category;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM pdf_documents $whereSQL");
$countStmt->execute($params);
$totalDocs = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalDocs / $perPage);

$params[':limit']  = $perPage;
$params[':offset'] = $offset;

$stmt = $pdo->prepare(
    "SELECT * FROM pdf_documents $whereSQL
     ORDER BY $sort $order
     LIMIT :limit OFFSET :offset"
);
// PDO named params can't bind :limit/:offset in some drivers unless we cast
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
foreach ($params as $k => $v) {
    if ($k !== ':limit' && $k !== ':offset') $stmt->bindValue($k, $v);
}
$stmt->execute();
$documents = $stmt->fetchAll();

// ── Categories for filter dropdown ───────────────────────────────────────────
$categories = $pdo->query("SELECT * FROM pdf_categories ORDER BY name")->fetchAll();

// ── Flash message ─────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt_size(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
function fmt_date(string $d): string {
    return date('M j, Y', strtotime($d));
}
function sortLink(string $col, string $label, string $cur, string $curOrder): string {
    $newOrder = ($cur === $col && $curOrder === 'asc') ? 'desc' : 'asc';
    $icon     = $cur === $col ? ($curOrder === 'asc' ? '↑' : '↓') : '';
    $params   = array_merge($_GET, ['sort' => $col, 'order' => $newOrder, 'page' => 1]);
    return '<a href="?' . http_build_query($params) . '" class="text-decoration-none text-dark fw-semibold">'
         . htmlspecialchars($label) . ' ' . $icon . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(MODULE_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{--brand:#1a73e8;--brand-dark:#1557b0;}
  body{background:#f8f9fa;font-family:'Segoe UI',system-ui,sans-serif;}
  .navbar{background:var(--brand)!important;}
  .navbar-brand,.nav-link,.navbar .btn-outline-light{color:#fff!important;}
  .doc-card{transition:box-shadow .2s,transform .2s;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;background:#fff;}
  .doc-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.12);transform:translateY(-2px);}
  .doc-card .pdf-thumb{height:160px;background:linear-gradient(135deg,#ea4335 0%,#c5221f 100%);display:flex;align-items:center;justify-content:center;}
  .doc-card .pdf-thumb i{font-size:3.5rem;color:rgba(255,255,255,.9);}
  .doc-card .card-body{padding:1rem;}
  .doc-card .doc-title{font-weight:600;font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .doc-card .doc-meta{font-size:.78rem;color:#6c757d;}
  .doc-card .badge-cat{font-size:.72rem;}
  .search-bar{background:#fff;border-radius:50px;box-shadow:0 1px 6px rgba(0,0,0,.1);padding:.4rem 1rem;}
  .search-bar input{border:none;outline:none;background:transparent;width:100%;}
  .sort-bar{background:#fff;border-radius:8px;padding:.5rem 1rem;border:1px solid #e0e0e0;}
  .upload-fab{position:fixed;bottom:2rem;right:2rem;z-index:999;width:56px;height:56px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 4px 12px rgba(26,115,232,.4);text-decoration:none;transition:background .2s;}
  .upload-fab:hover{background:var(--brand-dark);color:#fff;}
  .stats-badge{font-size:.75rem;color:#888;}
  .empty-state{text-align:center;padding:4rem 1rem;color:#888;}
  .empty-state i{font-size:4rem;color:#dee2e6;}
  .pagination .page-link{color:var(--brand);}
  .pagination .page-item.active .page-link{background:var(--brand);border-color:var(--brand);}
</style>
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg py-2 shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold fs-5" href="index.php">
      <i class="bi bi-collection-fill me-2"></i><?= htmlspecialchars(MODULE_NAME) ?>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="upload.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-cloud-upload me-1"></i>Upload PDF
      </a>
    </div>
  </div>
</nav>

<!-- ── Flash ─────────────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div class="container-fluid px-4 mt-3">
  <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
    <?= htmlspecialchars($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>

<div class="container-fluid px-4 py-4">

  <!-- ── Search & Filter bar ──────────────────────────────────────────────── -->
  <form method="get" class="row g-2 mb-4 align-items-center">
    <div class="col-12 col-md-5">
      <div class="search-bar d-flex align-items-center">
        <i class="bi bi-search text-secondary me-2"></i>
        <input type="text" name="search" placeholder="Search documents…" value="<?= htmlspecialchars($search) ?>">
        <?php if ($search): ?>
          <a href="?" class="text-secondary"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['name']) ?>"
            <?= $category === $cat['name'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="uploaded_at" <?= $sort==='uploaded_at'?'selected':'' ?>>Newest First</option>
        <option value="title"       <?= $sort==='title'?'selected':'' ?>>Title A–Z</option>
        <option value="view_count"  <?= $sort==='view_count'?'selected':'' ?>>Most Viewed</option>
        <option value="file_size"   <?= $sort==='file_size'?'selected':'' ?>>File Size</option>
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm w-100" type="submit">
        <i class="bi bi-funnel me-1"></i>Filter
      </button>
    </div>
  </form>

  <!-- ── Stats bar ─────────────────────────────────────────────────────────── -->
  <p class="stats-badge mb-3">
    <i class="bi bi-files me-1"></i>
    Showing <strong><?= count($documents) ?></strong> of <strong><?= $totalDocs ?></strong> document<?= $totalDocs !== 1 ? 's' : '' ?>
    <?= $search ? ' for <em>"'.htmlspecialchars($search).'"</em>' : '' ?>
  </p>

  <!-- ── Document grid ─────────────────────────────────────────────────────── -->
  <?php if (empty($documents)): ?>
    <div class="empty-state">
      <i class="bi bi-file-earmark-x d-block mb-3"></i>
      <h5>No documents found</h5>
      <p>Try a different search or <a href="upload.php">upload a PDF</a>.</p>
    </div>
  <?php else: ?>
  <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-6 g-3">
    <?php foreach ($documents as $doc): ?>
    <div class="col">
      <div class="doc-card h-100 d-flex flex-column">
        <a href="viewer.php?id=<?= $doc['id'] ?>" class="text-decoration-none">
          <div class="pdf-thumb">
            <i class="bi bi-file-earmark-pdf-fill"></i>
          </div>
        </a>
        <div class="card-body d-flex flex-column flex-grow-1">
          <p class="doc-title mb-1" title="<?= htmlspecialchars($doc['title']) ?>">
            <?= htmlspecialchars($doc['title']) ?>
          </p>
          <?php if ($doc['category']): ?>
            <span class="badge badge-cat bg-primary bg-opacity-10 text-primary mb-1">
              <?= htmlspecialchars($doc['category']) ?>
            </span>
          <?php endif; ?>
          <div class="doc-meta mt-auto">
            <span><i class="bi bi-eye me-1"></i><?= number_format($doc['view_count']) ?></span>
            <span class="ms-2"><i class="bi bi-hdd me-1"></i><?= fmt_size($doc['file_size']) ?></span>
            <div class="mt-1"><?= fmt_date($doc['uploaded_at']) ?></div>
          </div>
          <div class="d-flex gap-1 mt-2">
            <a href="viewer.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary flex-grow-1">
              <i class="bi bi-eye"></i> View
            </a>
            <a href="download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-download"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Pagination ─────────────────────────────────────────────────────────── -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-4 d-flex justify-content-center">
    <ul class="pagination pagination-sm">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>

</div><!-- /container -->

<!-- Floating upload button -->
<a href="upload.php" class="upload-fab" title="Upload PDF">
  <i class="bi bi-plus-lg"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
