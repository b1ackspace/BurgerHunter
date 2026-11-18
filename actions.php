<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Проверяем, что юзер из сессии реально существует в текущей БД (защита после удаления .db)
$stmtCheck = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmtCheck->execute([$_SESSION['user']['user_id']]);
$currentUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$currentUserId = $currentUser['user_id'];

$developers = $db->query("SELECT * FROM users WHERE role = 'Разработчик'")->fetchAll(PDO::FETCH_ASSOC);
$projects = $db->query("SELECT * FROM projects")->fetchAll(PDO::FETCH_ASSOC);

function logHistory($db, $bugId, $userId, $field, $oldVal, $newVal) {
    if ($oldVal !== $newVal) {
        $stmt = $db->prepare("INSERT INTO bughistory (bug_id, changed_by, field, old_val, new_val) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$bugId, $userId, $field, (string)$oldVal, (string)$newVal]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_bug') {
        $projectName = trim($_POST['project_name'] ?? 'Без проекта');

        $stmtProj = $db->prepare("SELECT project_id FROM projects WHERE name = ?");
        $stmtProj->execute([$projectName]);
        $project = $stmtProj->fetch(PDO::FETCH_ASSOC);

        if ($project) {
            $projectId = $project['project_id'];
        } else {
            $code = 'PRJ-' . strtoupper(substr(uniqid(), -6));
            $stmtNewProj = $db->prepare("INSERT INTO projects (code, name, lead_id) VALUES (?, ?, ?)");
            $stmtNewProj->execute([$code, $projectName, $currentUserId]);
            $projectId = $db->lastInsertId();
        }

        $stmt = $db->prepare("
            INSERT INTO bugs (project_id, reporter_id, title, description, expected_result, actual_result, priority, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'создание')
        ");
        $stmt->execute([
            $projectId,
            $currentUserId,
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['expected_result'] ?? ''),
            trim($_POST['actual_result'] ?? ''),
            $_POST['priority'] ?? 'Средний'
        ]);
        logHistory($db, $db->lastInsertId(), $currentUserId, 'status', '—', 'создание');
    }
    if ($action === 'assign_dev') {
        $bugId = (int)$_POST['bug_id'];
        $devId = (int)$_POST['assignee_id'];
        $oldBug = $db->query("SELECT * FROM bugs WHERE bug_id = $bugId")->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("UPDATE bugs SET assignee_id = ?, status = 'назначен инженер' WHERE bug_id = ?");
        $stmt->execute([$devId, $bugId]);

        logHistory($db, $bugId, $currentUserId, 'assignee_id', $oldBug['assignee_id'] ?? 'не назначен', $devId);
        logHistory($db, $bugId, $currentUserId, 'status', $oldBug['status'], 'назначен инженер');
    }

    if ($action === 'change_status') {
        $bugId = (int)$_POST['bug_id'];
        $newStatus = $_POST['new_status'];
        $oldBug = $db->query("SELECT * FROM bugs WHERE bug_id = $bugId")->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("UPDATE bugs SET status = ? WHERE bug_id = ?");
        $stmt->execute([$newStatus, $bugId]);

        logHistory($db, $bugId, $currentUserId, 'status', $oldBug['status'], $newStatus);
    }

    if ($action === 'add_comment') {
        $stmt = $db->prepare("INSERT INTO comments (bug_id, user_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['bug_id'], $currentUserId, $_POST['body']]);
    }

    header("Location: index.php");
    exit;
}

$bugs = $db->query("
    SELECT b.*, p.code AS project_code, p.name AS project_name, r.name AS reporter_name, a.name AS assignee_name
    FROM bugs b
    LEFT JOIN projects p ON b.project_id = p.project_id
    LEFT JOIN users r ON b.reporter_id = r.user_id
    LEFT JOIN users a ON b.assignee_id = a.user_id
    ORDER BY b.bug_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$nextStageMap = [
    'назначен инженер' => 'идет исправление',
    'идет исправление' => 'передана в QA',
    'передана в QA'    => 'проверено QA'
];
