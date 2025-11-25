<?php
// pages/datasets.php
// List and create datasets (modal). Comments in English.

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/../config/config.php';

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));

// fetch projects for select
$projects = [];
try {
    $projects = $pdo->query("SELECT id, name FROM proyectos ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
}

// fetch datasets with optional filters
$search = trim($_GET['q'] ?? '');
$project_filter = intval($_GET['project_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(d.name LIKE :q OR d.original_filename LIKE :q OR d.version LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if ($project_filter > 0) {
    $where[] = "d.project_id = :proj";
    $params[':proj'] = $project_filter;
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM datasets d {$where_sql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$rows = [];
try {
    $sql = "SELECT d.*, p.name as project_name, u.username as uploaded_by_username, dc.conn_type
            FROM datasets d
            LEFT JOIN proyectos p ON p.id = d.project_id
            LEFT JOIN usuarios u ON u.id = d.uploaded_by
            LEFT JOIN data_connections dc ON dc.id = d.connection_id
            {$where_sql}
            ORDER BY d.created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

function query_with($overrides = []) {
    $qs = array_merge($_GET, $overrides);
    return http_build_query($qs);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Datasets — KalTire MLOps</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="page-wrapper">
    <div class="app">
      <?php
        $partial = __DIR__ . '/../partials/sidebar.php';
        if (file_exists($partial)) include $partial;
      ?>

      <main class="main">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <h2 style="margin:0">Datasets</h2>
          <div>
            <button id="btnOpenCreate" class="primary-btn">+ New Dataset</button>
          </div>
        </div>

        <?php if ($flash): ?>
          <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:<?= $flash['type']=='success' ? 'rgba(40,167,69,0.12)' : 'rgba(220,53,69,0.08)' ?>;color:<?= $flash['type']=='success' ? '#28a745' : '#dc3545' ?>">
            <?= htmlspecialchars($flash['message']) ?>
          </div>
        <?php endif; ?>

        <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
          <input type="search" name="q" placeholder="Search name or file..." value="<?= htmlspecialchars($search) ?>" style="padding:8px;border-radius:8px;background:var(--glass);border:none;color:#ddd" />
          <select name="project_id" style="padding:8px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.03);color:#ddd">
            <option value="0">All projects</option>
            <?php foreach($projects as $p): ?>
              <option value="<?= intval($p['id']) ?>" <?= $project_filter == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn">Filter</button>
        </form>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;">
          <?php if (empty($rows)): ?>
            <div class="card">
              <p class="muted">No datasets yet.</p>
              <button id="btnCreateFirst" class="primary-btn">Create your first dataset</button>
            </div>
          <?php else: ?>
            <?php foreach($rows as $d): ?>
              <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div>
                    <div style="font-weight:700"><?= htmlspecialchars($d['name']) ?> <span style="color:var(--muted);font-weight:600"><?= htmlspecialchars($d['version'] ?? '') ?></span></div>
                    <div class="muted small"><?= htmlspecialchars($d['project_name'] ?? '-') ?> • <?= htmlspecialchars($d['conn_type'] ?? '-') ?></div>
                  </div>
                  <div style="text-align:right">
                    <div class="muted tiny"><?= htmlspecialchars($d['created_at'] ?? '') ?></div>
                    <a href="<?= htmlspecialchars($d['path'] ?? '#') ?>" class="btn" target="_blank">Download</a>
                  </div>
                </div>
                <div style="margin-top:8px;color:#cfd6da;font-size:13px;min-height:36px;">
                  <?= nl2br(htmlspecialchars($d['original_filename'] ?? '')) ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- pagination -->
        <?php
          $totalPages = max(1, ceil($total / $perPage));
        ?>
        <?php if ($totalPages > 1): ?>
          <div style="margin-top:12px;display:flex;gap:8px;align-items:center;justify-content:center">
            <?php for($p=1;$p<=$totalPages;$p++): ?>
              <a href="?<?= query_with(['page'=>$p]) ?>" class="btn" style="<?= $p===$page ? 'background:var(--kaltire-orange);color:#111' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>

        <!-- Create Dataset Modal -->
        <div id="createModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
          <div class="modal-overlay" data-close></div>
          <div class="modal-dialog" role="document" aria-labelledby="modalTitle">
            <button class="modal-close" aria-label="Close" data-close>×</button>

            <form class="modal-form" method="POST" action="../actions/dataset_create.php" enctype="multipart/form-data" novalidate>
              <h3 id="modalTitle">Create dataset</h3>

              <label>
                <div class="label-text">Project *</div>
                <select name="project_id" required>
                  <option value="">-- select project --</option>
                  <?php foreach($projects as $p): ?>
                    <option value="<?= intval($p['id']) ?>"><?= htmlspecialchars($p['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label>
                <div class="label-text">Name *</div>
                <input name="name" required placeholder="Dataset name (logical)" />
              </label>

              <label>
                <div class="label-text">Role</div>
                <select name="role" id="dataset_role">
                    <option value="train">Training data</option>
                    <option value="production">Production data</option>
               </select>
              </label>


              <label>
                <div class="label-text">Description</div>
                <textarea name="description" placeholder="Short description (optional)"></textarea>
              </label>

              <label>
                <div class="label-text">Connection type</div>
                <select name="connection_type" id="connection_type">
                  <option value="csv_file">CSV file (upload)</option>
                  <option value="treads_db">Treads DB (upload .sql)</option>
                  <option value="other">Other (connection string)</option>
                </select>
              </label>

              <div id="fileUploadRow">
                <label>
                  <div class="label-text">File (CSV / SQL)</div>
                  <input type="file" name="file" accept=".csv,.txt,.sql,.zip" />
                </label>
              </div>

              <div id="connStringRow" style="display:none">
                <label>
                  <div class="label-text">Connection info (other)</div>
                  <textarea name="connection_string" placeholder="Connection string / JSON / notes"></textarea>
                </label>
              </div>

              <label>
                <div class="label-text">Tags (comma separated)</div>
                <input name="tags" placeholder="mlops,anomaly,production" />
              </label>

              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

              <div class="modal-actions">
                <button type="submit" class="primary-btn">Create dataset</button>
                <button type="button" class="btn" data-close>Cancel</button>
              </div>
            </form>
          </div>
        </div>
        <!-- /modal -->

      </main>
    </div>
  </div>

  <script src="../assets/js/dashboard.js" defer></script>
  <script src="../assets/js/projects-modal.js" defer></script>
  <!-- small page-local script to toggle file vs conn string -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const openBtn = document.getElementById('btnOpenCreate');
      const btnCreateFirst = document.getElementById('btnCreateFirst');
      const modal = document.getElementById('createModal');
      const overlay = modal && modal.querySelector('.modal-overlay');
      const closeButtons = modal && modal.querySelectorAll('[data-close]');
      const connectionType = document.getElementById('connection_type');
      const fileRow = document.getElementById('fileUploadRow');
      const connRow = document.getElementById('connStringRow');

      function showModal() {
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        modal.classList.add('open');
        // focus first input
        const f = modal.querySelector('select[name="project_id"], input[name="name"]');
        if (f) f.focus();
      }
      function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        modal.classList.remove('open');
      }
      if (openBtn) openBtn.addEventListener('click', showModal);
      if (btnCreateFirst) btnCreateFirst.addEventListener('click', showModal);
      if (closeButtons) closeButtons.forEach(b => b.addEventListener('click', closeModal));
      if (overlay) overlay.addEventListener('click', closeModal);
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

      function updateRows() {
        const v = connectionType.value;
        if (v === 'other') {
          fileRow.style.display = 'none';
          connRow.style.display = 'block';
        } else {
          fileRow.style.display = 'block';
          connRow.style.display = 'none';
        }
      }
      connectionType.addEventListener('change', updateRows);
      updateRows();
    });
  </script>
</body>
</html>
