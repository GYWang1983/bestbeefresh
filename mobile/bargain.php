<?php

/**
 * 砍价页面
 * ============================================================================
 * * 版权所有 2015-2016 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
*/

define('IN_ECTOUCH', true);

require(dirname(__FILE__) . '/include/init.php');
require(ROOT_PATH . 'include/cls_wechat.php');

/*------------------------------------------------------ */
//-- INPUT
/*------------------------------------------------------ */
$act = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'my';
$bargain_id = isset($_REQUEST['id'])  ? intval($_REQUEST['id']) : 0;
$user_bargain_id = isset($_REQUEST['ubid'])  ? intval($_REQUEST['ubid']) : 0;

// 检查是否登录
if (empty($_SESSION['user_id']))
{
	if (is_ajax())
	{
		ajax_error('请先登录再参加活动');
	}
	else 
	{
		show_message('参加活动需要先登录网站', '现在去登录', 'user.php?act=login', 'error');
	}
}

if ($act == 'add')
{
	$user_bargain = get_user_bargain_detail($user_bargain_id);
	
	// 检查砍价活动状态
	if (empty($user_bargain) || $user_bargain['user_bargain_status'] != 1)
	{
		ajax_error('砍价活动已结束');
	}
	
	// 检查用户是否已经参与过砍价
	if (!empty($user_bargain['detail']))
	{
		foreach ($user_bargain['detail'] as &$item)
		{
			if ($item['friend_id'] == $_SESSION['user_id'])
			{
				ajax_error('已经帮TA砍过价了');
			}
		}
	}
	
	// 是否已经砍到最低价
	if ($user_bargain['shop_price'] - $user_bargain['bargain_price'] <= $user_bargain['min_price'])
	{
		ajax_error('已经砍到最低价了');
	}
	
	// 本次砍价最多可砍掉的金额
	$bargain_limit = $user_bargain['shop_price'] - $user_bargain['min_price'] - $user_bargain['bargain_price'];
	
	// 计算随机砍价金额
	$k = rand(0, 100) <= $user_bargain['success_rate'] ? 1 : -1;
	$pre = $k > 0 ? 'success' : 'fail';
	$min = $user_bargain["{$pre}_min_price"] * 100;
	$max = $user_bargain["{$pre}_max_price"] * 100;
	$rnd = rand($min, $max) * $k / 100;
	$rnd = min($rnd, $bargain_limit);
		
	// 插入数据
	$data = array(
		'bargain_id' => $user_bargain['id'],
		'user_id'    => $user_bargain['user_id'],
		'friend_id'  => $_SESSION['user_id'],
		'price'      => $rnd,
		'add_time'   => time(),
	);
	
	// 地理位置信息
	if (!empty($_POST['longitude']))
	{
		$data['longitude'] = $_POST['longitude'];
		$data['latitude']  = $_POST['latitude'];
	}
	
	// 插入记录
	$db->autoExecute($ecs->table('user_bargain_detail'), $data);
	
	// 更新砍价总金额
	$sql = "UPDATE " . $ecs->table('user_bargain') . " SET bargain_price = bargain_price + ($rnd) WHERE id = {$user_bargain_id}";
	$db->query($sql);
	
	// 返回数据
	$result = array(
		'bargain_success' => $rnd > 0 ? 1 : 0,
		'bargain_price'   => $rnd,
		'bargian_msg'	  => $rnd > 0 ? "手起刀落，砍掉{$rnd}元" : "掌柜暴怒，涨价{$rnd}元",
		'now_price'       => price_format($user_bargain['shop_price'] - $user_bargain['bargain_price'] - $rnd),
		'total_bargain_price' => price_format($user_bargain['bargain_price'] + $rnd),
	);
	
	echo json_encode($result);
	exit;
}

$wechat = new WechatApi();

// 检查是否存在昵称和头像
$sql = "SELECT * FROM wxch_user WHERE uid = " . intval($_SESSION['user_id']);
$wx_user = $db->getRow($sql);
if (!empty($wx_user) && empty($wx_user['headimgurl']))
{	
	if ($act == 'my')
	{
		// 尝试通过微信API获取昵称和头像
		$wx_user = $wechat->get_user_info($wx_user['wxid']);
		
		if (!empty($wx_user) && $wx_user['subscribe'] == 1)
		{
			// 更新微信用户数据
			$db->autoExecute('wxch_user', array (
				'subscribe' => $wx_user['subscribe'],
				'nickname'  => $wx_user['nickname'],
				'sex'       => $wx_user['sex'],
				'city'      => $wx_user['city'],
				'country'   => $wx_user['country'],
				'province'  => $wx_user['province'],
				'language'  => $wx_user['language'],
				'headimgurl'     => $wx_user['headimgurl'],
				'subscribe_time' => $wx_user['subscribe_time'],
				'dateline'		 => time(),
			), 'UPDATE', 'uid = ' . $_SESSION['user_id']);
		}
	}
	elseif ($act == 'other')
	{
		$uri = str_replace('/mobile/', '/', $_SERVER['REQUEST_URI']);		
		$callback = $_CFG['site_url'] . $uri;
		weixin_oauth($callback, 'INFO');
	}
}

