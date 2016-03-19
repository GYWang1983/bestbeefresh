<?php

/**
 * ECSHOP 取货页面
 * ============================================================================
 * * 版权所有 2015-2015 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'includes/lib_order.php');
//require_once(ROOT_PATH . 'includes/lib_goods.php');

/* 权限判断 */
admin_priv('pickup');

$action = empty($_REQUEST['act']) ? 'default' : trim($_REQUEST['act']);


if ($action == 'default')
{
	
	
}
elseif ($action == 'query')
{
	if (!empty($_POST['code']))
	{
		$code = trim($_POST['code']);
		$user_id = check_pickup_code($code);
	}
	elseif (!empty($_POST['ordersn']))
	{
		$code = trim($_POST['ordersn']);
		$sql = "SELECT user_id FROM " . $ecs->table('order_info') . " WHERE order_sn = '$code'";
		$user_id = $db->getOne($sql);
	}
	
	if (empty($user_id))
	{
		echo json_encode(array('errcode' => 10, 'msg' => '没有可以取货的商品'));
		exit;
	}
	
	//TODO
	$shop_id = $_SESSION['shop_list'][0];
	
	//查询包裹
	$packlist = get_pickup_packs($user_id, $shop_id);
	
	$packs = array();
	$pids = array();
	foreach ($packlist as $p)
	{
		$packs[] = array(
			'sn' => substr($p['create_date'], 6, 2) . '-' . $p['pos_row'] . '-' . str_pad($p['pos_sn'], 2, '0', STR_PAD_LEFT),
			'status' => $p['status'],	
		);
		if ($p['status'] == 2)
		{
			$pids[] = $p['id'];
		}
	}
	
	if (empty($pids))
	{
		echo json_encode(array('errcode' => 10, 'msg' => '没有可以取货的商品'));
		exit;
	}
	
	// 获取商品
	$goods = get_pickup_goods($pids);
	if (empty($goods))
	{
		echo json_encode(array('errcode' => 10, 'msg' => '没有可以取货的商品'));
		exit;
	}
	
	// Update order status
	$orders = get_pickup_orders($pids);
	if (!empty($orders))
	{
		$status = array(
			'shipping_status' => SS_RECEIVED,
			'receive_time'    => time()
		);
		foreach ($orders as &$o)
		{
			update_order($o['order_id'], $status);
			order_action($o['order_sn'], OS_CONFIRMED, SS_RECEIVED, PS_PAYED, '门店取货');
		}
	}
	
	// Update package status
	$sql = "UPDATE " . $ecs->table('pickup_pack') . " SET status=3 WHERE id IN (" . implode(',', $pids) . ")";
	$db->query($sql);
		 
	$response = array(
		'errcode' => 0,
		'mobile'  => $orders[0]['mobile'],
		'goods'   => $goods,
		'orders'  => $orders,
		'packs'   => $packs,
	);
	
	echo json_encode($response);
	exit;
}

$smarty->display(is_wechat_browser() ? 'pickup_wechat.htm' : 'pickup_wechat.htm');

/**
 * 检查取货码，返回对应的user_id
 * 
 * @param string $code 取货码
 * 
 * errcode:
 * 1 - 取货码不存
 * 2 - 该取货码已经取过货了
 * 3 - 取货码已过期
 */
function check_pickup_code($code)
{
	global $ecs, $db;
	
	$sql = "SELECT * FROM " . $ecs->table('pickup_code') . " WHERE code = '$code'";
	$qcode = $db->getRow($sql);

	if (empty($qcode))
	{
		echo json_encode(array('errcode' => 1, 'msg' => '取货码不存在'));
		exit;
	}
	elseif ($qcode['status'] == 2)
	{
		echo json_encode(array('errcode' => 2, 'msg' => '取货码已使用'));
		exit;
	}
	elseif ($qcode['status'] == 3)
	{
		echo json_encode(array('errcode' => 3, 'msg' => '取货码已过期'));
		exit;
	}
	
	return $qcode['user_id'];
}

/**
 * 获取指定用户可取货的包裹列表
 * 
 * @param integer $user_id
 */
function get_pickup_packs($user_id, $shop_id)
{
	global $ecs, $db;
	
	$sql = "SELECT id, create_date, pos_row, pos_sn, status FROM " . $ecs->table('pickup_pack') . 
		" WHERE user_id = '$user_id' AND shop_id = '{$shop_id}' AND status = 2";
	$packs = $db->getAll($sql);
	return $packs;
}

/**
 * 获取指定用户可取货的商品列表
 * 
 * @param array $pids
 */
function get_pickup_goods($pids)
{
	global $ecs, $db;
	
	$sql = "SELECT og.goods_id, og.goods_sn, og.goods_name, og.goods_attr, sum(og.goods_number) AS goods_number, og.free_more " .
		" FROM " . $ecs->table('order_info', 'o') . "," . $ecs->table('order_goods', 'og') .
		" WHERE o.order_id = og.order_id AND o.package_id IN (" . implode(',', $pids). ")" . 
		" GROUP BY og.goods_id, og.free_more ORDER BY og.goods_id";
	$rs = $db->getAll($sql);
	
	$goods = array();
	foreach ($rs AS $g)
	{
		if (!empty($g['free_more']))
		{
			$g['goods_number'] += get_free_more_number($g['free_more'], $g['goods_number']);
			$g['free_more_desc'] = get_free_more_desc($g['free_more']);
		}
		
		if (array_key_exists($g['goods_id'], $goods))
		{
			$goods[$g['goods_id']]['goods_number'] += $g['goods_number'];
		}
		else
		{
			$goods[$g['goods_id']] = $g;
		}
	}
	
	return $goods;
}

/**
 * 获取指定用户可取货的订单列表
 *
 * @param array $pids
 */
function get_pickup_orders($pids)
{
	global $ecs, $db;
	
	$sql = "SELECT o.order_id, o.order_sn, o.mobile, o.pay_time, og.goods_id, og.goods_sn, og.goods_name, og.goods_number, og.free_more " .
			" FROM " . $ecs->table('order_info', 'o') . "," . $ecs->table('order_goods', 'og') .
			" WHERE o.order_id = og.order_id AND o.package_id IN (" . implode(',', $pids) . ")" .
			" ORDER BY o.order_id";
	$rs = $db->getAll($sql);
	
	$orders = array();
	$order_id = 0;
	foreach ($rs as $g)
	{
		if ($order_id != $g['order_id'])
		{
			$orders[] = array(
				'order_id' => $g['order_id'],
				'order_sn' => $g['order_sn'],
				'mobile'   => $g['mobile'],
				'pay_time' => date('Y-m-d H:i', $g['pay_time']),
				'goods' => array()
			);
			$order_id = $g['order_id'];
		}
		
		unset($g['order_id']);
		unset($g['order_sn']);
		unset($g['pay_time']);
		
		$g['free_more_desc'] = get_free_more_desc($g['free_more']);
		$orders[count($orders) - 1]['goods'][] = $g;
	}
	
	return $orders;
}
?>