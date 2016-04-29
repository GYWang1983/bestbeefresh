<?php
/**
 * ECSHOP 程序说明
 * ===========================================================
 * * 版权所有 2014-2016 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ==========================================================
 */
define('IN_ECS', true);

require('./init.php');
require(ROOT_PATH . 'includes/cls_wechat.php');
require(ROOT_PATH . 'api/wechat/event.php');

$wechat_api = new WechatApi();

// 检查签名
if (!$wechat_api->checkSignature($_REQUEST['signature'], $_REQUEST['timestamp'], $_REQUEST['nonce'])) {
	exit;
}

$echostr = $_REQUEST['echostr'];
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
	echo $echostr;
	exit;
}

// Read Post data
$raw = file_get_contents ('php://input');
!empty ($raw) || die ('Invalid Input');
$data = new SimpleXMLElement($raw);

$input = array();
foreach ( $data as $key => $value ) {
	$input[$key] = strval($value);
}

$openid = $input['FromUserName'];
$raw = str_replace(array("\n", "\r"), '', $raw);

if ($input['MsgType'] == 'event') {
	
	$event = strtolower ($input['Event']);
	$model = ROOT_PATH . "api/wechat/{$event}.php";

	if (file_exists($model)) {
		require_once($model);
		$class =  '\\Api\\Wechat\\'.$event;
		if (class_exists($class)) {
			$handler = new $class();
			$handler->doEvent($input);
		}
	}
}