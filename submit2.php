<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';
$submit2=true;

@header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>正在为您跳转到支付页面，请稍候...</title>
	<style type="text/css">
body{margin:0;padding:0}
#waiting{position:absolute;left:50%;top:50%;height:35px;margin:-35px 0 0 -160px;padding:20px;font:bold 16px/30px "宋体",Arial;background:#f9fafc url(/assets/img/loading.gif) no-repeat 20px 20px;text-indent:40px;border:1px solid #c5d0dc}
	</style>
</head>
<body>
<p id="waiting">正在为您跳转到支付页面，请稍候...</p>
<?php
$typeid=intval($_GET['typeid']);
$trade_no=daddslashes($_GET['trade_no']);
$order=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' LIMIT 1");
if(!$order)sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if($order['status']>0){
	sysmsg('该订单('.$order['out_trade_no'].')已完成支付，请勿重复发起支付');
}

$userrow = $DB->getRow("SELECT `gid`,`money`,`mode`,`channelinfo`,`ordername` FROM `pre_user` WHERE `uid`='{$order['uid']}' LIMIT 1");

// 获取订单支付方式ID、支付插件、支付通道、支付费率
$submitData = \lib\Channel::submit2($typeid, $userrow['gid'], $order['money']);

if(!$submitData){
	sysmsg('<center>当前支付方式无法使用</center>', '跳转提示');
}

if($submitData['mode']==1 && ($order['tid']==2 || $order['tid']==4)){ //商户直清模式充值余额与购买用户组
	$userrow = $DB->getRow("SELECT `gid`,`money`,`mode`,`channelinfo` FROM `pre_user` WHERE `uid`='{$conf['reg_pay_uid']}' LIMIT 1");
	if($order['tid']==2) $rate = $submitData['rate'];
	$submitData = \lib\Channel::submit2($typeid, $userrow['gid'], $order['money']);
	if(!$submitData){
		sysmsg('<center>当前支付方式无法使用</center>', '跳转提示');
	}
	if($order['tid']==2) $submitData['rate'] = $rate;
	$submitData['mode'] = 0;
}

if($userrow['mode']==1 && $order['tid']!=4 || $order['tid']==2){ //订单加费模式（排除购买用户组）或余额充值
	$realmoney = round($order['money']*(100+100-$submitData['rate'])/100,2);
	$getmoney = $order['money'];
}else{
	$realmoney = $order['money'];
	$getmoney = round($order['money']*$submitData['rate']/100,2);
}

// 判断通道单笔支付限额
if(!empty($submitData['paymin']) && $submitData['paymin']>0 && $order['money']<$submitData['paymin']){
	sysmsg('<center>当前支付方式单笔最小限额为'.$submitData['paymin'].'元，请选择其他支付方式！</center>', '跳转提示');
}
if(!empty($submitData['paymax']) && $submitData['paymax']>0 && $order['money']>$submitData['paymax']){
	sysmsg('<center>当前支付方式单笔最大限额为'.$submitData['paymax'].'元，请选择其他支付方式！</center>', '跳转提示');
}
// 商户直清模式判断商户余额
if($submitData['mode']==1 && $realmoney-$getmoney>$userrow['money']){
	sysmsg('当前商户余额不足，无法完成支付，请商户登录用户中心充值余额');
}

// 随机增减金额
if($conf['pay_payaddstart']!=0&&$conf['pay_payaddmin']!=0&&$conf['pay_payaddmax']!=0&&$realmoney>=$conf['pay_payaddstart'])$realmoney = $realmoney + randomFloat(round($conf['pay_payaddmin'],2),round($conf['pay_payaddmax'],2));

$DB->exec("UPDATE pre_order SET type='{$submitData['typeid']}',channel='{$submitData['channel']}',realmoney='$realmoney',getmoney='$getmoney' WHERE trade_no='$trade_no'");


$order['realmoney'] = $realmoney;
$order['type'] = $submitData['typeid'];
$order['channel'] = $submitData['channel'];
$order['typename'] = $submitData['typename'];

try{
	$result = \lib\Plugin::loadForSubmit($submitData['plugin'], $trade_no);
	\lib\Payment::echoDefault($result);
}catch(Exception $e){
	sysmsg($e->getMessage());
}
?>
</body>
</html>