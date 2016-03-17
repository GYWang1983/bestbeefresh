<?php

/**
 * ECSHOP 验证码图片类
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: cls_captcha.php 17217 2011-01-19 06:29:08Z liubo $
*/

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

class captcha
{
    /**
     * 背景图片所在目录
     *
     * @var string  $folder
     */
    var $folder     = 'data/captcha';

    /**
     * 图片的文件类型
     *
     * @var string  $img_type
     */
    var $img_type   = 'png';

    /*------------------------------------------------------ */
    //-- 存在session中的名称
    /*------------------------------------------------------ */
    var $session_word = 'captcha_word';

    /**
     * 背景图片以及背景颜色
     *
     * 0 => 背景图片的文件名
     * 1 => Red, 2 => Green, 3 => Blue
     * @var array   $themes
     */
    var $themes_jpg = array(
        1 => array('captcha_bg1.jpg', 255, 255, 255),
        2 => array('captcha_bg2.jpg', 0, 0, 0),
        3 => array('captcha_bg3.jpg', 0, 0, 0),
        4 => array('captcha_bg4.jpg', 0, 0, 0),
        5 => array('captcha_bg5.jpg', 255, 255, 255),
    );

    var $themes_gif = array(
        1 => array('captcha_bg1.gif', 255, 255, 255),
        2 => array('captcha_bg2.gif', 0, 0, 0),
        3 => array('captcha_bg3.gif', 0, 0, 0),
        4 => array('captcha_bg4.gif', 255, 255, 255),
        5 => array('captcha_bg5.gif', 255, 255, 255),
    );

    /**
     * 图片的宽度
     *
     * @var integer $width
     */
    var $width      = 130;

    /**
     * 图片的高度
     *
     * @var integer $height
     */
    var $height     = 20;
    
    /**
     * 字体大小
     *
     * @var integer $height
     */
    var $fontSize    = 10;

    /**
     * 构造函数
     *
     * @access  public
     * @param   string  $folder     背景图片所在目录
     * @param   integer $width      图片宽度
     * @param   integer $height     图片高度
     * @return  bool
     */
    function __construct($folder = '', $width = 145, $height = 20)
    {
    	if (!empty($folder))
        {
            $this->folder = $folder;
        }

        $this->width    = $width;
        $this->height   = $height;

        /* 检查是否支持 GD */
        if (PHP_VERSION >= '4.3')
        {

            return (function_exists('imagecreatetruecolor') || function_exists('imagecreate'));
        }
        else
        {

            return (((imagetypes() & IMG_GIF) > 0) || ((imagetypes() & IMG_JPG)) > 0 );
        }
    }


    /**
     * 检查给出的验证码是否和session中的一致
     *
     * @access  public
     * @param   string  $word   验证码
     * @return  bool
     */
    function check_word($word)
    {
        $recorded = isset($_SESSION[$this->session_word]) ? base64_decode($_SESSION[$this->session_word]) : '';
        $given    = $this->encrypts_word(strtoupper($word));

        return (preg_match("/$given/", $recorded));
    }

    /**
     * 生成图片并输出到浏览器
     *
     * @access  public
     * @param   string  $word   验证码
     * @return  mix
     */
    function generate_image($word = false)
    {
        if (!$word)
        {
            $word = $this->generate_word();
        }

        /* 记录验证码到session */
        $this->record_word($word);

        /* 验证码长度 */
        $letters = strlen($word);

        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);

        if (function_exists('imagecreatefromjpeg') && ((imagetypes() & IMG_JPG) > 0))
        {
            $theme  = $this->themes_jpg[mt_rand(1, count($this->themes_jpg))];
        }
        else
        {
            $theme  = $this->themes_gif[mt_rand(1, count($this->themes_gif))];
        }

