(function( $ ){          
    var target = null;
    var template = null;
    var lock = false;
    var variables = {
        'last'      :    0        
    } 
    var settings = {
        'amount'      :   '10',          
        'address'     :   'comments.php',
        'format'      :   'json',
        'template'    :   '.single_item',
        'trigger'     :   '.get_more',
        'scroll'      :   false,
        'offset'      :   '100',
        'spinner_code':   ''
    }
    
    var methods = {
        init  :   function(options){
            return this.each(function(){
              
                if(options){
                    $.extend(settings, options);
                }
                variables.last = 0;
                if ($('.more_div_template', this).length == 0) {
                	template = $(this).children(settings.template).wrap('<div class="more_div_template"/>').parent();
                	template.css('display','none');
                } else {
                	template = $('.more_div_template', this);
                }
                $(settings.template + ':visible').remove();
                $('.more_loader_spinner', this).remove();
                
                target = $(this);
                target.append('<div class="more_loader_spinner">'+settings.spinner_code+'</div>')
                target.children(settings.template).remove()   
                if(settings.scroll == false) {
                	target.find(settings.trigger).bind('click.more',methods.get_data);
                	target.more('get_data');
                }                
                else{
                    if($(this).height() <= $(this).attr('scrollHeight')){
                        target.more('get_data',settings.amount*2);
                    }
                    target.bind('scroll.more',methods.check_scroll);
                }
            })
        },
        check_scroll : function(){
            if((target.scrollTop()+target.height()+parseInt(settings.offset)) >= target.attr('scrollHeight') && lock == false){
                target.more('get_data');
            }
        },
        debug : function(){
            var debug_string = '';
            $.each(variables, function(k,v){
                debug_string += k+' : '+v+'\n';
            })
            alert(debug_string);
        },     
        remove : function(){            
            target.children(settings.trigger).unbind('.more');
            target.unbind('.more');
            //target.children(settings.trigger).remove();
        },
        add_elements : function(data){
            var root = target, counter = 0;
            if(data){
                $(data).each(function(){
                    counter++;
                    var t = template;
                    $.each(this, function(key, value){                          
                        if(t.find('.'+key)) t.find('.'+key).html(value);
                    })
                    if(settings.scroll == 'true'){
                        root.children('.more_loader_spinner').before(t.html())  
                    }else{
                        root.children(settings.trigger).before(t.html())  
                    }
                    root.children(settings.template+':last').attr('id', 'more_element_'+ ((variables.last++)+1))  
                })
            }            
            else  methods.remove()
            target.children('.more_loader_spinner').css('display','none');
            if(counter < settings.amount) methods.remove()            
        },
        get_data : function(){
            var ile;
            if (lock) return;
            lock = true;
            target.children(".more_loader_spinner").css('display','block');
            $(settings.trigger).css('display','none');
            if(typeof(arguments[0]) == 'number') ile=arguments[0];
            else {
                ile = settings.amount;              
            }
            
            $.post(settings.address, {
                last : variables.last,
                amount : ile
            }, function(data){            
                $(settings.trigger).css('display','block')
                methods.add_elements(data)
                lock = false;
            }, settings.format)
            
        }
    };
    $.fn.more = function(method){
        if(methods[method]) 
            return methods[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ));
        else if(typeof method == 'object' || !method) 
            return methods.init.apply(this, arguments);
        else $.error('Method ' + method +' does not exist!');

    }    
})(jQuery)