<?php
// dashboard.php
// Improved dashboard — uses a reusable sidebar partial.
// Comments in English

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/config.php';

// Fetch current user (safe fallback)
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id, username, nombre_completo, email, rol FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user = null;
}

// Safe count helper: sanitize table name and return 0 on error
function safe_count($pdo, $table) {
    $table_sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table_sanitized === '') return 0;
    try {
        $sql = "SELECT COUNT(*) AS c FROM `{$table_sanitized}`";
        $stmt = $pdo->query($sql);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? intval($r['c']) : 0;
    } catch (Exception $e) {
        return 0;
    }
}

$projects_count     = safe_count($pdo, 'proyectos');
$datasets_count     = safe_count($pdo, 'datasets');
$models_count       = safe_count($pdo, 'modelos');
$deploy_count       = safe_count($pdo, 'deployments');
$experiments_count  = safe_count($pdo, 'experiments');

// Try to fetch recent projects
$rows = [];
try {
    $stmt = $pdo->query("SELECT id, name, data_version, prod_dataset, updated_at FROM proyectos ORDER BY updated_at DESC LIMIT 6");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// Active experiments (running / queued)
$exps = [];
try {
    $s = $pdo->query("SELECT id, name, status, progress FROM experiments WHERE status IN ('running','queued') ORDER BY id DESC LIMIT 5");
    $exps = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $exps = [];
}

// Recent activities
$recent_activities = [];
try {
    $stmt = $pdo->query("SELECT message, created_at FROM activity ORDER BY created_at DESC LIMIT 8");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>MLOps Panel — Dashboard</title>

  <!-- load external stylesheet from assets -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="page-wrapper">
    <div class="app">

      <!-- SIDEBAR include (shared partial) -->
      <?php
        $partial = __DIR__ . '/partials/sidebar.php';
        if (file_exists($partial)) {
            // Make $user available inside the partial
            include $partial;
        } else {
            // fallback: small inline sidebar to avoid fatal/notice in case file missing
            echo '<aside class="sidebar"><div class="brand"><img src="assets/img/logo_kaltire.png" class="brand-logo" alt="logo"><div class="brand-info"><h1>KalTire MLOps</h1><div class="muted">Control Center</div></div></div><nav class="nav-wrapper"><div class="nav-scrollable"><a href="dashboard.php">Dashboard</a><a href="pages/proyectos.php">Projects</a></div><div class="sidebar-footer"><a class="cta" href="pages/proyectos.php">+ New Project</a></div></nav></aside>';
        }
      ?>

      <!-- MAIN -->
      <main class="main" role="main">
        <div class="topbar">
          <div class="top-left">
            <button id="btnToggleSidebar" aria-label="Toggle sidebar" class="hamburger">☰</button>
            <div>
              <div class="greeting">Welcome back<?php echo $user ? ', ' . htmlspecialchars($user['username']) : ''; ?> 👋</div>
              <div class="muted small">Overview of your MLOps workspace</div>
            </div>
          </div>

          <div class="top-actions">
            <div class="search" role="search">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              <input type="search" placeholder="Search projects, models..." aria-label="Search projects">
            </div>

            <!-- user menu -->
            <div class="user-menu" aria-haspopup="true" aria-expanded="false">
              <button id="userBtn" class="user-btn" aria-label="Open user menu">
                <span class="avatar"><?= htmlspecialchars(strtoupper(substr($user['username'] ?? 'U', 0, 1))) ?></span>
                <span class="user-name"><?= htmlspecialchars($user['username'] ?? 'user') ?></span>
              </button>
              <div id="userMenuDropdown" class="user-dropdown" role="menu" aria-hidden="true">
                <a href="pages/profile.php">Profile</a>
                <a href="logout.php">Logout</a>
              </div>
            </div>

          </div>
        </div>

        <!-- KPIs -->
        <section class="kpis">
          <div class="card">
            <div class="kpi-title">Projects</div>
            <div class="kpi-value"><?php echo $projects_count; ?></div>
          </div>
          <div class="card">
            <div class="kpi-title">Datasets</div>
            <div class="kpi-value"><?php echo $datasets_count; ?></div>
          </div>
          <div class="card">
            <div class="kpi-title">Models</div>
            <div class="kpi-value"><?php echo $models_count; ?></div>
          </div>
          <div class="card">
            <div class="kpi-title">Active Deployments</div>
            <div class="kpi-value"><?php echo $deploy_count; ?></div>
          </div>
        </section>

        <!-- Projects + Right widgets -->
        <section class="projects">
          <div class="project-list card">
            <h3 class="section-title">Recent Projects</h3>

            <?php if (empty($rows)): ?>
              <div class="empty-state">
                <p class="muted">No projects found yet.</p>
                <p>Create your first project to track datasets, models and deployments.</p>
                <a href="pages/proyectos.php" class="primary-btn">Create Project</a>
              </div>
            <?php else: ?>
              <?php foreach($rows as $r): ?>
                <div class="project">
                  <div class="meta">
                    <div class="p-title"><?php echo htmlspecialchars($r['name']); ?></div>
                    <div class="muted small"><?php echo 'Data v: ' . htmlspecialchars($r['data_version'] ?? 'n/a'); ?> • <?php echo 'Prod dataset: ' . htmlspecialchars($r['prod_dataset'] ?? 'n/a'); ?></div>
                    <div class="muted tiny"><?php echo isset($r['updated_at']) ? $r['updated_at'] : ''; ?></div>
                  </div>
                  <div style="text-align:right">
                    <div class="badge">Open</div>
                    <div style="height:6px"></div>
                    <a href="pages/proyectos.php?id=<?php echo intval($r['id']); ?>" class="link">Manage →</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <aside class="widgets">
            <div class="widget card">
              <h4>Active Experiments</h4>
              <div class="muted small">Running experiments and training jobs.</div>
              <div class="list">
                <?php if(empty($exps)): ?>
                  <div class="muted">No active experiments.</div>
                <?php else: ?>
                  <?php foreach($exps as $e): ?>
                    <div class="exp-row">
                      <div class="exp-title"><?php echo htmlspecialchars($e['name']); ?></div>
                      <div class="exp-status"><?php echo htmlspecialchars($e['status']); ?></div>
                      <div class="progress-bar" aria-hidden="true"><div style="width:<?php echo isset($e['progress']) ? intval($e['progress']) : 0; ?>%"></div></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="widget card">
              <h4>Recent Activity</h4>
              <?php if(empty($recent_activities)): ?>
                <div class="muted">No activity to show.</div>
              <?php else: ?>
                <ul class="activity-list">
                  <?php foreach($recent_activities as $act): ?>
                    <li>
                      <div class="act-msg"><?php echo htmlspecialchars($act['message']); ?></div>
                      <div class="muted tiny"><?php echo htmlspecialchars($act['created_at']); ?></div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>

            <div class="widget card">
              <h4>Quick Actions</h4>
              <div class="actions">
                <a href="pages/datasets.php" class="btn">Upload Dataset</a>
                <a href="pages/modelos.php" class="btn primary">Train Model</a>
                <a href="pages/deployments.php" class="btn">Deploy</a>
              </div>
            </div>

          </aside>
        </section>

        <footer class="app-footer">
          KalTire MLOps • version 0.1 • <?php echo date('Y'); ?>
        </footer>
      </main>

    </div>
  </div>

  <!-- load external JS -->
  <script src="assets/js/dashboard.js" defer></script>
</body>
</html>
