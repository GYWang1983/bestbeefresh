<?php

/**
 * ECSHOP 用户中心语言项
 * ============================================================================
 * * 版权所有 2015-2016
 * 
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
 * $Id: sms.php 17217 2015-08-01 06:29:08Z wanggaoyuan $
*/

/* *
 * 错误码说明
 * 0 - 发送成功
 * 1 - 未开启手机验证码
 * 2 - 手机号不正确
 * 3 - 手机号已被占用/手机号未注册
 * 4 - 间隔时间内已获取过验证码
 * 5 - 短信发送失败
 * 6 - ip地址已被屏蔽
 * 7 - 图片验证码错误
 */

define('IN_ECTOUCH', true);

require(dirname(__FILE__) . '/include/init.php');
require(ROOT_PATH . 'include/lib_sms.php');

require_once(ROOT_PATH . 'include/cls_json.php');
require_once(ROOT_PATH . 'lang/' .$_CFG['lang']. '/sms.php');


$step_allow = array('register', 'rebind', 'getpassword', 'wxbind');

$step = empty($_REQUEST['step']) ? "" : trim($_REQUEST['step']);
if (!in_array($step, $step_allow))
{
	die('Hacking attempt');
}

$result = array('error' => 0, 'message' => '');
$json = new JSON;

/* 检查图片验证码 */
$captcha_enable =  $step == 'register'    && (intval($_CFG['captcha']) & CAPTCHA_REGISTER);
$captcha_enable |= $step == 'rebind'      && (intval($_CFG['captcha']) & CAPTCHA_REBIND);
$captcha_enable |= $step == 'wxbind'      && false;
$captcha_enable |= $step == 'getpassword' && (intval($_CFG['captcha']) & CAPTCHA_GET_PASSWORD);

if ($captcha_enable && gd_version() > 0)
{
	if (empty($_POST['captcha']))
	{
		$result['error'] = 7;
		$result['message'] = $_LANG['invalid_captcha'];
		die($json->encode($result));
	}

	/* 检查验证码 */
	include_once(ROOT_PATH . 'includes/cls_captcha.php');

	$validator = new captcha();
	if (!$validator->check_word($_POST['captcha']))
	{
		$result['error'] = 7;
		$result['message'] = $_LANG['invalid_captcha'];
		die($json->encode($result));
	}
}

$mobile = trim($_POST['mobile']);

$old_log = '';
if(file_exists("../request.log")){
	$old_log = file_get_contents("../request.log");
}
$log = "ip=".real_ip()." mobile=".$mobile." time=".date('Y-m-d H:i:s',time())."\r\n";
$new_log = $old_log.$log;
file_put_contents("../request.log",$new_log);

$denied_log = '';
if(file_exists("../denied.log")){
	$denied_log = file_get_contents("../denied.log");
}

$ip_array = explode(",", $denied_log);

if(in_array(real_ip(), $ip_array)) {
	$result['error'] = 6;
	$result['message'] = $_LANG['invalid_mobile_phone'];
	die($json->encode($result));
}

/* 提交的手机号是否正确 */
if (!ismobile($mobile))
{
	$result['error'] = 2;
	$result['message'] = $_LANG['invalid_mobile_phone'];
	die($json->encode($result));
}

$count = $db->getOne("SELECT COUNT(id) FROM " . $ecs->table('verifycode') ." WHERE getip='" . real_ip() . "' AND dateline>" . gmtime() ."-60 AND status=1");
if ($count > 50 && !stristr($denied_log, $_G['clientip']))
{
	$log = real_ip().",";
	$new_log = $denied_log.$log;
	file_put_contents("denied.log",$new_log);

	$result['error'] = 6;
	$result['message'] = $_LANG['invalid_mobile_phone'];
	die($json->encode($result));
}

/* 获取验证码请求是否获取过 */
$sql = "SELECT COUNT(id) FROM " . $ecs->table('verifycode') ." WHERE mobile='$mobile' AND status=1 AND getip='" . real_ip() . "' AND dateline>" . gmtime() ."-".$_CFG['ecsdxt_smsgap'];
if ($db->getOne($sql) > 0)
{
	$result['error'] = 4;
	$result['message'] = sprintf($_LANG['get_verifycode_excessived'], $_CFG['ecsdxt_smsgap']);
	die($json->encode($result));
}


