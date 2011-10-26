<?php
/**
 * Cleeng For WordPress
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://cleeng.com/license/new-bsd.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to theteam@cleeng.com so we can send you a copy immediately.
 *
 */
ob_start(); 
define( 'WP_USE_THEMES', false );
require('../../../wp-load.php');
ob_end_clean();

header( 'pragma: no-cache' );
header( 'cache-control: no-cache' );
header( 'expires: 0' );

if (isset($_REQUEST['backendWidget']) && $_REQUEST['backendWidget']) {
    define( 'WP_ADMIN', true );
}

if (!defined('CLEENG_PLUGIN_URL')) {
    define('CLEENG_PLUGIN_URL',
            WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)));
}

$cleeng = Cleeng_Core::load('Cleeng_WpClient');


$mode = @$_REQUEST['cleengMode'];


/**
 * Compatibility with other plugins
 *
 */

// WP Super Cache
if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}

/**
 * End of compatibility code
 */

/**
 * Extract content between cleeng tags ([cleeng_content] and [/cleeng_content])
 * @global stdClass $post
 * @global <type> $page
 * @param int $postId
 * @param int $contentId
 * @return string
 */
if (!function_exists('cleeng_extract_content')) {
    function cleeng_extract_content( $postId, $contentId ) {
        global $post, $page, $pages;

        remove_filter( 'the_content', 'cleeng_add_layers', 100 );

        $wpQuery = new WP_Query( array( 'p' => $postId ) );
        @$wpQuery->the_post();

        if ( ! count( $pages ) || empty( $pages[0] ) ) {
            $wpQuery = new WP_Query( array( 'page_id' => $postId ) );
            @$wpQuery->the_post();
        }

        if ( ! is_array( $pages ) || ! count( $pages ) ) {
            return '';
        }

        foreach ( $pages as $page ) {

            $page = apply_filters( 'the_content', $page );

            $pattern = '/\[cleeng_content.*?id\s*=\s*\"' . $contentId . '".*?[^\\\]\](.*?[^\\\])\[\/cleeng_content\]/is';

            if ( preg_match( $pattern, $page, $mm ) ) {
                return $mm[1];
            }
        }

        return '';
    }
}

/**
 * Which mode are we operating in?
 */
switch ( $mode ) {
    case 'getLogoURL':
        /**
         * Get Cleeng logo
         */
        echo $cleeng->getLogoUrl( $_REQUEST['contentId'], $_REQUEST['logoId'], $_REQUEST['logoWidth'], $_REQUEST['logoLocale'] );
        exit;
    case 'auth':
        /**
         * Login: redirect to Cleeng authentication page
         */
        $cleeng->authenticate();
        exit;
    case 'registerPublisher':
        /**
         * Open publisher registration form
         */
        $cleeng->registerPublisher();        
        exit;
    case 'getContentInfo' :
        /**
         * Retrieve information about cleeng items
         */
        try {
            $ids = array( );
            $contentInfo = array( );
            if ( isset( $_REQUEST['content'] ) && is_array( $_REQUEST['content'] ) ) {
                $content = array( );
                $ids = array( );
                foreach ( $_REQUEST['content'] as $c ) {
                    $id = intval( @$c['id'] );
                    $postId = intval( @$c['postId'] );
                    if ( $id && $postId ) {
                        $ids[] = $id;
                        $content[$id] = array(
                            'postId' => $postId
                        );
                    }
                }
                if ( count( $ids ) ) {
                    $contentInfo = $cleeng->getContentInfo( $ids );

                    if ( sizeof( $contentInfo ) ) {
                        foreach ( $contentInfo as $key => $val ) {
                            if ( $val['purchased'] == true ) {
                                $contentInfo[$key]['content'] = cleeng_extract_content( $content[$key]['postId'], $key );
                            }
                        }
                    }
                }
            }
            header( 'content-type: application/json' );
            echo json_encode( $contentInfo );
        } catch ( Exception $e ) {
            header( 'content-type: application/json' );
            echo json_encode( array() );
        }
        exit;
    case 'getContentDefaultConditions':
        try{
            $defaultConditions = $cleeng->getContentDefaultConditions();
            header( 'content-type: application/json' );
            echo json_encode( $defaultConditions );
        } catch ( Exception $e ) {
            header( 'content-type: application/json' );
            echo json_encode( array() );
        }
        exit;
    case 'paypal':
        if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] ) {
            $scheme = 'https://';
        } else {
            $scheme = 'http://';
        }
        $closePopupUrl = CLEENG_PLUGIN_URL . '/channel.html';
        $contentId = intval($_REQUEST['content_id']);
        header('Location: ' . $cleeng->getUrl() . '/content/purchase-digital-goods?'
                .http_build_query(array(
                    'content_id' => $contentId,
                    'redirect_uri' => $closePopupUrl,
                    'client_id' => $cleeng->getOption('clientId')
                )));
        exit;
    case 'getPurchaseSummary':
        try {
        $purchaseSummary = $cleeng->getPurchaseSummary();
        
        header( 'content-type: application/json' );
        echo json_encode( $purchaseSummary );
        } catch ( Exception $e ) {
            header( 'content-type: application/json' );
            echo json_encode( array() );
        }
        exit;
    case 'getAppSecureKey':
        header( 'content-type: application/json' );

        if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
            $options = Cleeng_Core::get_config();
            echo json_encode(array('appSecureKey' => $options['appSecureKey']));
        } else {
            echo json_encode(array());
        }
        exit;
    case 'savePublisherToken':
        if (current_user_can('edit_posts') || current_user_can('edit_pages') && isset($_REQUEST['token'])) {
            $user = wp_get_current_user();
            update_user_meta($user->ID, '_cleeng_publisher_token', $_REQUEST['token']);
        }
        exit;
}