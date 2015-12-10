<?php
/**
 * ECSHOP 管理中心打包管理
 * ============================================================================
 * * 版权所有 2015-2015 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
$_REQUEST['act'] = empty($_REQUEST['act']) ? 'list' : $_REQUEST['act'];

/*------------------------------------------------------ */
//-- 供货列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    /* 检查权限 */
    admin_priv('pickup_pack');

    /* 查询 */
    $result = pickup_pack_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['pickup_pack_list']); // 当前导航
    //$smarty->assign('action_link', array('href' => 'supply_goods.php?act=add', 'text' => $_LANG['add_supply_goods']));

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('pickup_pack_list', $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_asc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('pickup_pack_list.htm');
}
/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('pickup_pack');

    $result = pickup_pack_list();

    $smarty->assign('pickup_pack_list', $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('pickup_pack_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}
/*------------------------------------------------------ */
//-- 打印分拣单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'print_shipping')
{
	$smarty->display('print_shipping.htm');
}
/*------------------------------------------------------ */
//-- 打印包裹标签
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'print_label')
{
	$print_date = empty($_REQUEST['print_date']) ? date('Ymd', time()) : trim($_REQUEST['print_date']);
	
	$sql = "SELECT p.id, p.pos_row, p.pos_sn, u.mobile_phone FROM " . 
			$ecs->table('pickup_pack', 'p') . ', ' . $ecs->table('users', 'u') .
			" WHERE p.user_id = u.user_id AND create_date = '$print_date'" .
			" ORDER BY p.user_id ASC";
	$rs = $db->getAll($sql);
	//var_dump($rs);
	
	// for test
	/*for ($i = 0; $i < 50; $i++) {
		$rs[] = $rs[1];
	}*/
	
	$smarty->assign('rs', $rs);
	$smarty->assign('total_num', count($rs));
	$smarty->assign('date', substr($print_date, 6, 2));
	$smarty->assign('config', $_CFG);
	$smarty->display('print_label.htm');
}


/**
 *  获取包裹列表信息
 *
 * @return array
 */
function pickup_pack_list()
{
	global $db, $ecs, $_LANG;
	
    $result = get_filter();
    if ($result === false)
    {
        /* 过滤信息 */
    	$filter['create_date'] = empty($_REQUEST['create_date']) ? date('Ymd', time()) : trim($_REQUEST['create_date']);
    	$filter['mobile_phone'] = empty($_REQUEST['mobile_phone']) ? '' : trim($_REQUEST['mobile_phone']);
    	$filter['status'] = empty($_REQUEST['status']) ? '' : trim($_REQUEST['status']);
    	
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);

        $where = '';
        if (!empty($filter['create_date']))
        {
        	$where .= " AND p.create_date = '$filter[create_date]'";
        }
        if (!empty($filter['mobile_phone']))
        {
        	$where .= " AND u.mobile_phone LIKE '%" . mysql_like_quote($filter['mobile_phone']) . "'";
        }
        if (!empty($filter['status']))
        {
        	$where .= " AND p.status = '$filter[status]'";
        }
        
        /* 记录总数 */
        $sql = "SELECT count(*) FROM " . $ecs->table('pickup_pack', 'p') . ', ' . $ecs->table('users', 'u') .
        	" WHERE p.user_id = u.user_id " . $where;
        $filter['record_count']   = $db->getOne($sql);
        
        /* 分页大小 */
        $filter = page_and_size($filter);

        /* 查询 */
        $sql = "SELECT p.*, u.mobile_phone, count(o.order_id) AS order_num FROM " . 
        	$ecs->table('pickup_pack', 'p') . ', ' . $ecs->table('order_info', 'o') . ', ' . $ecs->table('users', 'u') .
        	" WHERE p.id = o.package_id AND p.user_id = u.user_id " . $where .
        	" GROUP BY o.package_id " .
        	" ORDER BY p.$filter[sort_by] $filter[sort_order]" .
            " LIMIT $filter[start], $filter[page_size]";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $db->getAll($sql);
    foreach ($row as &$pack)
    {
    	$pack['create_date'] = substr($pack['create_date'], 0, 4) .'-'. substr($pack['create_date'], 4, 2) .'-'. substr($pack['create_date'], 6, 2);
    }
    
    $arr = array('result' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}
?>