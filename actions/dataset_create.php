<?php
// actions/dataset_create.php
// Handle Create Dataset form submission (file upload / connection config).
// Comments in English (as requested).

session_start();
require_once __DIR__ . '/../config/config.php'; // expects $pdo

// Basic guard: logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Forbidden');
}

// simple CSRF check
$posted_token = $_POST['csrf_token'] ?? '';
if (empty($posted_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Invalid CSRF token.'];
    header('Location: ../pages/datasets.php');
    exit();
}

// get inputs (trim)
$name = trim($_POST['name'] ?? '');
$project_id = intval($_POST['project_id'] ?? 0);
$conn_type = $_POST['connection_type'] ?? 'csv_file'; // 'treads_db'|'csv_file'|'other'
$tags = trim($_POST['tags'] ?? '');
$description = trim($_POST['description'] ?? '');
$owner_id = !empty($_POST['owner_id']) ? intval($_POST['owner_id']) : null;

// validation
if ($name === '' || $project_id <= 0) {
    $_SESSION['flash'] = ['type'=>'error','message'=>'Project and name are required.'];
    header('Location: ../pages/datasets.php');
    exit();
}

// prepare upload directories
$baseUploads = __DIR__ . '/../uploads/datasets';
if (!is_dir($baseUploads)) {
    mkdir($baseUploads, 0755, true);
}

$uploaded_file_path = null;
$original_filename = null;
$size_bytes = 0;
$created_by = $_SESSION['user_id'];

$connection_id = null;
$connection_string = null;

// Handle different connection types
if ($conn_type === 'csv_file' || $conn_type === 'treads_db') {
    // Expect a file upload named 'file'
    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['flash'] = ['type'=>'error','message'=>'No file uploaded for selected connection type.'];
        header('Location: ../pages/datasets.php');
        exit();
    }

    $f = $_FILES['file'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = ['type'=>'error','message'=>'Upload error.'];
        header('Location: ../pages/datasets.php');
        exit();
    }

    // Basic validation by extension
    $origName = $f['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($conn_type === 'csv_file') {
        if (!in_array($ext, ['csv','txt','zip'])) {
            $_SESSION['flash'] = ['type'=>'error','message'=>'CSV upload must be CSV/TXT (or ZIP).'];
            header('Location: ../pages/datasets.php');
            exit();
        }
        $subdir = 'csvs';
    } else { // treads_db (SQL file)
        if (!in_array($ext, ['sql','zip'])) {
            $_SESSION['flash'] = ['type'=>'error','message'=>'SQL upload must be .sql or .zip.'];
            header('Location: ../pages/datasets.php');
            exit();
        }
        $subdir = 'sqls';
    }

    // move uploaded file
    $targetDir = $baseUploads . '/' . $subdir;
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    $safeBase = preg_replace('/[^a-z0-9_\-\.]/i','_', pathinfo($origName, PATHINFO_FILENAME));
    $newName = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeBase . '.' . $ext;
    $target = $targetDir . '/' . $newName;

    if (!move_uploaded_file($f['tmp_name'], $target)) {
        $_SESSION['flash'] = ['type'=>'error','message'=>'Unable to store uploaded file.'];
        header('Location: ../pages/datasets.php');
        exit();
    }

    $uploaded_file_path = 'uploads/datasets/' . $subdir . '/' . $newName; // web relative path
    $original_filename = $origName;
    $size_bytes = filesize($target);

    // If treads_db we may want to create a connection row so admin can store connection metadata
    if ($conn_type === 'treads_db') {
        // Optionally get a friendly name for the connection
        $conn_name = trim($_POST['conn_name'] ?? ('Treads DB for ' . $name));
        $created_by = $_SESSION['user_id'];

        $stmt = $pdo->prepare("INSERT INTO data_connections (project_id, name, conn_type, uploaded_file, created_by) VALUES (?, ?, 'treads_db', ?, ?)");
        $stmt->execute([$project_id, $conn_name, $uploaded_file_path, $created_by]);
        $connection_id = $pdo->lastInsertId();
    }
} else {
    // conn_type === 'other' : expect connection_string in a textarea
    $connection_string = trim($_POST['connection_string'] ?? '');
    if ($connection_string === '') {
        $_SESSION['flash'] = ['type'=>'error','message'=>'Provide connection info for "Other" type.'];
        header('Location: ../pages/datasets.php');
        exit();
    }
    // store in data_connections table
    $conn_name = trim($_POST['conn_name'] ?? ('Other connection for ' . $name));
    $stmt = $pdo->prepare("INSERT INTO data_connections (project_id, name, conn_type, connection_string, created_by) VALUES (?, ?, 'other', ?, ?)");
    $stmt->execute([$project_id, $conn_name, $connection_string, $created_by]);
    $connection_id = $pdo->lastInsertId();
}

// Determine next version label for (project_id, name)
$role = $_POST['role'] ?? 'train'; // debe venir del form (train|production)
$name_clean = trim($name);

try {
    $vstmt = $pdo->prepare(
       "SELECT MAX(version_number) as maxv
        FROM datasets
        WHERE project_id = :pid AND name = :name AND role = :role"
    );
    $vstmt->execute([':pid'=>$project_id, ':name'=>$name_clean, ':role'=>$role]);
    $maxv = (int)$vstmt->fetchColumn();
    $next = $maxv + 1;
    $version_label = 'v' . $next;
} catch (Exception $e) {
    $next = 1;
    $version_label = 'v1';
}

// Insert dataset record
try {
    $sql = "INSERT INTO datasets
    (name, path, project_id, uploaded_by, version_number, version_label, role, original_filename, size_bytes, connection_id, created_at)
    VALUES (:name, :path, :project_id, :uploaded_by, :ver_num, :version_label, :role, :orig, :size, :conn_id, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
    ':name'=>$name_clean,
    ':path'=>$uploaded_file_path,
    ':project_id'=>$project_id,
    ':uploaded_by'=>$_SESSION['user_id'],
    ':ver_num'=>$next,
    ':version_label'=>$version_label,
    ':role'=>$role,
    ':orig'=>$original_filename,
    ':size'=>$size_bytes,
    ':conn_id'=>$connection_id
    ]);

    $_SESSION['flash'] = ['type'=>'success', 'message' => 'Dataset created (' . htmlspecialchars($version_label) . ').'];
    header('Location: ../pages/datasets.php');
    exit();
} catch (Exception $e) {
    // rollback file if needed
    if (!empty($uploaded_file_path)) {
        @unlink(__DIR__ . '/../' . $uploaded_file_path);
    }
    $_SESSION['flash'] = ['type'=>'error','message'=>'Error saving dataset: ' . $e->getMessage()];
    header('Location: ../pages/datasets.php');
    exit();
}
