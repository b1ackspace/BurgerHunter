<?php require_once 'actions.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>BugHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <header>
        <h1> BugHunter</h1>
        <div class="header-controls">
            <span><b><?= htmlspecialchars($currentUser['name']) ?></b> [<?= $currentUser['role'] ?>]</span>
            <button type="button" id="theme-toggle" class="theme-btn">☀️</button>
            <a href="login.php?logout=1" class="btn-logout">Выйти</a>
        </div>
    </header>
    <div class="card">
        <h3> Новый баг</h3>
        <form method="POST" class="bug-form-grid">
            <input type="hidden" name="action" value="create_bug">
            <div class="field-group">
                    <label>Проект</label>
                    <input type="text" name="project_name" required>
                </div>
                <div class="field-group">
                    <label>Тема бага</label>
                    <input type="text" name="title" required>
                </div>
                <div class="field-group">
                    <label>Приоритет</label>
                    <select name="priority">
                        <option value="Низкий">Низкий</option>
                        <option value="Средний" selected>Средний</option>
                        <option value="Высокий">Высокий</option>
                        <option value="Критический">Критический</option>
                    </select>
                </div>
            </div>
            <div class="field-group">
                <label>Описание проблемы </label>
                <textarea name="description"required></textarea>
            </div>
            <div class="bug-form-results">
                <div class="field-group">
                    <label> Ожидаемый результат</label>
                    <textarea name="expected_result" required></textarea>
                </div>
                <div class="field-group">
                    <label> Фактический результат</label>
                    <textarea name="actual_result"required></textarea>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                <button type="submit" style="padding: 12px 28px; font-size: 15px;">+ Создать заявку</button>
            </div>
        </form>
    </div>
    <div class="card">
        <h3>Доска багов (<?= count($bugs) ?>)</h3>
        <?php foreach ($bugs as $index => $bug): ?>
            <div class="bug-item" style="animation-delay: <?= $index * 0.06 ?>s">
                <div class="bug-header">
                    <div>
                        <span style="color: var(--accent); font-weight: 800;">#<?= $bug['bug_id'] ?> [<?= htmlspecialchars($bug['project_code']) ?>]</span>
                        <span class="bug-title"><?= htmlspecialchars($bug['title']) ?></span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <span class="badge prio-<?= $bug['priority'] ?>"><?= $bug['priority'] ?></span>
                        <span class="badge status-badge"><?= $bug['status'] ?></span>
                    </div>
                </div>
                <p class="bug-meta">
                    Автор: <b><?= htmlspecialchars($bug['reporter_name']) ?></b> &nbsp;•&nbsp; 
                    Инженер: <b><?= htmlspecialchars($bug['assignee_name'] ?? 'Не назначен') ?></b>
                </p>
                <div class="bug-report-body">
                    <div>
                        <strong style="color: var(--text-muted);"> Описание:</strong><br>
                        <span><?= nl2br(htmlspecialchars($bug['description'] ?? '')) ?></span>
                    </div>
                    <div class="bug-results-compare">
                        <div>
                            <strong style="color: var(--green);"> Ожидаемый результат:</strong><br>
                            <span><?= nl2br(htmlspecialchars($bug['expected_result'] ?? '')) ?></span>
                        </div>
                        <div>
                            <strong style="color: var(--danger);"> Фактический результат:</strong><br>
                            <span><?= nl2br(htmlspecialchars($bug['actual_result'] ?? '')) ?></span>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <?php if ($bug['status'] === 'создание'): ?>
                        <form method="POST" class="form-row">
                            <input type="hidden" name="action" value="assign_dev">
                            <input type="hidden" name="bug_id" value="<?= $bug['bug_id'] ?>">
                            <select name="assignee_id" required>
                                <option value="">-- Выберите разработчика --</option>
                                <?php foreach ($developers as $dev): ?>
                                    <option value="<?= $dev['user_id'] ?>"><?= htmlspecialchars($dev['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Назначить инженера ➔</button>
                        </form>
                    <?php elseif (isset($nextStageMap[$bug['status']])): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="bug_id" value="<?= $bug['bug_id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $nextStageMap[$bug['status']] ?>">
                            <button type="submit" class="btn-green">
                                Перевести в «<?= $nextStageMap[$bug['status']] ?>» ➔
                            </button>
                        </form>
                    <?php else: ?>
                        <span style="color: var(--green); font-weight: 700;">✅ Проверено QA</span>
                    <?php endif; ?>
                </div>
                <div class="details-grid">
                    <div>
                        <strong> Комментарии:</strong>
                        <ul class="comment-list">
                            <?php
                            $cStmt = $db->prepare("SELECT c.*, u.name FROM comments c LEFT JOIN users u ON c.user_id = u.user_id WHERE bug_id = ?");
                            $cStmt->execute([$bug['bug_id']]);
                            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $c):
                            ?>
                                <li><b><?= htmlspecialchars($c['name']) ?>:</b> <?= htmlspecialchars($c['body']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="POST" style="display:flex; gap:8px; margin-top:10px;">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="bug_id" value="<?= $bug['bug_id'] ?>">
                            <input type="text" name="body" placeholder="Написать комментарий..." style="padding:7px 10px;" required>
                            <button type="submit" style="padding:7px 14px;">OK</button>
                        </form>
                    </div>
                    <div>
                        <strong> История:</strong>
                        <ul class="history-list">
                            <?php
                            $hStmt = $db->prepare("SELECT h.*, u.name FROM bughistory h LEFT JOIN users u ON h.changed_by = u.user_id WHERE bug_id = ?");
                            $hStmt->execute([$bug['bug_id']]);
                            foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h):
                            ?>
                                <li>[<?= $h['changed_at'] ?>] <b><?= htmlspecialchars($h['name']) ?></b>: <?= $h['field'] ?> (<?= htmlspecialchars($h['old_val']) ?> ➔ <?= htmlspecialchars($h['new_val']) ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>