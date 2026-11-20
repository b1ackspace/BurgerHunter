<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$stmtCheck = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmtCheck->execute([$_SESSION['user']['user_id']]);
$currentUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$currentUserId = $currentUser['user_id'];
$isAdmin  = ($currentUser['role'] === 'Админ');
$isTester = ($currentUser['role'] === 'Тестировщик');

// ===== CSRF-ТОКЕН =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
function checkCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            abort(419);
        }
    }
}

// ===== ПРОЕКТЫ И ПОЛЬЗОВАТЕЛИ =====
$projects = $db->query("SELECT * FROM projects")->fetchAll(PDO::FETCH_ASSOC);
$developers = $db->query("SELECT * FROM users WHERE role = 'Разработчик'")->fetchAll(PDO::FETCH_ASSOC);
$allUsers = $db->query("SELECT user_id, name, role FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ===== ЛИМИТЫ ПОЛЕЙ =====
$LIMITS = [
    'title'           => ['min' => 5,   'max' => 120],
    'description'     => ['min' => 10,  'max' => 2000],
    'expected_result' => ['min' => 5,   'max' => 1000],
    'actual_result'   => ['min' => 5,   'max' => 1000],
    'comment'         => ['min' => 1,   'max' => 500],
    'project_name'    => ['min' => 2,   'max' => 60],
];

$nextStageMap = [
    'назначен инженер' => 'идет исправление',
    'идет исправление' => 'передана в QA',
    'передана в QA'    => 'проверено QA'
];

// ===== ФУНКЦИИ =====
function validateField(string $field, string $value, array $LIMITS, array &$errors): string {
    $value = trim($value);
    if (!isset($LIMITS[$field])) return $value;
    $len = mb_strlen($value);
    $min = $LIMITS[$field]['min'];
    $max = $LIMITS[$field]['max'];
    if ($len < $min) {
        $errors[] = "Выберите проект";
    } elseif ($len > $max) {
        $errors[] = "Выберите проект";
    }
    return $value;
}

function logHistory($db, $bugId, $userId, $field, $oldVal, $newVal) {
    if ($oldVal !== $newVal) {
        $stmt = $db->prepare("INSERT INTO bughistory (bug_id, changed_by, field, old_val, new_val) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$bugId, $userId, $field, (string)$oldVal, (string)$newVal]);
    }
}

function abort($code = 404) {
    header("Location: error.php?code=$code");
    exit;
}

function renderHeader($currentUser) {
    $initial = mb_strtoupper(mb_substr($currentUser['name'], 0, 1));
    ?>
    <header>
        <h1><a href="index.php" style="color: inherit; text-decoration: none;">BugHunter</a></h1>
        <div class="header-controls">
            <a href="index.php">Главная</a>
            <a href="bugs.php">Все баги</a>
            <a href="bug_create.php" class="btn-green" style="padding: 8px 14px; color: white;">+ Создать баг</a>
            <a href="profile.php" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: var(--text-main);">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                    <?= $initial ?>
                </div>
                <b><?= htmlspecialchars($currentUser['name']) ?></b>
                <span style="font-size: 12px; color: var(--text-muted);">[<?= htmlspecialchars($currentUser['role']) ?>]</span>
            </a>
            <button type="button" id="theme-toggle" class="theme-btn">☀️</button>
            <a href="login.php?logout=1" class="btn-logout">Выйти</a>
        </div>
    </header>
    <?php
}