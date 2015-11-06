<?php

/**
 * ECSHOP 容联云通讯平台
 * ============================================================================
 * * 版权所有 2005-2012 南京蜂蚁网络科技有限公司，并保留所有权利。
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

$sms_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/sms/yuntongxun.php';

if (file_exists($sms_lang))
{
    global $_LANG;
    include_once($sms_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'yuntongxun_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_text']  = '1';

    /* 是否支持在线支付 */
    $modules[$i]['is_voice']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'Bestbeefresh';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '1.0.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
    	array('name' => 'account_sid',   'type' => 'text', 'value' => ''),
    	array('name' => 'account_token', 'type' => 'text', 'value' => ''),
    	array('name' => 'server_domain', 'type' => 'text', 'value' => ''),
    	array('name' => 'server_port',   'type' => 'text', 'value' => ''),
    	array('name' => 'soft_version',  'type' => 'text', 'value' => '2013-12-26'),
    	array('name' => 'appId',         'type' => 'text', 'value' => ''),
    		
    	array('name' => 'register_tmpl', 'type' => 'text', 'value' => ''),
    	array('name' => 'login_tmpl',    'type' => 'text', 'value' => ''),
    	array('name' => 'bind_tmpl',     'type' => 'text', 'value' => ''),
    	array('name' => 'resetpwd_tmpl', 'type' => 'text', 'value' => ''),
    		
    	array('name' => 'debug',         'type' => 'select', 'value' => '0'),
    );

    return;
}

/**
 * yuntongxun类
 */
class yuntongxun
{

	private $config = array();
	
    /**
     * 构造函数
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function __construct($config)
    {
    	include_once(__DIR__ . '/sdk/CCPRestSDK.php');
    	
    	if (is_string($config) && ($arr = unserialize($config)) !== false)
    	{
    		$this->config = array();
	    	foreach ($arr AS $key => $val)
	        {
	            $this->config[$val['name']] = $val['value'];
	        }
    	}
    	elseif (is_array($config))
    	{
    		$this->config = $config;
    	}
    	
    }

    /**
     * 发送文字短信
     * @param unknown $mobile
     * @param unknown $data
     * @param unknown $step
     */
    function send_text($mobile, $data, $step)
    {
    	global $_CFG;
    	
    	$rest = new REST($this->config['server_domain'], $this->config['server_port'], $this->config['soft_version']);
    	$rest->setAccount($this->config['account_sid'], $this->config['account_token']);
    	$rest->setAppId($this->config['appId']);
    	if ($this->config['debug'] == 1)
    	{
    		$rest->setLog(TRUE, ROOT_PATH . '/data/yuntongxun_sms.log');
    	}
    	
    	//TODO: 
    	$param = array($data['verifycode'], intval($_CFG['ecsdxt_sms_validtime'] / 60));
    	
    	// 发送模板短信
    	$result = $rest->sendTemplateSMS($mobile, $param, $this->config[$step . '_tmpl']);
    	if($result == NULL) {
    		return array('errcode' => 1, 'errmsg'=> 'unknown error');
    	}
    	if($result->statusCode != 0) {
    		return array('errcode' => $result->statusCode, 'errmsg'=> $result->statusMsg);
    	}else{
    		return true;
    	}
    }
    
    /**
     * 发送语音
     * @param unknown $mobile
     * @param unknown $data
     * @param unknown $step
     */
    function send_void($mobile, $data, $step)
    {
    	
    }
}

?>