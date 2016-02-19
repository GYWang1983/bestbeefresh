<?php 

function weixin_oauth($callback, $scope = 'BASE') {
	global $db;
	
	$rs = $db->getRow("SELECT * FROM `wxch_config` WHERE `id` = 1");
	$param ['appid'] = $rs['appid'];
	
	$oauth = intval($_REQUEST['oauth']);
	if ($oauth == 0) {
		
		$param ['redirect_uri'] = $callback . (strpos($callback, '?') > 0 ? '&' : '?') . 'oauth=1';
		$param ['response_type'] = 'code';
		
		if ($scope == 'INFO')
		{
			$param ['scope'] = 'snsapi_userinfo';
		}
		else
		{
			$param ['scope'] = 'snsapi_base';
		}

		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query ( $param ) . '#wechat_redirect';
		ecs_header("Location: $url\n");
		exit;
		
	} elseif ($oauth == 1) {
		
		$param ['secret'] = $rs['appsecret'];
		$param ['code'] = $_REQUEST['code'];
		$param ['grant_type'] = 'authorization_code';
		
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query ( $param );
		$content = file_get_contents ( $url );
		$token = json_decode ( $content, true );
		
		$user_info = $db->getRow("SELECT * FROM `wxch_user` WHERE `wxid` = '{$token[openid]}'");
		if (empty($user_info)) {
			//register
			if (register_openid($token['openid'])) {
				$user_info = $GLOBALS['user']->get_user_info($user_info['openid']);
			} else {
				return false;
			}
		} else {
			//login
			$user_info = $GLOBALS['user']->get_profile_by_id($user_info['uid']);
			if (!empty($user_info) && $user_info['status'] == 1) {
		  
	            $GLOBALS['user']->set_session($user_info);
	            $GLOBALS['user']->set_cookie($user_info, TRUE);
				
	            update_user_info();      // 更新用户信息
	            update_user_cart();
	            recalculate_price();     // 重新计算购物车中的商品价格
	
	    	} else {
	    		return false;
	    	}

		}
		
		if ($token['scope'] == 'snsapi_userinfo')
		{
			$url = "https://api.weixin.qq.com/sns/userinfo?access_token={$token[access_token]}&openid={$token[openid]}&lang=zh_CN";
			$content = file_get_contents ( $url );
			$info = json_decode ( $content, true );
			
			// 更新微信用户数据
			$db->autoExecute('wxch_user', array (
					//'subscribe' => $info['subscribe'],
					'nickname'  => $info['nickname'],
					'sex'       => $info['sex'],
					'city'      => $info['city'],
					'country'   => $info['country'],
					'province'  => $info['province'],
					'language'  => $info['language'],
					'headimgurl'     => $info['headimgurl'],
					//'subscribe_time' => $info['subscribe_time'],
					'dateline'		 => time(),
			), 'UPDATE', 'uid = ' . $user_info['uid']);
		}
		
		
		$_SESSION['openid'] = $token['openid'];
		return $user_info;
	}
}
?>