<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = 'SMTP账号管理';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$alert = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    try {
        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO {$prefix}accounts (host, port, user, pwd, from_name, from_addr, daily_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array(
                $_POST['host'],
                $_POST['port'],
                $_POST['user'],
                $_POST['pwd'],
                $_POST['from_name'],
                $_POST['from_addr'],
                $_POST['daily_limit'],
                $_POST['status']
            ));
            $alert = '添加成功';
        } elseif ($action === 'edit') {
            $stmt = $db->prepare("UPDATE {$prefix}accounts SET host=?, port=?, user=?, pwd=?, from_name=?, from_addr=?, daily_limit=?, status=? WHERE id=?");
            $stmt->execute(array(
                $_POST['host'],
                $_POST['port'],
                $_POST['user'],
                $_POST['pwd'],
                $_POST['from_name'],
                $_POST['from_addr'],
                $_POST['daily_limit'],
                $_POST['status'],
                $_POST['id']
            ));
            $alert = '修改成功';
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM {$prefix}accounts WHERE id=?");
            $stmt->execute(array($_POST['id']));
            $alert = '删除成功';
        } elseif ($action === 'toggle') {
            $stmt = $db->prepare("UPDATE {$prefix}accounts SET status = CASE WHEN status=1 THEN 0 ELSE 1 END WHERE id=?");
            $stmt->execute(array($_POST['id']));
            $alert = '状态已更新';
        }
    } catch (Exception $e) {
        $alert = '操作失败: ' . $e->getMessage();
        $alertType = 'error';
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM {$prefix}accounts WHERE 1=1";
$params = array();
if ($search !== '') {
    $sql .= " AND (host LIKE ? OR user LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

// 获取今日各账号发送统计
$todayStats = array();
$stmt = $db->query("SELECT account_id, COUNT(*) as cnt FROM {$prefix}logs WHERE status='success' AND DATE(send_time) = " . sql_today() . " GROUP BY account_id");
foreach ($stmt->fetchAll() as $row) {
    $todayStats[$row['account_id']] = $row['cnt'];
}
?>

<?php if ($alert): ?>
<div class="alert alert-<?php echo $alertType; ?>"><?php echo htmlspecialchars($alert); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">SMTP账号列表</div>
    <div class="search-form">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="搜索服务器或账号" value="<?php echo htmlspecialchars($search); ?>" style="min-width:200px;">
            <button type="submit" class="btn btn-primary btn-sm">搜索</button>
            <?php if ($search): ?>
            <a href="accounts.php" class="btn btn-sm" style="background:#f0f0f0;color:#333;">重置</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-success btn-sm" onclick="openModal('modalForm');resetForm();">+ 添加账号</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>SMTP服务器</th>
                <th>端口</th>
                <th>账号</th>
                <th>发件人名称</th>
                <th>发件地址</th>
                <th>今日发送/日限额</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $acc): ?>
            <?php $todaySent = isset($todayStats[$acc['id']]) ? $todayStats[$acc['id']] : 0; ?>
            <tr>
                <td><?php echo htmlspecialchars($acc['id']); ?></td>
                <td><?php echo htmlspecialchars($acc['host']); ?></td>
                <td><?php echo htmlspecialchars($acc['port']); ?></td>
                <td><?php echo htmlspecialchars($acc['user']); ?></td>
                <td><?php echo htmlspecialchars(!empty($acc['from_name']) ? $acc['from_name'] : '-'); ?></td>
                <td><?php echo htmlspecialchars(!empty($acc['from_addr']) ? $acc['from_addr'] : '-'); ?></td>
                <td><?php echo $todaySent; ?> / <?php echo htmlspecialchars($acc['daily_limit']); ?></td>
                <td>
                    <?php if ($acc['status'] == 1): ?>
                        <span class="badge badge-success">启用</span>
                    <?php else: ?>
                        <span class="badge badge-danger">禁用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick='editAccount(<?php echo json_encode($acc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>编辑</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除吗？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($acc['id']); ?>">
                        <button type="submit" class="btn btn-danger btn-sm">删除</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($acc['id']); ?>">
                        <button type="submit" class="btn btn-sm" style="background:#f0f0f0;color:#333;"><?php echo $acc['status'] == 1 ? '禁用' : '启用'; ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($accounts)): ?>
            <tr><td colspan="9" class="empty-state">暂无数据</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="modalForm">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">添加账号</span>
            <button class="modal-close" onclick="closeModal('modalForm')">&times;</button>
        </div>
        <form method="post" id="accountForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label>SMTP服务器</label>
                    <input type="text" name="host" id="host" required>
                </div>
                <div class="form-group">
                    <label>端口</label>
                    <input type="number" name="port" id="port" value="465" required>
                </div>
                <div class="form-group">
                    <label>账号</label>
                    <input type="text" name="user" id="user" required>
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="text" name="pwd" id="pwd" required>
                </div>
                <div class="form-group">
                    <label>发件人名称</label>
                    <input type="text" name="from_name" id="from_name">
                </div>
                <div class="form-group">
                    <label>发件地址</label>
                    <input type="email" name="from_addr" id="from_addr">
                </div>
                <div class="form-group">
                    <label>日限额</label>
                    <input type="number" name="daily_limit" id="daily_limit" value="100" required>
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="status">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#f0f0f0;color:#333;" onclick="closeModal('modalForm')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = '添加账号';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('host').value = '';
    document.getElementById('port').value = '465';
    document.getElementById('user').value = '';
    document.getElementById('pwd').value = '';
    document.getElementById('from_name').value = '';
    document.getElementById('from_addr').value = '';
    document.getElementById('daily_limit').value = '100';
    document.getElementById('status').value = '1';
}
function editAccount(data) {
    openModal('modalForm');
    document.getElementById('modalTitle').textContent = '编辑账号';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('host').value = data.host;
    document.getElementById('port').value = data.port;
    document.getElementById('user').value = data.user;
    document.getElementById('pwd').value = data.pwd;
    document.getElementById('from_name').value = data.from_name || '';
    document.getElementById('from_addr').value = data.from_addr || '';
    document.getElementById('daily_limit').value = data.daily_limit;
    document.getElementById('status').value = data.status;
}
</script>

<?php require __DIR__ . '/inc/foot.php'; ?>
