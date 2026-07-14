<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '收件人管理';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'add') {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $group_id = (int)(isset($_POST['group_id']) ? $_POST['group_id'] : 0);
        $status = (int)(isset($_POST['status']) ? $_POST['status'] : 1);
        if ($email !== '') {
            $stmt = $db->prepare("INSERT INTO {$prefix}recipients (email, name, group_id, status) VALUES (?, ?, ?, ?)");
            $stmt->execute(array($email, $name, $group_id, $status));
            $msg = '收件人添加成功';
        }
    } elseif ($action === 'edit') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $group_id = (int)(isset($_POST['group_id']) ? $_POST['group_id'] : 0);
        $status = (int)(isset($_POST['status']) ? $_POST['status'] : 1);
        if ($id > 0 && $email !== '') {
            $stmt = $db->prepare("UPDATE {$prefix}recipients SET email = ?, name = ?, group_id = ?, status = ? WHERE id = ?");
            $stmt->execute(array($email, $name, $group_id, $status, $id));
            $msg = '收件人更新成功';
        }
    } elseif ($action === 'delete') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM {$prefix}recipients WHERE id = ?");
            $stmt->execute(array($id));
            $msg = '收件人删除成功';
        }
    } elseif ($action === 'toggle') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE {$prefix}recipients SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute(array($id));
            $msg = '状态切换成功';
        }
    } elseif ($action === 'import') {
        $group_id = (int)(isset($_POST['import_group_id']) ? $_POST['import_group_id'] : 0);
        $lines = trim(isset($_POST['import_emails']) ? $_POST['import_emails'] : '');
        if ($lines !== '') {
            $emails = preg_split('/\r\n|\r|\n/', $lines);
            $inserted = 0;
            $sql = sql_insert_ignore("{$prefix}recipients", "email, group_id, status") . " VALUES (?, ?, 1)";
            $stmt = $db->prepare($sql);
            foreach ($emails as $line) {
                $email = trim($line);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stmt->execute(array($email, $group_id));
                    $inserted++;
                }
            }
            $msg = "批量导入完成，成功插入 {$inserted} 条记录";
        }
    }
}

// 搜索条件
$searchEmail = trim(isset($_GET['email']) ? $_GET['email'] : '');
$searchName = trim(isset($_GET['name']) ? $_GET['name'] : '');
$searchGroup = (int)(isset($_GET['group_id']) ? $_GET['group_id'] : 0);

$where = array();
$params = array();
if ($searchEmail !== '') {
    $where[] = "r.email LIKE ?";
    $params[] = '%' . $searchEmail . '%';
}
if ($searchName !== '') {
    $where[] = "r.name LIKE ?";
    $params[] = '%' . $searchName . '%';
}
if ($searchGroup > 0) {
    $where[] = "r.group_id = ?";
    $params[] = $searchGroup;
}

$sql = "SELECT r.*, g.name as group_name FROM {$prefix}recipients r LEFT JOIN {$prefix}groups g ON r.group_id = g.id";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY r.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$recipients = $stmt->fetchAll();

// 获取分组列表
$groups = $db->query("SELECT id, name FROM {$prefix}groups ORDER BY id DESC")->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div class="card-title" style="margin-bottom:0;">收件人列表</div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-success" onclick="openModal('modal-import')">批量导入</button>
            <button class="btn btn-primary" onclick="openModal('modal-add')">+ 新建收件人</button>
        </div>
    </div>

    <form class="search-form" method="get">
        <input type="text" name="email" placeholder="搜索邮箱" value="<?php echo htmlspecialchars($searchEmail); ?>">
        <input type="text" name="name" placeholder="搜索姓名" value="<?php echo htmlspecialchars($searchName); ?>">
        <select name="group_id">
            <option value="0">全部分组</option>
            <?php foreach ($groups as $g): ?>
            <option value="<?php echo (int)$g['id']; ?>" <?php if ($searchGroup == $g['id']) echo 'selected'; ?>><?php echo htmlspecialchars($g['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">搜索</button>
        <a href="recipients.php" class="btn btn-sm" style="background:#e0e0e0;color:#333;">重置</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>邮箱</th>
                <th>姓名</th>
                <th>所属分组</th>
                <th>状态</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recipients)): ?>
            <tr><td colspan="7" class="empty-state">暂无收件人</td></tr>
            <?php else: ?>
            <?php foreach ($recipients as $r): ?>
            <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['email']); ?></td>
                <td><?php echo htmlspecialchars(!empty($r['name']) ? $r['name'] : '-'); ?></td>
                <td><?php echo htmlspecialchars(!empty($r['group_name']) ? $r['group_name'] : '未分组'); ?></td>
                <td>
                    <?php if ($r['status'] == 1): ?>
                    <span class="badge badge-success">启用</span>
                    <?php else: ?>
                    <span class="badge badge-danger">禁用</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editRecipient(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars($r['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(isset($r['name']) ? $r['name'] : '', ENT_QUOTES); ?>', <?php echo (int)(isset($r['group_id']) ? $r['group_id'] : 0); ?>, <?php echo (int)$r['status']; ?>)">编辑</button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-success"><?php echo $r['status'] == 1 ? '禁用' : '启用'; ?></button>
                    </form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该收件人？');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
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
            <div style="font-weight:600;">新建收件人</div>
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
                    <label>姓名</label>
                    <input type="text" name="name">
                </div>
                <div class="form-group">
                    <label>所属分组</label>
                    <select name="group_id">
                        <option value="0">未分组</option>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
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
            <div style="font-weight:600;">编辑收件人</div>
            <button class="modal-close" onclick="closeModal('modal-edit')">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" name="email" id="edit-email" required>
                </div>
                <div class="form-group">
                    <label>姓名</label>
                    <input type="text" name="name" id="edit-name">
                </div>
                <div class="form-group">
                    <label>所属分组</label>
                    <select name="group_id" id="edit-group_id">
                        <option value="0">未分组</option>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="edit-status">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#e0e0e0;color:#333;" onclick="closeModal('modal-edit')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量导入弹窗 -->
<div class="modal" id="modal-import">
    <div class="modal-content">
        <div class="modal-header">
            <div style="font-weight:600;">批量导入收件人</div>
            <button class="modal-close" onclick="closeModal('modal-import')">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="import">
                <div class="form-group">
                    <label>所属分组</label>
                    <select name="import_group_id">
                        <option value="0">未分组</option>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>邮箱列表（每行一个）</label>
                    <textarea name="import_emails" placeholder="example1@gmail.com&#10;example2@gmail.com" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#e0e0e0;color:#333;" onclick="closeModal('modal-import')">取消</button>
                <button type="submit" class="btn btn-success">导入</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRecipient(id, email, name, groupId, status) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-group_id').value = groupId;
    document.getElementById('edit-status').value = status;
    openModal('modal-edit');
}
</script>

<?php require __DIR__ . '/inc/foot.php'; ?>
