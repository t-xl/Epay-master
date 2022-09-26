<?php

class ysepay_plugin
{
	static public $info = [
		'name'        => 'ysepay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '银盛支付', //支付插件显示名称
		'author'      => '银盛支付', //支付插件作者
		'link'        => 'https://www.ysepay.com/', //支付插件作者链接
		'types'       => ['alipay','qqpay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '银盛公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/?sitename='.$sitename];
		}elseif($order['typename']=='wxpay'){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()==true && ($channel['appwxa']>0||$channel['appwxmp']>0)){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/?sitename='.$sitename];
			}
		}elseif($order['typename']=='qqpay'){
			return ['type'=>'jump','url'=>'/pay/qqpay/'.TRADE_NO.'/?sitename='.$sitename];
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/?sitename='.$sitename];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && ($channel['appwxa']>0||$channel['appwxmp']>0)){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='qqpay'){
			return self::qqpay();
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//扫码支付
	static private function qrcode($bank_type){
		global $siteurl, $channel, $order, $ordername, $conf;

		require(PAY_ROOT."inc/config.php");
		require(PAY_ROOT."inc/PayClient.php");

		$method = 'ysepay.online.qrcodepay';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'currency' => 'CNY',
			'seller_id' => $config['partner_id'],
			'seller_name' => '',
			'timeout_express' => '7d',
			'business_code' => '',
			'bank_type' => $bank_type,
		];

		$client = new PayClient ();
		$client->partnerId = $config['partner_id'];
		$client->rsaPrivateKey = $config['merchant_private_key'];
		$client->alipayrsaPublicKey = $config['ysepay_public_key'];
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';

		$bizContent = json_encode($params);
		$response = $client->execute($method, $bizContent);

		$responseNode = str_replace(".", "_", $method) . '_response';
		$result = json_decode(json_encode($response->$responseNode), true);

        if (isset($result['code']) && $result['code'] == 10000) {
			return $result['qr_code_url'];
        } elseif(isset($result['sub_code'])) {
			throw new Exception('['.$result['sub_code'].']'.$result['sub_msg']);
		}else{
			throw new Exception('系统异常，状态未知！');
		}
	}

