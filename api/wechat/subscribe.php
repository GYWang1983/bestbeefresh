<?php
namespace Api\Wechat;
use Api\Wechat\event;

class subscribe extends event {

	/**
	 * 处理微信事件
	 */
	protected function handle() {
		global $db, $ecs;
		
		require_once(ROOT_PATH . 'includes/lib_passport.php');
		
		$allow_agent = false;
		
		$user_info = $db->getRow("SELECT * FROM `wxch_user` WHERE `wxid` = '" . $this->openid . "'");
		
		if (empty($user_info)) {
			$user_id = register_openid($this->openid, TRUE);
			echo "user_id=$user_id";
			$allow_agent = true;
		} elseif ($user_info['subscribe'] == 0) {
			$db->query("UPDATE `wxch_user` SET subscribe = 1, subscribe_time = " . time() . " WHERE `wxid` = '" . $this->openid . "'");
			$user_id = $user_info['uid'];
			$allow_agent = true;
		} elseif ($user_info['subscribe'] == 2) {
			$db->query("UPDATE `wxch_user` SET subscribe = 1 WHERE `wxid` = '" . $this->openid . "'");
			$user_id = $user_info['uid'];
		}
		
		if (!empty($this->data['EventKey']) && $allow_agent) {
			$qrscene = substr($this->data['EventKey'], 8);
			$agent = $db->getOne("SELECT user_id FROM " . $ecs->table('promotion_agent') . " WHERE wx_qrcode = " . intval($qrscene));
			if (!empty($agent)) {
				$sql = "UPDATE " . $ecs->table('users') . " SET parent_id = $agent WHERE user_id = $user_id";
				$db->query($sql);
			}
		}
	}
}