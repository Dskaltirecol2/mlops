<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';

if (!isset($_GET['id'])) {
    die("Project ID required.");
}

$project_id = intval($_GET['id']);

/* CSRF token */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];

/* ------------------------------------------------------------
   FETCH PROJECT
------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT p.*, u.username AS owner_name
    FROM proyectos p
    LEFT JOIN usuarios u ON p.owner_id = u.id
    WHERE p.id = ? AND p.deleted_at IS NULL
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Project not found.");
}

/* ------------------------------------------------------------
   FETCH EXPERIMENTS
------------------------------------------------------------ */
$exp_stmt = $pdo->prepare("
    SELECT e.*, 
           m.name AS model_name,
           mv.version_label AS model_version_label
    FROM experiments e
    LEFT JOIN models m ON e.model_id = m.id
    LEFT JOIN model_versions mv ON e.model_version_id = mv.id
    WHERE e.project_id = ?
    ORDER BY e.created_at DESC
");
$exp_stmt->execute([$project_id]);
$experiments = $exp_stmt->fetchAll();

/* ------------------------------------------------------------
   FETCH DATASETS FOR EXPERIMENTS
------------------------------------------------------------ */
function getExperimentDatasets($pdo, $model_version_id) {
    $q = $pdo->prepare("
        SELECT mvd.*, d.name AS dataset_name
        FROM model_version_datasets mvd
        LEFT JOIN datasets d ON mvd.dataset_id = d.id
        WHERE mvd.model_version_id = ?
    ");
    $q->execute([$model_version_id]);
    return $q->fetchAll();
}

/* ------------------------------------------------------------
   FETCH MODELS OF PROJECT
------------------------------------------------------------ */
$models_stmt = $pdo->prepare("
    SELECT * FROM models
    WHERE project_id = ?
    ORDER BY created_at DESC
");
$models_stmt->execute([$project_id]);
$models = $models_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Project</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<!-- 🔥 LAYOUT CORRECTO (igual al dashboard) -->
<div class="page-wrapper">
    <div class="app">

        <!-- SIDEBAR (MISMA RUTA QUE DASHBOARD) -->
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <!-- MAIN CONTENT (MISMA CLASE QUE DASHBOARD) -->
        <main class="main">

            <!-- HEADER -->
            <div class="page-header">
                <h1><?= htmlspecialchars($project['name']) ?></h1>

                <button class="btn btn-orange">Edit Project</button>
            </div>

            <!-- PROJECT INFO CARD -->
            <div class="card mt-3">
                <h2>Project Information</h2>

                <p><strong>Owner:</strong> <?= htmlspecialchars($project['owner_name']) ?></p>

                <p>
                    <strong>Status:</strong>
                    <span class="status <?= $project['status'] ?>">
                        <?= htmlspecialchars($project['status']) ?>
                    </span>
                </p>

                <p><strong>Description:</strong><br>
                    <?= nl2br(htmlspecialchars($project['description'])) ?>
                </p>

                <p class="meta">
                    Created at: <?= $project['created_at'] ?><br>
                    Updated at: <?= $project['updated_at'] ?>
                </p>
            </div>


            <!-- EXPERIMENTS SECTION -->
            <div class="section-header mt-5">
                <h2>Experiments</h2>
                <button class="btn btn-orange" id="btnOpenCreateExperiment">+ New Experiment</button>
            </div>

            <?php if (empty($experiments)): ?>
                <p>No experiments yet.</p>
            <?php else: ?>
                <div class="grid-cards">
                    <?php foreach ($experiments as $exp): ?>

                        <?php 
                        $datasets = getExperimentDatasets($pdo, $exp['model_version_id']);
                        $train = null;
                        $prod = null;
                        foreach ($datasets as $ds) {
                            if ($ds['dataset_role'] === 'train') $train = $ds;
                            if ($ds['dataset_role'] === 'production') $prod = $ds;
                        }
                        ?>

                        <div class="card">
                            <h3><?= htmlspecialchars($exp['name']) ?></h3>

                            <p class="mini">
                                Status:
                                <span class="status <?= $exp['status'] ?>">
                                    <?= $exp['status'] ?>
                                </span><br>

                                Model:
                                <?= htmlspecialchars($exp['model_name']) ?>
                                (<?= htmlspecialchars($exp['model_version_label']) ?>)
                            </p>

                            <p class="mini">
                                <strong>Train Dataset:</strong>
                                <?= $train ? htmlspecialchars($train['dataset_name']) : "—" ?><br>

                                <strong>Production Dataset:</strong>
                                <?= $prod ? htmlspecialchars($prod['dataset_name']) : "—" ?>
                            </p>

                            <div class="btn-row">
                                <a href="experiment_manage.php?id=<?= $exp['id'] ?>" class="btn btn-dark">Manage</a>
                                <a href="../actions/experiment_delete.php?id=<?= $exp['id'] ?>&csrf=<?= $csrf ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('Delete experiment?')">Delete</a>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <!-- MODELS SECTION -->
            <div class="section-header mt-5">
                <h2>Models in this Project</h2>
            </div>

            <?php if (empty($models)): ?>
                <p>No models yet.</p>
            <?php else: ?>
                <div class="grid-cards">
                    <?php foreach ($models as $model): ?>
                        <div class="card">
                            <h3><?= htmlspecialchars($model['name']) ?></h3>
                            <p class="mini">Current version: <?= $model['current_version'] ?></p>
                            <a href="model_manage.php?id=<?= $model['id'] ?>" class="btn btn-dark">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- MODAL: NEW EXPERIMENT -->
<div id="modalExperiment" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-overlay" data-close></div>

  <div class="modal-dialog" role="document" aria-labelledby="expModalTitle">
    <button class="modal-close" aria-label="Close" data-close>×</button>

    <form class="modal-form" method="POST" action="../actions/create_experiment.php">

      <h3 id="expModalTitle">Create experiment</h3>

      <label>
        <div class="label-text">Experiment Name *</div>
        <input name="experiment_name" required placeholder="Experiment name">
      </label>

      <label>
        <div class="label-text">Model Name *</div>
        <input name="model_name" required placeholder="Model name">
      </label>

      <label>
        <div class="label-text">Train Dataset Name *</div>
        <input name="dataset_train" required placeholder="Train dataset">
      </label>

      <label>
        <div class="label-text">Production Dataset Name *</div>
        <input name="dataset_prod" required placeholder="Production dataset">
      </label>

      <label>
        <div class="label-text">Config (JSON)</div>
        <textarea name="config_json" placeholder="{ }"></textarea>
      </label>

      <input type="hidden" name="project_id" value="<?= $project_id ?>">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="modal-actions">
        <button type="submit" class="primary-btn">Create</button>
        <button type="button" class="primary-btn" data-close>Close</button>
      </div>

    </form>
  </div>
</div>


<script>
const modalExperiment = document.getElementById("modalExperiment");
const btnOpenModal = document.getElementById("btnOpenCreateExperiment");

// abrir
btnOpenModal.addEventListener("click", () => {
    modalExperiment.classList.add("open");
    modalExperiment.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
});

// cerrar (botones con data-close)
modalExperiment.querySelectorAll("[data-close]").forEach(btn => {
    btn.addEventListener("click", () => {
        modalExperiment.classList.remove("open");
        modalExperiment.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    });
});

// cerrar al hacer click fuera
modalExperiment.querySelector(".modal-overlay").addEventListener("click", () => {
    modalExperiment.classList.remove("open");
    modalExperiment.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
});
</script>

</body>
</html>