if ($act == 'my')
{
	$user_bargain_id = check_user_bargain($bargain_id, $_SESSION['user_id']);
	if (empty($user_bargain_id))
	{
		if ($wx_user['subscribe'] == 1)
		{
			// 创建活动
			$user_bargain_id = create_user_bargain($bargain_id, $_SESSION['user_id']);
			if (empty($user_bargain_id))
			{
				show_message('砍价活动不存在', '返回首页', 'index.php', 'error');
			}
			
			$bargain = get_user_bargain_detail($user_bargain_id);
		}
		else
		{
			// 未关注公众号不能参加活动，只获取基本信息
			$bargain = get_bargain_info($bargain_id);
			if (empty($bargain))
			{
				show_message('砍价活动不存在', '返回首页', 'index.php', 'error');
			}
		}
	}
	else 
	{
		$bargain = get_user_bargain_detail($user_bargain_id);
	}
}
elseif ($act == 'other')
{
	$bargain = get_user_bargain_detail($user_bargain_id);
	if (empty($bargain))
	{
		show_message('砍价活动不存在', '返回首页', 'index.php', 'error');
	}
	
	if ($bargain['user_id'] == $_SESSION['user_id'])
	{
		// 通过别人的链接进入自己的活动页面
		$act = 'my';
	}
}
elseif ($act == 'done')
{
	// 检查活动
	$user_bargain_id = check_user_bargain($bargain_id, $_SESSION['user_id']);
	if (empty($user_bargain_id))
	{
		show_message('您还没有参加当前砍价活动', '参加活动', 'bargain.php?id=' . $bargain_id, 'error');
	}
	
	$bargain = get_user_bargain_detail($user_bargain_id);
	
	// 检查活动状态
	if ($bargain['user_bargain_status'] == 2)
	{
		$sql = "SELECT order_id FROM " . $ecs->table('order_info') . " WHERE user_id = $_SESSION[user_id] AND extension_code = 'bargain' AND extension_id = $bargain_id";
		$order_id = $db->getOne($sql);
		show_message('您已经参与过这个活动了', '查看订单', 'user.php?act=order_detail&order_id=' . $order_id, 'error');
	}
	elseif ($bargain['user_bargain_status'] == 3)
	{
		show_message('当前活动已过期', '返回首页', 'index.php', 'error');
	}
	
	// 清空购物车中所有拍卖商品 
	include_once(ROOT_PATH . 'include/lib_order.php');
	clear_cart(CART_BARGAIN_GOODS);
	
	// 加入购物车
	$cart = array(
		'user_id'        => $_SESSION['user_id'],
		'session_id'     => SESS_ID,
		'goods_id'       => $bargain['goods_id'],
		'goods_sn'       => addslashes($bargain['goods_sn']),
		'goods_name'     => addslashes($bargain['goods_name']),
		'market_price'   => $bargain['shop_price'],
		'goods_price'    => $bargain['shop_price'],
		'goods_number'   => 1,
		'goods_attr'     => $bargain['amount_desc'],
		'is_real'        => 1,
		'extension_code' => 'bargain',
		'extension_id'   => $bargain_id,
		'rec_type'       => CART_BARGAIN_GOODS,
		'is_gift'        => 0,
		'add_time'       => time(),
	);
	$db->autoExecute($ecs->table('cart'), $cart, 'INSERT');
	
	$_SESSION['extension_code'] = 'bargain';
	$_SESSION['extension_id']   = $bargain_id;
	
	// 跳转到订单确认页面
	ecs_header("Location: ./flow.php?step=checkout&flow_type=" . CART_BARGAIN_GOODS . "\n");
	exit;
}

// 获取当前用户的砍价结果
if (!empty($bargain['detail']))
{
	foreach ($bargain['detail'] as &$item)
	{
		$item['formated_price'] = abs($item['price']);
		if ($item['friend_id'] == $_SESSION['user_id'])
		{
			$bargain['my_bargain_price'] = $item['price'];
		}
	}
}

