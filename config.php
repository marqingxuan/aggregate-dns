<?php
// 网易163 SMTP配置
$smtp_host = 'smtp.163.com';
$smtp_port = 465;
$smtp_user = 'fdbsnian@163.com';
$smtp_pwd  = 'EYGCPASIBQGSLEHT';
$from_name = '知有';
$self_addr = 'fdbsnian@163.com';

// 每批最多发送数（自动分批）
$batch_num = 45;
// 批次间隔秒数
$sleep_time = 3;

// 可选的发送总数上限（0表示不限制）
$max_total = 0;   // 例如设为 500，则最多只发前500个邮箱
?>