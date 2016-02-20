<?php

/**
 * ECSHOP 优惠券过期提醒
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/bonus_remind.php';
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
    $modules[$i]['desc']    = 'bonus_remind_desc';

    /* 作者 */
    $modules[$i]['author']  = 'gy.wang';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '0.1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'lead_days', 'type' => 'text', 'value' => '1'),
    );

    return;
}

$lead_days = intval($cron['lead_days']);
$lead_days = $lead_days > 0 ?: 1;

// 计算到期时间点
$add_day_str = "+{$lead_days} days";
$t = strtotime($add_day_str);
$date = date('Y-m-d', $t);
$time = strtotime($date . ' 23:59:59');

$sql = "SELECT b.user_id, sum(b.amount) AS amount, count(b.bonus_id) as cnt, u.wxid FROM " . 
	   $ecs->table('user_bonus', b) . ", wxch_user AS u " .
	   " WHERE b.user_id = u.uid AND b.order_id = 0 AND b.expire_time <= {$time}" . 
	   " GROUP BY b.user_id ";
$query = $db->query($sql);

$link = $_CFG['site_url'] . '/mobile/user.php?act=bonus';
$tmplid = 'n3Pmjdmez5JmaEUCAHEHujfj8BgqMXNkO5FGfAhHACM';

$param = array(
	'first'    => array('color' => '#000000'),
	'keynote1' => array('color' => '#000000'),
	'keynote2' => array('color' => '#000000'),
	"remark"   => array('color' => '#0000ff', 'value' => "\n点击查看我的优惠券。\n如有任何问题可致电客服咨询：{$_CFG[service_phone]}"),
);

include_once(ROOT_PATH . '/includes/cls_wechat.php');
$wechat = new WechatApi();

while ($rs = $db->fetch_array($query))
{
	$param['first']['value'] = "您有{$rs[cnt]}张优惠券明天即将过期，请在过期前使用。";
	$param['keynote1']['value'] = "共计{$rs[amount]}元优惠券";
	$param['keynote2']['value'] = date('Y年n月j日', $time);
	$result = $wechat->send_template_msg($rs['wxid'], $tmplid, $param, $link);
}
?>