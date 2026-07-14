<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '黑名单管理';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $email = trim($_POST['email'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($email !== '') {
            try {
                $stmt = $db->prepare("INSERT INTO blacklist (email, reason) VALUES (?, ?)");
                $stmt->execute([$email, $reason]);
                $msg = '黑名单添加成功';
            } catch (PDOException $e) {
                $msg = '添加失败：该邮箱已在黑名单中';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM blacklist WHERE id = ?");
            $stmt->execute([$id]);
            $msg = '黑名单删除成功';
        }
    }
}

// 搜索
$searchEmail = trim($_GET['email'] ?? '');

$where = [];
$params = [];
if ($searchEmail !== '') {
    $where[] = "email LIKE ?";
    $params[] = '%' . $searchEmail . '%';
}

$sql = "SELECT * FROM blacklist";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div class="card-title" style="margin-bottom:0;">黑名单列表</div>
        <button class="btn btn-primary" onclick="openModal('modal-add')">+ 添加黑名单</button>
    </div>

    <form class="search-form" method="get">
        <input type="text" name="email" placeholder="搜索邮箱" value="<?php echo htmlspecialchars($searchEmail); ?>">
        <button type="submit" class="btn btn-primary btn-sm">搜索</button>
        <a href="blacklist.php" class="btn btn-sm" style="background:#e0e0e0;color:#333;">重置</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>邮箱</th>
                <th>原因</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($list)): ?>
            <tr><td colspan="5" class="empty-state">暂无黑名单记录</td></tr>
            <?php else: ?>
            <?php foreach ($list as $item): ?>
            <tr>
                <td><?php echo (int)$item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['email']); ?></td>
                <td><?php echo htmlspecialchars($item['reason'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定从黑名单移除该邮箱？');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 添加弹窗 -->
<div class="modal" id="modal-add">
    <div class="modal-content">
        <div class="modal-header">
            <div style="font-weight:600;">添加黑名单</div>
            <button class="modal-close" onclick="closeModal('modal-add')">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>原因</label>
                    <input type="text" name="reason" placeholder="可选">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#e0e0e0;color:#333;" onclick="closeModal('modal-add')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/inc/foot.php'; ?>
