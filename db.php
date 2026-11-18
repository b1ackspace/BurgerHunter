<?php
session_start();
$db = new PDO('sqlite:bughunter.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON;');
$db->exec("
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('Заказчик', 'Тестировщик', 'Админ', 'Разработчик'))
);

CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    lead_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bugs (
    bug_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(project_id) ON DELETE CASCADE,
    reporter_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    assignee_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    description TEXT,
    expected_result TEXT,
    actual_result TEXT,
    status TEXT NOT NULL DEFAULT 'создание' CHECK (
        status IN ('создание', 'назначен инженер', 'идет исправление', 'передана в QA', 'проверено QA')
    ),
    priority TEXT NOT NULL DEFAULT 'Средний' CHECK (priority IN ('Низкий', 'Средний', 'Высокий', 'Критический'))
);

CREATE TABLE IF NOT EXISTS comments (
    comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL REFERENCES bugs(bug_id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    body TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bughistory (
    history_id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL REFERENCES bugs(bug_id) ON DELETE CASCADE,
    changed_by INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    field TEXT NOT NULL,
    old_val TEXT,
    new_val TEXT,
    changed_at TEXT DEFAULT CURRENT_TIMESTAMP
);
");
if ($db->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
    $pass = password_hash('1234', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Олег (Админ)', 'admin@bh.ru', $pass, 'Админ']);

    $db->exec("INSERT INTO projects (code, name, lead_id) VALUES ('BH-CORE', 'BugHunter Web App', 1)");
}