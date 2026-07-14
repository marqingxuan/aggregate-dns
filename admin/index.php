<?php
require __DIR__ . '/../data/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['username'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = '账号或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理员登录 - 邮件群发系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f3f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 40px; width: 90%; max-width: 380px; }
        h1 { font-size: 22px; color: #1a1a2e; margin-bottom: 24px; text-align: center; }
        .form-group { margin-bottom: 16px; }
        input { width: 100%; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        input:focus { outline: none; border-color: #69a7ff; }
        button { width: 100%; padding: 12px; background: #69a7ff; color: #fff; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; background: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <div class="card">
        <h1>管理员登录</h1>
        <?php if (isset($error)): ?><div class="alert"><?php echo $error; ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group"><input type="text" name="username" placeholder="管理员账号" required></div>
            <div class="form-group"><input type="password" name="password" placeholder="密码" required></div>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html>
