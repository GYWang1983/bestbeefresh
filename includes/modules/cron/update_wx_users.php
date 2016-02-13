<?php

/**
 * ECSHOP 刷新微信用户信息
 */
if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/update_wx_users.php';
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
    $modules[$i]['desc']    = 'update_wx_users_desc';

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

set_time_limit(0);
require_once(ROOT_PATH . 'includes/cls_wechat.php');
$wechat = new WechatApi();

// 检查是否存在昵称和头像
$rs = $db->getAll("SELECT uid, wxid FROM wxch_user");
foreach ($rs as &$user)
{
	$wx_user = $wechat->get_user_info($user['wxid']);
	if (!empty($wx_user))
	{
		if ($wx_user['subscribe'] == 1)
		{
			$data = array (
				'subscribe' => 1,
				'nickname'  => $wx_user['nickname'],
				'sex'       => $wx_user['sex'],
				'city'      => $wx_user['city'],
				'country'   => $wx_user['country'],
				'province'  => $wx_user['province'],
				'language'  => $wx_user['language'],
				'headimgurl'     => $wx_user['headimgurl'],
				'subscribe_time' => $wx_user['subscribe_time'],
				'dateline'  => time(),
			);
		}
		else
		{
			$data = array (
				'subscribe' => 0,
				'dateline'  => time(),
			);
		}
		// 更新微信用户数据
		$db->autoExecute('wxch_user', $data, 'UPDATE', 'uid = ' . $user['uid']);
	}
}

?>