$bargain['new_price'] = $bargain['shop_price'] - $bargain['bargain_price'];

$smarty->assign('bargain', $bargain); // 砍价活动
$smarty->assign('user',    $wx_user); // 当前用户
$smarty->assign('lang',    $_LANG);
$smarty->assign('config',  $_CFG);
$smarty->assign('act',     $act);
$smarty->display('bargain.dwt');


/**
 * 检查用户是否已参加指定的砍价活动
 * 
 * @param integer $bargain_id
 * @param integer $user_id
 * 
 * @return integer user_bargain_id
 */
function check_user_bargain($bargain_id, $user_id)
{
	global $ecs, $db;
	
	$sql = "SELECT id FROM " . $ecs->table('user_bargain') . " WHERE bargain_id = {$bargain_id} AND $user_id = {$user_id}";
	$user_bargain_id = $db->getOne($sql);
	
	return $user_bargain_id;
}


/**
 * 获取用户参与的砍价活动详情
 *
 * @param integer $user_bargain_id
 */
function get_user_bargain_detail($user_bargain_id)
{
	global $ecs, $db;
	
	$sql = "SELECT b.*, g.goods_name, g.goods_sn, g.goods_thumb, g.shop_price, g.amount_desc, " . 
			"u.id AS user_bargain_id,u.user_id, u.bargain_id, u.bargain_price, u.status AS user_bargain_status FROM " .
			$ecs->table('goods', 'g') . ','  . $ecs->table('bargain_goods', 'b')  . ',' . $ecs->table('user_bargain', 'u') .
			" WHERE u.bargain_id = b.id AND b.goods_id = g.goods_id AND u.id = {$user_bargain_id}";
	$bargain = $db->getRow($sql);
	if (empty($bargain))
	{
		return array();
	}

	$sql = "SELECT d.friend_id, d.price, u.nickname, u.headimgurl FROM " .
			$ecs->table('user_bargain', 'b') . ',' . $ecs->table('user_bargain_detail', 'd') . ', wxch_user AS u' .
			" WHERE b.bargain_id = d.bargain_id AND b.user_id = d.user_id AND d.friend_id = u.uid " .
			" AND b.user_id = {$bargain[user_id]} AND b.bargain_id = {$bargain[bargain_id]} ORDER BY d.add_time ASC";
	$list = $db->getAll($sql);
	if (!empty($list)) {
		$bargain['detail'] = $list;
	}

	$bargain['shop_price_formated']    = price_format($bargain['shop_price']);
	$bargain['bargain_price_formated'] = price_format($bargain['bargain_price']);
	$bargain['thumb'] = get_image_path($bargain['goods_id'], $bargain['goods_thumb'], true);
	$bargain['goods_idurl']   = build_uri('goods', array('gid' => $bargain['goods_id']), $bargain['goods_name']);
	
	return $bargain;
}


/**
 * 获取砍价活动基本信息
 * 
 * @param integer $bargain_id
 * @return array
 */
function get_bargain_info($bargain_id)
{
	global $ecs, $db;
	
	$sql = "SELECT b.*, g.goods_name, g.goods_thumb, g.shop_price, g.amount_desc FROM " .
			$ecs->table('goods', 'g') . ','  . $ecs->table('bargain_goods', 'b') .
			" WHERE b.goods_id = g.goods_id AND b.id = {$bargain_id}";
	$bargain = $db->getRow($sql);
	
	if (!empty($bargain))
	{
		$bargain['shop_price_formated']    = price_format($bargain['shop_price']);
		$bargain['bargain_price_formated'] = price_format($bargain['bargain_price']);
		$bargain['thumb'] = get_image_path($bargain['goods_id'], $bargain['goods_thumb'], true);
		$bargain['goods_idurl']   = build_uri('goods', array('gid' => $bargain['goods_id']), $bargain['goods_name']);
	}
	
	return $bargain;
}

/**
 * 创建用户参与砍价活动
 * 
 * @param integer $bargain_id
 * @param integer $user_id
 */
function create_user_bargain($bargain_id, $user_id)
{
	global $db, $ecs;
	
	$data = array(
		'bargain_id' => $bargain_id,
		'user_id'    => $user_id,
		'add_time'   => time(),
	);
	
	$db->autoExecute($ecs->table('user_bargain'), $data);
	
	return $db->insert_id();
}


function ajax_error($msg, $code = 1)
{
	echo json_encode(array('errcode' => $code, 'msg' => $msg));
	exit;
}
?>