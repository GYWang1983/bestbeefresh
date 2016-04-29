<?php
/**
 * 微信API类库
 * ============================================================================
 * * 版权所有 2015-2016 南京蜂蚁网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.bestbeefresh.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: wanggaoyuan $
*/

class WechatApi {

	const MEDIA_TYPE_IMAGE = 'image';
	const MEDIA_TYPE_VOICE = 'voice';
	const MEDIA_TYPE_VIDEO = 'video';
	const MEDIA_TYPE_THUMB = 'thumb';
	
	const MEDIA_TYPE_TEXT  = 'text';
	const MEDIA_TYPE_NEWS  = 'mpnews';
	const MEDIA_TYPE_CARD  = 'wxcard';
	
	const QRCODE_SCENE_LIMIT = 'QR_LIMIT_SCENE';
	const QRCODE_SCENE_LIMIT_STR = 'QR_LIMIT_STR_SCENE';
	
	private $cfg = array();
	
	private $errors = array();
	
	/**
	 * 构造函数
	 * 
	 * @param $config
	 * 
	 * @access  public
	 * @return  void
	 */
	function __construct($config = array())
	{
		require_once(ROOT_PATH . "data/wxtoken.php");
		$this->cfg = array_merge($data, $config);
	}
	
	/**
	 * 检查调用请求签名
	 * 
	 * @param string $signature
	 * @param string $timestamp
	 * @param string $nonce
	 * @return boolean
	 */
	public function checkSignature($signature, $timestamp, $nonce) {
	
		//从缓存文件中获取token
		$tmpArr = array($this->cfg['token'], $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = sha1(implode($tmpArr));
	
		return $tmpStr == $signature;
	}
	
	/**
	 * 上传媒体文件
	 * 
	 * @param $file  本地文件路径名
	 * @param $type  文件类型，图片（image）、语音（voice）、视频（video）和缩略图（thumb）
	 * @return media_id
	 */
    public function uploadmedia($file, $type = WechatApi::MEDIA_TYPE_IMAGE) {
        //检查本地文件是否存在，如不存在需要从OSS获取
        if (!file_exists($file) && !download_oss($file)) {
            return $this->error('Local file not exists');
        }
        
        $post_data['media'] = '@'.$file;
        $acctoken = $this->cfg['access_token'];
        
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token=$acctoken&type=$type";
        $result = $this->http_post($url, $post_data, 'file');
        $result = json_decode($result, true);
        return $result;
    }
    
    /**
     * 图文中的内容先上传到微信
     * 
     * @param string $image
     * @param string $remote
     * @return image url
     */
    public function uploadimg($image, $remote = false) {
    	//echo "$image ==>";
    	if (!$remote && !file_exists(SITE_PATH . $image) && STATIC_URL) {
    		//需要从阿里云OSS下载图片
    		$remote = TRUE;
    		$image = STATIC_URL . $image;
    	}
    	
    	$deltmp = false;
    	
    	if ($remote) {
    		//下载远程文件
    		$binary = @file_get_contents($image);
    		if ($binary === false) {
    			return false;
    		}
    		
    		//文件扩展名
    		$ext = pathinfo($image, PATHINFO_EXTENSION);
    		if (empty($ext) || $ext == 'gif') {
    			// 扩展名为gif时，接口报错
    			$ext = 'jpg';
    		}
    		
    		$image = SITE_PATH . '/' . IMG_TEMP_PATH . md5(rands(8) . time()) . '.' . $ext;
    		if (file_put_contents($image, $binary) === false) {
    			return false;
    		}
    		
    		$deltmp = true;
    		
    	} else {
    		$image = SITE_PATH . $image;
    		if (pathinfo($image, PATHINFO_EXTENSION) == 'gif') {
    			//gif文件要修改扩展名
    			$filename = SITE_PATH . '/' . IMG_TEMP_PATH . md5(rands(8) . time()) . '.jpg';
    			@copy($image, $filename);
    			$image = $filename;
    			$deltmp = true;
    		}
    	}
    	
    	$post_data['media'] = '@' . $image;
    	$acctoken = $this->cfg['access_token'];

    	$url = "https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=$acctoken";
    	$result = $this->http_post($url, $post_data, 'file');//echo $result . '<br>';
    	$result = json_decode($result, true);
    	
    	//删除临时下载文件
    	if ($deltmp) {
    		@unlink($image);
    	}
    	
    	if (!empty($result['errcode'])) {
    		return false;
    	} else {
    		return $result['url'];
    	}
    }
    
    /**
     * 上传图文素材
     * 
     * @param array $articles
     * [{
     *     'title' : "xxx",  
           'author': "xxx",
           'content_source_url': "www.xxx.com",
           'content': "content",
           'digest' : "digest",
           'cover'  : "/to/path/filename"
           'thumb_media_id': "xxxxxxxxxxxxxxxxxxxxxxxxxx"
           'show_cover_pic':0
     * }, {
     * ...
     * }]
     */
    public function uploadnews($articles) {
    	
    	//Check
    	if (empty($articles)) {
    	   	return $this->error('No articles');
    	}
    	
    	$idx = 1;
        foreach ($articles as $news) {
            //title
            if (empty($news['title'])) {
            	return $this->error("No title (Index=$idx)");
            }
            
            if (empty($news['content'])) {
            	return $this->error("No content (Index=$idx)");
            }
            
            if (empty($news['cover']) && empty($news['thumb_media_id'])) {
                return $this->error("No cover (Index=$idx)");
            }
            
            $idx++;
        }
        
        $acctoken = $this->cfg['access_token'];
        
        //Upload cover
        $idx = 1;
        foreach ($articles as &$news) {
        	if (empty($news['thumb_media_id'])) {
                $result = $this->uploadmedia($news['cover']);
                if (empty($result['errcode'])) {
                    $news['thumb_media_id'] = $result['media_id'];
                    unset($news['cover']);
                } else {
                    $result['errmsg'] .= " (Index=$idx)";
                    return $result;
                } 
        	}
        	
        	$idx++;
        }
        
    	$url = "https://api.weixin.qq.com/cgi-bin/media/uploadnews?access_token=$acctoken";
    	$data = array('articles' => $articles);
    	//echo $url;
    	//print_r($data);
    	$result = $this->http_post($url, $data);
    	//echo $result;
        $result = json_decode($result, true);
        
        if (empty($result['errcode'])) {
            $result['article'] = $articles;
        }
        
        return $result;
    }
    
    /**
     * 预览
     * 
     * @param mix $openid
     * @param string $media
     * @param string $type
     */
    public function preview($openid, $content, $type = WechatApi::MEDIA_TYPE_NEWS) {

        $acctoken = $this->cfg['access_token'];
        $url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token=$acctoken";
        
        //echo "url=$url \r\n";
        $data = array();
        $data['msgtype'] = $type;
        $data[$type][$type == WechatApi::MEDIA_TYPE_TEXT ? 'content' : 'media_id'] = $content;
        
        $send_user = (array) $openid;
        
        $error = array();
        foreach ($send_user as $to) {
        	
        	$data['touser'] = $to;
        	//print_r($data);
            $result = $this->http_post($url, $data);
            $result = json_decode($result, true);
            //print_r($result);
            if (!empty($result['errcode'])) {
            	$error[] = $result;
            	$errids[] = $to;
            }
        }
        
        if (empty($error)) {
        	return $result;
        } elseif (count($error) == 1) {
        	return $error;
        } else {
        	return array(
        	   'errcode' => 2,
        	   'errmsg'  => 'Send privew failed for several users',
        	   'errors'  => $error,
        	   'errids'  => $errids
        	);
        }
    }
    
    /**
     * 从腾讯服务器删除素材
     * 
     * @param string $msgid
     */
    public function delete($msgid) {
    	
        $acctoken = $this->cfg['access_token'];
        $url = "https://api.weixin.qq.com/cgi-bin/message/mass/delete?access_token=$acctoken";
        $data['msg_id'] = $msgid;
        
        $result = $this->http_post($url, $data);
        $result = json_decode($result, true);
        return $result;
    }
    
    /**
     * 发送模板消息
     * 
     * @param string $touser
     * @param string $tmplid
     * @param array $param
     */
    public function send_template_msg($touser, $tmplid, $param, $link) {
    	
    	$data = array(
    		'touser'       => $touser,
    		'template_id'  => $tmplid,
    		'url'          => $link,
    		'data'         => $param,
    	);
    	
    	$acctoken = $this->cfg['access_token'];
    	$url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$acctoken";
    	$result = $this->http_post($url, $data);
    	$result = json_decode($result, true);
    	return $result;
    }
    
    /**
     * 根据openid群发消息
     * 
     * @param string|array $openid
     * @param string $msgtype
     * @param mixed $content
     */
    public function mass_send_by_openid($openid, $msgtype, $content) {

    	$touser = is_array($openid) ? $openid : array($openid);
    	
    	$data = array(
    		'touser'       => $touser,
    		'msgtype'	   => $msgtype,
    	);
    	
    	if ($msgtype == WechatApi::MEDIA_TYPE_TEXT) {
    		$data[$msgtype] = array('content'  => $content);
    	} elseif ($msgtype == WechatApi::MEDIA_TYPE_CARD) {
    		$data[$msgtype] = array('card_id'  => $content);
    	} elseif ($msgtype == WechatApi::MEDIA_TYPE_VIDEO) {
    		$data[$msgtype] = $content;
    	} else {
    		$data[$msgtype] = array('media_id' => $content);
    	}
    	
    	$acctoken = $this->cfg['access_token'];
    	$url = "https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=$acctoken";
    	$result = $this->http_post($url, $data);
    	$result = json_decode($result, true);
    	return $result;
    }
    
    /**
     * 获取素材列表
     * 
     * @param string $type
     * @param number $offset
     * @param number $count
     * @return array
     */
    public function get_material($type = 'news', $offset = 0, $count = 20) {
    	
    	$data = array(
    		'type'    => $type,
    		'offset'  => $offset,
    		'count'   => $count
    	);
    	
    	$acctoken = $this->cfg['access_token'];
    	$url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=$acctoken";
    	$result = $this->http_post($url, $data);
    	$result = json_decode($result, true);
    	return $result;
    }
    
    /**
     * 获取用户信息
     * 
     * @param string $openid 用户OPENID
     */
    public function get_user_info($openid) {
    	
    	$acctoken = $this->cfg['access_token'];
    	$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$acctoken}&openid={$openid}&lang=zh_CN";
    	
    	$str = file_get_contents($url);
    	$json = json_decode($str, true);
    	if (!empty($json['errcode'])) {
    		return array();
    	}
    	
    	return $json;
    }
    
