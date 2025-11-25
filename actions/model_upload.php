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
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid CSRF token.'];
    header("Location: ../pages/proyectos.php");
    exit();
}

/* ------------------------------------------------------------
   INPUTS
------------------------------------------------------------ */
$model_id     = intval($_POST['model_id'] ?? 0);
$version_id   = intval($_POST['version_id'] ?? 0);   // <--- NEW
$version_label = trim($_POST['version_label'] ?? '');
$notes        = trim($_POST['notes'] ?? '');
$file         = $_FILES['model_file'] ?? null;

if (!$model_id || !$file) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Missing required fields.'];
    header("Location: ../pages/model_manage.php?id={$model_id}");
    exit();
}

/* ------------------------------------------------------------
   VALIDATE FILE EXTENSION
------------------------------------------------------------ */
$allowed_ext = ['joblib', 'pkl', 'h5', 'pt', 'onnx'];
$filename    = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => "Invalid file type: .$ext"];
    header("Location: ../pages/model_manage.php?id={$model_id}");
    exit();
}

/* ------------------------------------------------------------
   CASE 1 — Updating existing version
------------------------------------------------------------ */
if ($version_id > 0) {

    // fetch version label from database
    $stmt = $pdo->prepare("SELECT version_label FROM model_versions WHERE id = ?");
    $stmt->execute([$version_id]);
    $version_label = $stmt->fetchColumn();

    if (!$version_label) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Version not found.'];
        header("Location: ../pages/model_manage.php?id={$model_id}");
        exit();
    }

    $target_dir = __DIR__ . "/../models/$model_id/$version_label/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $target_file = $target_dir . "model.$ext";

    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to upload file.'];
        header("Location: ../pages/model_manage.php?id={$model_id}");
        exit();
    }

    // update record
    $upd = $pdo->prepare("
        UPDATE model_versions
        SET artifact_path = ?, notes = ?, trained_by = ?, trained_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([$target_file, $notes, $_SESSION['user_id'], $version_id]);

    // log
    $pdo->prepare("INSERT INTO activity (message, created_at) VALUES (?, NOW())")
        ->execute(["Replaced artifact of model version <strong>$version_label</strong>"]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => "Artifact updated for version <strong>$version_label</strong>."];
    header("Location: ../pages/model_manage.php?id={$model_id}");
    exit();
}

/* ------------------------------------------------------------
   CASE 2 — Creating new version
------------------------------------------------------------ */

if ($version_label === '') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Version label is required.'];
    header("Location: ../pages/model_manage.php?id={$model_id}");
    exit();
}

// check if exists
$check = $pdo->prepare("SELECT id FROM model_versions WHERE model_id = ? AND version_label = ?");
$check->execute([$model_id, $version_label]);
$existing_id = $check->fetchColumn();

if ($existing_id) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => "Version label <strong>$version_label</strong> already exists."];
    header("Location: ../pages/model_manage.php?id={$model_id}");
    exit();
}

// STORAGE
$target_dir = __DIR__ . "/../models/$model_id/$version_label/";
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

$target_file = $target_dir . "model.$ext";
move_uploaded_file($file['tmp_name'], $target_file);

// insert new
$ins = $pdo->prepare("
    INSERT INTO model_versions (model_id, version_label, artifact_path, notes, created_at, trained_at, trained_by)
    VALUES (?, ?, ?, ?, NOW(), NOW(), ?)
");
$ins->execute([$model_id, $version_label, $target_file, $notes, $_SESSION['user_id']]);

// update current version
$pdo->prepare("UPDATE models SET current_version = ? WHERE id = ?")
    ->execute([$version_label, $model_id]);

// log
$pdo->prepare("INSERT INTO activity (message, created_at) VALUES (?, NOW())")
    ->execute(["Uploaded new model version <strong>$version_label</strong>"]);

$_SESSION['flash'] = ['type' => 'success', 'message' => "Model version <strong>$version_label</strong> created successfully."];
header("Location: ../pages/model_manage.php?id={$model_id}");
exit();

?>
