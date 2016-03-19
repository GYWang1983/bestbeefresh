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

$sql = "SELECT o.order_id, o.user_id, o.shop_id, s.open_time, s.close_time FROM " . 
		$ecs->table('order_info', 'o') . ',' . $ecs->table('shop', 's') .
		" WHERE o.shop_id = s.shop_id AND o.order_status=" . OS_UNCONFIRMED . " AND o.pay_status=" . PS_PAYED . 
		" AND o.pay_time<=$locktime AND s.status != 2 ORDER BY o.shop_id, o.user_id";
$query = $db->query($sql);

$now = time();
$os = array(
	'order_status' => OS_CONFIRMED,
	'shipping_status' => SS_PREPARING,
	'confirm_time' => $now,
);

// 生成包裹
$shop_id = 0;
$user_id = 0;
while($o = $db->fetch_array($query))
{
	if ($o['shop_id'] != $shop_id || $o['user_id'] != $user_id)
	{
		$shop_id = $o['shop_id'];
		$user_id = $o['user_id'];
		
		$pickup_time = get_order_pickup_time($locktime, 0, $o['open_time'], $o['close_time']);
		
		$pack_obj = array(
			'shop_id'     => $shop_id,
			'user_id'     => $user_id,
			'start_time'  => $pickup_time['start'],
			'end_time'    => $pickup_time['end'],
			'expire_time' => $pickup_time['start'] + 3600 * intval($_CFG['shipping_limit_time']),
			'create_date' => date('Ymd', $pickup_time['start']),
		);

		$db->autoExecute($ecs->table('pickup_pack'), $pack_obj);
		$pack_id = $db->insert_id();	
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

// 规划包裹位置
$pack_date = date('Ymd', $pickup_time['start']);
$sql = "SELECT shop_id, count(id) AS pack_num FROM " . $ecs->table('pickup_pack') . " WHERE create_date = '$pack_date' GROUP BY shop_id";
$query = $db->query($sql);
while ($shop = $db->fetch_array($query))
{
	$sql = "SELECT id FROM " . $ecs->table('pickup_pack') . 
			" WHERE create_date = '$pack_date' AND shop_id = '$shop[shop_id]' ORDER BY user_id ASC";
	$pack_list = $db->getCol($sql);
	
	$pack_num = $shop['pack_num'];
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

// 生成取货码
$sql = "SELECT user_id, min(start_time) AS start_time, max(end_time) AS end_time, max(expire_time) AS abandon_time FROM " . 
		$ecs->table('pickup_pack') . " WHERE create_date = '$pack_date' GROUP BY user_id";
$query = $db->query($sql);
while ($rs = $db->fetch_array($query))
{
	$rs['code']        = make_pickup_code($rs);
	$rs['create_time'] = $now;
	$rs['status']      = 1;
	
	$db->autoExecute($ecs->table('pickup_code'), $rs);
}

function make_pickup_code($rs)
{
	return sha1("{$rs[end_time]}|{$rs[user_id]}|{$rs[start_time]}" . md5(time()));
}

?>