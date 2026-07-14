<?php
require_once 'config.php';

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$emails_raw = trim($_POST['emails'] ?? '');

// 校验基本输入
if(empty($title) || empty($content) || empty($emails_raw)){
    exit('标题、内容或收件人列表不能为空！<a href="index.html">返回</a>');
}

// 解析邮箱列表（按换行或逗号分割）
$email_list = preg_split('/[\s,]+/', $emails_raw, -1, PREG_SPLIT_NO_EMPTY);
// 去重、过滤空字符串
$email_list = array_unique(array_filter($email_list));
// 可选简单格式校验（只检查是否含 @）
$valid_emails = [];
foreach($email_list as $mail){
    if(strpos($mail, '@') !== false){
        $valid_emails[] = $mail;
    }
}
if(empty($valid_emails)){
    exit('未检测到有效邮箱地址，请检查格式！<a href="index.html">返回</a>');
}

// 如果设置了总数上限，则截取前 $max_total 个
if($max_total > 0 && count($valid_emails) > $max_total){
    $valid_emails = array_slice($valid_emails, 0, $max_total);
}

// 替换原来的硬编码列表为动态列表
$all_emails = $valid_emails;

// 以下为原始发送函数，未改动
function base64Encode($str){
    return base64_encode($str);
}

function sendMailBatch($smtp_host,$smtp_port,$user,$pwd,$fromName,$selfMail,$bccList,$subject,$body){
    $timeout = 15;
    $socket = fsockopen("ssl://".$smtp_host, $smtp_port, $errno, $errstr, $timeout);
    if(!$socket) return "连接SMTP失败：".$errstr;

    stream_set_blocking($socket, true);
    function getResp($sock){
        return fgets($sock, 512);
    }

    fputs($socket, "EHLO localhost\r\n"); getResp($socket);
    fputs($socket, "AUTH LOGIN\r\n"); getResp($socket);
    fputs($socket, base64Encode($user)."\r\n"); getResp($socket);
    fputs($socket, base64Encode($pwd)."\r\n"); getResp($socket);

    fputs($socket, "MAIL FROM:<".$user.">\r\n"); getResp($socket);
    fputs($socket, "RCPT TO:<".$selfMail.">\r\n"); getResp($socket);

    foreach($bccList as $mail){
        fputs($socket, "RCPT TO:<".$mail.">\r\n"); getResp($socket);
    }

    fputs($socket, "DATA\r\n"); getResp($socket);
    $header = "From: =?UTF-8?B?".base64Encode($fromName)."?= <".$user.">\r\n";
    $header .= "To: <".$selfMail.">\r\n";
    $header .= "Bcc: ".implode(',', $bccList)."\r\n";
    $header .= "Subject: =?UTF-8?B?".base64Encode($subject)."?=\r\n";
    $header .= "Content-Type: text/html; charset=utf-8\r\n";
    $header .= "\r\n";

    $msg = $header . $body . "\r\n.\r\n";
    fputs($socket, $msg);
    $res = getResp($socket);
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    if(strpos($res, "250") !== false){
        return true;
    }else{
        return "服务器返回：".$res;
    }
}

$batches = array_chunk($all_emails, $batch_num);
$ok = 0;
$fail = 0;
echo "<h3>发送日志</h3><hr>";

foreach($batches as $idx=>$batch){
    $ret = sendMailBatch(
        $smtp_host,
        $smtp_port,
        $smtp_user,
        $smtp_pwd,
        $from_name,
        $self_addr,
        $batch,
        $title,
        $content
    );
    $cnt = count($batch);
    if($ret === true){
        echo "<p style='color:green'>第".($idx+1)."批 共{$cnt}封 → 发送成功</p>";
        $ok += $cnt;
    }else{
        echo "<p style='color:red'>第".($idx+1)."批 失败：{$ret}</p>";
        $fail += $cnt;
    }
    sleep($sleep_time);
}

echo "<hr><h3>发送完成</h3>";
echo "<p>成功发送：{$ok} 封</p>";
echo "<p>发送失败：{$fail} 封</p>";
echo '<p><a href="index.html">返回重新发送</a>';
?>