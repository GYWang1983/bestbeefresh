<?php

/**
 * ECSHOP 自动发货订单
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

include_once(ROOT_PATH . 'includes/lib_order.php');

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/ship_order.php';
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
    $modules[$i]['desc']    = 'ship_order_desc';

    /* 作者 */
    $modules[$i]['author']  = 'gy.wang';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '0.1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        //array('name' => 'minuts_interval', 'type' => 'text', 'value' => ''),
    );

    return;
}

$sql = "SELECT order_id, confirm_time FROM " . $ecs->table('order_info') . " WHERE order_status = " . OS_CONFIRMED . " AND shipping_status = " . SS_PREPARING;
$rs = $db->getAll($sql);

foreach ($rs as $order)
{
	$pickup_time = get_order_pickup_time(0, $order['confirm_time']);
	$db->autoExecute($ecs->table('order_info'), array(
		'shipping_status'  => SS_SHIPPED,
		'shipping_time'    => time(),
		'receive_deadline' => $pickup_time['end'],
	), 'UPDATE', 'order_id=' . $order['order_id']);
}

?>