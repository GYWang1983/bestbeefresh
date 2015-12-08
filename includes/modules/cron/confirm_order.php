<?php

/**
 * ECSHOP 自动确认订单
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/confirm_order.php';
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
    $modules[$i]['desc']    = 'confirm_order_desc';

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


include_once(ROOT_PATH . 'includes/lib_order.php');
$locktime = strtotime(date('Y-m-d') . ' ' . $_CFG['order_lock_time']);

$sql = "SELECT order_id, user_id FROM " . $ecs->table('order_info') . " WHERE order_status=" . OS_UNCONFIRMED . " AND pay_status=" . PS_PAYED . " AND pay_time<=$locktime";
$orders = $db->getAll($sql);

if (!empty($orders))
{
	$now = time();
	
	$os = array(
		'order_status' => OS_CONFIRMED,
		'shipping_status' => SS_PREPARING,
		'confirm_time' => $now,
	);

	$pickup_time = get_order_pickup_time($locktime);
	$code_obj = array(
		'create_time'  => $now,
		'start_time'   => $pickup_time['start'],
		'end_time'     => $pickup_time['end'],
		'abandon_time' => $pickup_time['start'] + 3600 * intval($_CFG['shipping_limit_time']),
		'status'       => 1
	);
	
	$users = array();
	foreach ($orders as $o)
	{
		//生成取货码
		if (!in_array($o['user_id'], $users))
		{
			$code_obj['code']    = make_pickup_code($o);
			$code_obj['user_id'] = $o['user_id'];
			$db->autoExecute($ecs->table('pickup_code'), $code_obj);
			
			$users[] = $o['user_id'];
		}
		
		//TODO:生成包裹
		
		//更新订单状态
		update_order($o['order_id'], $os);
	
		// 计算并发放积分
		//$integral = integral_to_give($o);
		//log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $order['order_sn']));
		
		// 发放红包 
		send_order_bonus($o['order_id']);
	}
}

function make_pickup_code($order)
{
	return sha1("{$order[user_id]}|{$order[add_time]}{$order[pay_time]}" . md5(time()));
}

?>