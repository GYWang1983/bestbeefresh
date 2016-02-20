
$(function() {
	
	// image lazyload
	$('.goodsItem .thumb img').lazyload({
		threshold: 700, 
		effect: 'fadeIn' 
	});
	
	/*$('#get_search_box').click(function(){
		$(".mm-page").children('div').hide();
		$("#main-search").css('position','fixed').css('top','0px').css('width','100%').css('z-index','999').show();
		//$('#keywordBox').focus();
	});
	$("#main-search .close").click(function(){
		$(".mm-page").children('div').show();
		$("#main-search").hide();
	});*/
	
	
	$(".flash-sale .cart .icon-plus").click(function(e) {
	   var goods_id = $(e.target).parents('.cart').attr('data');
	   addToCart(goods_id, 0, 'flash_sale');
	});
	$(".flash-sale .cart .icon-minus").click(function(e) {
	   var goods_id = $(e.target).parents('.cart').attr('data');
	   decFromCart(goods_id, 0, 'flash_sale');
    });
	
	$(".cat-goods .cart .icon-plus").click(function(e) {
		var goods_id = $(e.target).parents('.cart').attr('data');
		addToCart(goods_id);
	});
	$(".cat-goods .cart .icon-minus").click(function(e) {
		var goods_id = $(e.target).parents('.cart').attr('data');
		decFromCart(goods_id);
	});
    
    for (var good_id in cart) {
        $('#goods' + good_id + ' .cart .num').text(cart[good_id]);
    }
    
    if (flash) {
    	for (var flash_id in flash) {
    		$('#flash' + flash_id + ' .cart .num').text(flash[flash_id]);
    	}
    }
    
    // 限时抢购倒计时
    $('.flash-sale .goodsItem').each(function(){
    	var el = $(this),
    		timeObj = el.find(".flash_time");
    	if (timeObj.attr('status') == 2) {
    		el.find('.cart').show();
    		startFlashTimer(el, timeObj.attr('remain'), 0);
    	} else if (timeObj.attr('status') == 4) {
    		var thumb = timeObj.parent().find('.thumb');
    		$('<img class="soldout" src="' + themes + '/images/soldout.png" />').appendTo(thumb);
    	}
    });
    
    var share_meta = {
    	'title': shop_title,
    	'desc': shop_desc,
    	'imgUrl':shop_logo,
    	'link':site_url
    };
    
    wx.ready(function(){
	    wx.onMenuShareTimeline(share_meta);
	    wx.onMenuShareAppMessage(share_meta);
	    wx.onMenuShareQQ(share_meta);
	    wx.onMenuShareWeibo(share_meta);
	    wx.onMenuShareQZone(share_meta);
    });
    
    // shop menu
	$('nav#top-shop-menu').mmenu({
		autoHeight: true,
		navbar: {
			title: '选择提货门店'
		},
		offCanvas: {
			position : 'right',
			zposition: 'front'
		},
		backButton: {close: true},
		extensions: ['fullscreen']
	}).show();
	
	$('.shop_item').click(function() {
		window.location.href = $(this).attr('href');
	});
    
    if (default_shop == 0) {
    	$('#select_shop').click();
    }
});


function startFlashTimer(el, remain, time) {
	setTimeout(function() {
		time++;
		if (time < remain) {
			var d = remain - time,
				h = Math.floor(d / 3600),
				m = Math.floor((d - h * 3600) / 60),
				s = d % 60;
				t = (h > 0 ? h + '时' : '') +  m + '分' + s + '秒';
			el.find('.flash_time span').text(t);
			startFlashTimer(el, remain, time);
		} else {
			el.find('.flash_time span').text('已结束');
			el.find('.flash_time').removeClass('text-black').addClass('text-gray');
			el.find('.cart').hide();
		}
	}, 1000);
}