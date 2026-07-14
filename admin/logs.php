<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/inc/head.php';
requireLogin();
$pageTitle = '发送日志';

$alert = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM logs WHERE id=?");
            $stmt->execute([$_POST['id']]);
            $alert = '删除成功';
        } elseif ($action === 'clear') {
            $db->exec("DELETE FROM logs");
            $alert = '日志已清空';
        }
    } catch (Exception $e) {
        $alert = '操作失败: ' . $e->getMessage();
        $alertType = 'error';
    }
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$searchRecipient = $_GET['recipient'] ?? '';
$searchStatus = $_GET['status'] ?? '';
$searchDateFrom = $_GET['date_from'] ?? '';
$searchDateTo = $_GET['date_to'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($searchRecipient !== '') {
    $where .= " AND l.recipient LIKE ?";
    $params[] = "%$searchRecipient%";
}
if ($searchStatus !== '') {
    $where .= " AND l.status = ?";
    $params[] = $searchStatus;
}
if ($searchDateFrom !== '') {
    $where .= " AND date(l.send_time) >= ?";
    $params[] = $searchDateFrom;
}
if ($searchDateTo !== '') {
    $where .= " AND date(l.send_time) <= ?";
    $params[] = $searchDateTo;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM logs l $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "SELECT l.*, t.name as template_name, a.user as account_user 
        FROM logs l 
        LEFT JOIN templates t ON l.template_id = t.id 
        LEFT JOIN accounts a ON l.account_id = a.id 
        $where 
        ORDER BY l.id DESC 
        LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

function buildUrl($pageNum, $params) {
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

$queryParams = [];
if ($searchRecipient !== '') $queryParams['recipient'] = $searchRecipient;
if ($searchStatus !== '') $queryParams['status'] = $searchStatus;
if ($searchDateFrom !== '') $queryParams['date_from'] = $searchDateFrom;
if ($searchDateTo !== '') $queryParams['date_to'] = $searchDateTo;
?>

<?php if ($alert): ?>
<div class="alert alert-<?php echo $alertType; ?>"><?php echo htmlspecialchars($alert); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-title">发送日志</div>
    <div class="search-form" style="justify-content: space-between; align-items: flex-start;">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;">
            <input type="text" name="recipient" placeholder="收件人" value="<?php echo htmlspecialchars($searchRecipient); ?>" style="min-width:160px;">
            <select name="status" style="min-width:100px;">
                <option value="">全部状态</option>
                <option value="success" <?php echo $searchStatus === 'success' ? 'selected' : ''; ?>>成功</option>
                <option value="fail" <?php echo $searchStatus === 'fail' ? 'selected' : ''; ?>>失败</option>
            </select>
            <input type="date" name="date_from" placeholder="开始日期" value="<?php echo htmlspecialchars($searchDateFrom); ?>">
            <input type="date" name="date_to" placeholder="结束日期" value="<?php echo htmlspecialchars($searchDateTo); ?>">
            <button type="submit" class="btn btn-primary btn-sm">筛选</button>
            <a href="logs.php" class="btn btn-sm" style="background:#f0f0f0;color:#333;">重置</a>
        </form>
        <form method="post" style="display:inline;" onsubmit="return confirm('确定清空所有日志吗？')">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-danger btn-sm">清空日志</button>
        </form>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>收件人</th>
                <th>模板</th>
                <th>账号</th>
                <th>状态</th>
                <th>错误信息</th>
                <th>发送时间</th>
                <th>批次ID</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo htmlspecialchars($log['id']); ?></td>
                <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                <td><?php echo htmlspecialchars($log['template_name'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($log['account_user'] ?: '-'); ?></td>
                <td>
                    <?php if ($log['status'] === 'success'): ?>
                        <span class="badge badge-success">成功</span>
                    <?php else: ?>
                        <span class="badge badge-danger">失败</span>
                    <?php endif; ?>
                </td>
                <td style="color:#999;font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($log['error'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($log['send_time']); ?></td>
                <td><?php echo htmlspecialchars($log['batch_id'] ?: '-'); ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除吗？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($log['id']); ?>">
                        <button type="submit" class="btn btn-danger btn-sm">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="9" class="empty-state">暂无数据</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?php echo htmlspecialchars(buildUrl($page - 1, $queryParams)); ?>">上一页</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars(buildUrl($i, $queryParams)); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo htmlspecialchars(buildUrl($page + 1, $queryParams)); ?>">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/foot.php'; ?>
