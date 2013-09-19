<?php
chdir('../');
require_once './system/common.inc.php';
echo "Start";
if($_GET['key'] != $_config['cronkey']) exit('ERROR CRON KEY');
$date = date('Ymd', TIMESTAMP+900);
$mdate = date('Y-m-d', TIMESTAMP+900);
$uid = 0;

while(true){
	$user = DB::fetch_first("SELECT uid, username, email FROM member WHERE uid>'{$uid}' ORDER BY uid LIMIT 0,1");
	$uid = $user['uid'];
	if(!$uid) break;
	echo "正在检查用户 {$user[username]}<br>";
	if(check_if_msg($user)){
		echo "正在发送邮件给 {$user[username]}...<br>";
		sendmsg($user);
	}
};
function check_if_msg($user){
	global $date, $uid;
	$setting = get_setting($user['uid']);
	if($setting['send_mail']) return true;
	if(!$setting['error_mail']) return false;
	$error_num = DB::result_first("SELECT COUNT(*) FROM sign_log WHERE status!='2' AND status!='-2' AND date='{$date}' AND uid='{$uid}'");
	if($error_num > 0) return true;
}
function sendmsg($user){
	global $date, $mdate, $uid;
	$stat = array();
	$stat['count']=0;
	$stat['undo']=0;
	$stat['failure']=0;
	$stat['not_max_exp']=0;
	$stat['unknow_exp']=0;
	$log = array();
	$query = DB::query("SELECT * FROM sign_log l LEFT JOIN my_tieba t ON t.tid=l.tid WHERE l.uid='{$uid}' AND l.date='{$date}' ORDER BY l.status DESC, l.tid ASC");
	$i = 1;

	$message='';
	while($result = DB::fetch($query)){
		$message .= '<tr><td>'.($i++)."</td><td><a href=\"http://tieba.baidu.com/f?kw={$result[unicode_name]}\" target=\"_blank\">{$result[name]}</a></td><td>"._status($result['status']).'</td><td>'._exp($result['exp']).'</td></tr>';
		$log[] = $result;
		$stat['count']++;
		if($result['status']==0){	
			$stat['undo']++;
		}
		elseif($result['status']<2){
			$stat['failure']++;
		}
		if($result['exp']==0){
			$stat['unknow_exp']++;
		}elseif ($result['exp']<8){
			$stat['not_max_exp']++;
		}
	}
	$summary = <<<EOF
<div style="text-align:center;font-size:16px">
{$stat['count']}贴吧中,{$stat['undo']}待签,{$stat['failure']}失败,{$stat['not_max_exp']}非最大经验			
</div>
EOF;
	$message .= '</tbody></table></div></body></html>';
	$pre_message = <<<EOF
<html>
<body>
<style type="text/css">
div.wrapper * { font: 12px "Microsoft YaHei", arial, helvetica, sans-serif; word-break: break-all; }
div.wrapper a { color: #15c; text-decoration: none; }
div.wrapper a:active { color: #d14836; }
div.wrapper a:hover { text-decoration: underline; }
div.wrapper p { line-height: 20px; margin: 0 0 .5em; text-align: center; }
div.wrapper .sign_title { font-size: 20px; line-height: 24px; }
div.wrapper .result_table { width: 85%; margin: 0 auto; border-spacing: 0; border-collapse: collapse; }
div.wrapper .result_table td { padding: 10px 5px; text-align: center; border: 1px solid #dedede; }
div.wrapper .result_table tr { background: #d5d5d5; }
div.wrapper .result_table tbody tr { background: #efefef; }
div.wrapper .result_table tbody tr:nth-child(odd) { background: #fafafa; }
</style>
<div class="wrapper">
<p class="sign_title">贴吧签到助手 - 签到报告</p>
{$summary}
<p>{$mdate}<br>若有大量贴吧签到失败，建议您重新设置 Cookie 相关信息</p>

<table class="result_table">
<thead><tr><td style="width: 40px">#</td><td>贴吧</td><td style="width: 75px">状态</td><td style="width: 75px">经验</td></tr></thead>
<tbody>
EOF;
//echo $pre_message,$message;
$annormal=$stat['failure']+$stat['unknow_exp']+$stat['not_max_exp'];
if($annormal>$stat['count']*30/100  && $annormal>0){$subject='[大量异常]';}
elseif ($annormal>$stat['count']/10 && $annormal>0){$subject='[少量异常]';}
elseif ($annormal>0){$subject='[个别异常]';}
else{
	$subject='[正常]';
}
$subject ="[{$mdate}] 贴吧签到助手报告- {$user[username]} - " .$subject;
	$res = send_mail($user['email'], $subject, $pre_message.$message);
	echo $res ? '邮件发送成功<br>' : '邮件发送失败<br>';
}
function _status($status){
	switch($status){
		case -2:	return '跳过签到';
		case -1:	return '无法签到';
		case 0:		return '待签到';
		case 1:		return '签到失败';
		case 2:		return '已签到';
	}
}
function _exp($exp){
	return $exp == 0 ? '-' : '+'.$exp;
}
