<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '邮件模板管理';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$alert = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    try {
        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO {$prefix}templates (name, title, content) VALUES (?, ?, ?)");
            $stmt->execute(array(
                $_POST['name'],
                $_POST['title'],
                $_POST['content']
            ));
            $alert = '添加成功';
        } elseif ($action === 'edit') {
            $stmt = $db->prepare("UPDATE {$prefix}templates SET name=?, title=?, content=? WHERE id=?");
            $stmt->execute(array(
                $_POST['name'],
                $_POST['title'],
                $_POST['content'],
                $_POST['id']
            ));
            $alert = '修改成功';
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM {$prefix}templates WHERE id=?");
            $stmt->execute(array($_POST['id']));
            $alert = '删除成功';
        }
    } catch (Exception $e) {
        $alert = '操作失败: ' . $e->getMessage();
        $alertType = 'error';
    }
}

$stmt = $db->query("SELECT * FROM {$prefix}templates ORDER BY id DESC");
$templates = $stmt->fetchAll();
?>

<?php if ($alert): ?>
<div class="alert alert-<?php echo $alertType; ?>"><?php echo htmlspecialchars($alert); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">邮件模板列表</div>
    <div class="search-form" style="justify-content: space-between;">
        <div></div>
        <button class="btn btn-success btn-sm" onclick="openModal('modalForm');resetForm();">+ 添加模板</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>模板名称</th>
                <th>标题</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td><?php echo htmlspecialchars($t['id']); ?></td>
                <td><?php echo htmlspecialchars($t['name']); ?></td>
                <td><?php echo htmlspecialchars($t['title']); ?></td>
                <td><?php echo htmlspecialchars($t['created_at']); ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick='editTemplate(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>编辑</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除吗？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($t['id']); ?>">
                        <button type="submit" class="btn btn-danger btn-sm">删除</button>
                    </form>
                    <button class="btn btn-sm" style="background:#f0f0f0;color:#333;" onclick='previewTemplate(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>预览</button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($templates)): ?>
            <tr><td colspan="5" class="empty-state">暂无数据</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="modalForm">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <span id="modalTitle">添加模板</span>
            <button class="modal-close" onclick="closeModal('modalForm')">&times;</button>
        </div>
        <form method="post" id="templateForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label>模板名称</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label>邮件标题</label>
                    <input type="text" name="title" id="title" required>
                </div>
                <div class="form-group">
                    <label>邮件内容</label>
                    <textarea name="content" id="content" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background:#f0f0f0;color:#333;" onclick="closeModal('modalForm')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="previewModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <span id="previewTitle">预览模板</span>
            <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>邮件标题</label>
                <input type="text" id="previewSubject" readonly style="background:#fafafa;">
            </div>
            <div class="form-group">
                <label>邮件内容</label>
                <textarea id="previewContent" readonly style="background:#fafafa; min-height: 200px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" style="background:#f0f0f0;color:#333;" onclick="closeModal('previewModal')">关闭</button>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = '添加模板';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('title').value = '';
    document.getElementById('content').value = '';
}
function editTemplate(data) {
    openModal('modalForm');
    document.getElementById('modalTitle').textContent = '编辑模板';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('title').value = data.title;
    document.getElementById('content').value = data.content;
}
function previewTemplate(data) {
    openModal('previewModal');
    document.getElementById('previewTitle').textContent = '预览: ' + data.name;
    document.getElementById('previewSubject').value = data.title;
    document.getElementById('previewContent').value = data.content;
}
</script>

<?php require __DIR__ . '/inc/foot.php'; ?>
