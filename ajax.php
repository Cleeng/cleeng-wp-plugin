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
define( 'WP_USE_THEMES', false );
require('../../../wp-load.php');
require_once dirname( __FILE__ ) . '/CleengClient.php';

header( 'pragma: no-cache' );
header( 'cache-control: no-cache' );
header( 'expires: 0' );

error_reporting(E_ALL);
$config = include dirname( __FILE__ ) . '/config.php';
$cleeng = new CleengClient( $config );

if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] ) {
    $scheme = 'https://';
} else {
    $scheme = 'http://';
}

$cleeng->setOption( 'callbackUrl', $scheme . $_SERVER['HTTP_HOST'] . '/' . trim( $_SERVER['PHP_SELF'], '/' ) . '?cleengMode=callback&cleengPopup=1' );

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

/**
 * Which mode are we operating in?
 */
switch ( $mode ) {
    case 'getLogoURL':
        /**
         * Get Cleeng logo
         */
        echo $cleeng->getLogoUrl( $_REQUEST['contentId'], $_REQUEST['logoId'], $_REQUEST['logoWidth'], $_REQUEST['logoLocale'] );
        break;
    case 'auth':
        /**
         * Login: redirect to Cleeng authentication page
         */
        $cleeng->authenticate();        
        break;
    case 'callback':
        /**
         * Cleeng will return here after authentication. We tell CleengClient
         * to proceed with OAuth stuff, and close popup window.
         * Parent window is refreshed so that widget will know that user is authenticated
         * status.
         */
        try {
            $cleeng->processCallback();
            if ($cleeng->isUserAuthenticated()) {
                setcookie('cleeng_user_auth', 1, time()+3600*24*60, '/');
            }    
        } catch (Exception $e) {
        }
        echo '<script type="text/javascript">
            //<![CDATA[
                opener.CleengWidget.getUserInfo();
                self.close();
            //]]>
            </script>            
            ';
        break;
    case 'purchase':
        /**
         * Purchase content
         */
        try {
            $cleeng->purchaseContent( (int) $_REQUEST['contentId'] );
        } catch ( Exception $e ) {
        }
        break;
    case 'logout':
        /**
         * Disconnect from Cleeng
         */
        $cleeng->logout();
        break;
    case 'getUserInfo':
        header( 'content-type: application/json' );
        try {
            if ( $cleeng->isUserAuthenticated() ) {
                echo json_encode( $cleeng->getUserInfo( @$_REQUEST['backendWidget'] ) );
                exit;
            }
        } catch ( Exception $e ) {                
        }
        echo json_encode( array() );
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
    case 'saveContent':
        /**
         * Save Cleeng content
         */
        if ( $cleeng->isUserAuthenticated() ) {
            $data = array(
                'itemType' => @$_POST['itemType'],
                'pageTitle' => @$_POST['pageTitle'],
                'url' => @$_POST['url'],
                'price' => @$_POST['price'],
                'shortDescription' => @$_POST['shortDescription'],
                'referralProgramEnabled' => (bool) @$_POST['referralProgramEnabled'],
                'referralRate' => @$_POST['referralRate'],
                'hasLayerDates' => (bool) @$_POST['hasLayerDates'],
                'layerStartDate' => @$_POST['layerStartDate'],
                'layerEndDate' => @$_POST['layerEndDate']
            );
            header( 'content-type: application/json' );
            try {

                $ret = $cleeng->createContent( array( $data ) );
                header( 'content-type: application/json' );
                echo json_encode( $ret );
            } catch ( Exception $e ) {
                print_r( $cleeng->getApiOutputBuffer() );
            }
        } else {
            header( 'content-type: application/json' );
            echo json_encode( array( 'response' => false, 'errorCode' => 'ERR_NO_AUTH' ) );
        }
        exit;
    case 'vote':
        /**
         * Vote for given content
         */
        if ( $cleeng->isUserAuthenticated() ) {

            $contentId = intval( @$_REQUEST['contentId'] );
            $liked = intval( @$_REQUEST['liked'] );

            if ( $contentId > 0 && ($liked == 0 || $liked == 1) ) {
                $ret = $cleeng->vote( $contentId, $liked );
                header( 'content-type: application/json' );
                echo json_encode( $ret['voted'] );
            }
        }
        exit;
    case 'refer':
        /**
         * Notify Platform that user has referred given item
         */
        header( 'content-type: application/json' );
        if ( $cleeng->isUserAuthenticated() ) {
            $contentId = intval( @$_REQUEST['contentId'] );
            if ( $contentId > 0 ) {
                $ret = $cleeng->referContent( $contentId );
                echo json_encode( $ret['referred'] );
            }
        }
        exit;
    case 'autologin':
        /**
         * Process with autologin
         */
        $id = trim( @$_REQUEST['id'] );
        $key = trim( @$_REQUEST['key'] );

        header( 'content-type: application/json' );

        if ( $cleeng->isUserAuthenticated() ) {
            echo json_encode( array( 'success' => true ) );
            exit;
        }

        if ( $id && $key ) {
            try {
                $ret = $cleeng->autologin( $id, $key );
            } catch ( Exception $e ) {
                echo $cleeng->getApiOutputBuffer();
                die;
            }
            echo json_encode( array( 'success' => $ret ) );
            exit;
        }
}