<?php
require_once 'init.php';

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;
$isOwnProfile = ($profileId === $currentUserId);

// Получаем данные профиля
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$profileId]);
$profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profileUser) abort(404); // ИСПРАВЛЕНО (было !$bug)

// Обработка обновления своего профиля
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    $newName = trim($_POST['name'] ?? '');
    $newBio  = trim($_POST['bio'] ?? '');
    
    if (mb_strlen($newName) < 2) {
        $error = "Имя должно быть не короче 2 символов";
    } else {
        $stmt = $db->prepare("UPDATE users SET name = ?, bio = ? WHERE user_id = ?");
        $stmt->execute([$newName, $newBio, $currentUserId]);
        
        // Обновляем сессию, чтобы имя сразу поменялось везде
        $_SESSION['user']['name'] = $newName;
        $_SESSION['user']['bio'] = $newBio;
        $currentUser['name'] = $newName; // для хедера
        $successMsg = "Профиль обновлён!";
        
        // Перезапрашиваем данные
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$profileId]);
        $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Статистика профиля
$stats = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM bugs WHERE reporter_id = ?) as bugs_created,
        (SELECT COUNT(*) FROM bugs WHERE assignee_id = ?) as bugs_assigned,
        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comments_count
");
$stats->execute([$profileId, $profileId, $profileId]);
$statsData = $stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профиль <?= htmlspecialchars($profileUser['name']) ?> — BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <?php renderHeader($currentUser); ?>

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
            <!-- Аватарка -->
            <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold;">
                <?= mb_strtoupper(mb_substr($profileUser['name'], 0, 1)) ?>
            </div>
            <div>
                <h2 style="margin: 0 0 5px 0;"><?= htmlspecialchars($profileUser['name']) ?></h2>
                <span class="badge status-badge" style="font-size: 14px;"><?= htmlspecialchars($profileUser['role']) ?></span>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div style="background: var(--green); color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px; text-align: center;">
                <?= $successMsg ?>
            </div>
        <?php endif; ?>

        <?php if ($isOwnProfile): ?>
            <form method="POST" class="bug-form-grid">
                <?= csrfField() ?>
                <div class="field-group">
                    <label>Ваше имя</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($profileUser['name']) ?>" required minlength="2" maxlength="50">
                </div>
                <div class="field-group">
                    <label>О себе (описание)</label>
                    <textarea name="bio" rows="4" maxlength="500" placeholder="Расскажите о себе..."><?= htmlspecialchars($profileUser['bio'] ?? '') ?></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn-green">💾 Сохранить</button>
                </div>
            </form>
        <?php else: ?>
            <div class="field-group">
                <label>О себе</label>
                <div class="bug-report-body" style="margin: 0;">
                    <?= nl2br(htmlspecialchars($profileUser['bio'] ?? 'Пользователь пока ничего о себе не рассказал.')) ?>
                </div>
            </div>
        <?php endif; ?>

        <hr style="border: none; border-top: 1px solid var(--input-border); margin: 24px 0;">

        <h3>Статистика</h3>
        <div class="bug-form-results" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="bug-report-body" style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: var(--accent);"><?= $statsData['bugs_created'] ?></div>
                <div style="font-size: 12px; color: var(--text-muted);">Создано багов</div>
            </div>
            <div class="bug-report-body" style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: var(--green);"><?= $statsData['bugs_assigned'] ?></div>
                <div style="font-size: 12px; color: var(--text-muted);">Назначено</div>
            </div>
            <div class="bug-report-body" style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: var(--danger);"><?= $statsData['comments_count'] ?></div>
                <div style="font-size: 12px; color: var(--text-muted);">Комментариев</div>
            </div>
        </div>

        <div style="margin-top: 24px; text-align: center;">
            <a href="index.php">← На главную</a>
        </div>
    </div>
</div>
</body>
</html>