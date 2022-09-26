<?php

class wxpayn_plugin
{
	static public $info = [
		'name'        => 'wxpayn', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '微信官方支付V3', //支付插件显示名称
		'author'      => '微信', //支付插件作者
		'link'        => 'https://pay.weixin.qq.com/', //支付插件作者链接
		'types'       => ['wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '公众号或小程序APPID',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户API证书序列号',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '商户APIv3密钥',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => [ //选择已开启的支付方式
			'1' => '扫码支付',
			'2' => '公众号支付',
			'3' => 'H5支付',
			'4' => '小程序支付',
		],
		'note' => '请将商户API私钥“apiclient_key.pem”放到 /plugins/wxpayn/cert/ 文件夹内（或 /plugins/wxpayn/cert/商户号/ 文件夹内）。<br/>上方APPID填写公众号或小程序的皆可，需要在微信服务商后台关联对应的公众号或小程序才能使用。无认证的公众号或小程序无法发起支付！', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $submit2, $conf;

		$urlpre = '/';
        if (!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'], $_SERVER['HTTP_HOST'])) {
			$urlpre = $conf['localurl_wxpay'];
        }
		
		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			if(in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/jspay/'.TRADE_NO.'/?d=1'];
			}elseif(in_array('4',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/wap/'.TRADE_NO.'/'];
			}else{
				if(!$submit2){
					return ['type'=>'jump','url'=>'/pay/submit/'.TRADE_NO.'/'];
				}
				return ['type'=>'page','page'=>'wxopen'];
			}
		}elseif(checkmobile()==true){
			if(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/h5/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype']) || in_array('4',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/wap/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/?sitename='.$sitename];
			}
		}else{
			return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/?sitename='.$sitename];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		$urlpre = $siteurl;
		if (!empty($conf['localurl_wxpay']) && !strpos($conf['localurl_wxpay'], $_SERVER['HTTP_HOST'])) {
			$urlpre = $conf['localurl_wxpay'];
		}

		if($mdevice=='wechat'){
			if(in_array('2',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/jspay/'.TRADE_NO.'/?d=1'];
			}elseif(in_array('4',$channel['apptype'])){
				return self::wap();
			}else{
				return ['type'=>'jump','url'=>$siteurl.'pay/submit/'.TRADE_NO.'/'];
			}
		}elseif($device=='mobile'){
			if(in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$urlpre.'pay/h5/'.TRADE_NO.'/'];
			}elseif(in_array('2',$channel['apptype']) || in_array('4',$channel['apptype'])){
				return self::wap();
			}else{
				return self::qrcode();
			}
		}else{
			return self::qrcode();
		}
	}

	//扫码支付
	static public function qrcode(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		if(in_array('1',$channel['apptype'])){
		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval($order['realmoney']*100),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];

		try{
			$client = new WxPayApi($pay_config);
			$result = $client->nativePay($param);
			$code_url = $result['code_url'];
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}

		}elseif(in_array('2',$channel['apptype'])){
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
		}elseif(in_array('4',$channel['apptype'])){
			$code_url = $siteurl.'pay/wap/'.TRADE_NO.'/';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}
		return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
	}

	//JS支付
	static public function jspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
		$channel['appid'] = $wxinfo['appid'];
		
		session_start();
		if($_SESSION[TRADE_NO.'_wxpay']){
			$jsApiParameters=$_SESSION[TRADE_NO.'_wxpay'];
		}else{
		
		//①、获取用户openid
		$tools = new \lib\wechat\JsApiPay($wxinfo['appid'], $wxinfo['appsecret']);
		$openId = $tools->GetOpenid();
		if(!$openId)return ['type'=>'error','msg'=>'OpenId获取失败('.$tools->data['errmsg'].')'];
		$blocks = checkBlockUser($openId, TRADE_NO);
		if($blocks) return $blocks;

		//②、统一下单
		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval($order['realmoney']*100),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openId
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];

		try{
			$client = new WxPayApi($pay_config);
			$result = $client->jsapiPay($param);
			$prepay_id = $result['prepay_id'];
			$jsApiParameters = $client->getJsApiParameters($prepay_id);
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}

