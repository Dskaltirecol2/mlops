<?php
// partials/sidebar.php
// Reusable sidebar partial with inline SVG icons and robust base URL detection.

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
$base = preg_replace('#/pages(?:/.*)?$#', '', $scriptDir);
if ($base === '/' || $base === '\\') $base = '';
$base = rtrim($base, '/');

$logo = ($base === '' ? '' : $base) . '/assets/img/logo_kaltire.png';
$dashboard_link = ($base === '' ? '' : $base) . '/dashboard.php';
$projects_link  = ($base === '' ? '' : $base) . '/pages/proyectos.php';
$datasets_link  = ($base === '' ? '' : $base) . '/pages/datasets.php';
$models_link    = ($base === '' ? '' : $base) . '/pages/modelos.php';
$metrics_link   = ($base === '' ? '' : $base) . '/pages/metricas.php';
$experiments_link = ($base === '' ? '' : $base) . '/pages/experiments.php';
$deployments_link = ($base === '' ? '' : $base) . '/pages/deployments.php';
$users_link     = ($base === '' ? '' : $base) . '/pages/usuarios.php';

// create link points to projects with new=1 so the projects page can open the modal automatically
$create_link    = $projects_link . '?new=1';

if (!isset($user) && !empty($_SESSION['user_id'])) {
    $user = ['id' => $_SESSION['user_id']];
}
?>
<aside class="sidebar" aria-label="sidebar">
  <div class="brand">
    <img src="<?= htmlspecialchars($logo) ?>" alt="KalTire" class="brand-logo">
    <div class="brand-info">
      <h1>KalTire MLOps</h1>
      <div class="muted">Control Center</div>
    </div>
  </div>

  <nav class="nav-wrapper" role="navigation" aria-label="Main Navigation">
    <div class="nav-scrollable" role="menu" aria-label="Main menu">

      <!-- Dashboard -->
      <a href="<?= htmlspecialchars($dashboard_link) ?>" aria-label="Dashboard" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 13.5L12 6l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-6.5z"/>
          </svg>
        </span>
        <span class="nav-label">Dashboard</span>
      </a>

      <!-- Projects -->
      <a href="<?= htmlspecialchars($projects_link) ?>" aria-label="Projects" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>
          </svg>
        </span>
        <span class="nav-label">Projects</span>
      </a>

      <!-- Datasets -->
      <a href="<?= htmlspecialchars($datasets_link) ?>" aria-label="Datasets" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 5h18v2H3V5zm0 4h18v2H3V9zm0 4h18v2H3v-2z"/>
          </svg>
        </span>
        <span class="nav-label">Datasets</span>
      </a>

      <!-- Models -->
      <a href="<?= htmlspecialchars($models_link) ?>" aria-label="Models" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2l8 4v8l-8 4-8-4V6l8-4z"/>
          </svg>
        </span>
        <span class="nav-label">Models</span>
      </a>

      <!-- Metrics -->
      <a href="<?= htmlspecialchars($metrics_link) ?>" aria-label="Metrics" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none">
            <path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M7 13v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 7v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M17 10v9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="nav-label">Metrics</span>
      </a>

      <!-- Experiments -->
      <a href="<?= htmlspecialchars($experiments_link) ?>" aria-label="Experiments" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 7h10l-2 10H9L7 7z"/>
          </svg>
        </span>
        <span class="nav-label">Experiments</span>
      </a>

      <!-- Deployments -->
      <a href="<?= htmlspecialchars($deployments_link) ?>" aria-label="Deployments" role="menuitem">
        <span class="nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2l3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7z"/>
          </svg>
        </span>
        <span class="nav-label">Deployments</span>
      </a>

      <?php if (!empty($user) && isset($user['rol']) && $user['rol'] === 'admin'): ?>
        <a href="<?= htmlspecialchars($users_link) ?>" aria-label="Users" role="menuitem">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
              <path d="M4 20a8 8 0 0 1 16 0"/>
            </svg>
          </span>
          <span class="nav-label">Users</span>
        </a>
      <?php endif; ?>

    </div> <!-- /.nav-scrollable -->

    <div class="sidebar-footer">
      <!-- id and data-new used by projects-modal.js to intercept/open modal when appropriate -->
      <a id="sidebarNewProject" class="cta" href="<?= htmlspecialchars($create_link) ?>" data-new="1">+ New Project</a>
    </div>
  </nav>
</aside>
