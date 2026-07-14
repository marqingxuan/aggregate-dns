<?php
require __DIR__ . '/../data/db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? $pageTitle : '管理后台'; ?> - 邮件群发系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f7fa; color: #333; font-size: 14px; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 220px; background: #1a1a2e; color: #fff; flex-shrink: 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 20px; font-size: 18px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu a { display: block; padding: 14px 20px; color: #a0a3bd; text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { color: #fff; background: rgba(105,167,255,0.1); border-left-color: #69a7ff; }
        .sidebar-menu a i { display: inline-block; width: 20px; margin-right: 8px; }
        .main { flex: 1; margin-left: 220px; }
        .topbar { height: 56px; background: #fff; border-bottom: 1px solid #e8e8e8; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; position: sticky; top: 0; z-index: 10; }
        .topbar-title { font-size: 16px; font-weight: 600; }
        .topbar-actions a { color: #666; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .topbar-actions a:hover { color: #69a7ff; }
        .content { padding: 24px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #fafafa; font-weight: 600; color: #666; }
        tr:hover td { background: #f5f7fa; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; transition: opacity 0.2s; }
        .btn-primary { background: #69a7ff; color: #fff; }
        .btn-danger { background: #ff6b6b; color: #fff; }
        .btn-success { background: #51cf66; color: #fff; }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.85; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #69a7ff; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .stat-value { font-size: 28px; font-weight: 700; color: #69a7ff; }
        .stat-label { color: #888; font-size: 13px; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-warning { background: #fff3e0; color: #ef6c00; }
        .pagination { display: flex; gap: 6px; margin-top: 16px; }
        .pagination a, .pagination span { padding: 6px 12px; border-radius: 4px; text-decoration: none; color: #333; background: #fff; border: 1px solid #e0e0e0; }
        .pagination a:hover { background: #69a7ff; color: #fff; border-color: #69a7ff; }
        .pagination .current { background: #69a7ff; color: #fff; border-color: #69a7ff; }
        .search-form { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .search-form input, .search-form select { padding: 8px 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 14px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 12px 20px; border-top: 1px solid #f0f0f0; text-align: right; }
        .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #999; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        @media (max-width: 768px) {
            .sidebar { width: 0; }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="sidebar-header">邮件群发系统</div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"><i>📊</i> 仪表盘</a>
            <a href="accounts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'accounts.php' ? 'active' : ''; ?>"><i>📧</i> SMTP账号</a>
            <a href="templates.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'templates.php' ? 'active' : ''; ?>"><i>📝</i> 邮件模板</a>
            <a href="groups.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>"><i>👥</i> 收件人分组</a>
            <a href="recipients.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'recipients.php' ? 'active' : ''; ?>"><i>📇</i> 收件人管理</a>
            <a href="blacklist.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'blacklist.php' ? 'active' : ''; ?>"><i>🚫</i> 黑名单</a>
            <a href="logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>"><i>📋</i> 发送日志</a>
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>"><i>⚙️</i> 系统设置</a>
            <a href="logout.php"><i>🚪</i> 退出登录</a>
        </div>
    </div>
    <div class="main">
        <div class="topbar">
            <div class="topbar-title"><?php echo isset($pageTitle) ? $pageTitle : '管理后台'; ?></div>
            <div class="topbar-actions">
                <a href="../index.html" target="_blank">前台首页</a>
                <a href="logout.php">退出</a>
            </div>
        </div>
        <div class="content">
