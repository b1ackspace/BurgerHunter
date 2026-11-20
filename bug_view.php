<?php
require_once 'init.php';

$bugId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("
    SELECT b.*, p.code AS project_code, p.name AS project_name, 
           r.name AS reporter_name, r.user_id AS reporter_id, 
           a.name AS assignee_name, a.user_id AS assignee_id
    FROM bugs b
    LEFT JOIN projects p ON b.project_id = p.project_id
    LEFT JOIN users r ON b.reporter_id = r.user_id
    LEFT JOIN users a ON b.assignee_id = a.user_id
    WHERE b.bug_id = ?
");
$stmt->execute([$bugId]);
$bug = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bug) abort(404); // 404

// Получаем комментарии и историю сразу, чтобы не делать запросы в шаблоне
$cStmt = $db->prepare("SELECT c.*, u.name, u.user_id FROM comments c LEFT JOIN users u ON c.user_id = u.user_id WHERE bug_id = ?");
$cStmt->execute([$bugId]);
$comments = $cStmt->fetchAll(PDO::FETCH_ASSOC);

$hStmt = $db->prepare("SELECT h.*, u.name, u.user_id FROM bughistory h LEFT JOIN users u ON h.changed_by = u.user_id WHERE bug_id = ? ORDER BY history_id DESC");
$hStmt->execute([$bugId]);
$history = $hStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Баг #<?= $bugId ?> — BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <?php renderHeader($currentUser); ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div>
                <h2 style="margin: 0 0 8px 0;">#<?= $bugId ?> [<?= htmlspecialchars($bug['project_code']) ?>] <?= htmlspecialchars($bug['title']) ?></h2>
                <p class="bug-meta">
                    Автор: <a href="profile.php?id=<?= $bug['reporter_id'] ?>"><b><?= htmlspecialchars($bug['reporter_name']) ?></b></a> &nbsp;•&nbsp;
                    Инженер: <?php if ($bug['assignee_id']): ?><a href="profile.php?id=<?= $bug['assignee_id'] ?>"><b><?= htmlspecialchars($bug['assignee_name']) ?></b></a><?php else: ?><b>Не назначен</b><?php endif; ?>
                </p>
            </div>
            <div style="display: flex; gap: 8px;">
                <span class="badge prio-<?= $bug['priority'] ?>"><?= $bug['priority'] ?></span>
                <span class="badge status-badge"><?= $bug['status'] ?></span>
            </div>
        </div>

        <div class="bug-report-body">
            <div>
                <strong style="color: var(--text-muted);">Описание проблемы:</strong><br>
                <span><?= nl2br(htmlspecialchars($bug['description'] ?? '')) ?></span>
            </div>
            <div class="bug-results-compare">
                <div>
                    <strong style="color: var(--green);">Ожидаемый результат:</strong><br>
                    <span><?= nl2br(htmlspecialchars($bug['expected_result'] ?? '')) ?></span>
                </div>
                <div>
                    <strong style="color: var(--danger);">Фактический результат:</strong><br>
                    <span><?= nl2br(htmlspecialchars($bug['actual_result'] ?? '')) ?></span>
                </div>
            </div>
        </div>
        
        <div class="form-row" style="margin-top: 16px; gap: 10px;">
            <?php if ($isAdmin || $bug['reporter_id'] == $currentUserId): ?>
                <a href="bug_edit.php?id=<?= $bugId ?>" style="padding: 8px 14px; background: var(--accent); color: white; border-radius: 8px; text-decoration: none; font-size: 14px;">✏️ Редактировать</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <form method="POST" action="actions.php" onsubmit="return confirm('Удалить этот баг?');">
                    <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
                    <input type="hidden" name="action" value="delete_bug">
                    <input type="hidden" name="bug_id" value="<?= $bugId ?>">
                    <button type="submit" style="background: var(--danger); color: white; padding: 8px 14px; border: none; border-radius: 8px; cursor: pointer;">🗑 Удалить</button>
                </form>
            <?php endif; ?>
        </div>
        
        <hr style="border: none; border-top: 1px solid var(--input-border); margin: 20px 0;">
        
        <div class="form-row">
            <?php if ($bug['status'] === 'создание' && ($isAdmin || $isTester)): ?>
                <form method="POST" action="actions.php" class="form-row">
                    <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
                    <input type="hidden" name="action" value="assign_dev">
                    <input type="hidden" name="bug_id" value="<?= $bugId ?>">
                    <select name="assignee_id" required style="width: auto; min-width: 200px;">
                        <option value="">-- Выберите исполнителя --</option>
                        <?php
                        $candidates = $isAdmin ? $allUsers : $developers;
                        foreach ($candidates as $u):
                        ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-green">Назначить ➔</button>
                </form>
            <?php elseif (isset($nextStageMap[$bug['status']])): ?>
                <form method="POST" action="actions.php">
                    <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="bug_id" value="<?= $bugId ?>">
                    <input type="hidden" name="new_status" value="<?= $nextStageMap[$bug['status']] ?>">
                    <button type="submit" class="btn-green">Перевести в «<?= $nextStageMap[$bug['status']] ?>» ➔</button>
                </form>
            <?php else: ?>
                <span style="color: var(--green); font-weight: 700;">✅ Проверено QA</span>
            <?php endif; ?>
        </div>
        
        <div class="details-grid">
            <div>
                <strong>Комментарии:</strong>
                <ul class="comment-list">
                    <?php foreach ($comments as $c): ?>
                        <li>
                            <b><a href="profile.php?id=<?= $c['user_id'] ?>"><?= htmlspecialchars($c['name']) ?></a>:</b> 
                            <?= htmlspecialchars($c['body']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" action="actions.php" style="display:flex; gap:8px; margin-top:10px;">
                    <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="bug_id" value="<?= $bugId ?>">
                    <input type="text" name="body" placeholder="Написать комментарий..." minlength="1" maxlength="500" required style="padding:7px 10px; flex:1;">
                    <button type="submit" style="padding:7px 14px;">OK</button>
                </form>
            </div>
            <div>
                <strong>История:</strong>
                <ul class="history-list">
                    <?php foreach ($history as $h): ?>
                        <li>
                            [<?= $h['changed_at'] ?>] 
                            <b><a href="profile.php?id=<?= $h['user_id'] ?>"><?= htmlspecialchars($h['name']) ?></a></b>: 
                            <?= $h['field'] ?> (<?= htmlspecialchars($h['old_val']) ?> ➔ <?= htmlspecialchars($h['new_val']) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>