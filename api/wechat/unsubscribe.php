<?php
namespace Api\Wechat;
use Api\Wechat\event;

class unsubscribe extends event {

	/**
	 * 处理微信事件
	 */
	protected function handle() {
		global $db, $ecs;
		
		$db->autoExecute('wxch_user', array('subscribe'=>2, 'dateline'=>time()), 'UPDATE', "wxid='" . $this->openid . "'");
	}
}