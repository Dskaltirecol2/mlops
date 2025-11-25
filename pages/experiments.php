<?php
// pages/experiments.php
// Experiments page: show list + modal to create an experiment.
// Requires: config/config.php, partials/sidebar.php
// Comments in English.

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';

// load the sidebar user object if needed
$user = ['id' => $_SESSION['user_id']];

// Fetch projects for the project selector
$stmt = $pdo->prepare("SELECT id, name FROM proyectos ORDER BY name ASC");
$stmt->execute();
$projects = $stmt->fetchAll();

// Fetch existing datasets and models for datalist/autocomplete
$stmt = $pdo->prepare("SELECT id, name FROM datasets ORDER BY name ASC");
$stmt->execute();
$datasets = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, name FROM models ORDER BY name ASC");
$stmt->execute();
$models = $stmt->fetchAll();

// Fetch recent experiments (limit 100)
$sql = "
SELECT e.id, e.name AS experiment_name, e.status, e.progress, e.created_at,
       p.id AS project_id, p.name AS project_name,
       d.name AS dataset_name, dv.version_label AS dataset_version_label,
       m.name AS model_name, mv.version_label AS model_version_label
FROM experiments e
LEFT JOIN proyectos p ON p.id = e.project_id
LEFT JOIN datasets d ON d.id = e.dataset_id
LEFT JOIN dataset_versions dv ON dv.id = e.dataset_version_id
LEFT JOIN models m ON m.id = e.model_id
LEFT JOIN model_versions mv ON mv.id = e.model_version_id
ORDER BY e.created_at DESC
LIMIT 100
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$experiments = $stmt->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Experiments — KalTire MLOps</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="page-wrapper">
    <div class="app" id="app-root">
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>

      <main class="main" role="main">
        <div class="topbar">
          <div class="top-left">
            <button class="hamburger" id="btnToggleSidebar" aria-label="Toggle sidebar">☰</button>
            <div>
              <div class="greeting">Welcome back, <strong class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'user') ?></strong></div>
              <div class="tiny muted">Overview of your MLOps workspace</div>
            </div>
          </div>

          <div class="top-actions">
            <div class="search">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="opacity:.7"><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <input id="searchInput" placeholder="Search projects, model..." />
            </div>
            <div class="user-menu">
              <button class="user-btn" id="userBtn" aria-haspopup="true" aria-expanded="false">
                <span class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U',0,1)) ?></span>
                <span class="user-name tiny"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
              </button>
            </div>
          </div>
        </div>

        <section class="kpis">
          <div class="card"><div class="kpi-title">Projects</div><div class="kpi-value"><?= count($projects) ?></div></div>
          <div class="card"><div class="kpi-title">Datasets</div><div class="kpi-value"><?= count($datasets) ?></div></div>
          <div class="card"><div class="kpi-title">Models</div><div class="kpi-value"><?= count($models) ?></div></div>
          <div class="card"><div class="kpi-title">Experiments</div><div class="kpi-value"><?= count($experiments) ?></div></div>
        </section>

        <section class="projects">
          <div class="project-list">
            <div class="section-title">Recent Experiments</div>

            <?php if (empty($experiments)): ?>
              <div class="empty-state card">
                <p>No experiments found yet.</p>
                <a href="#" class="primary-btn" id="openCreateExperiment">Create Experiment</a>
              </div>
            <?php else: ?>
              <?php foreach ($experiments as $e): ?>
                <div class="project card exp-row" data-id="<?= (int)$e['id'] ?>">
                  <div class="meta">
                    <div class="p-title"><?= htmlspecialchars($e['experiment_name']) ?></div>
                    <div class="tiny muted"><?= htmlspecialchars($e['project_name'] ?? '—') ?> • <?= htmlspecialchars($e['created_at']) ?></div>

                    <?php if (!empty($e['dataset_name'])): ?>
                      <div class="exp-status tiny">Dataset: <?= htmlspecialchars($e['dataset_name']) ?> <?= $e['dataset_version_label'] ? '(' . htmlspecialchars($e['dataset_version_label']) . ')' : '' ?></div>
                    <?php endif; ?>
                    <?php if (!empty($e['model_name'])): ?>
                      <div class="exp-status tiny">Model: <?= htmlspecialchars($e['model_name']) ?> <?= $e['model_version_label'] ? '(' . htmlspecialchars($e['model_version_label']) . ')' : '' ?></div>
                    <?php endif; ?>

                  </div>

                  <div style="min-width:200px;text-align:right;">
                    <div class="badge"><?= htmlspecialchars($e['status'] ?? 'unknown') ?></div>
                    <div style="margin-top:6px;">
                      <a class="link" href="/pages/experiment_detail.php?id=<?= (int)$e['id'] ?>">Open</a>
                    </div>
                    <div style="margin-top:8px;">
                      <div class="progress-bar" aria-hidden="true">
                        <div style="width: <?= (int)$e['progress'] ?>%"></div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <aside class="widgets">
            <div class="widget card">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div><strong>Active Experiments</strong></div>
                <button id="openCreateExperiment2" class="btn primary">New</button>
              </div>
              <p class="tiny muted" style="margin-top:8px;">Start a new experiment by configuring dataset and model versions.</p>
            </div>

            <div class="widget card">
              <strong>Quick Actions</strong>
              <div class="actions" style="margin-top:8px;">
                <a href="#" class="btn" id="openCreateExperiment3">Create Experiment</a>
                <a href="/pages/datasets.php" class="btn">Upload Dataset</a>
                <a href="/pages/modelos.php" class="btn">Create Model</a>
              </div>
            </div>
          </aside>
        </section>

        <footer class="app-footer">KalTire MLOps • version 0.1 • 2025</footer>
      </main>
    </div>
  </div>

  <!-- Modal: Create Experiment -->
  <div class="modal" id="modalCreateExperiment" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-overlay" tabindex="-1"></div>
    <div class="modal-dialog" role="document" aria-describedby="modalDesc">
      <button class="modal-close" id="modalClose" aria-label="Close">×</button>
      <h3 id="modalTitle">Create Experiment</h3>
      <form id="createExperimentForm" class="modal-form" method="post" action="/ajax/create_experiment.php">
        <div>
          <label class="label-text" for="project_id">Project</label>
          <select id="project_id" name="project_id" required>
            <option value="">-- Select a project --</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="label-text" for="name">Experiment name</label>
          <input id="name" name="name" placeholder="e.g. exp-2025-11-05" required />
        </div>

        <div>
          <label class="label-text" for="dataset_name">Dataset name (existing or new)</label>
          <input id="dataset_name" name="dataset_name" list="dataset-list" placeholder="sensor-windows" required />
          <datalist id="dataset-list">
            <?php foreach ($datasets as $d): ?>
              <option value="<?= htmlspecialchars($d['name']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div>
          <label class="label-text" for="dataset_role">Dataset role</label>
          <select id="dataset_role" name="dataset_role">
            <option value="train">train</option>
            <option value="production">production</option>
          </select>
        </div>

        <div>
          <label class="label-text" for="model_name">Model name (existing or new)</label>
          <input id="model_name" name="model_name" list="model-list" placeholder="cnn_v1" required />
          <datalist id="model-list">
            <?php foreach ($models as $m): ?>
              <option value="<?= htmlspecialchars($m['name']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div>
          <label class="label-text" for="model_version_label">Model version label (optional)</label>
          <input id="model_version_label" name="model_version_label" placeholder="baseline" />
        </div>

        <div>
          <label class="label-text" for="config_json">Configuration (JSON) - optional</label>
          <textarea id="config_json" name="config_json" placeholder='{"lr":0.001,"batch":32}'></textarea>
        </div>

        <div>
          <label class="label-text" for="notes">Notes</label>
          <textarea id="notes" name="notes" placeholder="Optional notes about this experiment"></textarea>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn" id="cancelModal">Cancel</button>
          <button type="submit" class="btn primary" id="submitExperiment">Create</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  // Minimal JS: modal behavior + AJAX submit
  (function(){
    const openButtons = document.querySelectorAll('#openCreateExperiment, #openCreateExperiment2, #openCreateExperiment3, #openCreateExperiment4');
    const modal = document.getElementById('modalCreateExperiment');
    const overlay = modal.querySelector('.modal-overlay');
    const closeBtn = document.getElementById('modalClose');
    const cancelBtn = document.getElementById('cancelModal');
    const form = document.getElementById('createExperimentForm');
    const submitBtn = document.getElementById('submitExperiment');

    function openModal(){ modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeModal(){ modal.classList.remove('open'); document.body.style.overflow = ''; }

    openButtons.forEach(b => b && b.addEventListener('click', (ev)=>{ ev.preventDefault(); openModal(); }));
    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });

    // Ajax submit
    form.addEventListener('submit', function(e){
      e.preventDefault();
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating...';

      const data = new FormData(form);
      fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(json => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create';
        if (json && json.success) {
          // Simple success handling: reload page or prepend new row
          window.location.reload();
        } else {
          alert('Error: ' + (json.message || 'Unknown error'));
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create';
        console.error(err);
        alert('Request failed. Check browser console.');
      });
    });

    // small: toggle sidebar (uses .app.collapsed)
    document.getElementById('btnToggleSidebar').addEventListener('click', function(){
      document.getElementById('app-root').classList.toggle('collapsed');
    });

  })();
  </script>
</body>
</html>
