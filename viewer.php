<?php
// =============================================================================
// PDF Library Module — Document Viewer (viewer.php)
// =============================================================================
require_once __DIR__ . '/config.php';

// Optional auth hook
if (function_exists('pdf_lib_auth_guard')) pdf_lib_auth_guard();

$pdo = db_connect();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM pdf_documents WHERE id = :id AND is_active = 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) { header('Location: index.php'); exit; }

// Increment view count
$pdo->prepare("UPDATE pdf_documents SET view_count = view_count + 1 WHERE id = :id")
    ->execute([':id' => $id]);

$pdfUrl = UPLOAD_URL . rawurlencode($doc['filename']);

function fmt_size(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($doc['title']) ?> — <?= htmlspecialchars(MODULE_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{--brand:#1a73e8;--toolbar-h:56px;--sidebar-w:300px;}
  *,*::before,*::after{box-sizing:border-box;}
  html,body{height:100%;margin:0;overflow:hidden;font-family:'Segoe UI',system-ui,sans-serif;}
  body{display:flex;flex-direction:column;background:#202124;}

  /* ── Toolbar ── */
  #toolbar{
    height:var(--toolbar-h);
    background:#fff;
    border-bottom:1px solid #e0e0e0;
    display:flex;
    align-items:center;
    padding:0 1rem;
    gap:.5rem;
    flex-shrink:0;
    z-index:10;
    box-shadow:0 1px 4px rgba(0,0,0,.1);
  }
  #toolbar .doc-title{font-weight:600;font-size:.95rem;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  #toolbar .page-info{font-size:.85rem;color:#5f6368;white-space:nowrap;}
  .tb-btn{background:none;border:none;border-radius:6px;padding:.35rem .5rem;cursor:pointer;color:#3c4043;display:flex;align-items:center;gap:.3rem;font-size:.85rem;transition:background .15s;}
  .tb-btn:hover{background:#f1f3f4;}
  .tb-btn i{font-size:1rem;}
  .tb-divider{width:1px;height:24px;background:#e0e0e0;margin:0 .25rem;}
  .zoom-input{width:60px;text-align:center;border:1px solid #dadce0;border-radius:4px;padding:.2rem .3rem;font-size:.85rem;}

  /* ── Main area ── */
  #main{flex:1;display:flex;overflow:hidden;}

  /* ── Sidebar ── */
  #sidebar{
    width:var(--sidebar-w);
    background:#fff;
    border-right:1px solid #e0e0e0;
    overflow-y:auto;
    flex-shrink:0;
    transition:width .2s,opacity .2s;
  }
  #sidebar.collapsed{width:0;opacity:0;overflow:hidden;}
  .sidebar-header{padding:1rem;border-bottom:1px solid #f0f0f0;background:#fafafa;}
  .sidebar-header h6{font-weight:700;color:#202124;margin:0;font-size:.9rem;}
  .meta-item{padding:.6rem 1rem;border-bottom:1px solid #f5f5f5;font-size:.82rem;}
  .meta-item .label{color:#80868b;margin-bottom:.1rem;}
  .meta-item .value{color:#202124;font-weight:500;word-break:break-word;}
  .related-item{display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;text-decoration:none;color:#202124;transition:background .15s;border-bottom:1px solid #f5f5f5;}
  .related-item:hover{background:#f8f9fa;}
  .related-item i{color:#ea4335;font-size:1.2rem;flex-shrink:0;}
  .related-item .ri-title{font-size:.82rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

  /* ── PDF Canvas area ── */
  #viewerWrap{flex:1;overflow:auto;display:flex;flex-direction:column;align-items:center;padding:24px 16px;gap:12px;}
  #pdfCanvas{box-shadow:0 4px 24px rgba(0,0,0,.4);border-radius:2px;}
  #loadingOverlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#202124;z-index:5;flex-direction:column;gap:1rem;color:#bdc1c6;}
  #loadingOverlay .spinner-border{width:2.5rem;height:2.5rem;color:var(--brand);}
  #viewerContainer{flex:1;position:relative;overflow:hidden;display:flex;}

  /* ── Page thumbnails strip ── */
  #thumbsPanel{width:80px;background:#292a2d;overflow-y:auto;flex-shrink:0;padding:8px 4px;display:flex;flex-direction:column;gap:6px;align-items:center;}
  #thumbsPanel.hidden{display:none;}
  .thumb-item{cursor:pointer;border-radius:3px;overflow:hidden;border:2px solid transparent;transition:border-color .15s;}
  .thumb-item:hover{border-color:#8ab4f8;}
  .thumb-item.active{border-color:var(--brand);}
  .thumb-item canvas{display:block;width:64px !important;height:auto !important;}
  .thumb-page-num{color:#9aa0a6;font-size:.62rem;text-align:center;margin-top:2px;}

  /* ── Responsive ── */
  @media(max-width:768px){
    #sidebar{position:fixed;top:var(--toolbar-h);left:0;height:calc(100% - var(--toolbar-h));z-index:20;box-shadow:2px 0 8px rgba(0,0,0,.2);}
    #thumbsPanel{display:none;}
    #toolbar .doc-title{max-width:160px;}
  }
</style>
</head>
<body>

<!-- ── Toolbar ──────────────────────────────────────────────────────────────── -->
<div id="toolbar">
  <!-- Back -->
  <a href="index.php" class="tb-btn text-decoration-none" title="Back to <?= htmlspecialchars(MODULE_NAME) ?>">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="tb-divider"></div>

  <!-- Title -->
  <span class="doc-title" title="<?= htmlspecialchars($doc['title']) ?>">
    <?= htmlspecialchars($doc['title']) ?>
  </span>

  <div class="ms-auto d-flex align-items-center gap-1 flex-wrap">
    <!-- Page nav -->
    <button class="tb-btn" id="btnPrevPage" title="Previous page"><i class="bi bi-chevron-up"></i></button>
    <span class="page-info"><span id="currentPage">1</span> / <span id="totalPages">—</span></span>
    <button class="tb-btn" id="btnNextPage" title="Next page"><i class="bi bi-chevron-down"></i></button>
    <div class="tb-divider"></div>

    <!-- Zoom -->
    <button class="tb-btn" id="btnZoomOut" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
    <input type="text" id="zoomInput" class="zoom-input" value="100%">
    <button class="tb-btn" id="btnZoomIn" title="Zoom in"><i class="bi bi-zoom-in"></i></button>
    <button class="tb-btn" id="btnFit" title="Fit page"><i class="bi bi-fullscreen"></i></button>
    <div class="tb-divider"></div>

    <!-- Sidebar toggle -->
    <button class="tb-btn" id="btnSidebar" title="Document info"><i class="bi bi-layout-sidebar-reverse"></i></button>

    <!-- Download -->
    <a href="download.php?id=<?= $doc['id'] ?>" class="tb-btn" title="Download">
      <i class="bi bi-download"></i>
    </a>

    <!-- Print -->
    <button class="tb-btn" id="btnPrint" title="Print"><i class="bi bi-printer"></i></button>
  </div>
</div>

<!-- ── Main ─────────────────────────────────────────────────────────────────── -->
<div id="main">

  <!-- Thumbnails -->
  <div id="thumbsPanel"></div>

  <!-- PDF Viewer -->
  <div id="viewerContainer">
    <div id="loadingOverlay">
      <div class="spinner-border"></div>
      <span>Loading document…</span>
    </div>
    <div id="viewerWrap">
      <!-- canvases appended by JS -->
    </div>
  </div>

  <!-- Sidebar -->
  <div id="sidebar">
    <div class="sidebar-header">
      <h6><i class="bi bi-info-circle me-2 text-primary"></i>Document Info</h6>
    </div>

    <div class="meta-item">
      <div class="label">Title</div>
      <div class="value"><?= htmlspecialchars($doc['title']) ?></div>
    </div>
    <?php if ($doc['category']): ?>
    <div class="meta-item">
      <div class="label">Category</div>
      <div class="value"><span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($doc['category']) ?></span></div>
    </div>
    <?php endif; ?>
    <div class="meta-item">
      <div class="label">File name</div>
      <div class="value"><?= htmlspecialchars($doc['original_name']) ?></div>
    </div>
    <div class="meta-item">
      <div class="label">File size</div>
      <div class="value"><?= fmt_size($doc['file_size']) ?></div>
    </div>
    <div class="meta-item">
      <div class="label">Uploaded by</div>
      <div class="value"><?= htmlspecialchars($doc['uploaded_by']) ?></div>
    </div>
    <div class="meta-item">
      <div class="label">Uploaded on</div>
      <div class="value"><?= date('F j, Y', strtotime($doc['uploaded_at'])) ?></div>
    </div>
    <div class="meta-item">
      <div class="label">Views</div>
      <div class="value"><?= number_format($doc['view_count']) ?></div>
    </div>
    <?php if ($doc['description']): ?>
    <div class="meta-item">
      <div class="label">Description</div>
      <div class="value"><?= nl2br(htmlspecialchars($doc['description'])) ?></div>
    </div>
    <?php endif; ?>

    <?php
    // Related docs (same category)
    if ($doc['category']) {
        $rel = $pdo->prepare(
            "SELECT id, title FROM pdf_documents
             WHERE category = :cat AND id != :id AND is_active = 1
             ORDER BY view_count DESC LIMIT 5"
        );
        $rel->execute([':cat' => $doc['category'], ':id' => $doc['id']]);
        $related = $rel->fetchAll();
        if ($related):
    ?>
    <div class="sidebar-header mt-2">
      <h6><i class="bi bi-files me-2 text-primary"></i>Related Documents</h6>
    </div>
    <?php foreach ($related as $r): ?>
      <a href="viewer.php?id=<?= $r['id'] ?>" class="related-item">
        <i class="bi bi-file-earmark-pdf-fill"></i>
        <span class="ri-title" title="<?= htmlspecialchars($r['title']) ?>"><?= htmlspecialchars($r['title']) ?></span>
      </a>
    <?php endforeach; ?>
    <?php endif; } ?>

  </div><!-- /sidebar -->
</div><!-- /main -->

<!-- PDF.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs" type="module"></script>
<script type="module">
import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';

const PDF_URL     = <?= json_encode($pdfUrl) ?>;
const viewerWrap  = document.getElementById('viewerWrap');
const thumbsPanel = document.getElementById('thumbsPanel');
const loading     = document.getElementById('loadingOverlay');
const curPageEl   = document.getElementById('currentPage');
const totPageEl   = document.getElementById('totalPages');
const zoomInput   = document.getElementById('zoomInput');

let pdfDoc     = null;
let scale      = 1.0;
let currentPage = 1;
let fitScale   = 1.0;
let rendering  = false;

// ── Load PDF ──────────────────────────────────────────────────────────────────
const loadingTask = pdfjsLib.getDocument(PDF_URL);
loadingTask.promise.then(pdf => {
  pdfDoc = pdf;
  totPageEl.textContent = pdf.numPages;
  calcFitScale().then(() => {
    scale = fitScale;
    updateZoomDisplay();
    renderAllPages().then(() => {
      loading.style.display = 'none';
      buildThumbs();
    });
  });
}).catch(err => {
  loading.innerHTML = `<i class="bi bi-exclamation-triangle fs-3 text-warning"></i><span class="text-warning">Failed to load PDF.<br><small>${err.message}</small></span>`;
});

async function calcFitScale() {
  const page = await pdfDoc.getPage(1);
  const vp   = page.getViewport({ scale: 1 });
  const availW = viewerWrap.clientWidth - 48;
  fitScale = Math.min(availW / vp.width, 2.5);
}

async function renderAllPages() {
  viewerWrap.innerHTML = '';
  for (let i = 1; i <= pdfDoc.numPages; i++) {
    const page = await pdfDoc.getPage(i);
    const viewport = page.getViewport({ scale });
    const canvas = document.createElement('canvas');
    canvas.id    = `page-${i}`;
    canvas.width  = viewport.width;
    canvas.height = viewport.height;
    canvas.style.display = 'block';
    viewerWrap.appendChild(canvas);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
  }
  setupScrollObserver();
}

// Track current page from scroll
function setupScrollObserver() {
  const wrap = viewerWrap;
  wrap.addEventListener('scroll', () => {
    const scrollTop = wrap.scrollTop + wrap.clientHeight / 2;
    let accum = 0;
    for (let i = 1; i <= pdfDoc.numPages; i++) {
      const c = document.getElementById(`page-${i}`);
      if (!c) continue;
      accum += c.height + 12;
      if (scrollTop < accum) {
        if (currentPage !== i) {
          currentPage = i;
          curPageEl.textContent = i;
          highlightThumb(i);
        }
        break;
      }
    }
  }, { passive: true });
}

// ── Thumbnails ────────────────────────────────────────────────────────────────
async function buildThumbs() {
  for (let i = 1; i <= pdfDoc.numPages; i++) {
    const page = await pdfDoc.getPage(i);
    const vp   = page.getViewport({ scale: 0.15 });
    const canvas = document.createElement('canvas');
    canvas.width  = vp.width;
    canvas.height = vp.height;
    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
    const wrap = document.createElement('div');
    wrap.className = 'thumb-item' + (i === 1 ? ' active' : '');
    wrap.dataset.page = i;
    wrap.appendChild(canvas);
    const num = document.createElement('div');
    num.className = 'thumb-page-num';
    num.textContent = i;
    wrap.appendChild(num);
    wrap.addEventListener('click', () => scrollToPage(i));
    thumbsPanel.appendChild(wrap);
  }
}

function highlightThumb(n) {
  document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
  const t = thumbsPanel.querySelector(`[data-page="${n}"]`);
  if (t) { t.classList.add('active'); t.scrollIntoView({ block: 'nearest' }); }
}

function scrollToPage(n) {
  const canvas = document.getElementById(`page-${n}`);
  if (canvas) canvas.scrollIntoView({ behavior: 'smooth' });
  currentPage = n;
  curPageEl.textContent = n;
  highlightThumb(n);
}

// ── Zoom ──────────────────────────────────────────────────────────────────────
function updateZoomDisplay() {
  zoomInput.value = Math.round(scale * 100) + '%';
}

async function applyZoom() {
  if (!pdfDoc) return;
  const savedPage = currentPage;
  await renderAllPages();
  scrollToPage(savedPage);
}

document.getElementById('btnZoomIn').addEventListener('click', () => {
  scale = Math.min(scale + 0.15, 4);
  updateZoomDisplay();
  applyZoom();
});
document.getElementById('btnZoomOut').addEventListener('click', () => {
  scale = Math.max(scale - 0.15, 0.25);
  updateZoomDisplay();
  applyZoom();
});
document.getElementById('btnFit').addEventListener('click', async () => {
  await calcFitScale();
  scale = fitScale;
  updateZoomDisplay();
  applyZoom();
});
zoomInput.addEventListener('change', () => {
  const v = parseFloat(zoomInput.value) / 100;
  if (!isNaN(v) && v >= 0.1 && v <= 5) { scale = v; applyZoom(); }
  else updateZoomDisplay();
});

// ── Page navigation ───────────────────────────────────────────────────────────
document.getElementById('btnPrevPage').addEventListener('click', () => {
  if (currentPage > 1) scrollToPage(currentPage - 1);
});
document.getElementById('btnNextPage').addEventListener('click', () => {
  if (currentPage < (pdfDoc?.numPages ?? 1)) scrollToPage(currentPage + 1);
});

// Keyboard shortcuts
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT') return;
  if (e.key === 'ArrowDown' || e.key === 'PageDown') {
    e.preventDefault();
    if (currentPage < pdfDoc?.numPages) scrollToPage(currentPage + 1);
  } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
    e.preventDefault();
    if (currentPage > 1) scrollToPage(currentPage - 1);
  } else if (e.key === '+' || e.key === '=') {
    scale = Math.min(scale + 0.15, 4); updateZoomDisplay(); applyZoom();
  } else if (e.key === '-') {
    scale = Math.max(scale - 0.15, 0.25); updateZoomDisplay(); applyZoom();
  }
});

// ── Sidebar toggle ────────────────────────────────────────────────────────────
document.getElementById('btnSidebar').addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('collapsed');
});

// ── Print ─────────────────────────────────────────────────────────────────────
document.getElementById('btnPrint').addEventListener('click', () => {
  const w = window.open(PDF_URL);
  w.onload = () => w.print();
});
</script>
</body>
</html>
