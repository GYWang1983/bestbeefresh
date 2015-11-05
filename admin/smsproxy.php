<?php

/**
 * ECSHOP 短信平台接入管理程序
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

$exc = new exchange($ecs->table('sms_proxy'), $db, 'proxy_code', 'proxy_name');

/*------------------------------------------------------ */
//-- 短信平台列表 ?act=list
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
	admin_priv('sms_proxy');
	
    /* 查询数据库中启用的短信平台 */
    $proxy_list = array();
    $sql = "SELECT * FROM " . $ecs->table('sms_proxy') . " WHERE enabled = '1' ORDER BY proxy_order";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        $proxy_list[$row['proxy_code']] = $row;
    }

    /* 取得插件文件中的短信平台 */
    $modules = read_modules('../includes/modules/sms');
    for ($i = 0; $i < count($modules); $i++)
    {
        $code = $modules[$i]['code'];
        $modules[$i]['proxy_code'] = $modules[$i]['code'];
        /* 如果数据库中有，取数据库中的名称和描述 */
        if (isset($proxy_list[$code]))
        {
            $modules[$i]['name'] = $proxy_list[$code]['proxy_name'];
            $modules[$i]['desc'] = $proxy_list[$code]['proxy_desc'];
            $modules[$i]['proxy_order'] = $proxy_list[$code]['proxy_order'];
            $modules[$i]['is_text']  = $proxy_list[$code]['is_text'];
            $modules[$i]['is_voice'] = $proxy_list[$code]['is_voice'];
            $modules[$i]['install']  = '1';
        }
        else
        {
            $modules[$i]['name'] = $_LANG[$modules[$i]['code']];
            $modules[$i]['desc'] = $_LANG[$modules[$i]['desc']];
            $modules[$i]['is_text']  = $_LANG[$modules[$i]['is_text']];
            $modules[$i]['is_voice'] = $_LANG[$modules[$i]['is_voice']];
            $modules[$i]['install']  = '0';
        }
    }

    include_once(ROOT_PATH.'includes/lib_compositor.php');
    assign_query_info();

    $smarty->assign('ur_here', $_LANG['09_smsproxy_list']);
    $smarty->assign('modules', $modules);
    $smarty->display('smsproxy_list.htm');
}
/*------------------------------------------------------ */
//-- 安装短信平台 ?act=install&code=".$code."
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'install')
{
    admin_priv('sms_proxy');

    /* 取相应插件信息 */
    $set_modules = true;
    include_once(ROOT_PATH.'includes/modules/sms/' . $_REQUEST['code'] . '.php');

    $data = $modules[0];

    $proxy['proxy_code']  = $data['code'];
    $proxy['proxy_name']  = $_LANG[$data['code']];
    $proxy['proxy_desc']  = $_LANG[$data['desc']];
    $proxy['is_text']     = $data['is_text'];
    $proxy['is_voice']    = $data['is_voice'];
    $proxy['proxy_config']  = array();

    foreach ($data['config'] AS $key => $value)
    {
        $config_desc = (isset($_LANG[$value['name'] . '_desc'])) ? $_LANG[$value['name'] . '_desc'] : '';
        $proxy['proxy_config'][$key] = $value +
            array('label' => $_LANG[$value['name']], 'value' => $value['value'], 'desc' => $config_desc);

        if ($proxy['proxy_config'][$key]['type'] == 'select' ||
            $proxy['proxy_config'][$key]['type'] == 'radiobox')
        {
            $proxy['proxy_config'][$key]['range'] = $_LANG[$proxy['proxy_config'][$key]['name'] . '_range'];
        }
    }

    assign_query_info();

    $smarty->assign('action_link',  array('text' => $_LANG['09_smsproxy_list'], 'href' => 'smsproxy.php?act=list'));
    $smarty->assign('proxy', $proxy);
    $smarty->display('smsproxy_edit.htm');
}

