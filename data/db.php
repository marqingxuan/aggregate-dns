<?php
// SQLite 数据库连接与初始化
$dbFile = __DIR__ . '/mail.db';
$dsn = 'sqlite:' . $dbFile;
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $db = new PDO($dsn, null, null, $options);
} catch (PDOException $e) {
    die('数据库连接失败: ' . $e->getMessage());
}

// 初始化表结构
function initDb($db) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            host TEXT NOT NULL,
            port INTEGER NOT NULL DEFAULT 465,
            user TEXT NOT NULL,
            pwd TEXT NOT NULL,
            from_name TEXT,
            from_addr TEXT,
            status INTEGER DEFAULT 1,
            send_count INTEGER DEFAULT 0,
            daily_limit INTEGER DEFAULT 100,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER DEFAULT 0,
            email TEXT NOT NULL,
            name TEXT,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS blacklist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            template_id INTEGER DEFAULT 0,
            account_id INTEGER DEFAULT 0,
            recipient TEXT NOT NULL,
            status TEXT NOT NULL,
            error TEXT,
            send_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            batch_id TEXT
        )",
        "CREATE TABLE IF NOT EXISTS settings (
            skey TEXT PRIMARY KEY,
            svalue TEXT
        )"
    ];
    foreach ($tables as $sql) {
        $db->exec($sql);
    }
}

initDb($db);

// 公共函数
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function requireLogin() {
    session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: index.php');
        exit;
    }
}

function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT svalue FROM settings WHERE skey = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['svalue'] : $default;
}

function setSetting($db, $key, $value) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (skey, svalue) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

function getStats($db) {
    $stats = [];
    $stats['total_sent'] = $db->query("SELECT COUNT(*) FROM logs WHERE status = 'success'")->fetchColumn();
    $stats['total_fail'] = $db->query("SELECT COUNT(*) FROM logs WHERE status = 'fail'")->fetchColumn();
    $stats['today_sent'] = $db->query("SELECT COUNT(*) FROM logs WHERE status = 'success' AND date(send_time) = date('now')")->fetchColumn();
    $stats['total_accounts'] = $db->query("SELECT COUNT(*) FROM accounts WHERE status = 1")->fetchColumn();
    $stats['total_templates'] = $db->query("SELECT COUNT(*) FROM templates")->fetchColumn();
    $stats['total_recipients'] = $db->query("SELECT COUNT(*) FROM recipients WHERE status = 1")->fetchColumn();
    $stats['total_blacklist'] = $db->query("SELECT COUNT(*) FROM blacklist")->fetchColumn();
    return $stats;
}
