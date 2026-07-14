<?php
/**
 * 数据库配置文件
 * 
 * 支持 MySQL 和 SQLite 两种数据库
 * 修改 DB_TYPE 即可切换，无需改动其他代码
 */

// 数据库类型：mysql 或 sqlite
define('DB_TYPE', 'mysql');

// MySQL 配置（当 DB_TYPE = 'mysql' 时生效）
define('DB_HOST', 'localhost');   // 数据库主机
define('DB_PORT', 3306);          // 数据库端口
define('DB_NAME', 'mail_system'); // 数据库名
define('DB_USER', 'root');        // 用户名
define('DB_PASS', '');            // 密码
define('DB_PREFIX', '');          // 表前缀（不需要请留空）
define('DB_CHARSET', 'utf8mb4');  // 字符集
?>
