<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';

/* ------------------------------------------------------------
   CSRF VALIDATION
------------------------------------------------------------ */
if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Invalid CSRF token.'
    ];
    header("Location: ../pages/proyectos.php");
    exit();
}

/* ------------------------------------------------------------
   INPUT VALIDATION
------------------------------------------------------------ */
$project_id     = intval($_POST['project_id'] ?? 0);
$exp_name       = trim($_POST['experiment_name'] ?? '');
$model_name     = trim($_POST['model_name'] ?? '');
$dataset_train  = trim($_POST['dataset_train'] ?? '');
$dataset_prod   = trim($_POST['dataset_prod'] ?? '');
$config_json    = trim($_POST['config_json'] ?? '');

if (!$project_id || $exp_name === '' || $model_name === '' ||
    $dataset_train === '' || $dataset_prod === '') {

    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Missing required fields.'
    ];
    header("Location: ../pages/proyectos_view.php?id=" . $project_id);
    exit();
}

/* Validate JSON */
if ($config_json !== '' && json_decode($config_json) === null && json_last_error() !== JSON_ERROR_NONE) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Config JSON is invalid.'
    ];
    header("Location: ../pages/proyectos_view.php?id=" . $project_id);
    exit();
}

$user_id = $_SESSION['user_id'];

/* ------------------------------------------------------------
   EXECUTE NEW STORED PROCEDURE create_experiment
------------------------------------------------------------ */

try {

    /**
     * NEW STORED PROCEDURE SIGNATURE:
     *
     * CALL create_experiment(
     *    p_project_id,
     *    p_experiment_name,
     *    p_dataset_train,
     *    p_dataset_prod,
     *    p_model_name,
     *    p_model_version_label,
     *    p_created_by,
     *    p_config_json
     * )
     */

    $stmt = $pdo->prepare("
        CALL create_experiment(
            :project_id,
            :experiment_name,
            :dataset_train,
            :dataset_prod,
            :model_name,
            :model_version_label,
            :created_by,
            :config_json
        )
    ");

    $stmt->execute([
        ':project_id'            => $project_id,
        ':experiment_name'       => $exp_name,
        ':dataset_train'         => $dataset_train,
        ':dataset_prod'          => $dataset_prod,
        ':model_name'            => $model_name,
        ':model_version_label'   => 'v1',
        ':created_by'            => $user_id,
        ':config_json'           => $config_json
    ]);

    $stmt->closeCursor();

    /* ------------------------------------------------------------
       INSERT ACTIVITY LOG
    ------------------------------------------------------------ */
    $msg = "New experiment created: <strong>{$exp_name}</strong>";
    $a = $pdo->prepare("INSERT INTO activity (message, created_at) VALUES (?, NOW())");
    $a->execute([$msg]);

    /* ------------------------------------------------------------
       SUCCESS
    ------------------------------------------------------------ */
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Experiment created successfully.'
    ];

    header("Location: ../pages/proyectos_view.php?id=" . $project_id);
    exit();

} catch (Exception $e) {

    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => "Error creating experiment: " . $e->getMessage()
    ];

    header("Location: ../pages/proyectos_view.php?id=" . $project_id);
    exit();
}

?>