		$_SESSION[TRADE_NO.'_wxpay'] = $jsApiParameters;
		}
		
		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$jsApiParameters, 'redirect_url'=>$redirect_url]];
	}

	//聚合收款码接口
	static public function jsapi($type,$money,$name,$openid){
		global $siteurl, $channel, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) throw new Exception('支付通道绑定的微信公众号不存在');
		$channel['appid'] = $wxinfo['appid'];

		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		$param = [
			'description' => $name,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval($money*100),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openid
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];

		try{
			$client = new WxPayApi($pay_config);
			$result = $client->jsapiPay($param);
			$prepay_id = $result['prepay_id'];
			$jsApiParameters = $client->getJsApiParameters($prepay_id);
			return $jsApiParameters;
		} catch (Exception $e) {
			throw new Exception('微信支付下单失败！'.$e->getMessage());
		}
	}

	//手机支付
	static public function wap(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		
		if(in_array('4',$channel['apptype']) && !isset($_GET['qrcode'])){
			try{
				$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
				if(!$wxinfo)return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
				$access_token = wx_get_access_token($wxinfo['appid'], $wxinfo['appsecret']);
				if($access_token){
					$jump_url = $siteurl.'pay/mini/'.TRADE_NO.'/';
					$path = 'pages/pay/pay';
					$query = 'money='.$order['realmoney'].'&url='.$jump_url;
					$code_url = wxa_generate_scheme($access_token, $path, $query);
				}
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			$code_url = $siteurl.'pay/jspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}
	}

	//H5支付
	static public function h5(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval($order['realmoney']*100),
				'currency' => 'CNY'
			],
			'scene_info' => [
				'payer_client_ip' => $clientip,
				'h5_info' => [
					'type' => 'Wap',
					'app_name' => $conf['sitename'],
					'app_url' => $siteurl,
				],
			]
		];

		try{
			$client = new WxPayApi($pay_config);
			$result = $client->h5Pay($param);
			$redirect_url=$siteurl.'pay/return/'.TRADE_NO.'/';
			$url=$result['h5_url'].'&redirect_url='.urlencode($redirect_url);
			return ['type'=>'jump','url'=>$url];
		} catch (Exception $e) {
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$e->getMessage()];
		}
	}

	//小程序支付
	static public function mini(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		$channel['appid'] = $wxinfo['appid'];

		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		$tools = new \lib\wechat\MiniAppPay($wxinfo['appid'], $wxinfo['appsecret']);
		$openId = $tools->GetOpenid($code);
		if(!$openId)exit('{"code":-1,"msg":"OpenId获取失败('.$tools->data['errmsg'].')"}');
		$blocks = checkBlockUser($openId, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		//②、统一下单
		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		$param = [
			'description' => $ordername,
			'out_trade_no' => TRADE_NO,
			'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'amount' => [
				'total' => intval($order['realmoney']*100),
				'currency' => 'CNY'
			],
			'payer' => [
				'openid' => $openId
			],
			'scene_info' => [
				'payer_client_ip' => $clientip
			]
		];

		try{
			$client = new WxPayApi($pay_config);
			$result = $client->jsapiPay($param);
			$prepay_id = $result['prepay_id'];
			$jsApiParameters = $client->getJsApiParameters($prepay_id, true);
			exit(json_encode(['code'=>0, 'data'=>$jsApiParameters]));
		} catch (Exception $e) {
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败！'.$e->getMessage()]));
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		try{
			$client = new WxPayApi($pay_config);
			$data = $client->notify();
		} catch (Exception $e) {
			header("HTTP/1.1 499 Error");
			exit(json_encode(['code'=>'FAIL', 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE));
		}

		if ($data['trade_state'] == 'SUCCESS') {
			if($data['out_trade_no'] == TRADE_NO){
				processNotify($order, $data['transaction_id'], $data['payer']['openid']);
			}
		}
		exit(json_encode(['code'=>'SUCCESS', 'message'=>'']));
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$pay_config = require(PAY_ROOT.'inc/WxPayConfig.php');
		require(PAY_ROOT.'inc/WxPayApi.class.php');

		$param = [
			'transaction_id' => $order['api_trade_no'],
			'out_refund_no' => $order['trade_no'],
			'amount' => [
				'refund' => intval($order['refundmoney']*100),
				'total' => intval($order['realmoney']*100),
				'currency' => 'CNY'
			]
		];

		try{
			$client = new WxPayApi($pay_config);
			$result = $client->refund($param);
			$result = ['code'=>0, 'trade_no'=>$result['out_trade_no'], 'refund_fee'=>$result['amount']['refund']];
		} catch (Exception $e) {
			$result = ['code'=>-1, 'msg'=>$e->getMessage()];
		}
		return $result;
	}
}