<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';

if (!isset($_GET['id'])) {
    die("Model ID required.");
}

$model_id = intval($_GET['id']);

/* ------------------------------------------------------------
   FETCH MODEL
------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT m.*, p.name AS project_name, p.id AS project_id
    FROM models m
    LEFT JOIN proyectos p ON p.id = m.project_id
    WHERE m.id = ?
");
$stmt->execute([$model_id]);
$model = $stmt->fetch();

if (!$model) {
    die("Model not found.");
}

/* ------------------------------------------------------------
   FETCH MODEL VERSIONS
------------------------------------------------------------ */
$vstmt = $pdo->prepare("
    SELECT mv.*, e.id AS experiment_id, e.name AS experiment_name
    FROM model_versions mv
    LEFT JOIN experiments e ON e.model_version_id = mv.id
    WHERE mv.model_id = ?
    ORDER BY mv.created_at DESC
");
$vstmt->execute([$model_id]);
$versions = $vstmt->fetchAll();

/* ------------------------------------------------------------
   FETCH EXPERIMENTS USING THIS MODEL
------------------------------------------------------------ */
$estmt = $pdo->prepare("
    SELECT e.*
    FROM experiments e
    WHERE e.model_id = ?
    ORDER BY e.created_at DESC
");
$estmt->execute([$model_id]);
$experiments = $estmt->fetchAll();

/* CSRF TOKEN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Model — <?= htmlspecialchars($model['name']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="page-wrapper">
    <div class="app">

        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="main">

            <!-- HEADER -->
            <div class="page-header" style="justify-content:space-between;align-items:center;">
                <h1><?= htmlspecialchars($model['name']) ?></h1>

                <div style="display:flex;gap:10px;">
                    <a href="proyectos_view.php?id=<?= $model['project_id'] ?>" class="btn">← Back</a>

                    <!-- NEW VERSION BUTTON -->
                    <button class="btn btn-orange" 
                            onclick="openUploadModal(null, 'Create New Version')">
                        + New Model Version
                    </button>
                </div>
            </div>

            <!-- MODEL INFO CARD -->
            <div class="card mt-3">
                <h2>Model Information</h2>

                <p><strong>Project:</strong>
                    <a href="proyectos_view.php?id=<?= $model['project_id'] ?>" class="link">
                        <?= htmlspecialchars($model['project_name']) ?>
                    </a>
                </p>

                <p><strong>Current Version:</strong> <?= htmlspecialchars($model['current_version']) ?></p>

                <p class="meta">
                    Created at: <?= htmlspecialchars($model['created_at']) ?>
                </p>
            </div>

            <!-- MODEL VERSIONS -->
            <div class="card mt-3">
                <h2>Model Versions</h2>

                <?php if (empty($versions)): ?>
                    <p class="muted">No versions found.</p>
                <?php else: ?>
                    <div class="grid-cards">
                        <?php foreach ($versions as $v): ?>
                            <div class="card">
                                <h3><?= htmlspecialchars($v['version_label']) ?></h3>

                                <p class="mini">
                                    Created at: <?= htmlspecialchars($v['created_at']) ?><br>

                                    <?php if ($v['experiment_id']): ?>
                                        From experiment:
                                        <a class="link"
                                           href="experiment_manage.php?id=<?= $v['experiment_id'] ?>">
                                           <?= htmlspecialchars($v['experiment_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="muted">No experiment linked</span>
                                    <?php endif; ?>
                                </p>

                                <p class="tiny">
                                    <?= $v['artifact_path'] ? basename($v['artifact_path']) : "No artifact uploaded" ?>
                                </p>

                                <div class="btn-row">
                                    <?php if ($v['artifact_path']): ?>
                                        <a href="<?= $v['artifact_path'] ?>" class="btn btn-dark" download>
                                            Download
                                        </a>
                                        <button class="btn btn-orange"
                                            onclick="openUploadModal(<?= $v['id'] ?>, 'Replace Artifact for <?= $v['version_label'] ?>')">
                                            Replace Artifact
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-orange"
                                            onclick="openUploadModal(<?= $v['id'] ?>, 'Upload Artifact for <?= $v['version_label'] ?>')">
                                            Upload Artifact
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>


<!-- MODAL: UPLOAD MODEL ARTIFACT -->
<div id="uploadModal" class="modal" aria-hidden="true">
    <div class="modal-overlay" data-close></div>

    <div class="modal-dialog">
        <button class="modal-close" data-close>×</button>

        <form class="modal-form" method="POST" action="../actions/model_upload.php"
              enctype="multipart/form-data">

            <h3 id="uploadTitle">Upload Model Version</h3>

            <input type="hidden" name="model_id" value="<?= $model_id ?>">
            <input type="hidden" name="version_id" id="version_id" value="">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">

            <label>
                <div class="label-text">Model File *</div>
                <input type="file" name="model_file"
                    accept=".joblib,.pkl,.h5,.pt,.onnx"
                    required>
            </label>

            <label id="versionLabelWrapper">
                <div class="label-text">Version Label *</div>
                <input type="text" name="version_label" id="version_label" placeholder="v2">
            </label>

            <label>
                <div class="label-text">Notes (optional)</div>
                <textarea name="notes" placeholder="Training details or comments..."></textarea>
            </label>

            <div class="modal-actions">
                <button type="submit" class="primary-btn">Upload</button>
                <button type="button" class="primary-btn" data-close>Cancel</button>
            </div>

        </form>
    </div>
</div>


<!-- MODAL JS -->
<script>
function openUploadModal(versionId, title) {
    const modal = document.getElementById("uploadModal");
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    document.getElementById("uploadTitle").textContent = title;

    document.getElementById("version_id").value = versionId ?? "";

    // If editing existing version → hide version label field
    if (versionId) {
        document.getElementById("versionLabelWrapper").style.display = "none";
        document.getElementById("version_label").required = false;
    } else {
        document.getElementById("versionLabelWrapper").style.display = "block";
        document.getElementById("version_label").required = true;
    }
}

// close modal
document.querySelectorAll("[data-close]").forEach(btn => {
    btn.addEventListener("click", () => {
        const modal = document.getElementById("uploadModal");
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    });
});
</script>

</body>
</html>
