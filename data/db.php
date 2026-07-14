<?php
require __DIR__ . '/config.php';

// ========== PHP 5.3/5.4 兼容层：password_hash / password_verify ==========
if (!function_exists('password_hash')) {
    function password_hash($password, $algo, $options = array()) {
        $cost = isset($options['cost']) ? $options['cost'] : 10;
        $salt = isset($options['salt']) ? $options['salt'] : '';
        if (empty($salt)) {
            $salt = substr(str_replace('+', '.', base64_encode(pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand()))), 0, 22);
        }
        $hash = sprintf('$2y$%02d$%s', $cost, $salt);
        return crypt($password, $hash);
    }
}
if (!function_exists('password_verify')) {
    function password_verify($password, $hash) {
        return crypt($password, $hash) === $hash;
    }
}

// ========== 数据库连接 ==========
$DB_TYPE = defined('DB_TYPE') ? DB_TYPE : 'sqlite';

if ($DB_TYPE === 'mysql') {
    if (!extension_loaded('pdo_mysql')) {
        die('错误：当前 PHP 环境未启用 pdo_mysql 扩展，无法连接 MySQL 数据库。<br>请修改 data/config.php 将 DB_TYPE 改为 "sqlite"，或在 php.ini 中启用 extension=pdo_mysql');
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    );
    try {
        $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die('数据库连接失败: ' . $e->getMessage());
    }
} else {
    if (!extension_loaded('pdo_sqlite')) {
        die('错误：当前 PHP 环境未启用 pdo_sqlite 扩展。请在 php.ini 中启用 extension=pdo_sqlite');
    }
    $dbFile = __DIR__ . '/mail.db';
    $dsn = 'sqlite:' . $dbFile;
    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    );
    try {
        $db = new PDO($dsn, null, null, $options);
    } catch (PDOException $e) {
        die('数据库连接失败: ' . $e->getMessage());
    }
}

// ========== SQL 方言辅助函数 ==========
function sql_today() {
    global $DB_TYPE;
    return $DB_TYPE === 'mysql' ? 'CURDATE()' : "date('now')";
}

function sql_date_sub($days) {
    global $DB_TYPE;
    return $DB_TYPE === 'mysql'
        ? "DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)"
        : "date('now', '-" . (int)$days . " days')";
}

function sql_now() {
    global $DB_TYPE;
    return $DB_TYPE === 'mysql' ? 'NOW()' : "datetime('now')";
}

function sql_insert_ignore($table, $columns) {
    global $DB_TYPE;
    return $DB_TYPE === 'mysql'
        ? "INSERT IGNORE INTO {$table} ({$columns})"
        : "INSERT OR IGNORE INTO {$table} ({$columns})";
}

function sql_replace_into($table, $columns) {
    global $DB_TYPE;
    return $DB_TYPE === 'mysql'
        ? "REPLACE INTO {$table} ({$columns})"
        : "INSERT OR REPLACE INTO {$table} ({$columns})";
}

// 表名加前缀
function tbl($name) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    return $prefix . $name;
}

// ========== 初始化表结构 ==========
function initDb($db) {
    global $DB_TYPE;
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

    if ($DB_TYPE === 'mysql') {
        // MySQL 建表语句
        $tables = array(
            "CREATE TABLE IF NOT EXISTS {$prefix}users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}accounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                host VARCHAR(255) NOT NULL,
                port INT NOT NULL DEFAULT 465,
                user VARCHAR(255) NOT NULL,
                pwd VARCHAR(255) NOT NULL,
                from_name VARCHAR(255),
                from_addr VARCHAR(255),
                status TINYINT DEFAULT 1,
                send_count INT DEFAULT 0,
                daily_limit INT DEFAULT 100,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}groups (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}recipients (
                id INT PRIMARY KEY AUTO_INCREMENT,
                group_id INT DEFAULT 0,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                status TINYINT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}blacklist (
                id INT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                reason VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                template_id INT DEFAULT 0,
                account_id INT DEFAULT 0,
                recipient VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL,
                error TEXT,
                send_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                batch_id VARCHAR(50)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS {$prefix}settings (
                skey VARCHAR(100) PRIMARY KEY,
                svalue TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        // SQLite 建表语句
        $tables = array(
            "CREATE TABLE IF NOT EXISTS {$prefix}users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS {$prefix}accounts (
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
            "CREATE TABLE IF NOT EXISTS {$prefix}templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS {$prefix}groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS {$prefix}recipients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER DEFAULT 0,
                email TEXT NOT NULL,
                name TEXT,
                status INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS {$prefix}blacklist (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS {$prefix}logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER DEFAULT 0,
                account_id INTEGER DEFAULT 0,
                recipient TEXT NOT NULL,
                status TEXT NOT NULL,
                error TEXT,
                send_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                batch_id TEXT
            )",
            "CREATE TABLE IF NOT EXISTS {$prefix}settings (
                skey TEXT PRIMARY KEY,
                svalue TEXT
            )"
        );
    }

    foreach ($tables as $sql) {
        $db->exec($sql);
    }
}

initDb($db);

// ========== 公共函数 ==========
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
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    $stmt = $db->prepare("SELECT svalue FROM {$prefix}settings WHERE skey = ?");
    $stmt->execute(array($key));
    $row = $stmt->fetch();
    return $row ? $row['svalue'] : $default;
}

function setSetting($db, $key, $value) {
    global $DB_TYPE;
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    if ($DB_TYPE === 'mysql') {
        $stmt = $db->prepare("REPLACE INTO {$prefix}settings (skey, svalue) VALUES (?, ?)");
    } else {
        $stmt = $db->prepare("INSERT OR REPLACE INTO {$prefix}settings (skey, svalue) VALUES (?, ?)");
    }
    $stmt->execute(array($key, $value));
}

function getStats($db) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    $stats = array();
    $stats['total_sent'] = $db->query("SELECT COUNT(*) FROM {$prefix}logs WHERE status = 'success'")->fetchColumn();
    $stats['total_fail'] = $db->query("SELECT COUNT(*) FROM {$prefix}logs WHERE status = 'fail'")->fetchColumn();
    $stats['today_sent'] = $db->query("SELECT COUNT(*) FROM {$prefix}logs WHERE status = 'success' AND DATE(send_time) = " . sql_today())->fetchColumn();
    $stats['total_accounts'] = $db->query("SELECT COUNT(*) FROM {$prefix}accounts WHERE status = 1")->fetchColumn();
    $stats['total_templates'] = $db->query("SELECT COUNT(*) FROM {$prefix}templates")->fetchColumn();
    $stats['total_recipients'] = $db->query("SELECT COUNT(*) FROM {$prefix}recipients WHERE status = 1")->fetchColumn();
    $stats['total_blacklist'] = $db->query("SELECT COUNT(*) FROM {$prefix}blacklist")->fetchColumn();
    return $stats;
}
