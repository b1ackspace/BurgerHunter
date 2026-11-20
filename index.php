<?php require_once 'actions.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>BurgerHunter</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="container">
    <header>
        <h1>BugHunter</h1>
        <div class="header-controls">
            <span><b><?= htmlspecialchars($currentUser['name']) ?></b> [<?= $currentUser['role'] ?>]</span>
            <button type="button" id="theme-toggle" class="theme-btn">☀️</button>
            <a href="login.php?logout=1" class="btn-logout">Выйти</a>
        </div>
    </header>

    <?php if ($isAdmin && count($pendingRequests) > 0): ?>
        <div class="card" style="border: 2px solid var(--accent);">
            <h3> Заявки на роль (<?= count($pendingRequests) ?>)</h3>
            <?php foreach ($pendingRequests as $r): ?>
                <div class="bug-item" style="animation-delay: 0s;">
                    <p class="bug-meta">
                        <b><?= htmlspecialchars($r['user_name']) ?></b> (<?= htmlspecialchars($r['user_email']) ?>)
                        хочет стать <b><?= htmlspecialchars($r['desired_role']) ?></b>
                        <span style="color: var(--text-muted);">— <?= $r['created_at'] ?></span>
                    </p>
                    <div class="form-row" style="gap: 8px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="review_request">
                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                            <input type="hidden" name="decision" value="одобрена">
                            <button type="submit" class="btn-green">✅ Одобрить</button>
                        </form>
                        <form method="POST" style="display:inline;">
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

    <?php if (!empty($flashErrors)): ?>
    <div class="alert-error">
        <?php foreach ($flashErrors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Новый баг</h3>
    <form method="POST" class="bug-form-grid" id="createBugForm">
        <input type="hidden" name="action" value="create_bug">

        <div class="field-group">
    <label>Проект</label>
    <div class="custom-select" id="projectCustomSelect">
        <input type="hidden" name="project_name" id="projectInput" required>
        <div class="custom-select-trigger" onclick="toggleDropdown('projectDropdown')">
            <span id="projectLabel">-- Выберите проект --</span>
            <span class="arrow">▼</span>
        </div>
        <div class="custom-select-options" id="projectDropdown">
            <?php foreach ($projects as $p): ?>
                <div class="custom-option" onclick="selectOption('projectInput', 'projectLabel', '<?= htmlspecialchars($p['name']) ?>', 'projectDropdown')">
                    <?= htmlspecialchars($p['name']) ?> [<?= htmlspecialchars($p['code']) ?>]
                </div>
            <?php endforeach; ?>
            <div class="custom-option" style="color: var(--accent);" onclick="showManualInput()">➕ Другой проект</div>
        </div>
    </div>
    <input type="text" name="project_name_manual" id="projectManual" placeholder="Название нового проекта" minlength="2" maxlength="60" style="display:none; margin-top:6px;">
</div>
        <div class="field-group">
            <label>Тема бага <span class="char-counter" data-for="title"></span></label>
            <input type="text" name="title"
                   value="<?= htmlspecialchars($flashOld['title'] ?? '') ?>"
                   minlength="5" maxlength="120" required
                   data-counter="title">
        </div>
<div class="field-group">
    <label>Приоритет</label>
    <div class="custom-select">
        <input type="hidden" name="priority" id="priorityInput" value="Средний">
        <div class="custom-select-trigger" onclick="toggleDropdown('priorityDropdown')">
            <span id="priorityLabel">Средний</span>
            <span class="arrow">▼</span>
        </div>
        <div class="custom-select-options" id="priorityDropdown">
            <?php foreach (['Низкий','Средний','Высокий','Критический'] as $p): ?>
                <div class="custom-option" onclick="selectOption('priorityInput', 'priorityLabel', '<?= $p ?>', 'priorityDropdown')"><?= $p ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
        <div class="field-group">
            <label>Описание проблемы <span class="char-counter" data-for="description"></span></label>
            <textarea name="description" rows="3"
                      minlength="10" maxlength="2000" required
                      data-counter="description"><?= htmlspecialchars($flashOld['description'] ?? '') ?></textarea>
        </div>

        <div class="bug-form-results">
            <div class="field-group">
                <label>Ожидаемый результат <span class="char-counter" data-for="expected_result"></span></label>
                <textarea name="expected_result" rows="3"
                          minlength="5" maxlength="1000" required
                          data-counter="expected_result"><?= htmlspecialchars($flashOld['expected_result'] ?? '') ?></textarea>
            </div>
            <div class="field-group">
                <label>Фактический результат <span class="char-counter" data-for="actual_result"></span></label>
                <textarea name="actual_result" rows="3"
                          minlength="5" maxlength="1000" required
                          data-counter="actual_result"><?= htmlspecialchars($flashOld['actual_result'] ?? '') ?></textarea>
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
                        <strong style="color: var(--text-muted);">Описание:</strong><br>
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

                <!--удалить / редактировать-->
                <?php if ($isAdmin || $bug['reporter_id'] == $currentUserId): ?>
                    <div class="form-row" style="gap: 8px; margin-bottom: 8px;">
                        <button type="button" onclick="toggleEdit(<?= $bug['bug_id'] ?>)" style="background: var(--accent); color: #fff;">✏️ Редактировать</button>
                        <?php if ($isAdmin): ?>
                            <form method="POST" onsubmit="return confirm('Удалить баг #<?= $bug['bug_id'] ?>?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete_bug">
                                <input type="hidden" name="bug_id" value="<?= $bug['bug_id'] ?>">
                                <button type="submit" style="background: var(--danger); color: #fff;">🗑 Удалить</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <form method="POST" id="edit-form-<?= $bug['bug_id'] ?>" style="display:none;" class="bug-form-grid">
                        <input type="hidden" name="action" value="edit_bug">
                        <input type="hidden" name="bug_id" value="<?= $bug['bug_id'] ?>">
                        <div class="field-group">
                            <label>Тема бага</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($bug['title']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label>Приоритет</label>
                            <select name="priority">
                                <?php foreach (['Низкий','Средний','Высокий','Критический'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $bug['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Описание</label>
                            <textarea name="description"><?= htmlspecialchars($bug['description'] ?? '') ?></textarea>
                        </div>
                        <div class="bug-form-results">
                            <div class="field-group">
                                <label>Ожидаемый результат</label>
                                <textarea name="expected_result"><?= htmlspecialchars($bug['expected_result'] ?? '') ?></textarea>
                            </div>
                            <div class="field-group">
                                <label>Фактический результат</label>
                                <textarea name="actual_result"><?= htmlspecialchars($bug['actual_result'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" onclick="toggleEdit(<?= $bug['bug_id'] ?>)">Отмена</button>
                            <button type="submit" class="btn-green">💾 Сохранить</button>
                        </div>
                    </form>
                <?php endif; ?>
                <div class="form-row">
                    <?php if ($bug['status'] === 'создание' && ($isAdmin || $isTester)): ?>
                        <form method="POST" class="form-row">
                            <input type="hidden" name="action" value="assign_dev">
                            <input type="hidden" name="bug_id" value="<?= $bug['bug_id'] ?>">
                            <select name="assignee_id" required>
                                <option value="">-- Выберите исполнителя --</option>
                                <?php
                                $candidates = $isAdmin ? $allUsers : $developers;
                                foreach ($candidates as $u):
                                ?>
                                    <option value="<?= $u['user_id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Назначить ➔</button>
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
                        <strong>Комментарии:</strong>
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
                        <strong>История:</strong>
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
<script>
function toggleEdit(id) {
    const f = document.getElementById('edit-form-' + id);
    f.style.display = (f.style.display === 'none') ? 'block' : 'none';
}
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const parent = dropdown.closest('.custom-select');
    document.querySelectorAll('.custom-select.open').forEach(el => {
        if (el !== parent) el.classList.remove('open');
    });
    parent.classList.toggle('open');
}
function selectOption(inputId, labelId, value, dropdownId) {
    document.getElementById(inputId).value = value;
    document.getElementById(labelId).textContent = value;
    document.getElementById(dropdownId).closest('.custom-select').classList.remove('open');
}
function showManualInput() {
    document.getElementById('projectInput').removeAttribute('required');
    document.getElementById('projectInput').name = 'project_name_ignored';
    document.getElementById('projectManual').name = 'project_name';
    document.getElementById('projectManual').style.display = 'block';
    document.getElementById('projectManual').required = true;
    document.getElementById('projectDropdown').closest('.custom-select').classList.remove('open');
    document.getElementById('projectLabel').textContent = '-- Ввести вручную --';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select')) {
        document.querySelectorAll('.custom-select.open').forEach(el => el.classList.remove('open'));
    }
});
</script>
</body>
</html>