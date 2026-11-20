<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'init.php';

// ПРОВЕРКА CSRF
checkCsrf();

$action = $_POST['action'] ?? '';
$errors = [];

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
        header("Location: bug_create.php");
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
        $projectId, $currentUserId,
        $title, $description, $expected, $actual,
        $_POST['priority'] ?? 'Средний'
    ]);
    $newBugId = $db->lastInsertId();
    logHistory($db, $newBugId, $currentUserId, 'status', '—', 'создание');

    header("Location: bug_view.php?id=$newBugId");
    exit;
}

if ($action === 'assign_dev') {
    $bugId      = (int)$_POST['bug_id'];
    $assigneeId = (int)$_POST['assignee_id'];

    $stmt = $db->prepare("SELECT * FROM bugs WHERE bug_id = ?");
    $stmt->execute([$bugId]);
    $oldBug = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($oldBug) {
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
    header("Location: bug_view.php?id=$bugId");
    exit;
}

if ($action === 'change_status') {
    $bugId = (int)$_POST['bug_id'];
    $newStatus = $_POST['new_status'];

    $stmt = $db->prepare("SELECT * FROM bugs WHERE bug_id = ?");
    $stmt->execute([$bugId]);
    $oldBug = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($oldBug) {
        $stmt = $db->prepare("UPDATE bugs SET status = ? WHERE bug_id = ?");
        $stmt->execute([$newStatus, $bugId]);
        logHistory($db, $bugId, $currentUserId, 'status', $oldBug['status'], $newStatus);
    }
    header("Location: bug_view.php?id=$bugId");
    exit;
}

if ($action === 'add_comment') {
    $bugId = (int)$_POST['bug_id'];
    $body = validateField('comment', $_POST['body'] ?? '', $LIMITS, $errors);

    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
        header("Location: bug_view.php?id=$bugId");
        exit;
    }

    $stmt = $db->prepare("INSERT INTO comments (bug_id, user_id, body) VALUES (?, ?, ?)");
    $stmt->execute([$bugId, $currentUserId, $body]);

    header("Location: bug_view.php?id=$bugId");
    exit;
}

if ($action === 'delete_bug') {
    if (!$isAdmin) abort(403);
    $bugId = (int)$_POST['bug_id'];

    $stmt = $db->prepare("DELETE FROM bugs WHERE bug_id = ?");
    $stmt->execute([$bugId]);

    header("Location: bugs.php");
    exit;
}

if ($action === 'edit_bug') {
    $bugId = (int)$_POST['bug_id'];
    
    $stmt = $db->prepare("SELECT * FROM bugs WHERE bug_id = ?");
    $stmt->execute([$bugId]);
    $bug = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bug) abort(404);
    if ($bug['reporter_id'] != $currentUserId && !$isAdmin) abort(403);

    $newTitle       = validateField('title',           $_POST['title'] ?? $bug['title'],                     $LIMITS, $errors);
    $newDescription = validateField('description',     $_POST['description'] ?? $bug['description'],         $LIMITS, $errors);
    $newExpected    = validateField('expected_result', $_POST['expected_result'] ?? $bug['expected_result'], $LIMITS, $errors);
    $newActual      = validateField('actual_result',   $_POST['actual_result'] ?? $bug['actual_result'],     $LIMITS, $errors);
    $newPriority    = $_POST['priority'] ?? $bug['priority'];

    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
        $_SESSION['flash_old'] = $_POST; 
        header("Location: bug_edit.php?id=$bugId");
        exit;
    }

    $stmt = $db->prepare("UPDATE bugs SET title = ?, description = ?, expected_result = ?, actual_result = ?, priority = ? WHERE bug_id = ?");
    $stmt->execute([$newTitle, $newDescription, $newExpected, $newActual, $newPriority, $bugId]);

    if ($bug['title'] !== $newTitle) logHistory($db, $bugId, $currentUserId, 'title', $bug['title'], $newTitle);
    if ($bug['description'] !== $newDescription) logHistory($db, $bugId, $currentUserId, 'description', $bug['description'], $newDescription);
    if ($bug['expected_result'] !== $newExpected) logHistory($db, $bugId, $currentUserId, 'expected_result', $bug['expected_result'], $newExpected);
    if ($bug['actual_result'] !== $newActual) logHistory($db, $bugId, $currentUserId, 'actual_result', $bug['actual_result'], $newActual);
    if ($bug['priority'] !== $newPriority) logHistory($db, $bugId, $currentUserId, 'priority', $bug['priority'], $newPriority);

    header("Location: bug_view.php?id=$bugId");
    exit;
}

if ($action === 'review_request') {
    if (!$isAdmin) abort(403);
    $requestId = (int)$_POST['request_id'];
    $decision  = $_POST['decision'];

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
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;