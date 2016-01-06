$(function() {

	$(".cart .icon-plus").click(function(e) {
	   var dom = $(e.target).parents('.cart'),
	   	   goods_id = dom.attr('data'),
	       ext = dom.attr('ext');
	   addToCart(goods_id, 0, ext);
	});
	$(".cart .icon-minus").click(function(e) {
	   var dom = $(e.target).parents('.cart'),
	       goods_id = dom.attr('data'),
	       ext = dom.attr('ext'),
	       num = parseInt(dom.children('.num').text());
	   if (num > 1) {
		   decFromCart(goods_id, 0, ext);
	   } else {
		   
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