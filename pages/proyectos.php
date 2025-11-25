<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/../config/config.php';

// Fetch owners for owner select in modal (optional)
$owners = [];
try {
    $owners = $pdo->query("SELECT id, username FROM usuarios ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $owners = [];
}

// Ensure CSRF token exists for forms (delete & modal)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// Fetch current user
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id, username, nombre_completo, rol FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

// Flash messages
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Basic search & filter
$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? ''); // draft|active|archived or empty = all
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build WHERE clause safely
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE :q OR description LIKE :q OR tags LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if (in_array($status, ['draft','active','archived'])) {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

// Exclude soft-deleted rows if column exists (best-effort)
try {
    $hasDeletedAt = false;
    $cols = $pdo->query("SHOW COLUMNS FROM proyectos LIKE 'deleted_at'")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($cols)) $hasDeletedAt = true;
    if ($hasDeletedAt) {
        $where[] = "deleted_at IS NULL";
    }
} catch (Exception $e) {
    // ignore
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// total count for pagination
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM proyectos {$where_sql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

// fetch page
try {
    $sql = "SELECT p.*, u.username AS owner_username
            FROM proyectos p
            LEFT JOIN usuarios u ON u.id = p.owner_id
            {$where_sql}
            ORDER BY updated_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    // bind params
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// helper for building query string for pagination links
function query_with($overrides = []) {
    $qs = array_merge($_GET, $overrides);
    return http_build_query($qs);
}

// simple slugify helper (PHP usage elsewhere)
function slugify($s){
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower($s));
    $s = trim($s, '-');
    return $s ?: 'project';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Projects — KalTire MLOps</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="page-wrapper">
    <div class="app">

      <!-- Sidebar -->
      <?php
        $partial = __DIR__ . '/../partials/sidebar.php';
        if (file_exists($partial)) {
            include $partial;
        } else {
            echo '<aside class="sidebar"><div class="brand"><img src="../assets/img/logo_kaltire.png" class="brand-logo" alt="logo"><div class="brand-info"><h1>KalTire MLOps</h1><div class="muted">Control Center</div></div></div><nav class="nav-wrapper"><div class="nav-scrollable"><a href="../dashboard.php">Dashboard</a><a href="proyectos.php">Projects</a></div><div class="sidebar-footer"><a class="cta" href="proyecto_new.php">+ New Project</a></div></nav></aside>';
        }
      ?>

      <main class="main">
        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <h2 style="margin:0">Projects</h2>
          <div>
            <!-- open modal button -->
            <button id="btnOpenCreate" type="button" class="primary-btn">+ New Project</button>
          </div>
        </div>

        <!-- Flash message -->
        <?php if($flash): ?>
          <div style="margin-bottom:12px; padding:10px; border-radius:8px; background:<?= $flash['type']=='success' ? 'rgba(40,167,69,0.12)' : 'rgba(220,53,69,0.08)' ?>; color:<?= $flash['type']=='success' ? '#28a745' : '#dc3545' ?>">
            <?= htmlspecialchars($flash['message']) ?>
          </div>
        <?php endif; ?>

        <!-- Filter form -->
        <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
          <input type="search" name="q" placeholder="Search projects or tags..." value="<?= htmlspecialchars($search) ?>" style="padding:8px;border-radius:8px;background:var(--glass);border:none;color:#ddd" />
          <select name="status" style="padding:8px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.03);color:#ddd">
            <option value="">All status</option>
            <option value="draft" <?= $status==='draft' ? 'selected' : '' ?>>Draft</option>
            <option value="active" <?= $status==='active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $status==='archived' ? 'selected' : '' ?>>Archived</option>
          </select>
          <button type="submit" class="btn">Filter</button>
        </form>

        <!-- Projects display -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
          <?php if(empty($rows)): ?>
            <div class="card">
              <p class="muted">No projects found.</p>
              <button id="btnOpenCreateFirst" class="primary-btn" type="button">Create your first project</button>
            </div>
          <?php else: ?>
            <?php foreach($rows as $r): ?>
              <div class="card project">
                <!-- Project info -->
                <div>
                  <div class="p-title"><?= htmlspecialchars($r['name']) ?></div>
                </div>

                <!-- Project meta data: Owner and Updated Time in the same line -->
                <div class="meta-info">
                  <div class="owner">
                    <strong>Owner:</strong> <?= htmlspecialchars($r['owner_username']) ?>
                  </div>
                  <div class="updated-at">
                    <strong>Updated at:</strong> <?= htmlspecialchars($r['updated_at']) ?>
                  </div>
                </div>

                <!-- Project status -->
                <div class="badge"><?= htmlspecialchars($r['status']) ?></div>

                <!-- Project description -->
                <div class="muted" style="font-size:13px;min-height:36px;"><?= nl2br(htmlspecialchars($r['description'] ?: '')) ?></div>

                <!-- Action buttons in one line -->
                <div class="actions">
                  <a href="proyectos_view.php?id=<?= intval($r['id']) ?>" class="btn">Manage</a>
                  <a href="proyecto_edit.php?id=<?= intval($r['id']) ?>" class="btn">Edit</a>
                  <?php if(isset($user['rol']) && $user['rol'] === 'admin'): ?>
                    <form method="POST" action="../actions/project_delete.php" style="display:inline" onsubmit="return confirm('Delete project <?= htmlspecialchars($r['name']) ?>?');">
                      <input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <button type="submit" class="btn" style="background:rgba(220,53,69,0.06);color:#ff7b7b">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php
          $totalPages = max(1, ceil($total / $perPage));
        ?>
        <?php if($totalPages > 1): ?>
          <div style="margin-top:12px;display:flex;gap:8px;align-items:center;justify-content:center">
            <?php for($p=1;$p<=$totalPages;$p++): ?>
              <a href="?<?= query_with(['page'=>$p]) ?>" class="btn" style="<?= $p===$page ? 'background:var(--kaltire-orange);color:#111' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>

        <!-- Create Project Modal -->
        <div id="createModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
          <div class="modal-overlay" data-close></div>
          <div class="modal-dialog" role="document" aria-labelledby="modalTitle">
            <button class="modal-close" aria-label="Close" data-close>×</button>

            <form class="modal-form" method="POST" action="../actions/project_store.php" novalidate>
              <h3 id="modalTitle">Create project</h3>

              <label>
                <div class="label-text">Name *</div>
                <input name="name" required placeholder="Project name" />
              </label>

              <label>
                <div class="label-text">Description</div>
                <textarea name="description" placeholder="Short description"></textarea>
              </label>

              <div class="form-row" style="display:flex;gap:12px;">
                <label style="flex:1">
                  <div class="label-text">Data version</div>
                  <input name="data_version" placeholder="e.g. v1.0 or commit-hash" />
                </label>

                <label style="flex:1">
                  <div class="label-text">Prod dataset</div>
                  <input name="prod_dataset" placeholder="Prod dataset id/name" />
                </label>
              </div>

              <div class="form-row" style="display:flex;gap:12px;">
                <label style="flex:1">
                  <div class="label-text">Status</div>
                  <select name="status">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                  </select>
                </label>

                <label style="flex:1">
                  <div class="label-text">Tags (comma separated)</div>
                  <input name="tags" placeholder="mlops,anomaly,demo" />
                </label>
              </div>

              <label>
                <div class="label-text">Owner (optional)</div>
                <select name="owner_id">
                  <option value="">-- none --</option>
                  <?php foreach($owners as $ow): ?>
                    <option value="<?= intval($ow['id']) ?>"><?= htmlspecialchars($ow['username']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

              <div class="modal-actions">
                <button type="submit" class="primary-btn">Create project</button>
                <button type="button" class="primary-btn" data-close>Close</button>
              </div>
            </form>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add the JavaScript at the end of the body -->
  <script src="../assets/js/projects-modal.js"></script>
</body>
</html>