if ($step == 'register')
{
	/* 是否开启手机短信验证注册 */
	if($_CFG['ecsdxt_mobile_reg'] == '0') {
		$result['error'] = 1;
		$result['message'] = $_LANG['ecsdxt_mobile_reg_closed'];
        die($json->encode($result));
	}

	/* 提交的手机号是否已经注册帐号 */
    if ($user->check_mobile_phone($mobile))
    {
        $result['error'] = 3;
		$result['message'] = $_LANG['mobile_phone_registered'];
        die($json->encode($result));
    }

	$verifycode = getverifycode();

    $smarty->assign('shop_name',	$_CFG['shop_name']);
    $smarty->assign('user_mobile',	$mobile);
    $smarty->assign('verify_code',  $verifycode);

    $content = $smarty->fetch('str:' . $_CFG['ecsdxt_mobile_reg_value']);
	
	/* 发送注册手机短信验证 */
	$ret = sendsms($mobile, $content);
	
	if($ret === true)
	{
		//插入获取验证码数据记录
		$sql = "INSERT INTO " . $ecs->table('verifycode') . "(mobile, getip, verifycode, dateline, `type`) VALUES ('" . $mobile . "', '" . real_ip() . "', '$verifycode', '" . gmtime() ."', 1)";
		$db->query($sql);

		$result['error'] = 0;
		$result['message'] = $_LANG['send_mobile_verifycode_successed'];
		die($json->encode($result));
	}
	else
	{
		$result['error'] = 5;
		$result['message'] = $_LANG['send_mobile_verifycode_failured'] . $ret;
		die($json->encode($result));
	}
}
elseif ($step == 'getpassword')
{
	/* 检查手机号是否已注册 */
	$user_info = $user->get_profile_by_mobile($mobile);
	if (empty($user_info))
	{
		$result['error'] = 3;
		$result['message'] = $_LANG['mobile_phone_not_registered'];
		die($json->encode($result));
	}
	
	$verifycode = getverifycode();
	
	$smarty->assign('shop_name',	$_CFG['shop_name']);
	$smarty->assign('user_mobile',	$mobile);
	$smarty->assign('verify_code',  $verifycode);
	
	$content = $smarty->fetch('str:' . $_CFG['ecsdxt_mobile_changepwd_value']);
	
	/* 发送注册手机短信验证 */
	$ret = sendsms($mobile, $content);
	
	if($ret === true)
	{
		//插入获取验证码数据记录
		$sql = "INSERT INTO " . $ecs->table('verifycode') . "(mobile, getip, verifycode, dateline, `type`) VALUES ('" . $mobile . "', '" . real_ip() . "', '$verifycode', '" . gmtime() ."', 2)";
		$db->query($sql);
		
		$result['error'] = 0;
		$result['message'] = $_LANG['send_mobile_verifycode_successed'];
		$result['uid'] = $user_info['user_id'];

		die($json->encode($result));
	}
	else
	{
		$result['error'] = 5;
		$result['message'] = $_LANG['send_mobile_verifycode_failured'] . $ret;
		die($json->encode($result));
	}
}
elseif ($step == 'rebind')
{
	/* 是否开启手机绑定 */
	if($_CFG['ecsdxt_mobile_bind'] == '0') {
		$result['error'] = 1;
		$result['message'] = $_LANG['ecsdxt_mobile_bind_closed'];
        die($json->encode($result));
	}

	/* 提交的手机号是否已经绑定帐号 */
    $sql = "SELECT COUNT(user_id) FROM " . $ecs->table('users') ." WHERE mobile_phone = '$mobile'";

    if ($db->getOne($sql) > 0)
    {
        $result['error'] = 3;
		$result['message'] = $_LANG['mobile_phone_binded'];
        die($json->encode($result));
    }

	$verifycode = getverifycode();

    $smarty->assign('shop_name',	$_CFG['shop_name']);
    $smarty->assign('user_mobile',	$mobile);
    $smarty->assign('verify_code',  $verifycode);

    $content = $smarty->fetch('str:' . $_CFG['ecsdxt_mobile_bind_value']);
	
	/* 发送注册手机短信验证 */
	$ret = sendsms($mobile, $content);
	
	if($ret === true)
	{
		//插入获取验证码数据记录
		$sql = "INSERT INTO " . $ecs->table('verifycode') . "(mobile, getip, verifycode, dateline, `type`) VALUES ('" . $mobile . "', '" . real_ip() . "', '$verifycode', '" . gmtime() ."', 3)";
		$db->query($sql);

		$result['error'] = 0;
		$result['message'] = $_LANG['bind_mobile_verifycode_successed'];
		die($json->encode($result));
	}
	else
	{
		$result['error'] = 5;
		$result['message'] = $_LANG['bind_mobile_verifycode_failured'] . $ret;
		die($json->encode($result));
	}
}
elseif ($step == 'wxbind')
{
	/* 提交的手机号是否已经绑定帐号 */
	$sql = "SELECT COUNT(user_id) FROM " . $ecs->table('users') ." a, wxch_user b WHERE ".
			" a.user_id = b.uid AND a.mobile_phone = '$mobile'";

	if ($db->getOne($sql) > 0)
	{
		$result['error'] = 3;
		$result['message'] = $_LANG['mobile_phone_binded'];
		die($json->encode($result));
	}

	$verifycode = getverifycode();

	$smarty->assign('shop_name',	$_CFG['shop_name']);
	$smarty->assign('user_mobile',	$mobile);
	$smarty->assign('verify_code',  $verifycode);

	$content = $smarty->fetch('str:' . $_CFG['ecsdxt_mobile_bind_value']);

	/* 发送注册手机短信验证 */
	$ret = sendsms($mobile, $content);

	if($ret === true)
	{
		//插入获取验证码数据记录
		$sql = "INSERT INTO " . $ecs->table('verifycode') . "(mobile, getip, verifycode, dateline, `type`) VALUES ('" . $mobile . "', '" . real_ip() . "', '$verifycode', '" . gmtime() ."', 4)";
		$db->query($sql);

		$result['error'] = 0;
		$result['message'] = $_LANG['bind_mobile_verifycode_successed'];
		die($json->encode($result));
	}
	else
	{
		$result['error'] = 5;
		$result['message'] = $_LANG['bind_mobile_verifycode_failured'] . $ret;
		die($json->encode($result));
	}
}


?>