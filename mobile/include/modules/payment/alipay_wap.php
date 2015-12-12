<?php

/**
 * ECSHOP 支付宝手机支付插件
 * ============================================================================
 * * 版权所有 2014 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
 */

if (!defined('IN_ECTOUCH'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'lang/' .$GLOBALS['_CFG']['lang']. '/payment/alipay_wap.php';

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
    $modules[$i]['desc']    = 'alipay_wap_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'ECTOUCH TEAM';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.ectouch.cn';

    /* 版本号 */
    $modules[$i]['version'] = '1.0.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
    	array('name' => 'alipay_title',      'type' => 'text',   'value' => '最鲜蜂水果订购'),
    	array('name' => 'alipay_expiretime', 'type' => 'text',   'value' => '120'),
        array('name' => 'alipay_account',    'type' => 'text',   'value' => ''),
        array('name' => 'alipay_key',        'type' => 'text',   'value' => ''),
        array('name' => 'alipay_partner',    'type' => 'text',   'value' => ''),
    );

    return;
}

/**
 * 类
 */
class alipay_wap
{

    /**
     * 构造函数
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function __construct()
    {

    }

    function get_config($payment)
    {
    	$config = array(
    		'partner'    => $payment['alipay_partner'],
    		'seller_id'  => $payment['alipay_partner'],
    		'sign_type'  => 'RSA',
    		'transport'  => 'http',
    		'input_charset' => 'utf-8',
    		'cacert'              => ROOT_PATH . '../data/cert/alipay_cacert.pem',
    		'private_key_path'    => ROOT_PATH . '../data/cert/rsa_private_key.pem',
    		'ali_public_key_path' => ROOT_PATH . '../data/cert/alipay_public_key.pem',
    	);
    	return $config;
    }
    
    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $payment    支付方式信息
     */
    function get_code($order, $payment)
    {
    	global $_CFG;
    	
    	$html = $alipay_form . "<script>
    $('body').html(\"<iframe id='alipay_frame' src='flow.php?step=pay_code&log={$order[log_id]}' style='width:100%;border:0;height:100%;min-height:300px;'></iframe>\");
</script>";
    	//$html = $order['order_sn'] . '_' . $order['log_id'];
    	return $html;
    }
	
    function get_code2($order, $payment)
    {
    	global $_CFG;
    	
    	require_once("alipaylib/alipay_submit.class.php");
    	 
    	//建立请求
    	$alipaySubmit = new AlipaySubmit($this->get_config($payment));    	 
    	$param = array(
    		"service"       => "alipay.wap.create.direct.pay.by.user",
    		"partner"       => trim($payment['alipay_partner']),
    		"seller_id"     => trim($payment['alipay_partner']),
    		"payment_type"	=> 1,
    		"notify_url"	=> $_CFG['site_url'] . '/alipay_wap/respond.html',
    		"return_url"	=> $_CFG['site_url'] . '/alipay_wap/respond_sync.html',
    		"out_trade_no"	=> $order['order_sn'] . '_' . $order['log_id'],
    		"subject"	    => $payment['alipay_title'] . "[$order[order_sn]]",
    		"total_fee"	    => $order['order_amount'],
    		"it_b_pay"	    => $payment['alipay_expiretime'] . 'm',
    		"_input_charset"=> 'utf-8',
    		//"show_url"	    => $show_url,
    		//"body"	        => $body,
    		//"extern_token"	=> $extern_token,
    	);
    	 
    	$alipay_form = $alipaySubmit->buildRequestForm($param, 'post', '确认支付');
    	return $alipay_form;
    }
    
	/**
	 * 手机支付宝异步响应操作
	 */
	function respond($payment)
	{
		global $_CFG, $ecs, $db;
		require_once("alipaylib/alipay_notify.class.php");
		//file_put_contents(__DIR__ .'/respond.log', print_r($_REQUEST, true), FILE_APPEND);
		$is_sync = $payment['call_type'] == 'sync';
		$alipayNotify = new AlipayNotify($this->get_config($payment));
		$verify_result = $is_sync ? $alipayNotify->verifyReturn() : $alipayNotify->verifyNotify();
		if ($verify_result) {
			
			$tmp = explode('_', $_REQUEST['out_trade_no']);
			$order_sn = $tmp[0];
			$log_id = $tmp[1];

			$trade_no     = $_REQUEST['trade_no'];     //支付宝交易号
			$trade_status = $_REQUEST['trade_status']; //交易状态
			
			$order_url = $_CFG['site_url'] . "/user.php?act=order_detail&order_sn={$order_sn}";
		
			if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
				// 支付成功
    			if ($is_sync) {
    				//跳转到订单详情
    				show_message('支付完成', '查看订单', "javascript:top.location.href='{$order_url}'");
    			} else {
    				//异步通知更新支付状态
    				order_paid($log_id, PS_PAYED, 'alipay_wap');
    				//更新pay_log的order_sn，退款时会用到
    				$sql = "UPDATE " . $ecs->table('pay_log') . " SET outer_sn = '$trade_no' WHERE log_id = '$log_id'";
    				$db->query($sql);
    				
    			}
    			
			} elseif ($trade_status == 'TRADE_CLOSED') {
				$sql = 'UPDATE ' . $ecs->table('pay_log') . " SET is_paid = '4' WHERE log_id = '$log_id'";
            	$db->query($sql);
			}
		
			//其他支付状态
			if ($is_sync) {
				echo "<html><script>top.location.href='{$order_url}';</sctipt></html>";
			} else {
				echo 'success';
			}
			
		} else {
			echo 'fail';
		}
		
		exit;
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
		
	}
}
?>