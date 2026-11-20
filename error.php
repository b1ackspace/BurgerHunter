<?php
$code = (int)($_GET['code'] ?? 404);
$messages = [
    403 => ['title' => '403 — Доступ запрещён', 'text' => 'У вас нет прав для просмотра этой страницы или выполнения этого действия.'],
    404 => ['title' => '404 — Не найдено', 'text' => 'Запрашиваемая страница, баг или проект не существует.'],
    419 => ['title' => '419 — CSRF токен недействителен', 'text' => 'Ваша сессия устарела или запрос был подделан. Обновите страницу и попробуйте снова.'],
    500 => ['title' => '500 — Внутренняя ошибка', 'text' => 'Что-то пошло не так на сервере. Попробуйте позже.'],
];
$msg = $messages[$code] ?? $messages[500];
http_response_code($code);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $msg['title'] ?> — BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container" style="max-width: 600px; margin-top: 80px; text-align: center;">
    <div class="card">
        <h1 style="font-size: 64px; margin: 0; color: var(--accent);"><?= $code ?></h1>
        <h2 style="margin-top: 8px;"><?= $msg['title'] ?></h2>
        <p style="color: var(--text-muted);"><?= $msg['text'] ?></p>
        <a href="index.php" class="btn-green" style="padding: 10px 20px; color: #fff; display: inline-block; margin-top: 16px;">← На главную</a>
    </div>
</div>
</body>
</html>