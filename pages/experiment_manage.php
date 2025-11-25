<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';

if (!isset($_GET['id'])) {
    die("Experiment ID required.");
}

$experiment_id = intval($_GET['id']);

/* ------------------------------------------------------------
   FETCH EXPERIMENT
------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT e.*, 
           m.name AS model_name,
           mv.version_label AS model_version_label,
           u.username AS created_by_username,
           p.name AS project_name,
           p.id AS project_id
    FROM experiments e
    LEFT JOIN models m ON e.model_id = m.id
    LEFT JOIN model_versions mv ON e.model_version_id = mv.id
    LEFT JOIN usuarios u ON e.created_by = u.id
    LEFT JOIN proyectos p ON p.id = e.project_id
    WHERE e.id = ?
");
$stmt->execute([$experiment_id]);
$exp = $stmt->fetch();

if (!$exp) {
    die("Experiment not found.");
}

/* ------------------------------------------------------------
   FETCH DATASETS
------------------------------------------------------------ */
$dstmt = $pdo->prepare("
    SELECT mvd.dataset_role, d.name
    FROM model_version_datasets mvd
    LEFT JOIN datasets d ON d.id = mvd.dataset_id
    WHERE mvd.model_version_id = ?
");
$dstmt->execute([$exp["model_version_id"]]);
$datasets = $dstmt->fetchAll();

$train_ds = "—";
$prod_ds  = "—";

foreach ($datasets as $ds) {
    if ($ds["dataset_role"] === "train") $train_ds = $ds["name"];
    if ($ds["dataset_role"] === "production") $prod_ds = $ds["name"];
}

/* ------------------------------------------------------------
   FETCH ACTIVITY LOG
------------------------------------------------------------ */
$log_stmt = $pdo->prepare("
    SELECT message, created_at
    FROM activity
    WHERE message LIKE ?
    ORDER BY created_at DESC
");
$log_stmt->execute(["%{$exp['name']}%"]);
$logs = $log_stmt->fetchAll();

/* Pretty print JSON */
$config_json_pretty = "";
if ($exp["config_json"]) {
    $decoded = json_decode($exp["config_json"], true);
    $config_json_pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Experiment — <?= htmlspecialchars($exp['name']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="page-wrapper">
    <div class="app">

        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="main">

            <!-- TITLE -->
            <div class="page-header" style="justify-content: space-between; align-items:center;">
                <h1><?= htmlspecialchars($exp['name']) ?></h1>

                <a href="proyectos_view.php?id=<?= $exp['project_id'] ?>" class="btn">
                    ← Back to Project
                </a>
            </div>

            <!-- INFO CARD -->
            <div class="card mt-3">
                <h2>Experiment Information</h2>

                <p><strong>Project:</strong>
                    <a class="link" href="proyectos_view.php?id=<?= $exp['project_id'] ?>">
                        <?= htmlspecialchars($exp["project_name"]) ?>
                    </a>
                </p>

                <p><strong>Model:</strong>
                    <?= htmlspecialchars($exp["model_name"]) ?>
                    (<?= htmlspecialchars($exp["model_version_label"]) ?>)
                </p>

                <p><strong>Status:</strong>
                    <span class="status <?= $exp['status'] ?>">
                        <?= htmlspecialchars($exp["status"]) ?>
                    </span>
                </p>

                <p><strong>Created by:</strong> 
                    <?= htmlspecialchars($exp["created_by_username"]) ?>
                </p>

                <p class="meta">
                    Created at: <?= htmlspecialchars($exp["created_at"]) ?>
                </p>
            </div>


            <!-- DATASETS CARD -->
            <div class="card mt-3">
                <h2>Datasets Used</h2>

                <p><strong>Train Dataset:</strong> <?= htmlspecialchars($train_ds) ?></p>
                <p><strong>Production Dataset:</strong> <?= htmlspecialchars($prod_ds) ?></p>
            </div>


            <!-- CONFIG JSON -->
            <div class="card mt-3">
                <h2>Experiment Config</h2>

                <?php if ($config_json_pretty): ?>
                    <pre style="
                        background:#0c0e11;
                        padding:16px;
                        border-radius:12px;
                        overflow:auto;
                        white-space:pre-wrap;
                        max-height:400px;
                    "><?= htmlspecialchars($config_json_pretty) ?></pre>
                <?php else: ?>
                    <p class="muted">No config JSON stored.</p>
                <?php endif; ?>
            </div>


            <!-- ACTIVITY LOG -->
            <div class="card mt-3">
                <h2>Activity</h2>

                <?php if (empty($logs)): ?>
                    <p class="muted">No activity logged for this experiment.</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($logs as $log): ?>
                            <li>
                                <div class="act-msg"><?= $log["message"] ?></div>
                                <div class="tiny"><?= $log["created_at"] ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </main>

    </div>
</div>

</body>
</html>
