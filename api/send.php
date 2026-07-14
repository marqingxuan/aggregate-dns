<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/SMTP.php';
require __DIR__ . '/../vendor/PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(array('code' => 1, 'msg' => '非法请求'));
}

$recipients = isset($_POST['recipients']) ? $_POST['recipients'] : '';
$subject = isset($_POST['subject']) ? $_POST['subject'] : '';
$content = isset($_POST['content']) ? $_POST['content'] : '';
$template_id = intval(isset($_POST['template_id']) ? $_POST['template_id'] : 0);
$group_id = intval(isset($_POST['group_id']) ? $_POST['group_id'] : 0);

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

// 如果使用模板
if ($template_id > 0) {
    $stmt = $db->prepare("SELECT * FROM {$prefix}templates WHERE id = ?");
    $stmt->execute(array($template_id));
    $template = $stmt->fetch();
    if ($template) {
        $subject = $template['title'];
        $content = $template['content'];
    }
}

// 获取收件人列表
$emails = array();
if (!empty($recipients)) {
    $lines = explode("\n", str_replace(array("\r\n", "\r"), "\n", $recipients));
    foreach ($lines as $line) {
        $email = trim($line);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }
} elseif ($group_id > 0) {
    $stmt = $db->prepare("SELECT email FROM {$prefix}recipients WHERE group_id = ? AND status = 1");
    $stmt->execute(array($group_id));
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $emails[] = $row['email'];
    }
}

$emails = array_unique($emails);
if (empty($emails)) {
    jsonResponse(array('code' => 1, 'msg' => '没有有效的收件人'));
}

// 黑名单过滤
$blacklist = array();
$stmt = $db->query("SELECT email FROM {$prefix}blacklist");
foreach ($stmt->fetchAll() as $row) {
    $blacklist[] = $row['email'];
}
$emails = array_diff($emails, $blacklist);
if (empty($emails)) {
    jsonResponse(array('code' => 1, 'msg' => '所有收件人均在黑名单中'));
}

// 获取可用账号（轮询）
$stmt = $db->query("SELECT * FROM {$prefix}accounts WHERE status = 1 ORDER BY send_count ASC");
$accounts = $stmt->fetchAll();
if (empty($accounts)) {
    jsonResponse(array('code' => 1, 'msg' => '没有可用的SMTP账号'));
}

// 系统设置
$send_interval = intval(getSetting($db, 'send_interval', '1'));
$batch_size = intval(getSetting($db, 'batch_size', '10'));
$retry_times = intval(getSetting($db, 'retry_times', '3'));

$batch_id = uniqid('batch_');
$total = count($emails);
$success = 0;
$fail = 0;
$results = array();

// 分批发送
$chunks = array_chunk($emails, $batch_size);
$account_idx = 0;

foreach ($chunks as $chunk) {
    $account = $accounts[$account_idx % count($accounts)];
    $account_idx++;

    // 检查账号今日限额
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}logs WHERE account_id = ? AND status = 'success' AND DATE(send_time) = " . sql_today());
    $stmt->execute(array($account['id']));
    $today_sent = $stmt->fetchColumn();
    if ($today_sent >= $account['daily_limit']) {
        continue; // 跳过已满账号
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $account['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $account['user'];
        $mail->Password = $account['pwd'];
        $mail->SMTPSecure = $account['port'] == 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $account['port'];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(!empty($account['from_addr']) ? $account['from_addr'] : $account['user'], !empty($account['from_name']) ? $account['from_name'] : 'Mailer');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $content;

        foreach ($chunk as $email) {
            $mail->clearAddresses();
            $mail->addAddress($email);

            $retry = 0;
            $sent = false;
            $error_msg = '';

            while ($retry < $retry_times && !$sent) {
                try {
                    $mail->send();
                    $sent = true;
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                    $retry++;
                    if ($retry < $retry_times) {
                        sleep(1);
                    }
                }
            }

            if ($sent) {
                $success++;
                $status = 'success';
                $error_msg = '';
            } else {
                $fail++;
                $status = 'fail';
            }

            // 记录日志
            $stmt = $db->prepare("INSERT INTO {$prefix}logs (template_id, account_id, recipient, status, error, batch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($template_id, $account['id'], $email, $status, $error_msg, $batch_id));

            $results[] = array('email' => $email, 'status' => $status, 'error' => $error_msg);
        }

        // 更新账号发送计数
        $stmt = $db->prepare("UPDATE {$prefix}accounts SET send_count = send_count + ? WHERE id = ?");
        $stmt->execute(array(count($chunk), $account['id']));

    } catch (Exception $e) {
        $fail += count($chunk);
        foreach ($chunk as $email) {
            $stmt = $db->prepare("INSERT INTO {$prefix}logs (template_id, account_id, recipient, status, error, batch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($template_id, $account['id'], $email, 'fail', $e->getMessage(), $batch_id));
            $results[] = array('email' => $email, 'status' => 'fail', 'error' => $e->getMessage());
        }
    }

    // 发送间隔
    if ($send_interval > 0) {
        sleep($send_interval);
    }
}

jsonResponse(array(
    'code' => 0,
    'msg' => "发送完成：成功 {$success} 条，失败 {$fail} 条",
    'batch_id' => $batch_id,
    'total' => $total,
    'success' => $success,
    'fail' => $fail,
    'results' => $results
));
