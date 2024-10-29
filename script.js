;(function($) {
    var addUsers = {
        init: function() {
            $('.users-php').on('submit', 'form#add-user-2i1', this.saveUser);

        },
        saveUser:function(e){
            e.preventDefault();
            var emai = $('input[name="email"]').val();
            var data = $(this).serialize();
            if(email != ''){
                $.post(adduser_var.ajaxurl, data, function (res) {
                        // this_btn.attr( 'disabled', false );
                        // spinner.hide();

                    res = JSON.parse(res);
                    
                    if(res.success === true) {
                        alert(res.message);
                        if(res.reload){
                          window.location.href = res.reload;  
                        }
                        
                    } else {
                        if(res.show_input_username === true){
                            $('tr#add-username').show();
                        }
                        alert(res.message);
                    }

                });
            }
        }

    }

    $(function() {
        addUsers.init();

    })
    
})(jQuery);