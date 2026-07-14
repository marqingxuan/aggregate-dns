<?php
require __DIR__ . '/../data/db.php';
header('Content-Type: application/json; charset=utf-8');

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

switch ($type) {
    case 'templates':
        $stmt = $db->query("SELECT id, name, title FROM {$prefix}templates ORDER BY id DESC");
        jsonResponse(array('code' => 0, 'data' => $stmt->fetchAll()));
        break;

    case 'groups':
        $stmt = $db->query("SELECT id, name FROM {$prefix}groups ORDER BY id DESC");
        jsonResponse(array('code' => 0, 'data' => $stmt->fetchAll()));
        break;

    case 'recipients':
        $group_id = intval(isset($_GET['group_id']) ? $_GET['group_id'] : 0);
        if ($group_id > 0) {
            $stmt = $db->prepare("SELECT email, name FROM {$prefix}recipients WHERE group_id = ? AND status = 1");
            $stmt->execute(array($group_id));
        } else {
            $stmt = $db->query("SELECT email, name FROM {$prefix}recipients WHERE status = 1");
        }
        jsonResponse(array('code' => 0, 'data' => $stmt->fetchAll()));
        break;

    default:
        jsonResponse(array('code' => 1, 'msg' => '未知类型'));
}
