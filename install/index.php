<?php
require __DIR__ . '/../data/config.php';

$lockFile = __DIR__ . '/../data/installed.lock';
$installed = file_exists($lockFile);

if ($installed && empty($_GET['action'])) {
    exit('系统已安装，如需重装请删除 data/installed.lock');
}

$dbType = defined('DB_TYPE') ? DB_TYPE : 'sqlite';
$dbTypeName = $dbType === 'mysql' ? 'MySQL' : 'SQLite';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (strlen($username) < 3 || strlen($password) < 6) {
        $error = '管理员账号至少3位，密码至少6位';
    } else {
        require __DIR__ . '/../data/db.php';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
        try {
            $stmt = $db->prepare("INSERT INTO {$prefix}users (username, password) VALUES (?, ?)");
            $stmt->execute(array($username, $hash));
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $success = true;
        } catch (Exception $e) {
            $error = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>邮件群发系统 - 安装</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f3f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 40px; width: 90%; max-width: 460px; }
        h1 { font-size: 22px; color: #1a1a2e; margin-bottom: 8px; text-align: center; }
        p.sub { color: #64748b; text-align: center; margin-bottom: 24px; font-size: 14px; }
        .db-info { background: #f0f7ff; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #555; }
        .db-info strong { color: #1a1a2e; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 14px; color: #333; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #69a7ff; }
        button { width: 100%; padding: 12px; background: #69a7ff; color: #fff; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .success-box { text-align: center; }
        .success-box a { display: inline-block; margin-top: 16px; padding: 10px 24px; background: #69a7ff; color: #fff; text-decoration: none; border-radius: 8px; }
        .hint { font-size: 12px; color: #888; margin-top: 16px; text-align: center; }
        .hint a { color: #69a7ff; }
    </style>
</head>
<body>
    <div class="card">
        <h1>邮件群发系统</h1>
        <p class="sub">快速安装向导</p>

        <div class="db-info">
            <strong>当前数据库类型：</strong><?php echo htmlspecialchars($dbTypeName); ?><br>
            <?php if ($dbType === 'mysql'): ?>
            主机：<?php echo htmlspecialchars(DB_HOST); ?> | 数据库：<?php echo htmlspecialchars(DB_NAME); ?>
            <?php else: ?>
            文件：data/mail.db
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
        <div class="success-box">
            <div class="alert alert-success">安装成功！</div>
            <a href="../admin/">进入管理后台</a>
        </div>
        <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>管理员账号</label>
                <input type="text" name="username" placeholder="请输入管理员账号" required minlength="3">
            </div>
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="password" placeholder="至少6位" required minlength="6">
            </div>
            <button type="submit">立即安装</button>
        </form>
        <div class="hint">
            如需修改数据库配置，请编辑 <code>data/config.php</code>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
