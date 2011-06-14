<?php
/*
  Plugin Name: Cleeng for WordPress
  Plugin URI: http://www.cleeng.com/
  Version: 1.1.6
  Description: Cleeng helps you to make money with your digital content. The Cleeng plugin offers a single-click pay as you go solution to your website visitors; it avoids the hassle of multiple subscriptions. When publishing a post or page you as the publisher can define for which part of your content visitors need to pay (in between 0.14 and 19.99$/€). Read more tips and tricks on how to earn money on <a href="http://cleeng.com">http://cleeng.com</a>
  Author: Cleeng
  Author URI: http://cleeng.com
  License: New BSD License (http://cleeng.com/license/new-bsd.txt)
 */

define( 'CLEENG_WP_PLUGIN_PATH',
        WP_PLUGIN_URL . '/' . str_replace( basename( __FILE__ ), "", plugin_basename( __FILE__ ) ) );

require_once dirname( __FILE__ ) . '/includes/CleengClient.php';

try {
    CleengClient::checkCompatibility();
    $cleeng_compatible_env = true;
    $cleeng_config = include dirname( __FILE__ ) . '/includes/config.php';
    $cleeng = new CleengClient( $cleeng_config );
} catch (CleengClientException $e) {    
    $cleeng_compatible_env = false;
}


load_plugin_textdomain( 'cleeng', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/*
  CleengWP_Init includes necesarry plugin files in WP
 */
add_action( 'init', 'CleengWP_Init' );

function cleeng_display_incompatibility_warning() {
    echo '<div class="error cleeng_error"><p>';
    echo __('Your current environment is incompatible with Cleeng Plugin. Please make sure that you have PHP v5.1 or later, '
         . 'with JSON and CURL extensions enabled.');
    echo '</p></div>';
}

function CleengWP_Init() {
    global $cleeng_compatible_env;
    wp_enqueue_script( 'jquery' );
    if ( ! is_admin() ) {
        if ($cleeng_compatible_env) {
            require_once dirname( __FILE__ ) . '/includes/frontend.php';
        }
    } else {
        if ($cleeng_compatible_env) {
            require_once dirname( __FILE__ ) . '/includes/admin.php';
        } else {
            add_action( 'admin_notices', 'cleeng_display_incompatibility_warning' );
        }
    }
}
