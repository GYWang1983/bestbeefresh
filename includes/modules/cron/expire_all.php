<?php

/**
 * ECSHOP 设置过期记录状态
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/expire_all.php';
if (file_exists($cron_lang))
{
	global $_LANG;
	include_once($cron_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'expire_all_desc';

    /* 作者 */
    $modules[$i]['author']  = 'gy.wang';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '0.1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'minuts_interval',    'type' => 'text', 'value' => '60'),
    	array('name' => 'unpay_order_expire', 'type' => 'text', 'value' => '8'),
    );

    return;
}

include_once(ROOT_PATH . 'includes/lib_transaction.php');
include_once(ROOT_PATH . 'includes/lib_order.php');

make_order_expire($cron);
make_pickup_code_expire($cron);
make_verifycode_expire($cron);

/**
 * 订单过期
 */
function make_order_expire($cron)
{
	global $db, $ecs;
	
	$sql = "UPDATE " . $ecs->table('order_info') . " SET `order_status` = " . OS_EXPIRED . " WHERE `order_status` = " . OS_CONFIRMED . 
			" AND shipping_status = " . SS_SHIPPED . " AND receive_deadline < " . time();
	$db->query($sql);
	
	//24小时未支付则过期
	$expire_time = time() - intval($cron['unpay_order_expire']) * 3600;
	$sql = "SELECT * FROM " . $ecs->table('order_info') . 
		" WHERE order_status = " . OS_UNCONFIRMED . " AND pay_status = " . PS_UNPAYED . " AND add_time < $expire_time";
	$query = $db->query($sql);
	while ($rs = $db->fetch_array($query))
	{
		cancel_order($rs['order_id'], $rs['user_id'], OS_EXPIRED);
	}
}

/**
 * 取货码过期
 */
function make_pickup_code_expire($cron)
{
	global $db, $ecs;
	
	$sql = "UPDATE " . $ecs->table('pickup_code') . " SET `status` = 3 WHERE `status` = 1 AND abandon_time < " . time();
	$db->query($sql);
}

/**
 * 手机验证码过期
 */
function make_verifycode_expire($cron)
{
	global $db, $ecs;
	
	$sql = "UPDATE " . $ecs->table('verifycode') . " SET `status` = 3 WHERE `status` = 1 AND dateline < " . time();
	$db->query($sql);
}
?>