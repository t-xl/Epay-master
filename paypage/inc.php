<?php
include("../includes/common.php");

function showerror($msg){
	include ROOT.'paypage/error.php';
	exit;
}

function showerrorjson($msg){
	$result = ['code'=>-1, 'msg'=>$msg];
	exit(json_encode($result));
}

function check_paytype(){
	$type=null;
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger/')!==false){
		$type='wxpay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient/')!==false){
		$type='alipay';
	}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
		$type='qqpay';
	}
	return $type;
}

function alipayOpenId($channel){
	global $DB,$siteurl;
	$channel = \lib\Channel::get($channel);
	if(!$channel)showerror('支付通道不存在');
	define("PAY_ROOT", PLUGIN_ROOT.$channel['plugin'].'/');
	require_once(PAY_ROOT."inc/AlipayOauthService.php");
	$config['redirect_uri'] = $siteurl.'paypage/';
	$oauth = new AlipayOauthService($config);
	if(isset($_GET['auth_code'])){
		$result = $oauth->getToken($_GET['auth_code']);
		if($result['user_id']){
			return $result['user_id'];
		}else{
			showerror('支付宝快捷登录失败！['.$result['sub_code'].']'.$result['sub_msg']);
		}
	}else{
		$oauth->oauth();
	}
}

function weixinOpenId($channel){
	global $DB;
	$channel = \lib\Channel::get($channel);
	if(!$channel)showerror('支付通道不存在');
	
	$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
	if(!$wxinfo)showerror('支付通道绑定的微信公众号不存在');

	$tools = new \lib\wechat\JsApiPay($wxinfo['appid'], $wxinfo['appsecret']);
	$openId = $tools->GetOpenid();
	if(!$openId)showerror('OpenId获取失败('.$tools->data['errmsg'].')');
	return $openId;
}
