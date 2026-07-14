<?php
require __DIR__ . '/../data/db.php';
require __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/SMTP.php';
require __DIR__ . '/../vendor/PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['code' => 1, 'msg' => '非法请求']);
}

$recipients = $_POST['recipients'] ?? '';
$subject = $_POST['subject'] ?? '';
$content = $_POST['content'] ?? '';
$template_id = intval($_POST['template_id'] ?? 0);
$group_id = intval($_POST['group_id'] ?? 0);

// 如果使用模板
if ($template_id > 0) {
    $stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();
    if ($template) {
        $subject = $template['title'];
        $content = $template['content'];
    }
}

// 获取收件人列表
$emails = [];
if (!empty($recipients)) {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $recipients));
    foreach ($lines as $line) {
        $email = trim($line);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }
} elseif ($group_id > 0) {
    $stmt = $db->prepare("SELECT email FROM recipients WHERE group_id = ? AND status = 1");
    $stmt->execute([$group_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $emails[] = $row['email'];
    }
}

$emails = array_unique($emails);
if (empty($emails)) {
    jsonResponse(['code' => 1, 'msg' => '没有有效的收件人']);
}

// 黑名单过滤
$blacklist = [];
$stmt = $db->query("SELECT email FROM blacklist");
foreach ($stmt->fetchAll() as $row) {
    $blacklist[] = $row['email'];
}
$emails = array_diff($emails, $blacklist);
if (empty($emails)) {
    jsonResponse(['code' => 1, 'msg' => '所有收件人均在黑名单中']);
}

// 获取可用账号（轮询）
$stmt = $db->query("SELECT * FROM accounts WHERE status = 1 ORDER BY send_count ASC");
$accounts = $stmt->fetchAll();
if (empty($accounts)) {
    jsonResponse(['code' => 1, 'msg' => '没有可用的SMTP账号']);
}

// 系统设置
$send_interval = intval(getSetting($db, 'send_interval', '1'));
$batch_size = intval(getSetting($db, 'batch_size', '10'));
$retry_times = intval(getSetting($db, 'retry_times', '3'));

$batch_id = uniqid('batch_');
$total = count($emails);
$success = 0;
$fail = 0;
$results = [];

// 分批发送
$chunks = array_chunk($emails, $batch_size);
$account_idx = 0;

foreach ($chunks as $chunk) {
    $account = $accounts[$account_idx % count($accounts)];
    $account_idx++;

    // 检查账号今日限额
    $stmt = $db->prepare("SELECT COUNT(*) FROM logs WHERE account_id = ? AND status = 'success' AND date(send_time) = date('now')");
    $stmt->execute([$account['id']]);
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
        $mail->setFrom($account['from_addr'] ?: $account['user'], $account['from_name'] ?: 'Mailer');
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
            $stmt = $db->prepare("INSERT INTO logs (template_id, account_id, recipient, status, error, batch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$template_id, $account['id'], $email, $status, $error_msg, $batch_id]);

            $results[] = ['email' => $email, 'status' => $status, 'error' => $error_msg];
        }

        // 更新账号发送计数
        $stmt = $db->prepare("UPDATE accounts SET send_count = send_count + ? WHERE id = ?");
        $stmt->execute([count($chunk), $account['id']]);

    } catch (Exception $e) {
        $fail += count($chunk);
        foreach ($chunk as $email) {
            $stmt = $db->prepare("INSERT INTO logs (template_id, account_id, recipient, status, error, batch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$template_id, $account['id'], $email, 'fail', $e->getMessage(), $batch_id]);
            $results[] = ['email' => $email, 'status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    // 发送间隔
    if ($send_interval > 0) {
        sleep($send_interval);
    }
}

jsonResponse([
    'code' => 0,
    'msg' => "发送完成：成功 {$success} 条，失败 {$fail} 条",
    'batch_id' => $batch_id,
    'total' => $total,
    'success' => $success,
    'fail' => $fail,
    'results' => $results
]);
