<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/../config/config.php';

// basic CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Invalid CSRF token.'];
    header('Location: ../pages/proyecto_new.php');
    exit();
}

// Collect & validate form data
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$data_version = trim($_POST['data_version'] ?? '');
$prod_dataset = trim($_POST['prod_dataset'] ?? '');
$status = in_array($_POST['status'] ?? 'draft', ['draft','active','archived']) ? $_POST['status'] : 'draft';
$tags = trim($_POST['tags'] ?? '');
$owner_id = empty($_POST['owner_id']) ? null : intval($_POST['owner_id']);

if ($name === '') {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Project name is required.'];
    header('Location: ../pages/proyecto_new.php');
    exit();
}

// generate slug and ensure uniqueness (append suffix if exists)
function slugify($s) {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower($s));
    $s = trim($s, '-');
    return $s ?: 'project';
}

$slugBase = slugify($name);
$slug = $slugBase;
$ix = 1;
while (true) {
    $stmt = $pdo->prepare("SELECT id FROM proyectos WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    if (!$stmt->fetch()) break;
    $slug = $slugBase . '-' . $ix++;
}

// insert the project into the database
try {
    $stmt = $pdo->prepare("INSERT INTO proyectos (name, slug, description, owner_id, data_version, prod_dataset, status, tags, created_by, created_at, updated_at)
        VALUES (:name, :slug, :description, :owner_id, :data_version, :prod_dataset, :status, :tags, :created_by, NOW(), NOW())");
    
    // Insert project data
    $stmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':description' => $description ?: null,
        ':owner_id' => $owner_id,
        ':data_version' => $data_version ?: null,
        ':prod_dataset' => $prod_dataset ?: null,
        ':status' => $status,
        ':tags' => $tags ?: null,
        ':created_by' => intval($_SESSION['user_id'])
    ]);

    $_SESSION['flash'] = ['type'=>'success','message'=>'Project created successfully.'];
    header('Location: ../pages/proyectos.php');  // Redirect to projects page after creation
    exit();
} catch (Exception $e) {
    error_log('project_store error: ' . $e->getMessage());
    $_SESSION['flash'] = ['type'=>'error','message'=>'Error creating project.'];
    header('Location: ../pages/proyecto_new.php');  // Redirect on error
    exit();
}
?>
