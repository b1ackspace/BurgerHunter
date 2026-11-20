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

$developers = $db->query("SELECT * FROM users WHERE role = 'Разработчик'")->fetchAll(PDO::FETCH_ASSOC);
$projects   = $db->query("SELECT * FROM projects")->fetchAll(PDO::FETCH_ASSOC);

// Все пользователи — для выпадающего списка назначения админом
$allUsers = $db->query("SELECT user_id, name, role FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Лимиты полей
$LIMITS = [
    'title'           => ['min' => 5,   'max' => 120],
    'description'     => ['min' => 10,  'max' => 2000],
    'expected_result' => ['min' => 5,   'max' => 1000],
    'actual_result'   => ['min' => 5,   'max' => 1000],
    'comment'         => ['min' => 1,   'max' => 500],
    'project_name'    => ['min' => 2,   'max' => 60],
];

function validateField(string $field, string $value, array $LIMITS, array &$errors): string {
    $value = trim($value);
    if (!isset($LIMITS[$field])) return $value;
    $len = mb_strlen($value);
    $min = $LIMITS[$field]['min'];
    $max = $LIMITS[$field]['max'];
    if ($len < $min) {
        $errors[] = "Поле «$field»: минимум $min символов (сейчас $len).";
    } elseif ($len > $max) {
        $errors[] = "Поле «$field»: максимум $max символов (сейчас $len).";
    }
    return $value;
}

function logHistory($db, $bugId, $userId, $field, $oldVal, $newVal) {
    if ($oldVal !== $newVal) {
        $stmt = $db->prepare("INSERT INTO bughistory (bug_id, changed_by, field, old_val, new_val) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$bugId, $userId, $field, (string)$oldVal, (string)$newVal]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errors = [];

    // ---------- СОЗДАНИЕ БАГА ----------
    if ($action === 'create_bug') {
        $title       = validateField('title',           $_POST['title'] ?? '',           $LIMITS, $errors);
        $description = validateField('description',     $_POST['description'] ?? '',     $LIMITS, $errors);
        $expected    = validateField('expected_result', $_POST['expected_result'] ?? '', $LIMITS, $errors);
        $actual      = validateField('actual_result',   $_POST['actual_result'] ?? '',   $LIMITS, $errors);
        $projectName = validateField('project_name',    $_POST['project_name'] ?? '',    $LIMITS, $errors);

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['flash_old'] = [
                'project_name'    => $projectName,
                'title'           => $title,
                'description'     => $description,
                'expected_result' => $expected,
                'actual_result'   => $actual,
                'priority'        => $_POST['priority'] ?? 'Средний',
            ];
            header("Location: index.php");
            exit;
        }

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
            $title, $description, $expected, $actual,
            $_POST['priority'] ?? 'Средний'
        ]);
        logHistory($db, $db->lastInsertId(), $currentUserId, 'status', '—', 'создание');
    }

    // ---------- НАЗНАЧЕНИЕ ИСПОЛНИТЕЛЯ ----------
    if ($action === 'assign_dev') {
        $bugId      = (int)$_POST['bug_id'];
        $assigneeId = (int)$_POST['assignee_id'];
        $oldBug = $db->query("SELECT * FROM bugs WHERE bug_id = $bugId")->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$assigneeId]);
        $assigneeRole = $stmt->fetchColumn();

        $allowed = false;
        if ($assigneeRole) {
            if ($isAdmin) {
                $allowed = true;
            } elseif ($isTester && $assigneeRole === 'Разработчик') {
                $allowed = true;
            }
        }

        if ($allowed) {
            $stmt = $db->prepare("UPDATE bugs SET assignee_id = ?, status = 'назначен инженер' WHERE bug_id = ?");
            $stmt->execute([$assigneeId, $bugId]);

            logHistory($db, $bugId, $currentUserId, 'assignee_id', $oldBug['assignee_id'] ?? 'не назначен', $assigneeId);
            logHistory($db, $bugId, $currentUserId, 'status', $oldBug['status'], 'назначен инженер');
        }
    }

    // ---------- СМЕНА СТАТУСА ----------
    if ($action === 'change_status') {
        $bugId = (int)$_POST['bug_id'];
        $newStatus = $_POST['new_status'];
        $oldBug = $db->query("SELECT * FROM bugs WHERE bug_id = $bugId")->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("UPDATE bugs SET status = ? WHERE bug_id = ?");
        $stmt->execute([$newStatus, $bugId]);

        logHistory($db, $bugId, $currentUserId, 'status', $oldBug['status'], $newStatus);
    }

    // ---------- КОММЕНТАРИЙ ----------
    if ($action === 'add_comment') {
        $body = validateField('comment', $_POST['body'] ?? '', $LIMITS, $errors);
        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            header("Location: index.php");
            exit;
        }
        $stmt = $db->prepare("INSERT INTO comments (bug_id, user_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['bug_id'], $currentUserId, $body]);
    }

    // ---------- УДАЛЕНИЕ БАГА ----------
    if ($action === 'delete_bug' && $isAdmin) {
        $bugId = (int)$_POST['bug_id'];
        $stmt = $db->prepare("DELETE FROM bugs WHERE bug_id = ?");
        $stmt->execute([$bugId]);
    }

    // ---------- РЕДАКТИРОВАНИЕ БАГА ----------
    if ($action === 'edit_bug') {
        $bugId = (int)$_POST['bug_id'];
        $bug = $db->query("SELECT * FROM bugs WHERE bug_id = $bugId")->fetch(PDO::FETCH_ASSOC);
        if ($bug && ($bug['reporter_id'] == $currentUserId || $isAdmin)) {
            $newTitle       = validateField('title',           $_POST['title'] ?? $bug['title'],                     $LIMITS, $errors);
            $newDescription = validateField('description',     $_POST['description'] ?? $bug['description'],         $LIMITS, $errors);
            $newExpected    = validateField('expected_result', $_POST['expected_result'] ?? $bug['expected_result'], $LIMITS, $errors);
            $newActual      = validateField('actual_result',   $_POST['actual_result'] ?? $bug['actual_result'],     $LIMITS, $errors);
            $newPriority    = $_POST['priority'] ?? $bug['priority'];

            if (!empty($errors)) {
                $_SESSION['flash_errors'] = $errors;
                header("Location: index.php");
                exit;
            }

            $stmt = $db->prepare("UPDATE bugs SET title = ?, description = ?, expected_result = ?, actual_result = ?, priority = ? WHERE bug_id = ?");
            $stmt->execute([$newTitle, $newDescription, $newExpected, $newActual, $newPriority, $bugId]);

            if ($bug['title'] !== $newTitle) logHistory($db, $bugId, $currentUserId, 'title', $bug['title'], $newTitle);
            if ($bug['description'] !== $newDescription) logHistory($db, $bugId, $currentUserId, 'description', $bug['description'], $newDescription);
            if ($bug['expected_result'] !== $newExpected) logHistory($db, $bugId, $currentUserId, 'expected_result', $bug['expected_result'], $newExpected);
            if ($bug['actual_result'] !== $newActual) logHistory($db, $bugId, $currentUserId, 'actual_result', $bug['actual_result'], $newActual);
            if ($bug['priority'] !== $newPriority) logHistory($db, $bugId, $currentUserId, 'priority', $bug['priority'], $newPriority);
        }
    }

    // ---------- ЗАЯВКИ НА РОЛЬ ----------
    if ($action === 'review_request' && $isAdmin) {
        $requestId = (int)$_POST['request_id'];
        $decision  = $_POST['decision']; // 'одобрена' | 'отклонена'

        $req = $db->prepare("SELECT * FROM role_requests WHERE request_id = ?");
        $req->execute([$requestId]);
        $request = $req->fetch(PDO::FETCH_ASSOC);

        if ($request && $request['status'] === 'ожидает') {
            $stmt = $db->prepare("UPDATE role_requests SET status = ?, reviewed_by = ? WHERE request_id = ?");
            $stmt->execute([$decision, $currentUserId, $requestId]);

            if ($decision === 'одобрена') {
                $stmt = $db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->execute([$request['desired_role'], $request['user_id']]);
            }
        }
    }

    header("Location: index.php");
    exit;
}

// ---------- СПИСОК БАГОВ ----------
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

// ---------- ЗАЯВКИ НА РОЛЬ (для админа) ----------
$pendingRequests = [];
if ($isAdmin) {
    $stmt = $db->query("
        SELECT rr.*, u.name AS user_name, u.email AS user_email
        FROM role_requests rr
        JOIN users u ON rr.user_id = u.user_id
        WHERE rr.status = 'ожидает'
        ORDER BY rr.created_at DESC
    ");
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- FLASH-СООБЩЕНИЯ ----------
$flashErrors = $_SESSION['flash_errors'] ?? [];
$flashOld    = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_errors'], $_SESSION['flash_old']);