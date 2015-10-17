<?php
/**
 * ECSHOP 微信支付插件
 * ============================================================================
 * * 版权所有 2015-2016 蜂蚁网络科技 版权所有
 */

if (!defined('IN_ECTOUCH')) {
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'lang/' .$GLOBALS['_CFG']['lang']. '/payment/wxpay.php';

if (file_exists($payment_lang))
{
    global $_LANG;

    include_once($payment_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'wxpay_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'gy.wang';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '0.1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'wxpay_mchid',         'type' => 'text',   'value' => ''),               //微信商户ID
    	array('name' => 'wxpay_key',           'type' => 'text',   'value' => ''),               //API密钥
    	array('name' => 'wxpay_title',         'type' => 'text',   'value' => '最鲜蜂水果订购'),      //支付显示标题 
    	array('name' => 'wxpay_expiretime',    'type' => 'text',   'value' => '120'),            //支付超时时间
    	array('name' => 'wxpay_debug',         'type' => 'select', 'value' => '0'),              //开启Debug
    );

    return;
}

/**
 * 类
 */
class wxpay
{

    /**
     * 构造函数
     *
     * @access  public
     * @param
     *
     * @return void
     */
	var $parameters; //cft 参数
	var $payments; //配置信息

    function __construct()
    {
    	
    }

    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $payment    支付方式信息
     */
    function get_code($order, $payment, $opt_only = FALSE)
    {
    	$wxtk = read_config('wxtoken');
    	$payment = array_merge($payment, $wxtk);
    	
    	$result = $this->unifiedorder($order, $payment);
    	
    	if (!$result['errcode'])
    	{    			    	
	    	// pay options
	    	$options = $this->makePayOptions($result, $payment);
	    	$opts = json_encode($options);
	    	
	    	$code = "<script>
wx.ready(function() {
    var payOpts = $opts;
    payOpts['complete'] = function(res) {alert(res)};
    wx.chooseWXPay(payOpts);
});
</script>";
			
			return $code;
    	}
    	else
    	{
    		// TODO error message
    	}

    }
    
    /**
     * 微信支付结果通知回调函数
     * 
     * @param array $payment
     */
    public function respond($payment)
    {
    	global $db, $ecs;
    	
    	$xml = file_get_contents('php://input');
    	$data = xml2array($xml);
    	if (empty($data))
    	{
    		$this->outputXml('FAIL', '参数格式校验错误');
    	}
    	
    	// 检查签名
    	$sendSign = $data['sign'];
    	unset($data['sign']);
    	$sign = $this->sign($data, $payment['wxpay_key']);
    	    	
    	if ($sendSign != $sign)
    	{
    		$this->outputXml('FAIL', '签名失败');
    	}
    	
    	$log_id = intval($data['attach']);
    	
    	if ($data['return_code'] == 'FAIL' || $data['result_code'] == 'FAIL')
    	{   
    		//支付结果为失败
    		$sql = "UPDATE " . $ecs->table('pay_log') . " SET is_paid = 9 WHERE log_id = '$log_id'";
    		$db->query($sql);
    		
    		$data['paytype'] = 'wxpay';
    		insert_error_log('pay', json_encode($data));
    	}
    	else
    	{
    		// 支付成功
    		order_paid($log_id, PS_PAYED, 'wxpay');
    	}
    	
    	$this->outputXml();
    }
    
    /**
     * 退款流程
     * 
     * @param array $order
     * @param array $payment
     */
    public function refund($order, $payment)
    {
    	
    }

