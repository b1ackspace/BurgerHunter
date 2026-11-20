<?php
require_once 'init.php';
// Получаем все баги
$bugs = $db->query("
    SELECT b.*, p.code AS project_code, r.name AS reporter_name, r.user_id AS reporter_id, 
           a.name AS assignee_name, a.user_id AS assignee_id
    FROM bugs b
    LEFT JOIN projects p ON b.project_id = p.project_id
    LEFT JOIN users r ON b.reporter_id = r.user_id
    LEFT JOIN users a ON b.assignee_id = a.user_id
    ORDER BY b.bug_id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Все баги — BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <?php renderHeader($currentUser); ?>

    <div class="card">
        <h3>Доска багов (<?= count($bugs) ?>)</h3>
        <?php if (empty($bugs)): ?>
            <p>Багов пока нет. <a href="bug_create.php">Создайте первый!</a></p>
        <?php else: ?>
            <?php foreach ($bugs as $bug): ?>
                <div class="bug-item">
                    <div class="bug-header">
                        <div>
                            <a href="bug_view.php?id=<?= $bug['bug_id'] ?>" style="color: var(--accent); font-weight: 800; text-decoration: none;">#<?= $bug['bug_id'] ?> [<?= htmlspecialchars($bug['project_code']) ?>]</a>
                            <span class="bug-title"><?= htmlspecialchars($bug['title']) ?></span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <span class="badge prio-<?= $bug['priority'] ?>"><?= $bug['priority'] ?></span>
                            <span class="badge status-badge"><?= $bug['status'] ?></span>
                        </div>
                    </div>
                    <p class="bug-meta">
                    Автор: 
                    <a href="profile.php?id=<?= $bug['reporter_id'] ?>"><b><?= htmlspecialchars($bug['reporter_name']) ?></b></a> 
                    &nbsp;•&nbsp;
                    Инженер: 
                    <?php if ($bug['assignee_id']): ?>
                      <a href="profile.php?id=<?= $bug['assignee_id'] ?>"><b><?= htmlspecialchars($bug['assignee_name']) ?></b></a>
                    <?php else: ?>
                      <b>Не назначен</b>
                         <?php endif; ?>
                    </p>
                    <div style="text-align: right;">
                        <a href="bug_view.php?id=<?= $bug['bug_id'] ?>" class="btn-green" style="padding: 6px 12px; font-size: 13px;">Открыть</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>