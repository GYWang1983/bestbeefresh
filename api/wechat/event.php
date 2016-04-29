<?php
namespace Api\Wechat;

class event {
	
	protected $data = null;
	protected $openid = null;
	
	public function doEvent($input) {
		
		$this->data = $input;
		$this->openid = $input['FromUserName'];
		
		$this->handle();
	}
	
	protected function handle() {
		
	}
}