    /**
     * 生成永久二维码
     * @param string $type
     * @param string|integer $scene_val
     */
    public function create_permanent_qrcode($type = WechatApi::QRCODE_SCENE_LIMIT, $scene_val) {
    	
    	if ($type == WechatApi::QRCODE_SCENE_LIMIT) {
    		$scene = array('scene_id'  => $scene_val);
    	} else {
    		$scene = array('scene_str' => $scene_val);
    	}
    	
    	$data = array(
    		'action_name'  => $type,
    		'action_info'  => array('scene' => $scene),
    	);
    	
    	$acctoken = $this->cfg['access_token'];
    	$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$acctoken";
    	$result = $this->http_post($url, $data);
    	$result = json_decode($result, true);
    	return $result;
    }
    
    /**
     * 提交http请求
     * 
     * @param string $url
     * @param mixed $data
     * @param string $type
     */
    private function http_post($url, $param, $type = 'json') {
    	
    	$oCurl = curl_init ();
    	if (stripos ( $url, "https://" ) !== FALSE) {
    		curl_setopt ( $oCurl, CURLOPT_SSL_VERIFYPEER, FALSE );
    		curl_setopt ( $oCurl, CURLOPT_SSL_VERIFYHOST, FALSE );
    	}
    	
    	if ($type == 'json') {
    		$strPOST = json_encode($param);
    	} else {
    		$strPOST = $param;
    	}
    	//var_dump($strPOST);exit;
    	if (empty($header)) {
    		if ($type == 'form') {
    			$header [] = "content-type: application/x-www-form-urlencoded; charset=UTF-8";
    		}
    	}
    	
    	//var_dump($strPOST);exit;
    	curl_setopt ( $oCurl, CURLOPT_URL, $url );
    	if (!empty($header)) {
    		curl_setopt ( $oCurl, CURLOPT_HTTPHEADER, $header );
    	}
    	curl_setopt ( $oCurl, CURLOPT_FOLLOWLOCATION, 1 );
    	curl_setopt ( $oCurl, CURLOPT_AUTOREFERER, 1 );
    	curl_setopt ( $oCurl, CURLOPT_RETURNTRANSFER, 1 );
    	curl_setopt ( $oCurl, CURLOPT_POST, true );
    	curl_setopt ( $oCurl, CURLOPT_POSTFIELDS, $strPOST );
    	$sContent = curl_exec ( $oCurl );
    	$aStatus = curl_getinfo ( $oCurl );
    	curl_close ( $oCurl );
    	
    	if (intval ( $aStatus ["http_code"] ) == 200) {
    		return $sContent;
    	} else {
    		return false;
    	}
    }
    
    /**
     * 构造错误信息
     * @param string $errmsg
     * @param int $errcode
     */
    private function error($errmsg, $errcode = 1) {
    	return array(
    	   'errcode' => $errcode,
           'errmsg'  => $errmsg
    	);
    }
}