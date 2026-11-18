<?php
require_once 'db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([trim($_POST['email'])]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $error = 'Неверный email или пароль!';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div style="position: fixed; top: 20px; right: 20px;">
    <button type="button" id="theme-toggle" class="theme-btn">☀️</button>
</div>
<div class="container" style="max-width: 420px; margin-top: 60px;">
    <h1 style="text-align: center;">BugHunter</h1>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top: 0;">Вход в систему</h3>
        <form method="POST" class="auth-form">
            <input type="email" name="email" placeholder="Email (по умолчанию: admin@bh.ru)" required>
            <input type="password" name="password" placeholder="Пароль (по умолчанию: 1234)" required>
            <button type="submit">Войти</button>
        </form>
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
        </p>
    </div>
</div>
</body>
</html>
