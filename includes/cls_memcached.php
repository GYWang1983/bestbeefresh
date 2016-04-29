<?php

if (!defined('IN_ECS'))
{
	die('Hacking attempt');
}

class cls_memcached {
	
	var $host = null;
	var $port = null;
	var $prefix = null;
	
	var $connection = null;
	
	function __construct($host, $port, $prefix = '', $user = NULL, $pwd = NULL) {
		$this->host = $host;
		$this->port = $port;
		$this->prefix = $prefix;
	}
	
	
	public function set($key, $value, $expire = 0) {
		$this->connect();
		$this->connection->set($this->prefix . $key, $value, $expire);
	} 
	
	public function get($key) {
		$this->connect();
		$this->connection->get($this->prefix . $key);
	}	
	
	private function connect() {
		if (empty($this->connection)) {
			$this->connection = new Memcached;  //声明一个新的memcached链接
			$this->connection->setOption(Memcached::OPT_COMPRESSION, false); //关闭压缩功能
			$this->connection->setOption(Memcached::OPT_BINARY_PROTOCOL, true); //使用binary二进制协议
			$this->connection->addServer($this->host, $this->port); //添加OCS实例地址及端口号
		}
	}
}