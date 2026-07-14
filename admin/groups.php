<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '收件人分组管理';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'add') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO {$prefix}groups (name) VALUES (?)");
            $stmt->execute(array($name));
            $msg = '分组添加成功';
        }
    } elseif ($action === 'edit') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        if ($id > 0 && $name !== '') {
            $stmt = $db->prepare("UPDATE {$prefix}groups SET name = ? WHERE id = ?");
            $stmt->execute(array($name, $id));
            $msg = '分组更新成功';
        }
    } elseif ($action === 'delete') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM {$prefix}groups WHERE id = ?");
            $stmt->execute(array($id));
            $msg = '分组删除成功';
        }
    }
}

// 查询分组列表及收件人数量
$stmt = $db->query("SELECT g.*, (SELECT COUNT(*) FROM {$prefix}recipients WHERE group_id = g.id) as recipient_count FROM {$prefix}groups g ORDER BY g.id DESC");
$groups = $stmt->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div class="card-title" style="margin-bottom:0;">分组列表</div>
        <button class="btn btn-primary" onclick="openModal('modal-add')">+ 新建分组</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>分组名称</th>
                <th>收件人数量</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($groups)): ?>
            <tr><td colspan="5" class="empty-state">暂无分组</td></tr>
            <?php else: ?>
            <?php foreach ($groups as $g): ?>
            <tr>
                <td><?php echo (int)$g['id']; ?></td>
                <td><?php echo htmlspecialchars($g['name']); ?></td>
                <td><?php echo (int)$g['recipient_count']; ?></td>
                <td><?php echo htmlspecialchars($g['created_at']); ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editGroup(<?php echo (int)$g['id']; ?>, '<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>')">编辑</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该分组？');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
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
            <div style="font-weight:600;">新建分组</div>
            <button class="modal-close" onclick="closeModal('modal-add')">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>分组名称</label>
                    <input type="text" name="name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#e0e0e0;color:#333;" onclick="closeModal('modal-add')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑弹窗 -->
<div class="modal" id="modal-edit">
    <div class="modal-content">
        <div class="modal-header">
            <div style="font-weight:600;">编辑分组</div>
            <button class="modal-close" onclick="closeModal('modal-edit')">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label>分组名称</label>
                    <input type="text" name="name" id="edit-name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#e0e0e0;color:#333;" onclick="closeModal('modal-edit')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function editGroup(id, name) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    openModal('modal-edit');
}
</script>

<?php require __DIR__ . '/inc/foot.php'; ?>
