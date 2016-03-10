<?php

/**
 * ECSHOP 自动确认订单
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/give_bonus.php';
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
    $modules[$i]['desc']    = 'give_bonus_desc';

    /* 作者 */
    $modules[$i]['author']  = 'gy.wang';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '0.1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'mind_sql', 'type' => 'textarea', 'value' => 'SELECT u.user_id, w.wxid FROM ecs_users u, wxch_user w WHERE
    u.user_id = w.uid AND u.mobile_phone IS NOT NULL AND w.subscribe = 1
    AND NOT EXISTS (SELECT 1 FROM ecs_user_bonus b WHERE u.user_id = b.user_id AND b.order_id = 0 AND b.expire_time < unix_timestamp())
    AND NOT EXISTS (SELECT 1 FROM ecs_order_info o WHERE u.user_id = o.user_id AND o.add_time > unix_timestamp() - 864000)'),
    	array('name' => 'give_bonus_id',  'type' => 'text', 'value' => ''),
    	array('name' => 'notice_message', 'type' => 'textarea', 'value' => ''),
	);

    return;
}

$now = time();
$sql = "SELECT * FROM " . $ecs->table('bonus_type') . " WHERE send_type = 0 AND type_id = " . intval($cron['give_bonus_id']);
$bonus = $db->getRow($sql);
if (!empty($bonus) && $bonus['send_start_date'] <= $now && $bonus['send_end_date'] > $now)
{	
	$expire_time = get_expire_time($bonus);
	
	$ub = array(
		'bonus_type_id' => $bonus['type_id'],
		'amount'        => $bonus['type_money'],
		'add_time'      => $now,
		'expire_time'   => $expire_time,
	);
	
	include_once(ROOT_PATH . '/includes/cls_wechat.php');
	$wechat = new WechatApi();
	$href = $_CFG['site_url'] . '/mobile/';
	$tmplid = 'FRYrwOR7gxcvFGU3a1_RIv87GamInBnW_3CrENj-sa0';

	$message = array(
		'first'    => array('color' => '#0000ff', 'value' => "您已获得一张{$bonus[type_money]}元水果优惠券。\n{$cron[notice_message]}"),
		'keyword1' => array('color' => '#000000', 'value' => $bonus['type_name']),
		'keyword2' => array('color' => '#000000', 'value' => '最鲜蜂水果订购'),
		'keyword3' => array('color' => '#000000', 'value' => date('Y-m-d', $expire_time)),
		'keyword4' => array('color' => '#000000', 'value' => '结算付款时选择优惠券即可使用'),
		"remark"   => array('color' => '#000000', 'value' => "如有任何问题可致电客服咨询：{$_CFG[service_phone]}"),
	);
	
	$query = $db->query($cron['mind_sql']);
	while($rs = $db->fetch_array($query))
	{
		$ub['user_id'] = $rs['user_id'];
		$db->autoExecute($ecs->table('user_bonus'), $ub);
		
		$wechat->send_template_msg($rs['wxid'], $tmplid, $message, $href);
	}
}


function get_expire_time($bonus)
{
	if (!empty($bonus['use_time_limit']))
	{
		$expire_time = min(time() + intval($bonus['use_time_limit']), $bonus['use_end_date']);
	}
	else
	{
		$expire_time = intval($bonus['use_end_date']);
	}
	
	return $expire_time;
}
?>