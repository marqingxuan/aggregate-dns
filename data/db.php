<?php
// 防止重复加载
if (defined('DB_LOADED')) return;
define('DB_LOADED', true);

require __DIR__ . '/config.php';

// ========== 全局调试日志 ==========
$SYS_LOG_FILE = __DIR__ . '/runtime.log';
if (!function_exists('sys_log')) {
    function sys_log($msg) {
        global $SYS_LOG_FILE;
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        file_put_contents($SYS_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }
}

// 注册全局错误处理器
if (!function_exists('sys_error_handler')) {
    function sys_error_handler($errno, $errstr, $errfile, $errline) {
        $types = array(
            E_ERROR             => 'Fatal',
            E_WARNING           => 'Warning',
            E_PARSE             => 'Parse',
            E_NOTICE            => 'Notice',
            E_CORE_ERROR        => 'CoreError',
            E_CORE_WARNING      => 'CoreWarning',
            E_COMPILE_ERROR     => 'CompileError',
            E_COMPILE_WARNING   => 'CompileWarning',
            E_USER_ERROR        => 'UserError',
            E_USER_WARNING      => 'UserWarning',
            E_USER_NOTICE       => 'UserNotice',
            E_STRICT            => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable',
            E_DEPRECATED        => 'Deprecated',
            E_USER_DEPRECATED   => 'UserDeprecated',
        );
        $type = isset($types[$errno]) ? $types[$errno] : 'Unknown';
        sys_log("[{$type}] {$errstr} in {$errfile}:{$errline}");
        return false; // 继续执行 PHP 默认的错误处理
    }
    set_error_handler('sys_error_handler');
}

// 注册全局异常处理器
if (!function_exists('sys_exception_handler')) {
    function sys_exception_handler($e) {
        sys_log('[Exception] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    set_exception_handler('sys_exception_handler');
}

// 注册 shutdown 函数捕获 Fatal Error
if (!function_exists('sys_shutdown_handler')) {
    function sys_shutdown_handler() {
        $error = error_get_last();
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            sys_log('[Shutdown] ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        }
    }
    register_shutdown_function('sys_shutdown_handler');
}

// 同时让 PHP 运行时错误写入同一文件
ini_set('log_errors', 1);
ini_set('error_log', $SYS_LOG_FILE);

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
if (!function_exists('sql_today')) {
    function sql_today() {
        global $DB_TYPE;
        return $DB_TYPE === 'mysql' ? 'CURDATE()' : "date('now')";
    }
}

if (!function_exists('sql_date_sub')) {
    function sql_date_sub($days) {
        global $DB_TYPE;
        return $DB_TYPE === 'mysql'
            ? "DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)"
            : "date('now', '-" . (int)$days . " days')";
    }
}

if (!function_exists('sql_now')) {
    function sql_now() {
        global $DB_TYPE;
        return $DB_TYPE === 'mysql' ? 'NOW()' : "datetime('now')";
    }
}

if (!function_exists('sql_insert_ignore')) {
    function sql_insert_ignore($table, $columns) {
        global $DB_TYPE;
        return $DB_TYPE === 'mysql'
            ? "INSERT IGNORE INTO {$table} ({$columns})"
            : "INSERT OR IGNORE INTO {$table} ({$columns})";
    }
}

if (!function_exists('sql_replace_into')) {
    function sql_replace_into($table, $columns) {
        global $DB_TYPE;
        return $DB_TYPE === 'mysql'
            ? "REPLACE INTO {$table} ({$columns})"
            : "INSERT OR REPLACE INTO {$table} ({$columns})";
    }
}

if (!function_exists('tbl')) {
    function tbl($name) {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
        return $prefix . $name;
    }
}

// ========== 初始化表结构 ==========
if (!function_exists('initDb')) {
    function initDb($db) {
        global $DB_TYPE;
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

        if ($DB_TYPE === 'mysql') {
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
}

initDb($db);

// ========== 公共函数 ==========
if (!function_exists('jsonResponse')) {
    function jsonResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        session_start();
        if (empty($_SESSION['admin_id'])) {
            header('Location: index.php');
            exit;
        }
    }
}

if (!function_exists('getSetting')) {
    function getSetting($db, $key, $default = '') {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
        $stmt = $db->prepare("SELECT svalue FROM {$prefix}settings WHERE skey = ?");
        $stmt->execute(array($key));
        $row = $stmt->fetch();
        return $row ? $row['svalue'] : $default;
    }
}

if (!function_exists('setSetting')) {
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
}

if (!function_exists('getStats')) {
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
}
