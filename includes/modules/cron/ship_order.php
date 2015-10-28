<?php

/**
 * ECSHOP 自动发货订单
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/ship_order.php';
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
    $modules[$i]['desc']    = 'ship_order_desc';

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

$sql = "UPDATE " . $ecs->table('order_info') . " SET shipping_status=" . SS_SHIPPED . ", shipping_time=" . time() . 
" WHERE shipping_status=" . SS_PREPARING;
$db->query($sql);

?>