<?php
require_once 'db.php';
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role  = $_POST['role'];
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    if ($role === 'Админ') {
        $role = 'Заказчик';
    }
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $initialRole = in_array($role, ['Тестировщик', 'Разработчик'], true) ? 'Заказчик' : $role;
        $stmt->execute([$name, $email, $pass, $initialRole]);
        $userId = $db->lastInsertId();
        if (in_array($role, ['Тестировщик', 'Разработчик'], true)) {
            $stmtReq = $db->prepare("INSERT INTO role_requests (user_id, desired_role) VALUES (?, ?)");
            $stmtReq->execute([$userId, $role]);
        }
        $db->commit();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $_SESSION['user'] = $stmt->fetch(PDO::FETCH_ASSOC);
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
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
    <h1 style="text-align: center;">BugHunter</h1>
    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="card">
        <h3 style="margin-top: 0;">Регистрация</h3>
        <form method="POST" class="auth-form">
            <input type="text" name="name" placeholder="Ваше имя" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Придумайте пароль" required>
            <select name="role" id="role-select" required>
                <option value="Заказчик">Роль: Заказчик</option>
                <option value="Тестировщик">Роль: Тестировщик (по заявке)</option>
                <option value="Разработчик">Роль: Разработчик (по заявке)</option>
            </select>
            <div id="request-note" style="display:none; font-size:13px; color: var(--text-muted);">
                ⚠️ Роль будет выдана после одобрения заявки администратором.
            </div>
            <button type="submit" class="btn-green">Создать аккаунт</button>
        </form>
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            Уже есть аккаунт? <a href="login.php">Войти</a>
        </p>
    </div>
</div>
<script>
const roleSelect = document.getElementById('role-select');
const note = document.getElementById('request-note');
roleSelect.addEventListener('change', () => {
    note.style.display = (roleSelect.value === 'Тестировщик' || roleSelect.value === 'Разработчик') ? 'block' : 'none';
});
</script>
</body>
</html>