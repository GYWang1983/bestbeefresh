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
	
	$(".payment_box").click(selectPayment);
});


function selectPayment(e) {
	var pay_id = $(e.currentTarget).attr('data');
	if (pay_id != $('#payment').val()) {
		$(".payment_box .checked").removeClass('checked');
		$(e.currentTarget).find('.icon-checkmark').addClass('checked');
		$('#payment').val(pay_id);
	}
	
}