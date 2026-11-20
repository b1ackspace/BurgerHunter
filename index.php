<?php
require_once 'init.php';
$pendingRequests = [];
if ($isAdmin) {
    $pendingRequests = $db->query("
        SELECT rr.*, u.name AS user_name, u.email AS user_email
        FROM role_requests rr
        JOIN users u ON rr.user_id = u.user_id
        WHERE rr.status = 'ожидает'
        ORDER BY rr.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
$recentBugs = $db->query("
    SELECT b.bug_id, b.title, b.status, b.priority, u.name as author, u.user_id as author_id
    FROM bugs b LEFT JOIN users u ON b.reporter_id = u.user_id
    ORDER BY b.bug_id DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>BugHunter — Главная</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <?php renderHeader($currentUser); ?>

    <?php if ($isAdmin && count($pendingRequests) > 0): ?>
        <div class="card" style="border: 2px solid var(--accent);">
            <h3>🔔 Заявки на роль (<?= count($pendingRequests) ?>)</h3>
            <?php foreach ($pendingRequests as $r): ?>
                <div class="bug-item">
                    <p class="bug-meta">
                        <b><?= htmlspecialchars($r['user_name']) ?></b> (<?= htmlspecialchars($r['user_email']) ?>)
                        хочет стать <b><?= htmlspecialchars($r['desired_role']) ?></b>
                        <span style="color: var(--text-muted);">— <?= $r['created_at'] ?></span>
                    </p>
                    <div class="form-row">
                        <form method="POST" action="actions.php">
                            <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
                            <input type="hidden" name="action" value="review_request">
                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                            <input type="hidden" name="decision" value="одобрена">
                            <button type="submit" class="btn-green">✅ Одобрить</button>
                        </form>
                        <form method="POST" action="actions.php">
                            <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
                            <input type="hidden" name="action" value="review_request">
                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                            <input type="hidden" name="decision" value="отклонена">
                            <button type="submit" style="background: var(--danger); color: #fff;">❌ Отклонить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Последние баги</h3>
        <?php if (empty($recentBugs)): ?>
            <p>Багов пока нет. <a href="bug_create.php">Создайте первый!</a></p>
        <?php else: ?>
            <ul class="comment-list" style="max-height: none;">
                <?php foreach ($recentBugs as $b): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <a href="bug_view.php?id=<?= $b['bug_id'] ?>"><b>#<?= $b['bug_id'] ?></b> <?= htmlspecialchars($b['title']) ?></a>
                            <span style="font-size: 12px; margin-left: 8px; color: var(--text-muted);">(<a href="profile.php?id=<?= $b['author_id'] ?>"><?= htmlspecialchars($b['author']) ?></a>)</span>
                        </div>
                        <span class="badge prio-<?= $b['priority'] ?>"><?= $b['priority'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top: 16px; text-align: right;">
                <a href="bugs.php">Смотреть все баги ➔</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>