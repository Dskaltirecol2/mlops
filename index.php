<?php
session_start();
require_once __DIR__ . '/config/config.php'; // Aquí se define la conexión a la BD

// Si ya hay sesión, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Procesar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, password_hash, activo FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['activo'] == 1 && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Usuario o contraseña incorrectos, o cuenta inactiva.";
        }
    } else {
        $error = "Por favor ingresa usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyecto MLOps - Iniciar sesión</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #000000, #1c1c1c);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #fff;
        }

        .login-container {
            background-color: #111;
            padding: 2rem;
            border-radius: 12px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 0 20px rgba(241, 90, 34, 0.6);
            text-align: center;
        }

        .logo {
            width: 180px;
            margin-bottom: 1rem;
        }

        h2 {
            color: #F15A22;
            margin-bottom: 1rem;
        }

        input {
            width: 100%;
            padding: 0.8rem;
            margin: 0.5rem 0;
            border: none;
            border-radius: 6px;
            background-color: #222;
            color: #fff;
            font-size: 1rem;
        }

        input:focus {
            outline: 2px solid #F15A22;
        }

        button {
            background-color: #F15A22;
            color: white;
            border: none;
            width: 100%;
            padding: 0.8rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 0.5rem;
        }

        button:hover {
            background-color: #d94a1d;
        }

        .error {
            color: #ff6b6b;
            margin-top: 0.8rem;
            font-size: 0.9rem;
        }

        footer {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #888;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="assets/img/logo_kaltire.png" alt="Kal Tire Logo" class="logo">
        <h2>Panel de Control MLOps</h2>

        <form method="POST">
            <input type="text" name="username" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Iniciar Sesión</button>
        </form>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <footer>
            © <?= date('Y') ?> Kal Tire Mining Group | Proyecto MLOps
        </footer>
    </div>
</body>
</html>
