<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';

/* ------------------------------------------------------------
   FETCH MODELS
------------------------------------------------------------ */
$search = trim($_GET['q'] ?? '');
$project = trim($_GET['project'] ?? '');
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "m.name LIKE :q";
    $params[":q"] = "%$search%";
}

if ($project !== '') {
    $where[] = "m.project_id = :p";
    $params[":p"] = $project;
}

$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT m.*, p.name AS project_name
    FROM models m
    LEFT JOIN proyectos p ON p.id = m.project_id
    $where_sql
    ORDER BY m.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$models = $stmt->fetchAll();

/* ------------------------------------------------------------
   FETCH PROJECTS (for filter dropdown)
------------------------------------------------------------ */
$projects = $pdo->query("SELECT id, name FROM proyectos ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Models — KalTire MLOps</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="page-wrapper">
    <div class="app">

        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="main">

            <div class="page-header">
                <h1>Models</h1>
            </div>

            <!-- Filters -->
            <form method="GET" style="display:flex;gap:10px;margin-bottom:15px;">
                <input type="search" name="q" placeholder="Search model name..."
                    value="<?= htmlspecialchars($search) ?>"
                    style="padding:8px;border-radius:8px;background:var(--glass);border:none;color:#ddd;width:200px;">

                <select name="project" style="padding:8px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.05);color:#ddd">
                    <option value="">All projects</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $project == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn">Filter</button>
            </form>

            <!-- Models list -->
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">

                <?php if (empty($models)): ?>
                    <div class="card">
                        <p class="muted">No models found.</p>
                    </div>
                <?php else: ?>

                    <?php foreach ($models as $m): ?>
                        <div class="card">

                            <h3><?= htmlspecialchars($m['name']) ?></h3>

                            <p class="mini">
                                <strong>Project:</strong>
                                <?= htmlspecialchars($m['project_name']) ?><br>

                                <strong>Current Version:</strong>
                                <?= htmlspecialchars($m['current_version'] ?? '—') ?><br>

                                <span class="tiny"><?= $m['created_at'] ?></span>
                            </p>

                            <div class="btn-row">
                                <a href="model_manage.php?id=<?= $m['id'] ?>" class="btn btn-dark">
                                    Manage
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </main>
    </div>
</div>

</body>
</html>