        if (!file_exists($this->folder . $theme[0]))
        {
            return false;
        }
        else
        {
            $img_bg    = (function_exists('imagecreatefromjpeg') && ((imagetypes() & IMG_JPG) > 0)) ?
                            imagecreatefromjpeg($this->folder . $theme[0]) : imagecreatefromgif($this->folder . $theme[0]);
            $bg_width  = imagesx($img_bg);
            $bg_height = imagesy($img_bg);

            $img_org   = ((function_exists('imagecreatetruecolor')) && PHP_VERSION >= '4.3') ?
                          imagecreatetruecolor($this->width, $this->height) : imagecreate($this->width, $this->height);

            /* 将背景图象复制原始图象并调整大小 */
            if (function_exists('imagecopyresampled') && PHP_VERSION >= '4.3') // GD 2.x
            {
                imagecopyresampled($img_org, $img_bg, 0, 0, 0, 0, $this->width, $this->height, $bg_width, $bg_height);
            }
            else // GD 1.x
            {
                imagecopyresized($img_org, $img_bg, 0, 0, 0, 0, $this->width, $this->height, $bg_width, $bg_height);
            }
            imagedestroy($img_bg);

            $clr = imagecolorallocate($img_org, $theme[1], $theme[2], $theme[3]);

            $this->_writeCurve($img_org, $clr);
            
            /* 绘制边框 */
            //imagerectangle($img_org, 0, 0, $this->width - 1, $this->height - 1, $clr);

            /* 获得验证码的高度和宽度 */
            $x = ($this->width - (imagefontwidth(5) * $letters)) / 2;
            $y = ($this->height - imagefontheight(5)) / 2;
            //imagestring($img_org, 5, $x, $y, $word, $clr);

            $codeNX = 0; // 验证码第N个字符的左边距
            $fontttf = $this->folder . '5.ttf';
            for ($i = 0; $i < $letters; $i++) {
            	$l = substr($word, $i, 1);
            	$codeNX += mt_rand($this->fontSize*1.4, $this->fontSize*1.6);
            	// 写一个验证码字符
            	imagettftext($img_org, $this->fontSize, mt_rand(-40, 40), $codeNX, $this->fontSize*1.6, $clr, $fontttf, $l);
            }
            
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

            // HTTP/1.1
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0, max-age=0', false);

            // HTTP/1.0
            header('Pragma: no-cache');
            if ($this->img_type == 'jpeg' && function_exists('imagecreatefromjpeg'))
            {
                header('Content-type: image/jpeg');
                imageinterlace($img_org, 1);
                imagejpeg($img_org, false, 95);
            }
            else
            {
                header('Content-type: image/png');
                imagepng($img_org);
            }

            imagedestroy($img_org);

            return true;
        }
    }

    /*------------------------------------------------------ */
    //-- PRIVATE METHODs
    /*------------------------------------------------------ */

    /**
     * 对需要记录的串进行加密
     *
     * @access  private
     * @param   string  $word   原始字符串
     * @return  string
     */
    function encrypts_word($word)
    {
        return substr(md5($word), 1, 10);
    }

    /**
     * 将验证码保存到session
     *
     * @access  private
     * @param   string  $word   原始字符串
     * @return  void
     */
    function record_word($word)
    {
        $_SESSION[$this->session_word] = base64_encode($this->encrypts_word($word));
    }

    /**
     * 生成随机的验证码
     *
     * @access  private
     * @param   integer $length     验证码长度
     * @return  string
     */
    function generate_word($length = 4)
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        for ($i = 0, $count = strlen($chars); $i < $count; $i++)
        {
            $arr[$i] = $chars[$i];
        }

        mt_srand((double) microtime() * 1000000);
        shuffle($arr);

        return substr(implode('', $arr), 5, $length);
    }
    
    
    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     *
     *      高中的数学公式咋都忘了涅，写出来
     *		正弦型函数解析式：y=Asin(ωx+φ)+b
     *      各常数值对函数图像的影响：
     *        A：决定峰值（即纵向拉伸压缩的倍数）
     *        b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     *        φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     *        ω：决定周期（最小正周期T=2π/∣ω∣）
     *
     */
    private function _writeCurve($image) {
    	$px = $py = 0;
    	
    	$color = imagecolorallocate($image, 255, 255, 255);
    	exit;
    	// 曲线前部分
    	$A = mt_rand(1, $this->height/2);                  // 振幅
    	$b = mt_rand(-$this->height/4, $this->height/4);   // Y轴方向偏移量
    	$f = mt_rand(-$this->height/4, $this->height/4);   // X轴方向偏移量
    	$T = mt_rand($this->height, $this->width*2);  // 周期
    	$w = (2* M_PI)/$T;
    
    	$px1 = 0;  // 曲线横坐标起始位置
    	$px2 = mt_rand($this->width/2, $this->width * 0.8);  // 曲线横坐标结束位置
    
    	for ($px=$px1; $px<=$px2; $px = $px + 1) {
    		if ($w!=0) {
    			$py = $A * sin($w*$px + $f)+ $b + $this->height/2;  // y = Asin(ωx+φ) + b
    			$i = (int) ($this->fontSize/5);
    			while ($i > 0) {
    				imagesetpixel($image, $px + $i , $py + $i, $color);  // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
    				$i--;
    			}
    		}
    	}
    
    	// 曲线后部分
    	$A = mt_rand(1, $this->height/2);                  // 振幅
    	$f = mt_rand(-$this->height/4, $this->height/4);   // X轴方向偏移量
    	$T = mt_rand($this->height, $this->width*2);  // 周期
    	$w = (2* M_PI)/$T;
    	$b = $py - $A * sin($w*$px + $f) - $this->height/2;
    	$px1 = $px2;
    	$px2 = $this->width;
    
    	for ($px=$px1; $px<=$px2; $px=$px+ 1) {
    		if ($w!=0) {
    			$py = $A * sin($w*$px + $f)+ $b + $this->height/2;  // y = Asin(ωx+φ) + b
    			$i = (int) ($this->fontSize/5);
    			while ($i > 0) {
    				imagesetpixel($image, $px + $i, $py + $i, $color);
    				$i--;
    			}
    		}
    	}
    }
}

?>