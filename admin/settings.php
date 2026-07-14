<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '系统设置';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name'] ?? '');
    $send_interval = (int)($_POST['send_interval'] ?? 5);
    $batch_size = (int)($_POST['batch_size'] ?? 10);
    $retry_times = (int)($_POST['retry_times'] ?? 3);

    setSetting($db, 'site_name', $site_name);
    setSetting($db, 'send_interval', (string)$send_interval);
    setSetting($db, 'batch_size', (string)$batch_size);
    setSetting($db, 'retry_times', (string)$retry_times);

    $msg = '设置保存成功';
}

$site_name = getSetting($db, 'site_name', '邮件群发系统');
$send_interval = (int)getSetting($db, 'send_interval', '5');
$batch_size = (int)getSetting($db, 'batch_size', '10');
$retry_times = (int)getSetting($db, 'retry_times', '3');
?>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">基础配置</div>
    <form method="post">
        <div class="form-group">
            <label>站点名称</label>
            <input type="text" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>">
        </div>
        <div class="form-group">
            <label>发送间隔（秒）</label>
            <input type="number" name="send_interval" value="<?php echo (int)$send_interval; ?>" min="0">
        </div>
        <div class="form-group">
            <label>每批发送数量</label>
            <input type="number" name="batch_size" value="<?php echo (int)$batch_size; ?>" min="1">
        </div>
        <div class="form-group">
            <label>重试次数</label>
            <input type="number" name="retry_times" value="<?php echo (int)$retry_times; ?>" min="0">
        </div>
        <button type="submit" class="btn btn-primary">保存设置</button>
    </form>
</div>

<?php require __DIR__ . '/inc/foot.php'; ?>
