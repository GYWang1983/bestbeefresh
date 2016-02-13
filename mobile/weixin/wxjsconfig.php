<?php
define('IN_ECTOUCH', true);

require(__DIR__ . "/../data/wxtoken.php");
require(__DIR__ . "/../include/lib_base.php");

$debug = !empty($_GET['debug']) ? 'true' : 'false';

$refer = $_SERVER['HTTP_REFERER'];
if (empty($refer)) {
	quit('No HttpReferer. $refer=' . $refer);
}

$refer_param = parse_url($refer);
if (!$refer_param) {
	quit('HttpReferer error. $refer=' . $refer);
}

$host = $refer_param['host'];
if (!preg_match("/^(.*)\\.(bestbeefresh)\\.(com|net)$/i", $host)) {
	quit('Host domain not allowed. $host=' . $host);
}

/*$qstr = $refer_param['query'];
if (empty($pid) && preg_match("/&pid=(\d*)/i", $qstr, $matches)) {
    $pid = $matches[1];
}
    
if (empty($pid) && preg_match("/\\/pid\\/(\d+)/i", $refer, $matches)) {
    $pid = $matches[1];
}

if (empty($pid)) {
	quit('No pid. $qstr='.$qstr.';$refer='.$refer);
}*/

if (!empty($data)) {
	$jsapi_ticket = $data['jsapi_token'];
	$appid        = $data['appid'];
}

if (empty($jsapi_ticket)) {
    quit('No valid jsapi ticket.');
}

$noncestr = rands(16);
$timestamp = time();

$str = "jsapi_ticket=$jsapi_ticket&noncestr=$noncestr&timestamp=$timestamp&url=$refer";
$signature = sha1($str);

$api = htmlspecialchars($_GET['api']);
$api_list = explode(',', $api);
foreach ($api_list as $a) {
    $apis[] = "'" . trim($a) . "'";
}
$api_str = implode(',', $apis);

echo "wx.config({
    debug: $debug,
    appId: '$appid',
    timestamp: $timestamp,
    nonceStr:  '$noncestr',
    signature: '$signature',
    jsApiList: [$api_str]
});";

if ($debug == 'true') {
	echo '//$str=' . $str;
}
// \$str = $str";


function quit($msg) {
	global $debug;
	
	if ($debug == 'true') {
		echo $msg;
		exit;
	} else {
		http404();
	}
}
?>