elseif ($_REQUEST['act'] == 'get_config')
{
    check_authz_json('sms_proxy');

    $code = $_REQUEST['code'];

    /* 取相应插件信息 */
    $set_modules = true;
    include_once(ROOT_PATH.'includes/modules/sms/' . $code . '.php');
    $data = $modules[0]['config'];
    $config = '<table>';
    $range = '';
    foreach($data AS $key => $value)
    {
        $config .= "<tr><td width=80><span class='label'>";
        $config .= $_LANG[$data[$key]['name']];
        $config .= "</span></td>";
        if($data[$key]['type'] == 'text')
        {
            $config .= "<td><input name='cfg_value[]' type='text' value='" . $data[$key]['value'] . "' /></td>";
        }
        elseif($data[$key]['type'] == 'select')
        {
            $range = $_LANG[$data[$key]['name'] . '_range'];
            $config .= "<td><select name='cfg_value[]'>";
            foreach($range AS $index => $val)
            {
                $config .= "<option value='$index'>" . $range[$index] . "</option>";
            }
            $config .= "</select></td>";
        }
        $config .= "</tr>";
        //$config .= '<br />';
        $config .= "<input name='cfg_name[]' type='hidden' value='" .$data[$key]['name'] . "' />";
        $config .= "<input name='cfg_type[]' type='hidden' value='" .$data[$key]['type'] . "' />";
        $config .= "<input name='cfg_lang[]' type='hidden' value='" .$data[$key]['lang'] . "' />";
    }
    $config .= '</table>';

    make_json_result($config);
}
/*------------------------------------------------------ */
//-- 编辑短信平台 ?act=edit&code={$code}
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{
    admin_priv('sms_proxy');

    /* 查询该短信平台内容 */
    if (isset($_REQUEST['code']))
    {
        $_REQUEST['code'] = trim($_REQUEST['code']);
    }
    else
    {
        die('invalid parameter');
    }

    $sql = "SELECT * FROM " . $ecs->table('sms_proxy') . " WHERE proxy_code = '$_REQUEST[code]' AND enabled = '1'";
    $proxy = $db->getRow($sql);
    if (empty($proxy))
    {
        $links[] = array('text' => $_LANG['back_list'], 'href' => 'smsproxy.php?act=list');
        sys_msg($_LANG['payment_not_available'], 0, $links);
    }

    /* 取相应插件信息 */
    $set_modules = true;
    include_once(ROOT_PATH . 'includes/modules/sms/' . $_REQUEST['code'] . '.php');
    $data = $modules[0];

    /* 取得配置信息 */
    if (is_string($proxy['proxy_config']))
    {
        $store = unserialize($proxy['proxy_config']);
        /* 取出已经设置属性的code */
        $code_list = array();
        foreach ($store as $key=>$value)
        {
            $code_list[$value['name']] = $value['value'];
        }
        $proxy['proxy_config'] = array();

        /* 循环插件中所有属性 */
        foreach ($data['config'] as $key => $value)
        {
            $proxy['proxy_config'][$key]['desc'] = (isset($_LANG[$value['name'] . '_desc'])) ? $_LANG[$value['name'] . '_desc'] : '';
            $proxy['proxy_config'][$key]['label'] = $_LANG[$value['name']];
            $proxy['proxy_config'][$key]['name'] = $value['name'];
            $proxy['proxy_config'][$key]['type'] = $value['type'];

            if (isset($code_list[$value['name']]))
            {
                $proxy['proxy_config'][$key]['value'] = $code_list[$value['name']];
            }
            else
            {
                $proxy['proxy_config'][$key]['value'] = $value['value'];
            }

            if ($proxy['proxy_config'][$key]['type'] == 'select' ||
                $proxy['proxy_config'][$key]['type'] == 'radiobox')
            {
                $proxy['proxy_config'][$key]['range'] = $_LANG[$proxy['proxy_config'][$key]['name'] . '_range'];
            }
        }

    }

    assign_query_info();

    $smarty->assign('action_link',  array('text' => $_LANG['09_smsproxy_list'], 'href' => 'smsproxy.php?act=list'));
    $smarty->assign('ur_here', $_LANG['edit'] . $_LANG['smsproxy']);
    $smarty->assign('proxy', $proxy);
    $smarty->display('smsproxy_edit.htm');
}

