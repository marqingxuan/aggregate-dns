<?php
require __DIR__ . '/../data/db.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'templates':
        $stmt = $db->query("SELECT id, name, title FROM templates ORDER BY id DESC");
        jsonResponse(['code' => 0, 'data' => $stmt->fetchAll()]);
        break;

    case 'groups':
        $stmt = $db->query("SELECT id, name FROM groups ORDER BY id DESC");
        jsonResponse(['code' => 0, 'data' => $stmt->fetchAll()]);
        break;

    case 'recipients':
        $group_id = intval($_GET['group_id'] ?? 0);
        if ($group_id > 0) {
            $stmt = $db->prepare("SELECT email, name FROM recipients WHERE group_id = ? AND status = 1");
            $stmt->execute([$group_id]);
        } else {
            $stmt = $db->query("SELECT email, name FROM recipients WHERE status = 1");
        }
        jsonResponse(['code' => 0, 'data' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['code' => 1, 'msg' => '未知类型']);
}
