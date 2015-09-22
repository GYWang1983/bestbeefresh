
function send_bind_sms() {
	
	var mobile = $('#mobile_phone').val();
	if (!check_mobile(mobile)) {
		$('#alert').text('请输入正确的手机号');
		return;
	}
	
	var param = {'mobile':  mobile};
    $.post('sms.php?step=wxbind&r=' + Math.random(), param, function(result) {
    	switch(result.error)
    	{
    	case 0:
    	case 4:
    		$('#alert').text('验证码已发送');
    		break;
    	case 2:
    	case 3:
    		$('#alert').text(result.message);
    		break;
    	case 5:
    	case 6:
    		$('#alert').text('短信发送失败，请稍后再试');
    		break;
    	}
    }, 'json');
}

