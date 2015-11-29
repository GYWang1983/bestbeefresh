<?php
/**
 * ECSHOP 管理中心供货管理
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
    admin_priv('supply_goods');

    /* 查询 */
    $result = supply_goods_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['supply_goods_list']); // 当前导航
    $smarty->assign('action_link', array('href' => 'supply_goods.php?act=add', 'text' => $_LANG['add_supply_goods']));

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('supply_goods_list', $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_asc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('supply_goods_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('supply_goods');

    $result = supply_goods_list();

    $smarty->assign('supply_goods_list', $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('supply_goods_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}
/*------------------------------------------------------ */
//-- 列表页编辑名称
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_name')
{
    check_authz_json('supply_goods');

    $id     = intval($_POST['id']);
    $name   = json_str_iconv(trim($_POST['val']));

    /* 保存供货商信息 */
    $sql = "UPDATE " . $ecs->table('supply_goods') . " SET name = '$name' WHERE id = '$id'";
    if ($result = $db->query($sql))
    {
        //记日志
        admin_log($name, 'edit', 'supply_goods');
        clear_cache_files();
        make_json_result(stripslashes($name));
    }
    else
    {
        make_json_result(sprintf($_LANG['edit_fail'], $name));
    }
}
/*------------------------------------------------------ */
//-- 删除供货商品
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('supply_goods');

    $id = intval($_REQUEST['id']);
    $sql = "SELECT * FROM " . $ecs->table('supply_goods') . " WHERE id = '$id'";
    $supply_goods = $db->getRow($sql, TRUE);

    if (!empty($supply_goods))
    {
    	if (is_engaged($id))
    	{
    		sys_msg($_LANG['cannot_drop']);
    	}
        
    	//删除记录
        $sql = "DELETE FROM " . $ecs->table('supply_goods') . " WHERE id = '$id'";
        $db->query($sql);

        //删除关联表
        $sql = "DELETE FROM " . $ecs->table('goods_supplies') . " WHERE supply_id = '$id'";
        $db->query($sql);

        /* 记日志 */
        admin_log($supply_goods['name'], 'remove', 'supply_goods');

        /* 清除缓存 */
        clear_cache_files();
    }

    $url = 'supply_goods.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
    ecs_header("Location: $url\n");

    exit;
}
/*------------------------------------------------------ */
//-- 修改供货商状态
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'is_check')
{
    check_authz_json('supply_goods');

    $id = intval($_REQUEST['id']);
    $sql = "SELECT id, status FROM " . $ecs->table('supply_goods') . " WHERE id = '$id'";
    $supply_goods = $db->getRow($sql, TRUE);

    if (!empty($supply_goods))
    {
        $goods['status'] = empty($supply_goods['status']) ? 1 : 0;
        $db->autoExecute($ecs->table('supply_goods'), $goods, '', "id = '$id'");
        clear_cache_files();
        make_json_result($goods['status']);
    }

    exit;
}
/*------------------------------------------------------ */
//-- 批量操作
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'batch')
{
    /* 取得要操作的记录编号 */
    if (empty($_POST['checkboxes']))
    {
        sys_msg($_LANG['no_record_selected']);
    }
    else
    {
        /* 检查权限 */
        admin_priv('supply_goods');

        $ids = $_POST['checkboxes'];

        if (isset($_POST['remove']))
        {
            $sql = "SELECT * FROM " . $ecs->table('supply_goods') . " WHERE " . db_create_in($ids, 'id');
            $supply_goods = $db->getAll($sql);

            $dels = array();
            $names = array();
            foreach ($supply_goods as $key => $value)
            {
            	if (!is_engaged($value['id']))
            	{
            		$dels[] = $value['id'];
            		$names[] = $value['name'];
            	}
            }
            if (empty($dels))
            {
                sys_msg($_LANG['batch_drop_no']);
            }

            //删除记录
            $sql = "DELETE FROM " . $ecs->table('supply_goods') . " WHERE " . db_create_in($dels, 'id');
            $db->query($sql);

            //删除关联表
            $sql = "DELETE FROM " . $ecs->table('goods_supplies') . " WHERE " . db_create_in($dels, 'supply_id');;
            $db->query($sql);
            
            /* 记日志 */
            admin_log(implode(',', $names), 'remove', 'supply_goods');

            /* 清除缓存 */
            clear_cache_files();

            sys_msg($_LANG['batch_drop_ok']);
        }
    }
}
/*------------------------------------------------------ */
//-- 添加、编辑供货商
/*------------------------------------------------------ */
elseif (in_array($_REQUEST['act'], array('add', 'edit')))
{
    /* 检查权限 */
    admin_priv('supply_goods');

    if ($_REQUEST['act'] == 'add')
    {
        $supply_goods = array('cat_id' => 0);

        $smarty->assign('ur_here', $_LANG['add_supply_goods']);
        $smarty->assign('action_link', array('href' => 'supply_goods.php?act=list', 'text' => $_LANG['supply_goods_list']));
        $smarty->assign('form_action', 'insert');
    }
    elseif ($_REQUEST['act'] == 'edit')
    {
        /* 取得供货商信息 */
        $id = $_REQUEST['id'];
        $sql = "SELECT * FROM " . $ecs->table('supply_goods') . " WHERE id = '$id'";
        $supply_goods = $db->getRow($sql);
        if (empty(supply_goods))
        {
            sys_msg($_LANG['goods_not_exists']);
        }

        $smarty->assign('ur_here', $_LANG['edit_supply_goods']);
        $smarty->assign('action_link', array('href' => 'supply_goods.php?act=list', 'text' => $_LANG['supply_goods_list']));
        $smarty->assign('form_action', 'update');
    }

    $smarty->assign('supply_goods', $supply_goods);
    $smarty->assign('cat_list', cat_list(0, $supply_goods['cat_id']));
    $smarty->assign('suppliers_list', suppliers_list_name());
    
    assign_query_info();
    $smarty->display('supply_goods_info.htm');
}
/*------------------------------------------------------ */
//-- 提交添加、编辑供货商
/*------------------------------------------------------ */
elseif (in_array($_REQUEST['act'], array('insert', 'update')))
{
    /* 检查权限 */
    admin_priv('supply_goods');

    if ($_REQUEST['act'] == 'insert')
    {
        /* 提交值 */
        $supply_goods = array(
            'name'     => trim($_POST['name']),
            'cat_id'   => intval($_POST['cat_id']),
            'attr'     => trim($_POST['attr']),
        	'place'    => trim($_POST['place']),
        	'supplier' => intval($_POST['supplier']),
        	'price'    => intval($_POST['price']),
        	'unit_type'   => intval($_POST['unit_type']),
        	'unit_amount' => convert_unit_amount($_POST['unit_amount'], $_POST['unit_type']),	
        );

        $db->autoExecute($ecs->table('supply_goods'), $supply_goods, 'INSERT');

        /* 记日志 */
        admin_log($supply_goods['name'], 'add', '$supply_goods');

        /* 清除缓存 */
        clear_cache_files();

        /* 提示信息 */
        $links = array(array('href' => 'supply_goods.php?act=add',  'text' => $_LANG['continue_add_goods']),
                       array('href' => 'supply_goods.php?act=list', 'text' => $_LANG['back_supply_goods_list'])
                       );
        sys_msg($_LANG['add_ok'], 0, $links);

    }
    elseif ($_REQUEST['act'] == 'update')
    {
        /* 提交值 */
        $supply_goods = array('id' => trim($_POST['id']));

        $supply_goods['new'] = array(
            'name'     => trim($_POST['name']),
            'cat_id'   => intval($_POST['cat_id']),
            'attr'     => trim($_POST['attr']),
        	'place'    => trim($_POST['place']),
        	'supplier' => intval($_POST['supplier']),
        	'price'    => intval($_POST['price']),
        	'unit_type'   => intval($_POST['unit_type']),
        	'unit_amount' => convert_unit_amount($_POST['unit_amount'], $_POST['unit_type']),
        );

        /* 取得供货商信息 */
        $sql = "SELECT * FROM " . $ecs->table('supply_goods') . " WHERE id = '$supply_goods[id]'";
        $supply_goods['old'] = $db->getRow($sql);
        if (empty($supply_goods['old']))
        {
            sys_msg($_LANG['goods_not_exists']);
        }

        /* 保存供货商信息 */
        $db->autoExecute($ecs->table('supply_goods'), $supply_goods['new'], 'UPDATE', "id = '$supply_goods[id]'");

        /* 记日志 */
        admin_log($supply_goods['old']['name'], 'edit', 'supply_goods');

        /* 清除缓存 */
        clear_cache_files();

        /* 提示信息 */
        $links[] = array('href' => 'supply_goods.php?act=list', 'text' => $_LANG['back_supply_goods_list']);
        sys_msg($_LANG['edit_ok'], 0, $links);
    }

}

/**
 *  获取供应商品列表信息
 *
 * @return array
 */
function supply_goods_list()
{
	global $db, $ecs, $_LANG;
	
    $result = get_filter();
    if ($result === false)
    {
        /* 过滤信息 */
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);

        $where = '';

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " . $ecs->table('supply_goods') . $where;
        $filter['record_count']   = $db->getOne($sql);
        
        /* 分页大小 */
        $filter = page_and_size($filter);

        /* 查询 */
        $sql = "SELECT g.*, c.cat_name, if(g.unit_type = 1, round(g.price/g.unit_amount*1000,2), round(g.price/g.unit_amount,2)) AS unit_price FROM " . 
        	$ecs->table("supply_goods", 'g') . ', ' . $ecs->table("category", 'c') .
        	" WHERE g.cat_id = c.cat_id " . $where .
        	" ORDER BY $filter[sort_by] $filter[sort_order]" .
            " LIMIT $filter[start], $filter[page_size]";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $db->getAll($sql);
    foreach ($row as &$goods)
    {
    	$goods['unit_amount'] = convert_unit_amount($goods['unit_amount'], $goods['unit_type'], 'OUTPUT');
    	$goods['unit_price'] = $goods['unit_price'] . ' / ' . $_LANG['unit_type'][$goods['unit_type']];
    }
    
    $arr = array('result' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

/**
 * 判断供货商品是否在使用中
 * 
 * @param integer $id
 */
function is_engaged($id)
{
	//TODO:
	return false;
}

function convert_unit_amount($amount, $type, $dir = 'INPUT')
{
	if ($type == 1)
	{
		if ($dir == 'INPUT')
		{
			return floor(doubleval($amount) * 1000);
		}
		else
		{
			return $amount / 1000;
		}
	}
	else
	{
		return intval($amount);
	}
}
?>