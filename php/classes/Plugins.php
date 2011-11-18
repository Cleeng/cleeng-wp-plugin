<?php

class Cleeng_Plugins
{


    public function setup()
    {
        // add UI dialog
        wp_enqueue_script( 'jquery-ui-dialog' );

        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
        
        $this->wpClient = $cleeng;
        
        if ($this->wpClient->isUserAuthenticated()){
            $this->userInfo = $cleeng->getUserInfo(); 
        } else {
            $this->userInfo = false;
        }
        wp_enqueue_script( 'jquery-tmpl', CLEENG_PLUGIN_URL . 'js/jquery.tmpl.min.js');

        $admin = Cleeng_Core::load('Cleeng_Admin');
        add_action( "admin_head-plugins.php", array($admin, 'render_javascript') );
        wp_enqueue_style('jqueryUi.css', CLEENG_PLUGIN_URL . 'css/south-street/jquery-ui-1.8.2.custom.css');
    }

}