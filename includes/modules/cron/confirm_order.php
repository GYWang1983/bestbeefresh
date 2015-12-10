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

define('SHELF_ROW_NUM', 4);

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
	
	$pack_date = date('Ymd', $pickup_time['start']);
	$pack_obj = array(
		'create_date' => $pack_date,
		'expire_time' => $code_obj['abandon_time'],
	);
	
	$users = array();
	$packs = array();
	foreach ($orders as $o)
	{
		$user_id = $o['user_id'];
		
		// 生成取货码
		if (!in_array($user_id, $users))
		{
			$code_obj['code']    = make_pickup_code($o);
			$code_obj['user_id'] = $user_id;
			$db->autoExecute($ecs->table('pickup_code'), $code_obj);
			
			$users[] = $user_id;
		}
		
		// 生成包裹
		if (!array_key_exists($user_id, $packs))
		{
			$pack_obj['user_id'] = $user_id;
			$db->autoExecute($ecs->table('pickup_pack'), $pack_obj);
			$pack_id = $db->insert_id();
			$packs[$user_id] = $pack_id;
		}
		else
		{
			$pack_id = $packs[$user_id];
		}
		
		// 更新订单状态
		$os['package_id'] = $pack_id;
		update_order($o['order_id'], $os);
		
		// 计算并发放积分
		//$integral = integral_to_give($o);
		//log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($_LANG['order_gift_integral'], $order['order_sn']));
		
		// 发放红包 
		send_order_bonus($o['order_id']);
	}
	
	unset($users);
	unset($packs);
}

// 规划包裹位置
if (empty($pack_date))
{
	$pack_date = date('Ymd', time());
}

$sql = "SELECT id FROM " . $ecs->table('pickup_pack') . " WHERE create_date = '$pack_date' ORDER BY user_id ASC";
$pack_list = $db->getCol($sql);
$pack_num = count($pack_list);

if ($pack_num > 0)
{	
	$num_per_row  = floor($pack_num / SHELF_ROW_NUM);
	$overflow_num = $pack_num % SHELF_ROW_NUM;
	$n = 0;
	for ($row = 1; $row <= SHELF_ROW_NUM; $row++)
	{
		$col = $row <= $overflow_num ? $num_per_row + 1 : $num_per_row;
		for ($sn = 1; $sn <= $col; $sn++)
		{
			$sql = "UPDATE " . $ecs->table('pickup_pack') . " SET pos_row='$row', pos_sn='$sn' WHERE id=" . $pack_list[$n];
			$db->query($sql);
			$n++;
		}
	}
}

function make_pickup_code($order)
{
	return sha1("{$order[user_id]}|{$order[add_time]}{$order[pay_time]}" . md5(time()));
}

?>