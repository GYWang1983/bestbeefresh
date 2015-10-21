<?php

/**
 * ECSHOP 接收js日志接口
 * ============================================================================
 * * 版权所有 2015-2015 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
 */

define('IN_ECTOUCH', true);
define('INIT_NO_USERS', true);
define('INIT_NO_SMARTY', true);

require(dirname(__FILE__) . '/include/init.php');

$type = trim($_REQUEST['type']);
if (empty($type)) exit;

insert_error_log($type, $_POST['info'], trim($_POST['file']));
?>