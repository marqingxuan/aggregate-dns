<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '仪表盘';
$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$stats = getStats($db);

// 最近7天发送趋势
$stmt = $db->query("SELECT DATE(send_time) as day, COUNT(*) as count FROM {$prefix}logs WHERE status = 'success' AND send_time >= " . sql_date_sub(6) . " GROUP BY DATE(send_time) ORDER BY day");
$chartData = $stmt->fetchAll();

// 最近10条日志
$stmt = $db->query("SELECT l.*, t.name as template_name FROM {$prefix}logs l LEFT JOIN {$prefix}templates t ON l.template_id = t.id ORDER BY l.id DESC LIMIT 10");
$recentLogs = $stmt->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_sent']; ?></div>
        <div class="stat-label">发送成功</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_fail']; ?></div>
        <div class="stat-label">发送失败</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['today_sent']; ?></div>
        <div class="stat-label">今日发送</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_accounts']; ?></div>
        <div class="stat-label">可用账号</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_templates']; ?></div>
        <div class="stat-label">邮件模板</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_recipients']; ?></div>
        <div class="stat-label">收件人</div>
    </div>
</div>

<div class="card">
    <div class="card-title">最近发送记录</div>
    <table>
        <thead>
            <tr><th>收件人</th><th>模板</th><th>状态</th><th>时间</th><th>错误信息</th></tr>
        </thead>
        <tbody>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
                <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                <td><?php echo htmlspecialchars(!empty($log['template_name']) ? $log['template_name'] : '-'); ?></td>
                <td><span class="badge badge-<?php echo $log['status'] == 'success' ? 'success' : 'danger'; ?>"><?php echo $log['status'] == 'success' ? '成功' : '失败'; ?></span></td>
                <td><?php echo $log['send_time']; ?></td>
                <td style="color:#999;font-size:12px;"><?php echo htmlspecialchars(!empty($log['error']) ? $log['error'] : '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/inc/foot.php'; ?>
