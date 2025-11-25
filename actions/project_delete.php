<?php
// actions/project_delete.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/../config/config.php';

// only admin allowed
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['rol'] !== 'admin') {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Unauthorized'];
    header('Location: ../pages/proyectos.php');
    exit();
}

// CSRF & id
$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Invalid CSRF token'];
    header('Location: ../pages/proyectos.php');
    exit();
}
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Invalid project id'];
    header('Location: ../pages/proyectos.php');
    exit();
}

try {
    $s = $pdo->prepare("DELETE FROM proyectos WHERE id = ?");
    $s->execute([$id]);
    $_SESSION['flash'] = ['type'=>'success','message'=>'Project deleted'];
} catch (Exception $e) {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Error deleting project'];
}
header('Location: ../pages/proyectos.php');
exit();