	//公众号小程序支付
	static private function weixin($appid, $openid, $isminipg = '2'){
		global $siteurl, $channel, $order, $ordername, $conf;

		require(PAY_ROOT."inc/config.php");
		require(PAY_ROOT."inc/PayClient.php");

		$method = 'ysepay.online.weixin.pay';
		$params = [
			'out_trade_no' => TRADE_NO,
			'shopdate' => date("Ymd"),
			'subject' => $ordername,
			'total_amount' => $order['realmoney'],
			'currency' => 'CNY',
			'seller_id' => $config['partner_id'],
			'seller_name' => '',
			'timeout_express' => '7d',
			'business_code' => '',
			'appid' => $appid,
			'sub_openid' => $openid,
			'is_minipg' => $isminipg,
		];

		$client = new PayClient ();
		$client->partnerId = $config['partner_id'];
		$client->rsaPrivateKey = $config['merchant_private_key'];
		$client->alipayrsaPublicKey = $config['ysepay_public_key'];
		$client->notifyUrl = $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/';

		$bizContent = json_encode($params);
		$response = $client->execute($method, $bizContent);

		$responseNode = str_replace(".", "_", $method) . '_response';
		$result = json_decode(json_encode($response->$responseNode), true);

        if (isset($result['code']) && $result['code'] == 10000) {
			return $result['jsapi_pay_info'];
        } elseif(isset($result['sub_code'])) {
			throw new Exception('['.$result['sub_code'].']'.$result['sub_msg']);
		}else{
			throw new Exception('系统异常，状态未知！');
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$code_url = self::qrcode('1903000');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$code_url = self::qrcode('1902000');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//QQ扫码支付
	static public function qqpay(){
		try{
			$code_url = self::qrcode('1904000');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'QQ钱包支付下单失败！'.$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/')!==false){
			return ['type'=>'jump','url'=>$code_url];
		} elseif(checkmobile() && !isset($_GET['qrcode'])){
			return ['type'=>'qrcode','page'=>'qqpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'qqpay_qrcode','url'=>$code_url];
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$code_url = self::qrcode('9001002');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
		$tools = new \lib\wechat\JsApiPay($wxinfo['appid'], $wxinfo['appsecret']);
		$openId = $tools->GetOpenid();
		if(!$openId)return ['type'=>'error','msg'=>'OpenId获取失败('.$tools->data['errmsg'].')'];
		$blocks = checkBlockUser($openId, TRADE_NO);
		if($blocks) return $blocks;

		//②、统一下单
		try{
			$jsApiParameters = self::weixin($wxinfo['appid'], $openId, '2');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		
		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$jsApiParameters, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');
		
		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		$tools = new \lib\wechat\MiniAppPay($wxinfo['appid'], $wxinfo['appsecret']);
		$openId = $tools->GetOpenid($code);
		if(!$openId)exit('{"code":-1,"msg":"OpenId获取失败('.$tools->data['errmsg'].')"}');
		$blocks = checkBlockUser($openId, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		//②、统一下单
		try{
			$jsApiParameters = self::weixin($wxinfo['appid'], $openId, '1');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($jsApiParameters, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if($channel['appwxa']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$access_token = wx_get_access_token($wxinfo['appid'], $wxinfo['appsecret']);
				if($access_token){
					$jump_url = $siteurl.'pay/wxminipay/'.TRADE_NO.'/';
					$path = 'pages/pay/pay';
					$query = 'money='.$order['realmoney'].'&url='.$jump_url;
					$code_url = wxa_generate_scheme($access_token, $path, $query);
				}
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/config.php");
		require(PAY_ROOT."inc/PayClient.php");

		//计算得出通知验证结果
		$client = new PayClient();
		$client->partnerId = $config['partner_id'];
		$client->rsaPrivateKey = $config['merchant_private_key'];
		$client->alipayrsaPublicKey = $config['ysepay_public_key'];
		$verify_result = $client->rsaCheckV2($_POST, $client->alipayrsaPublicKey);

		if($verify_result) {//验证成功
			//商户订单号
			$out_trade_no = daddslashes($_POST['out_trade_no']);

			//支付宝交易号
			$trade_no = daddslashes($_POST['trade_no']);

			//买家支付宝
			$buyer_id = daddslashes($_POST['buyer_user_id']);

			//交易金额
			$total_amount = $_POST['total_amount'];

			if($_POST['trade_status'] == 'TRADE_FINISHED') {
				//退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
			}
			else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
				if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
					processNotify($order, $trade_no, $buyer_id);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//同步回调
	static public function return(){
		global $channel, $order;

		require(PAY_ROOT."inc/config.php");
		require(PAY_ROOT."inc/PayClient.php");

		//计算得出通知验证结果
		$client = new PayClient();
		$client->partnerId = $config['partner_id'];
		$client->rsaPrivateKey = $config['merchant_private_key'];
		$client->alipayrsaPublicKey = $config['ysepay_public_key'];
		$verify_result = $client->rsaCheckV2($_GET, $client->alipayrsaPublicKey);

		if($verify_result) {//验证成功
			//商户订单号
			$out_trade_no = daddslashes($_GET['out_trade_no']);

			//支付宝交易号
			$trade_no = daddslashes($_GET['trade_no']);

			//交易金额
			$total_amount = $_GET['total_amount'];

			if($out_trade_no == TRADE_NO && round($total_amount,2)==round($order['realmoney'],2)){
				processReturn($order, $trade_no);
			}else{
				return ['type'=>'error','msg'=>'订单信息校验失败'];
			}
		}
		else {
			//验证失败
			return ['type'=>'error','msg'=>'返回验证失败'];
		}
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require(PAY_ROOT."inc/config.php");
		require(PAY_ROOT."inc/PayClient.php");

		$method = 'ysepay.online.trade.refund';
		$params = [
			'out_trade_no' => $order['trade_no'],
			'shopdate' => date("Ymd"),
			'trade_no' => $order['api_trade_no'],
			'refund_amount' => $order['refundmoney'],
			'refund_reason' => '申请退款',
			'out_request_no' => $order['trade_no'],
		];

		$client = new PayClient ();
		$client->gatewayUrl = 'https://openapi.ysepay.com/gateway.do';
		$client->partnerId = $config['partner_id'];
		$client->rsaPrivateKey = $config['merchant_private_key'];
		$client->alipayrsaPublicKey = $config['ysepay_public_key'];

		$bizContent = json_encode($params);
		$response = $client->execute($method, $bizContent);

		$responseNode = str_replace(".", "_", $method) . '_response';
		$result = json_decode(json_encode($response->$responseNode), true);

        if (isset($result['code']) && $result['code'] == 10000) {
			return ['code'=>0, 'trade_no'=>$result['trade_no'], 'refund_fee'=>$result['refund_amount']];
        } elseif(isset($result['sub_code'])) {
			return ['code'=>-1, 'msg'=>'['.$result['sub_code'].']'.$result['sub_msg']];
		}else{
			return ['code'=>-1, 'msg'=>'未知错误'];
		}
	}
}