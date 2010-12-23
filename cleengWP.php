<?php
/*
  Plugin Name: Cleeng for WordPress
  Plugin URI: http://www.cleeng.com/
  Plugin Version: 0.9.3
  Description: Cleeng helps you to make money with your digital content. The Cleeng plugin offers a single-click pay as you go solution to your website visitors; it avoids the hassle of multiple subscriptions. When publishing a post or page you as the publisher can define for which part of your content visitors need to pay (in between 0.15 and 0.99$/€). Read more tips and tricks on how to earn money on <a href="http://cleeng.com">http://cleeng.com</a>
  Author: DG2ALL B.V. @ Cleeng.com
  Author URI: http://cleeng.com
  License: New BSD License (http://cleeng.com/license/new-bsd.txt)
 */

define( 'CLEENG_WP_PLUGIN_PATH',
        WP_PLUGIN_URL . '/' . str_replace( basename( __FILE__ ), "", plugin_basename( __FILE__ ) ) );

require_once dirname( __FILE__ ) . '/CleengClient.php';

$config = include dirname( __FILE__ ) . '/config.php';
$cleeng = new CleengClient( $config );

/*
  CleengWP_Init includes necesarry plugin files in WP
 */
add_action( 'init', 'CleengWP_Init' );

function CleengWP_Init() {
    wp_enqueue_script( 'jquery' );
    if ( ! is_admin() ) {
        require_once dirname( __FILE__ ) . '/frontend.php';
    } else {
        require_once dirname( __FILE__ ) . '/admin.php';
    }
}
