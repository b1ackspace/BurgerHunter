<?php
require_once 'init.php';

$bugId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM bugs WHERE bug_id = ?");
$stmt->execute([$bugId]);
$bug = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bug) abort(404); // <-- 404 проверка
if ($bug['reporter_id'] != $currentUserId && !$isAdmin) abort(403); // <-- 403 проверка

$flashErrors = $_SESSION['flash_errors'] ?? [];
$flashOld    = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_errors'], $_SESSION['flash_old']);
$data = array_merge($bug, $flashOld);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование #<?= $bugId ?> — BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <?php renderHeader($currentUser); ?>

    <?php if (!empty($flashErrors)): ?>
        <div class="alert-error">
            <?php foreach ($flashErrors as $e): ?>
                <div>• <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Редактирование бага #<?= $bugId ?></h3>
        <form method="POST" action="actions.php" class="bug-form-grid">
            <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
            <input type="hidden" name="action" value="edit_bug">
            <input type="hidden" name="bug_id" value="<?= $bugId ?>">

            <div class="field-group">
                <label>Тема бага</label>
                <input type="text" name="title" value="<?= htmlspecialchars($data['title']) ?>" minlength="5" maxlength="120" required>
            </div>

            <div class="field-group">
                <label>Приоритет</label>
                <select name="priority">
                    <?php foreach (['Низкий','Средний','Высокий','Критический'] as $p): ?>
                        <option value="<?= $p ?>" <?= $data['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-group">
                <label>Описание проблемы</label>
                <textarea name="description" rows="4" minlength="10" maxlength="2000" required><?= htmlspecialchars($data['description']) ?></textarea>
            </div>

            <div class="bug-form-results">
                <div class="field-group">
                    <label>Ожидаемый результат</label>
                    <textarea name="expected_result" rows="3" minlength="5" maxlength="1000" required><?= htmlspecialchars($data['expected_result']) ?></textarea>
                </div>
                <div class="field-group">
                    <label>Фактический результат</label>
                    <textarea name="actual_result" rows="3" minlength="5" maxlength="1000" required><?= htmlspecialchars($data['actual_result']) ?></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <a href="bug_view.php?id=<?= $bugId ?>" style="padding: 11px 18px; background: var(--sub-bg); color: var(--text-main); border: 1px solid var(--input-border); border-radius: 10px; text-decoration: none; font-size: 14px;">Отмена</a>
                <button type="submit" class="btn-green">💾 Сохранить</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>