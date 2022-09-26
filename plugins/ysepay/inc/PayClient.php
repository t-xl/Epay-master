<?php

require_once 'SignData.php';

class PayClient
{
    //商户ID
    public $partnerId;

    //私钥文件路径
    public $rsaPrivateKeyFilePath;

    //私钥值
    public $rsaPrivateKey;

    //网关
    public $gatewayUrl = "https://qrcode.ysepay.com/gateway.do";
    //返回数据格式
    public $format = "json";
    //api版本
    public $apiVersion = "3.0";

    // 表单提交字符集编码
    public $postCharset = "UTF-8";

    //使用文件读取文件格式，请只传递该值
    public $alipayPublicKey = null;

    //使用读取字符串格式，请只传递该值
    public $alipayrsaPublicKey;


    public $debugInfo = false;


    private $RESPONSE_SUFFIX = "_response";

    private $ERROR_RESPONSE = "error_response";

    private $SIGN_NODE_NAME = "sign";

    //签名类型
    public $signType = "RSA";

    //加密密钥和类型
    public $encryptKey;

    public $encryptType = "AES";

    //异步回调地址
    public $notifyUrl = "";

    //同步跳转地址
    public $returnUrl = "";

    public function generateSign($params, $signType = "RSA")
    {
        $params = array_filter($params);
        $params['sign_type'] = $signType;
        return $this->sign($this->getSignContent($params), $signType);
    }

    public function rsaSign($params, $signType = "RSA")
    {
        return $this->sign($this->getSignContent($params), $signType);
    }

