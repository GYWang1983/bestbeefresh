
$(function() {
	
	// 砍价
	$('#btkj').click(function(){
		
		// TODO: 获取Location
		
	    $.post('bargain.php?act=add', {
	    	'ubid': user_bargain_id
	    }, function(result) {
	        
	        if(result.errcode){
	            alert(result.msg);
	            return false;
	        }
	        
	        $('#kkan').show('fast');
	        $('#kkan').after(function() {
	        	var html = '<p class="hdsmnum">' + result.bargian_msg + '</p>';
	        	return html;
	        });

	        // 改变显示的按钮
	        $('#btkj').hide();
	        if (act == 'my') {
	        	$('#goumai').show();
	        } else {
	        	$('#btfx').show();
	        }
	        
	        // 显示砍价结果
	        var neg = result.bargain_price > 0 ? '-' : '+';
	        $('#my_bargain .monjia').text(neg + ' ' + Math.abs(result.bargain_price) + '元');
	        $('#my_bargain').show();
	        $('.ljkxx').text('累计砍下' + result.total_bargain_price + '元');
	        $('.userhd').text('现价' + result.now_price +'元');

	    }, 'json').complete(function(){
	    	setTimeout("$('#kkan').hide(10)", 1000);
	    });
	});
	
	// 找人帮忙
	$('.fx').click(function(){
	    $('#fx').show();
	});
	$('.close_fx').click(function(){
	    $('#fx').hide();
	});
});

// 分享设置
wx.ready(function(){
	wx.onMenuShareTimeline(share_meta);
    wx.onMenuShareAppMessage(share_meta);
    wx.onMenuShareQQ(share_meta);
    wx.onMenuShareWeibo(share_meta);
    wx.onMenuShareQZone(share_meta);
});