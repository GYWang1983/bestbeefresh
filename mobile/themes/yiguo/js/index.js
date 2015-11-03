
$(function() {
	
	// category menu
	$('nav#menu').show().mmenu();
	
	// image lazyload
	$('.goodsItem .thumb img').lazyload({ 
		effect:'fadeIn' 
	});
	
	$('#get_search_box').click(function(){
		$(".mm-page").children('div').hide();
		$("#main-search").css('position','fixed').css('top','0px').css('width','100%').css('z-index','999').show();
		//$('#keywordBox').focus();
	});
	$("#main-search .close").click(function(){
		$(".mm-page").children('div').show();
		$("#main-search").hide();
	});
	
	
	$(".cart .icon-plus").click(function(e) {
	   var goods_id = $(e.target).parents('.cart').attr('data');
	   addToCart(goods_id);
	});
	$(".cart .icon-minus").click(function(e) {
	   var goods_id = $(e.target).parents('.cart').attr('data');
       decFromCart(goods_id);
    });
    
    for (var good_id in cart) {
        $('#goods' + good_id + ' .cart .num').text(cart[good_id]);
    }
});