<?php

/**
 * ECSHOP 定期刷新微信token
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/refresh_wxtoken.php';
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
    $modules[$i]['desc']    = 'refresh_wxtoken_desc';

    /* 作者 */
    $modules[$i]['author']  = 'gy.wang';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '0.1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'minuts_interval', 'type' => 'text', 'value' => ''),
    );

    return;
}


$wx = $db->getRow("SELECT * FROM `wxch_config` WHERE `id`=1");
if (!empty($wx) && $wx['dateline'] <= $timestamp) {
	
	$tk = get_access_token($wx['appid'], $wx['appsecret']);
	
	if (!empty($tk)) {
		
		$jsticket = get_jsapi_ticket($tk['access_token']);
		$expire = $timestamp + intval($tk['expires_in']) - 900;
		
		$db->query("UPDATE `wxch_config` SET access_token='{$tk[access_token]}', jsapi_token='{$jsticket}',dateline='$expire' WHERE `id`=1");
	
		//write to cache file
		write_static_cache('wxtoken', array(
			'appid'        => $wx['appid'],
			'appsecret'    => $wx['appsecret'],
			'access_token' => $tk['access_token'],
			'jsapi_token'  => $jsticket,
			'expire'       => $expire,
		));
		
		// copy to mobile dir
		$src  = ROOT_PATH . '/temp/static_caches/wxtoken.php';
		$dest = ROOT_PATH . '/mobile/data/static_caches/wxtoken.php';
		@copy($src, $dest);
	}
	
}

function get_access_token($appid, $secret, $retry = 0) {

	$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
	$json = file_get_contents($url);
	$rs = json_decode($json, TRUE);

	if (!empty($rs) && empty($rs['errcode'])) {
		return $rs;
	}

	if ($rs['errcode'] == -1) {
			
		if ($retry < 5) {
			sleep(10);
			return get_access_token($appid, $secret, $retry + 1);
		}
		//outlog("ERROR: Refresh access token failed with retry $retry times. $rs[errcode]($rs[errmsg])");
			
	} else {
		//outlog("ERROR: $rs[errmsg]. (CODE=$rs[errcode])");
	}

	return 0;
}


function get_jsapi_ticket($acctoken) {

	$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token={$acctoken}";
	$json = file_get_contents($url);
	$rs = json_decode($json, TRUE);
	//outlog('INFO: Get js ticket result:' . $json);

	if (!empty($rs) && empty($rs['errcode'])) {
		return $rs['ticket'];
	} else {
		//outlog("ERROR: Refresh jsapi ticket failed. $rs[errcode]($rs[errmsg])");
		return null;
	}
}
?>