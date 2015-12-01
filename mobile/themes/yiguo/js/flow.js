$(function() {

	$(".cart .icon-plus").click(function(e) {
	   var goods_id = $(e.target).parents('.cart').attr('data');
	   addToCart(goods_id);
	});
	$(".cart .icon-minus").click(function(e) {
	   var goods_id = $(e.target).parents('.cart').attr('data');
	   var num = parseInt($(e.target).parents('.cart').children('.num').text());
	   if (num > 1) {
		   decFromCart(goods_id);
	   }
    });
});