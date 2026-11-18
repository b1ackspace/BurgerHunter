<?php
require_once 'db.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $pass, $role]);

        $userId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $_SESSION['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error = 'Пользователь с таким email уже существует!';
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
    <h1 style="text-align: center;"> BugHunter</h1>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top: 0;">Регистрация</h3>
        <form method="POST" class="auth-form">
            <input type="text" name="name" placeholder="Ваше имя" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Придумайте пароль" required>
            <select name="role" required>
                <option value="Заказчик">Роль: Заказчик</option>
                <option value="Тестировщик">Роль: Тестировщик</option>
                <option value="Разработчик">Роль: Разработчик</option>
                <option value="Админ">Роль: Админ</option>
            </select>
            <button type="submit" class="btn-green">Создать аккаунт</button>
        </form>
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            Уже есть аккаунт? <a href="login.php">Войти</a>
        </p>
    </div>
</div>
</body>
</html>