    /**
     * 统一下单
     */
    private function unifiedorder($order, $payment)
    {
    	global $_CFG;
    	
    	$paytime = intval($payment['wxpay_expiretime']);
    	$paytime = $paytime > 5 ? time() + $paytime * 60 : time() + 300; //支付失效时间最短为5分钟
    	
    	//微信订单信息
    	$wxOrder = array(
    		'appid' 			=> $payment['appid'], 		    //公众账号ID
    		'mch_id' 			=> $payment['wxpay_mchid'],		//商户号
    		'spbill_create_ip' 	=> REMOTE_ADDR,					//终端ip
    		'nonce_str' 		=> rands(32),					//随机字符串
    		'out_trade_no'  	=> $order['order_sn'],		    //本系统订单ID
    		'body'				=> "$payment[wxpay_title][$order[order_sn]]",	    //商品描述
    		'total_fee'         => $order['order_amount'] * 100,//支付金额
    		'trade_type'		=> 'JSAPI',						//支付方式
    		'openid'			=> $_SESSION['openid'],			//支付用户OpenId
    		'time_expire'       => date('YmdHis', $paytime),	//支付失效时间
    		'attach'            => $order['log_id'],   			//支付logid，用于支付结果通知
    		'notify_url'		=> $_CFG['site_url'] . '/wxpay/respond.html', //支付结果通知URL
    	);

    	// 签名
    	$wxOrder["sign"] = $this->sign($wxOrder, $payment['wxpay_key']);
    	
    	// 调用微信接口创建统一订单    	
    	require_once(ROOT_PATH . 'include/cls_curl.php');
    	$curl = new Curl(array(
    		'server' => 'https://api.mch.weixin.qq.com',
    		'ssl_verify_peer' => FALSE,
    	));
    	
    	$response = $curl->post('pay/unifiedorder', array2xml($wxOrder), 'xml');

    	// 检查错误
    	if ($response['return_code'] == 'FAIL')
    	{
    		return array('errcode'=>1, 'message'=>$response['return_msg']);
    	}
    	elseif ($response['result_code'] == 'FAIL')
    	{
    		return array('errcode'=>$response['err_code'], 'message'=>$response['err_code_des']);
    	}
    	
    	//保存统一订单号，以便支付失败后再次发起支付
    	$this->saveUnifiedorder($order['log_id'], $response['prepay_id'], $paytime);
    	
    	return $response;
    }
    
    /**
     * 生成微信支付JS参数
     *
     * @param array $order
     */
    private function makePayOptions($order, $payment)
    {
    	$options = array (
    		'appId' 	=> $payment['appid'],     		//公众号APPID
    		'timeStamp'	=> time(),         					//时间戳
    		'nonceStr' 	=> rands(32), 			//随机串
    		'package' 	=> "prepay_id={$order[prepay_id]}",	//微信订单ID
    		'signType' 	=> 'MD5',         					//微信签名方式
    	);
    
    	$options['paySign'] = $this->sign($options, $payment['wxpay_key']);
    
    	// 转化为JSSDK需要的参数
    	$options['timestamp'] = $options['timeStamp'];
    	unset($options['timeStamp']);
    	unset($options['appId']);
    
    	return $options;
    }
	
    /**
     * 保存同一下单接口返回的订单号
     * 
     * @param interger $log_id
     * @param string   $order_sn
     * @param integer  $deadline
     */
    private function saveUnifiedorder($log_id, $order_sn, $deadline)
    {
    	global $db, $ecs;
    	
    	$sql = "UPDATE " . $ecs->table('pay_log') . 
    	" SET outer_sn = '$order_sn', deadline = '$deadline' WHERE log_id = '$log_id'";
    	
    	$db->query($sql);
    }
    
	/**
	 * 生成微信签名
	 *
	 * @param array   $obj       签名对象
	 * @param string  $key       签名密钥，可为空
	 * @param boolean $urlencode 参数是否需要UrlEncode
	 */
	private function sign($obj, $key = NULL, $urlencode = FALSE)
	{
		foreach ($obj as $k => $v)
		{
			$p[$k] = $v;
		}
	
		//签名步骤一：按字典序排序参数
		ksort($p);
		$buff = array();
		foreach ($p as $k => $v)
		{
			$buff[] = $k . '=' . ($urlencode ? urlencode($v) : $v);
		}
		$s = implode('&', $buff);
	
		//签名步骤二：在string后加入KEY
		if (!empty($key))
		{
			$s = $s . "&key=$key";
		}
	
		//签名步骤三：MD5加密
		$s = md5($s);
	
		//签名步骤四：所有字符转为大写
		$result_ = strtoupper($s);
	
		return $result_;
	}
	
	/**
	 * 返回XML
	 *
	 * @param string $error
	 * @param string $msg
	 */
	private function outputXml($error = 'SUCCESS', $msg = 'OK')
	{
		$arr = array(
			'return_code' => $error,
			'return_msg'  => $msg,
		);
	
		ob_end_clean();
		$xml = array2xml($arr);
		echo $xml;
		exit;
	}
}

?>