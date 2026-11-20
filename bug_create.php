<?php
require_once 'init.php';
$flashErrors = $_SESSION['flash_errors'] ?? [];
$flashOld    = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_errors'], $_SESSION['flash_old']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создать баг — BugHunter</title>
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
        <h3>Новый баг</h3>
        <form method="POST" action="actions.php" class="bug-form-grid" id="createBugForm">
            <?= csrfField() ?> <!-- ДОБАВЛЕНО -->
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
                <input type="text" name="project_name_manual" id="projectManual"
                       placeholder="Название нового проекта"
                       minlength="2" maxlength="60"
                       style="display:none; margin-top:6px;">
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
                    <input type="hidden" name="priority" id="priorityInput" value="<?= htmlspecialchars($flashOld['priority'] ?? 'Средний') ?>">
                    <div class="custom-select-trigger" onclick="toggleDropdown('priorityDropdown')">
                        <span id="priorityLabel"><?= htmlspecialchars($flashOld['priority'] ?? 'Средний') ?></span>
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
                <button type="submit" class="btn-green" style="padding: 12px 28px; font-size: 15px;">+ Создать заявку</button>
            </div>
        </form>
    </div>
</div>
<script>
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
document.querySelectorAll('[data-counter]').forEach(el => {
    const counter = document.querySelector(`.char-counter[data-for="${el.dataset.counter}"]`);
    if (!counter) return;
    const max = parseInt(el.maxLength, 10);
    const min = parseInt(el.minLength, 10) || 0;
    function update() {
        const len = el.value.length;
        counter.textContent = `${len} / ${max}`;
        counter.classList.remove('warn','danger');
        if (len > max * 0.9) counter.classList.add('warn');
        if (len > max) counter.classList.add('danger');
        if (len < min) counter.classList.add('warn');
    }
    el.addEventListener('input', update);
    update();
});
</script>
</body>
</html>