/*------------------------------------------------------ */
//-- 提交短信平台 post
/*------------------------------------------------------ */
elseif (isset($_POST['Submit']))
{
    admin_priv('sms_proxy');

    /* 检查输入 */
    if (empty($_POST['proxy_name']))
    {
        sys_msg($_LANG['proxy_name'] . $_LANG['empty']);
    }

    $sql = "SELECT COUNT(*) FROM " . $ecs->table('sms_proxy') .
            " WHERE proxy_name = '$_POST[proxy_name]' AND proxy_code <> '$_POST[proxy_code]'";
    if ($db->GetOne($sql) > 0)
    {
        sys_msg($_LANG['proxy_name'] . $_LANG['repeat'], 1);
    }

    /* 取得配置信息 */
    $proxy_config = array();
    if (isset($_POST['cfg_value']) && is_array($_POST['cfg_value']))
    {
        for ($i = 0; $i < count($_POST['cfg_value']); $i++)
        {
            $proxy_config[] = array('name'  => trim($_POST['cfg_name'][$i]),
                                  'type'  => trim($_POST['cfg_type'][$i]),
                                  'value' => trim($_POST['cfg_value'][$i])
            );
        }
    }
    $proxy_config = serialize($proxy_config);

    /* 检查是编辑还是安装 */
    $link[] = array('text' => $_LANG['back_list'], 'href' => 'smsproxy.php?act=list');
    if ($_POST['proxy_id'])
    {
        /* 编辑 */
        $sql = "UPDATE " . $ecs->table('sms_proxy') .
               "SET proxy_name = '$_POST[proxy_name]'," .
               "    proxy_desc = '$_POST[proxy_desc]'," .
               "    proxy_config = '$proxy_config', " .
               "    is_text    =  '$_POST[is_text]', ".
               "    is_voice   =  '$_POST[is_voice]' ".
               "WHERE proxy_code = '$_POST[proxy_code]' LIMIT 1";
        $db->query($sql);

        /* 记录日志 */
        admin_log($_POST['proxy_name'], 'edit', 'smsproxy');

        sys_msg($_LANG['edit_ok'], 0, $link);
    }
    else
    {
        /* 安装，检查该短信平台是否曾经安装过 */
        $sql = "SELECT COUNT(*) FROM " . $ecs->table('sms_proxy') . " WHERE proxy_code = '$_REQUEST[proxy_code]'";
        if ($db->GetOne($sql) > 0)
        {
            /* 该短信平台已经安装过, 将该短信平台的状态设置为 enable */
            $sql = "UPDATE " . $ecs->table('sms_proxy') .
                   "SET proxy_name = '$_POST[proxy_name]'," .
                   "    proxy_desc = '$_POST[proxy_desc]'," .
                   "    proxy_config = '$proxy_config'," .
                   "    is_text    =  '$_POST[is_text]', ".
               	   "    is_voice   =  '$_POST[is_voice]', ".
                   "    enabled = '1' " .
                   "WHERE proxy_code = '$_POST[proxy_code]' LIMIT 1";
            $db->query($sql);
        }
        else
        {
            /* 该短信平台没有安装过, 将该短信平台的信息添加到数据库 */
            $sql = "INSERT INTO " . $ecs->table('sms_proxy') . " (proxy_code, proxy_name, proxy_desc, proxy_config, is_text, is_voice, enabled)" .
                   "VALUES ('$_POST[proxy_code]', '$_POST[proxy_name]', '$_POST[proxy_desc]', '$proxy_config', '$_POST[is_text]', 'is_voice', 1)";
            $db->query($sql);
        }

        /* 记录日志 */
        admin_log($_POST['proxy_name'], 'install', 'smsproxy');

        sys_msg($_LANG['install_ok'], 0, $link);
    }
}

/*------------------------------------------------------ */
//-- 卸载短信平台 ?act=uninstall&code={$code}
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'uninstall')
{
    admin_priv('sms_proxy');

    /* 把 enabled 设为 0 */
    $sql = "UPDATE " . $ecs->table('sms_proxy') .
           "SET enabled = '0' " .
           "WHERE proxy_code = '$_REQUEST[code]' LIMIT 1";
    $db->query($sql);

    /* 记录日志 */
    admin_log($_REQUEST['code'], 'uninstall', 'smsproxy');

    $link[] = array('text' => $_LANG['back_list'], 'href' => 'smsproxy.php?act=list');
    sys_msg($_LANG['uninstall_ok'], 0, $link);
}
/*------------------------------------------------------ */
//-- 修改短信平台名称
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_name')
{
    /* 检查权限 */
    check_authz_json('sms_proxy');

    /* 取得参数 */
    $code = json_str_iconv(trim($_POST['id']));
    $name = json_str_iconv(trim($_POST['val']));

    /* 检查名称是否为空 */
    if (empty($name))
    {
        make_json_error($_LANG['name_is_null']);
    }

    /* 检查名称是否重复 */
    if (!$exc->is_only('proxy_name', $name, $code))
    {
        make_json_error($_LANG['name_exists']);
    }

    /* 更新短信平台名称 */
    $exc->edit("proxy_name = '$name'", $code);
    make_json_result(stripcslashes($name));
}

/*------------------------------------------------------ */
//-- 修改短信平台描述
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'edit_desc')
{
    /* 检查权限 */
    check_authz_json('sms_proxy');

    /* 取得参数 */
    $code = json_str_iconv(trim($_POST['id']));
    $desc = json_str_iconv(trim($_POST['val']));

    /* 更新描述 */
    $exc->edit("proxy_desc = '$desc'", $code);
    make_json_result(stripcslashes($desc));
}
/*------------------------------------------------------ */
//-- 修改短信平台排序
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_order')
{
    /* 检查权限 */
    check_authz_json('sms_proxy');

    /* 取得参数 */
    $code = json_str_iconv(trim($_POST['id']));
    $order = intval($_POST['val']);

    /* 更新排序 */
    $exc->edit("proxy_order = '$order'", $code);
    make_json_result(stripcslashes($order));
}
?>
