<?php

/**
 * 发送短信
 * 
 * @param unknown $mobile
 * @param unknown $content
 * @param string $step
 * @param string $type
 * @return boolean
 */
function sendsms($mobile, $data, $step = 'register', $type = 'text')
{
	global $db, $ecs;
	
	$sql = "SELECT * FROM " . $ecs->table('sms_proxy') . " WHERE enabled = 1 AND is_{$type} = 1 ORDER BY proxy_order ASC";
	$rs = $db->getAll($sql); 
	
	foreach ($rs as $sms) {
		include_once(ROOT_PATH . 'include/modules/sms/' . $sms['proxy_code'] . '.php');
		$sms_obj = new $sms['proxy_code']($sms['proxy_config']);
		$method = "send_$type";
		$ret = $sms_obj->$method($mobile, $data, $step);
		if ($ret === true) {
			break;
		}
	}
	
	return $ret;
}



function ismobile($mobile)
{
	return (strlen($mobile) == 11 || strlen($mobile) == 12) && (preg_match("/^13\d{9}$/", $mobile) || preg_match("/^14\d{9}$/", $mobile) || preg_match("/^15\d{9}$/", $mobile) || preg_match("/^18\d{9}$/", $mobile) || preg_match("/^0\d{10}$/", $mobile) || preg_match("/^0\d{11}$/", $mobile));
}

/**
 * 获取6位数字验证码
 * @return string
 */
function getverifycode()
{
	$verifycode = rand(100000,999999);

	$verifycode = str_replace('1989','9819',$verifycode);
	$verifycode = str_replace('1259','9521',$verifycode);
	$verifycode = str_replace('10086','68001',$verifycode);

	return $verifycode;
}


/**
 * 检查手机验证码
 *
 * @access  public
 * @param   string       $mobile            手机号
 * @param   string       $verifycode        手机验证码
 * @param   string       $act               绑定类型
 *
 * @return  bool         $bool
 */
function check_sms_verifycode($mobile, $verifycode, $act = SMS_REGISTER)
{
	global $db, $ecs, $_CFG;
	
	$ip = real_ip();
	$expire = gmtime() - intval($_CFG['ecsdxt_sms_validtime']); //验证码10分钟内有效
	
	$SQL = "SELECT COUNT(id) FROM " . $ecs->table('verifycode') ." WHERE mobile='$mobile' AND verifycode='$verifycode' AND getip='$ip' AND status=1 AND `type`=$act AND dateline>=$expire";
	return $db->getOne($SQL) > 0;
}



?>