    public function getSignContent($params)
    {
        ksort($params);
        unset($params['sign']);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if ("@" != substr($v, 0, 1)) {

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }


    protected function sign($data, $signType = "RSA")
    {
        if ($this->checkEmpty($this->rsaPrivateKeyFilePath)) {
            $priKey = $this->rsaPrivateKey;
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($priKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        } else {
            $priKey = file_get_contents($this->rsaPrivateKeyFilePath);
            $res = openssl_get_privatekey($priKey);
        }

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        if (!$this->checkEmpty($this->rsaPrivateKeyFilePath)) {
            openssl_free_key($res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * RSA单独签名方法，未做字符串处理,字符串处理见getSignContent()
     * @param $data 待签名字符串
     * @param $privatekey 商户私钥，根据keyfromfile来判断是读取字符串还是读取文件，false:填写私钥字符串去回车和空格 true:填写私钥文件路径
     * @param $signType 签名方式，RSA:SHA1     RSA2:SHA256
     * @param $keyfromfile 私钥获取方式，读取字符串还是读文件
     * @return string
     */
    public function alonersaSign($data, $privatekey, $signType = "RSA", $keyfromfile = false)
    {

        if (!$keyfromfile) {
            $priKey = $privatekey;
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($priKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        } else {
            $priKey = file_get_contents($privatekey);
            $res = openssl_get_privatekey($priKey);
        }

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        if ($keyfromfile) {
            openssl_free_key($res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }


    protected function curl($url, $postFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (is_array($postFields) && 0 < count($postFields)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        $reponse = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new Exception($reponse, $httpStatusCode);
            }
        }

        curl_close($ch);
        return $reponse;
    }

    protected function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }


    protected function logCommunicationError($apiName, $requestUrl, $errorCode, $responseTxt)
    {
        $logData = array(
            date("Y-m-d H:i:s"),
            $apiName,
            $this->partnerId,
            PHP_OS,
            $requestUrl,
            $errorCode,
            str_replace("\n", "", $responseTxt)
        );

        echo json_encode($logData);
    }

    /**
     * 页面提交执行方法
     * @param $request 跳转类接口的request
     * @param string $httpmethod 提交方式,两个值可选：post、get;
     * @param null $appAuthToken 三方应用授权token
     * @return 构建好的、签名后的最终跳转URL（GET）或String形式的form（POST）
     * @throws Exception
     */
    public function pageExecute($method, $bizContent, $httpmethod = "POST")
    {

        //组装系统参数
        $sysParams["method"] = $method;
        $sysParams["partner_id"] = $this->partnerId;
        $sysParams["charset"] = $this->postCharset;
        $sysParams["sign_type"] = $this->signType;
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["version"] = $this->apiVersion;
        if (!$this->checkEmpty($this->notifyUrl)) {
            $sysParams["notify_url"] = $this->notifyUrl;
        }
        if (!$this->checkEmpty($this->returnUrl)) {
            $sysParams["return_url"] = $this->returnUrl;
        }
                
        //获取业务参数
        $sysParams["biz_content"] = $bizContent;

        //签名
        $sysParams["sign"] = $this->generateSign($sysParams, $this->signType);

        if ("GET" == strtoupper($httpmethod)) {

            //value做urlencode
            $preString = http_build_query($sysParams);
            //拼接GET请求串
            $requestUrl = $this->gatewayUrl . "?" . $preString;

            return $requestUrl;
        } else {
            //拼接表单字符串
            return $this->buildRequestForm($sysParams);
        }
    }


    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @return 提交表单HTML文本
     */
    private function buildRequestForm($para_temp)
    {

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . $this->gatewayUrl . "' method='POST'>";
        while (list ($key, $val) = $this->fun_adm_each($para_temp)) {
            if (false === $this->checkEmpty($val)) {
                $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
            }
        }

        //submit按钮控件请不要含有name属性
        $sHtml .= "<input type='submit' value='ok' style='display:none;''></form>";

        $sHtml .= "<script>document.forms['alipaysubmit'].submit();</script>";

        return $sHtml;
    }

    private function fun_adm_each(&$array)
    {
        $res = array();
        $key = key($array);
        if ($key !== null) {
            next($array);
            $res[1] = $res['value'] = $array[$key];
            $res[0] = $res['key'] = $key;
        } else {
            $res = false;
        }
        return $res;
    }


    public function execute($method, $bizContent)
    {

        //组装系统参数
        $sysParams["method"] = $method;
        $sysParams["partner_id"] = $this->partnerId;
        $sysParams["charset"] = $this->postCharset;
        $sysParams["sign_type"] = $this->signType;
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["version"] = $this->apiVersion;
        $sysParams["format"] = $this->format;
        if (!$this->checkEmpty($this->notifyUrl)) {
            $sysParams["notify_url"] = $this->notifyUrl;
        }

        //获取业务参数
        $sysParams["biz_content"] = $bizContent;

        //签名
        $sysParams["sign"] = $this->generateSign($sysParams, $this->signType);

        //发起HTTP请求
        $requestUrl = $this->gatewayUrl;
        try {
            $resp = $this->curl($requestUrl, $sysParams);
        } catch (Exception $e) {

            $this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
            return false;
        }

        $signData = null;

        $respObject = json_decode($resp);
        if (null !== $respObject) {
            $signData = $this->parserJSONSignData($method, $resp, $respObject);
        }else{
            $this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_RESPONSE_NOT_WELL_FORMED", $resp);
            return false;
        }

        // 验签
        $this->checkResponseSign($method, $signData, $resp, $respObject);

        return $respObject;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    private function checkEmpty($value)
    {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    /** rsaCheckV1 & rsaCheckV2
     *  验证签名
     *  在使用本方法前，必须初始化AopClient且传入公钥参数。
     *  公钥是否是读取字符串还是读取文件，是根据初始化传入的值判断的。
     **/
    public function rsaCheckV1($params, $rsaPublicKeyFilePath, $signType = 'RSA')
    {
        $sign = $params['sign'];
        unset($params['sign']);
        unset($params['sign_type']);
        return $this->verify($this->getSignContent($params), $sign, $rsaPublicKeyFilePath, $signType);
    }

    public function rsaCheckV2($params, $rsaPublicKeyFilePath, $signType = 'RSA')
    {
        $sign = $params['sign'];
        unset($params['sign']);
        return $this->verify($this->getSignContent($params), $sign, $rsaPublicKeyFilePath, $signType);
    }

    private function verify($data, $sign, $rsaPublicKeyFilePath, $signType = 'RSA')
    {

        if ($this->checkEmpty($this->alipayPublicKey)) {

            $pubKey = $this->alipayrsaPublicKey;
            $res = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($pubKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        } else {
            //读取公钥文件
            $pubKey = file_get_contents($rsaPublicKeyFilePath);
            //转换为openssl格式密钥
            $res = openssl_get_publickey($pubKey);
        }

        ($res) or die('银盛RSA公钥错误。请检查公钥文件格式是否正确');

        //调用openssl内置方法验签，返回bool值

        $result = FALSE;
        if ("RSA2" == $signType) {
            $result = (openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1);
        } else {
            $result = (openssl_verify($data, base64_decode($sign), $res) === 1);
        }

        if (!$this->checkEmpty($this->alipayPublicKey)) {
            //释放资源
            openssl_free_key($res);
        }

        return $result;
    }


    private function parserResponseSubCode($method, $responseContent, $respObject, $format)
    {

        if ("json" == $format) {

            $rootNodeName = str_replace(".", "_", $method) . $this->RESPONSE_SUFFIX;
            $errorNodeName = $this->ERROR_RESPONSE;

            $rootIndex = strpos($responseContent, $rootNodeName);
            $errorIndex = strpos($responseContent, $errorNodeName);

            if ($rootIndex > 0) {
                // 内部节点对象
                $rInnerObject = $respObject->$rootNodeName;
            } elseif ($errorIndex > 0) {

                $rInnerObject = $respObject->$errorNodeName;
            } else {
                return null;
            }

            // 存在属性则返回对应值
            if (isset($rInnerObject->sub_code)) {

                return $rInnerObject->sub_code;
            } else {

                return null;
            }


        } elseif ("xml" == $format) {

            // xml格式sub_code在同一层级
            return $respObject->sub_code;

        }


    }

    private function parserJSONSignData($method, $responseContent, $responseJSON)
    {

        $signData = new SignData();

        $signData->sign = $this->parserJSONSign($responseJSON);
        $signData->signSourceData = $this->parserJSONSignSource($method, $responseContent);


        return $signData;

    }

    private function parserJSONSignSource($method, $responseContent)
    {

        $rootNodeName = str_replace(".", "_", $method) . $this->RESPONSE_SUFFIX;

        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, $this->ERROR_RESPONSE);


        if ($rootIndex > 0) {

            return $this->parserJSONSource($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return $this->parserJSONSource($responseContent, $this->ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }

    private function parserJSONSource($responseContent, $nodeName, $nodeIndex)
    {
        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 2;
        $signIndex = strrpos($responseContent, "\"" . $this->SIGN_NODE_NAME . "\"");
        // 签名前-逗号
        $signDataEndIndex = $signIndex - 1;
        $indexLen = $signDataEndIndex - $signDataStartIndex;
        if ($indexLen < 0) {

            return null;
        }

        return substr($responseContent, $signDataStartIndex, $indexLen);

    }

    private function parserJSONSign($responseJSon)
    {

        return $responseJSon->sign;
    }

    /**
     * 验签
     * @param $request
     * @param $signData
     * @param $resp
     * @param $respObject
     * @throws Exception
     */
    public function checkResponseSign($method, $signData, $resp, $respObject)
    {

        if (!$this->checkEmpty($this->alipayPublicKey) || !$this->checkEmpty($this->alipayrsaPublicKey)) {


            if ($signData == null || $this->checkEmpty($signData->sign) || $this->checkEmpty($signData->signSourceData)) {

                throw new Exception(" check sign Fail! The reason : signData is Empty");
            }


            // 获取结果sub_code
            $responseSubCode = $this->parserResponseSubCode($method, $resp, $respObject, $this->format);


            if (!$this->checkEmpty($responseSubCode) || ($this->checkEmpty($responseSubCode) && !$this->checkEmpty($signData->sign))) {

                $checkResult = $this->verify($signData->signSourceData, $signData->sign, $this->alipayPublicKey, $this->signType);


                if (!$checkResult) {

                    if (strpos($signData->signSourceData, "\\/") > 0) {

                        $signData->signSourceData = str_replace("\\/", "/", $signData->signSourceData);

                        $checkResult = $this->verify($signData->signSourceData, $signData->sign, $this->alipayPublicKey, $this->signType);

                        if (!$checkResult) {
                            throw new Exception("check sign Fail! [sign=" . $signData->sign . ", signSourceData=" . $signData->signSourceData . "]");
                        }

                    } else {

                        throw new Exception("check sign Fail! [sign=" . $signData->sign . ", signSourceData=" . $signData->signSourceData . "]");
                    }

                }
            }


        }
    }

}
