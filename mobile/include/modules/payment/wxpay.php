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
    	//$result = array('prepay_id' => '12345678');
    	if (!$result['errcode'])
    	{    			    	
	    	// pay options
	    	$options = $this->makePayOptions($result, $payment);
	    	$opts = json_encode($options);
	    	
	    	$code = "<script>
wx.ready(function() {
    var payOpts = $opts;
    payOpts['complete'] = function(res) {
        var url = 'user.php?act=order_detail&order_id={$order[order_id]}',
        data = {
            'order_id': '$order[order_id]',
    	    'log_id':   '$order[log_id]'
        };
        
        if (res.errMsg == 'chooseWXPay:ok') {
            $.post('flow.php?step=paid&ajax=1', data, function() {
            	window.location.href = url;
    		});
    	} else if (res.errMsg == 'chooseWXPay:cancel') {
    		alert(url);
    		window.location.href = url;
    	} else {
    	    var err = res;
    	    err['paytype'] = 'wxpay';
    		err['step'] = 'jsapi';
    		err['order_id'] = '$order[order_id]';
    	    err['log_id'] =   '$order[log_id]';
    		$.post('log.php?type=pay', {'info':JSON.stringify(err)});
    		alert('非常抱歉，微信支付失败。您可以稍候再次尝试，或联系客服人员处理。');
    		window.location.href = url;
    	}
    };
    wx.chooseWXPay(payOpts);
});
</script>";
	    		
    	}
    	else
    	{
    		// 记录失败日志
    		$result['paytype'] = 'wxpay';
    		$result['step'] = 'unifiedorder';
    		$result['order_id'] = $order['order_id'];
    		$result['log_id'] = $order['log_id'];
    		insert_error_log('pay', $result, __FILE__);
    		
    		$code = '<div style="text-align:center; color:red; font-size:1.2rem;">非常抱歉，微信支付失败。您可以稍候再次尝试，或联系客服人员处理。</div>
<div style="text-align:center"><a href="user.php?act=order_detail&order_id=' . $order['order_id'] . '" class="c-btn3">查看订单</a></div>';
    	}
    	
    	return $code;

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
    		insert_error_log('pay', json_encode($data), __FILE__);
    	}
    	else
    	{
    		// 支付成功
    		order_paid($log_id, PS_PAYED, 'wxpay');
    		
    		//更新pay_log的order_sn，退款时会用到
    		$sql = "UPDATE " . $ecs->table('pay_log') . " SET outer_sn = '$data[transaction_id]' WHERE log_id = '$log_id'";
    	}
    	
    	$this->outputXml();
    }
    
    /**
     * 退款流程
     * 
     * @param array   $order_id
     * @param array   $payment
     * @param decimal $refund_amount
     */
    public function refund($order, $payment, $refund_amount = 0)
    {
    	global $db, $ecs;
    	
    	//获取pay_log记录
    	$sql = "SELECT * FROM " . $ecs->table('pay_log') . 
    		" WHERE order_id = '$order[order_id]' AND pay_id = '$payment[pay_id]' AND is_paid = 1 ORDER BY log_id DESC LIMIT 1";
    	$log = $db->getRow($sql);
    	
    	if (empty($log))
    	{
    		return false;
    	}
    	
    	$wxtk = read_config('wxtoken');
    	$payment = array_merge($payment, $wxtk);
    	
    	$amount = ($refund_amount > 0 && $refund_amount < $log['order_amount']) 
    				? $refund_amount : $log['order_amount'];
    	
    	$refund = array(
			'appid'          => $payment['appid'],			//公众账号ID
    		'mch_id'         => $payment['wxpay_mchid'],	//商户号
    		'op_user_id'     => $payment['wxpay_mchid'],	//操作员
    		'nonce_str'      => rands(32),					//随机字符串
    		'out_refund_no'  => $order['order_sn'],			//退款单ID，与订单ID相同
    		'out_trade_no'   => $order['order_sn'],			//本系统订单ID
    		'refund_fee'     => $amount * 100,				//退款金额
    		'total_fee'      => $log['order_amount'] * 100,	//订单支付金额
    		'transaction_id' => $log['outer_sn'],			//微信订单号
    	);
    	
    	$refund['sign'] = $this->sign($refund, $payment['wxpay_key']);
    	
    	// 调用微信退款接口
    	require_once(ROOT_PATH . 'include/cls_curl.php');
    	$curl = new Curl(array(
    		'server' => 'https://api.mch.weixin.qq.com',
    		'ssl_verify_peer' => FALSE,
    	));
    	
    	$curl->option(CURLOPT_SSLCERTTYPE, 'PEM');
		$curl->option(CURLOPT_SSLCERT, ROOT_PATH . '../data/cert/wxpay_cert.pem');
		$curl->option(CURLOPT_SSLKEYTYPE, 'PEM');
		$curl->option(CURLOPT_SSLKEY, ROOT_PATH . '../data/cert/wxpay_key.pem');
		
		$xml = array2xml($refund);
		$response = $curl->post('secapi/pay/refund', $xml, 'xml');
		
		// 检查错误
		if ($response['return_code'] == 'FAIL')
		{
			//return $this->errJSON($response['return_code'], $response['return_msg'], FALSE);
		}
		elseif ($response['result_code'] == 'FAIL')
		{
			//return $this->errJSON($response['err_code'], $response['err_code_des'], FALSE);
		}
		
		//TODO: pay_log 插入退款记录
    }

    /**
     * 统一下单
     */
    private function unifiedorder($order, $payment)
    {
    	global $_CFG, $db, $ecs;
    	
    	$timestamp = time();
    	
    	// 查找当前订单是否已经存在同一订单号
    	$sql = "SELECT * FROM " . $ecs->table('pay_log') . 
    		" WHERE order_id = '$order[order_id]' AND pay_id = '$payment[pay_id]' AND is_paid = 0 " .
    		" AND outer_sn IS NOT NULL AND deadline > $timestamp ORDER BY log_id DESC LIMIT 1";
    	$log = $db->getRow($sql);
    	
    	if (empty($log))
    	{
	    	$paytime = intval($payment['wxpay_expiretime']);
	    	$paytime = $paytime > 5 ? time() + $paytime * 60 : $timestamp + 300; //支付失效时间最短为5分钟
	    	
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
    	
    	}
    	else 
    	{
    		//已经存在统一订单ID，直接使用
    		$response = array(
    			'prepay_id' => 	$log['outer_sn']
    		);
    	}
    	
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