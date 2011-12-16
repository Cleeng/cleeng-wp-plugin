jQuery(function(){
    var advanced = 0;
    jQuery('#cleeng_advanced, #cleeng_advanced2').click(function(){
        if (advanced === 0) {
            jQuery('.cleeng_advanced').show();
            advanced = 1;
            jQuery('#arrow').addClass('bottom');
            location.href="#below";
        } else {
            jQuery('.cleeng_advanced').hide();
            advanced = 0;
            jQuery('#arrow').removeClass('bottom');

        }
        return false;
    });
        

    jQuery('a.cleeng-facebook, a.cleeng-twitter, a.publisher-account').click(function() {
        if (jQuery(this).hasClass('cleeng-twitter')) {
            width = 1110;
            height = 650;
        }else if(jQuery(this).hasClass('publisher-account')){
            width = 600;
            height = 760; 
        } else {
            width = 600;
            height = 400;
        }
        window.open(jQuery(this).attr('href'), 'shareWindow', 'menubar=no,width='
            + width + ',height=' + height + ',toolbar=no,resizable=yes');

        return false;
    });

});