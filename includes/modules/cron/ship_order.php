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

$shipping_time = time();
foreach ($rs as $order)
{
	$pickup_time = get_order_pickup_time(0, $order['confirm_time']);
	$db->autoExecute($ecs->table('order_info'), array(
		'shipping_status'  => SS_SHIPPED,
		'shipping_time'    => $shipping_time,
		'receive_deadline' => $pickup_time['end'],
	), 'UPDATE', 'order_id=' . $order['order_id']);
}

// 发送消息
$sql = "SELECT u.user_id, w.wxid, group_concat(o.order_sn) AS order_sn, count(o.order_id) AS order_count, sum(o.money_paid) AS order_amount FROM " .
	$ecs->table('order_info', 'o') . ',' . $ecs->table('users', 'u') . ', wxch_user w ' .
	" WHERE o.user_id = u.user_id AND u.user_id = w.uid AND o.order_status = " . OS_CONFIRMED . " AND o.shipping_status = " . SS_SHIPPED . " AND shipping_time = '$shipping_time' " .
	" GROUP BY o.user_id ";
$query = $db->query($sql);

include_once(ROOT_PATH . '/includes/cls_wechat.php');
$wechat = new WechatApi();
$link = $_CFG['site_url'] . '/mobile/user.php';
$tmplid = 'nJ0TLLQ7D2t9AVQPGJuThvYVQRWsfBGlGAau8-V-hb0';
$param = array(
	'first'    => array('color' => '#0000ff'),
	'keyword1' => array('color' => '#000000'),
	'keyword2' => array('color' => '#000000'),
	'keyword3' => array('color' => '#000000', 'value' => '中国药科大学江宁校区提货点'),
	"remark"   => array('color' => '#0000ff', 'value' => "为了水果新鲜，请尽快取货哦，我们将为您保存48小时。\n如有任何问题可致电客服咨询：{$_CFG[service_phone]}"),
);

while ($rs = $db->fetch_array($query))
{
	$param['first']['value'] = "最鲜蜂正在为您配送{$rs[order_count]}个水果订单，您可以于今天{$_CFG[shop_open_time]}后到{$_CFG[shop_address]}取货。";
	$param['keyword1']['value'] = $rs['order_sn'];
	$param['keyword2']['value'] = $rs['order_amount'] . '元';
	$result = $wechat->send_template_msg($rs['wxid'], $tmplid, $param, $link);
	//var_dump($result);
}
?>