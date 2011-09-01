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

global $cleeng;
global $cleeng_has_content;
global $cleeng_preload_content;
global $cleeng_content;
global $cleeng_user;

/**
 * If set to true, plugin will fetch content information on server side,
 * before page is displayed.
 */
$cleeng_preload_content = true;

/**
 * Will be set to true if any cleeng items should be displayed in The Loop
 */
$cleeng_has_content = false;

wp_enqueue_script( 'ZeroClipboard', CLEENG_WP_PLUGIN_PATH . 'js/ZeroClipboard.js', array( 'jquery' ) );
wp_enqueue_script( 'CleengFEWidgetWP', CLEENG_WP_PLUGIN_PATH . 'js/CleengFEWidgetWP.js', array( 'jquery' ) );
wp_enqueue_style( 'cleengFEWidget', CLEENG_WP_PLUGIN_PATH . 'css/cleengFEWidget.css' );

add_action( 'wp_print_footer_scripts', 'cleeng_autologin_script' );
add_action( 'wp_footer', 'cleeng_script_footer' );
add_action( 'wp_head', 'cleeng_script_head' );
add_action( 'loop_start', 'cleeng_loop_start' );
add_filter( 'the_content', 'cleeng_add_layers', 100 );

/**
 * Wordpress action.
 * Output <script> markup for autologin functionality
 *
 * @global boolean $cleeng_has_content
 * @global CleengClient $cleeng
 */
function cleeng_autologin_script() {
    global $cleeng_has_content;
    global $cleeng_user;
    global $cleeng;

    if ( $cleeng_has_content && ! $cleeng_user ) {
        echo '<script type="text/javascript" src="'
        . $cleeng->getAutologinScriptUrl()
        . '"></script>';
    }
}

function cleeng_script_head() {
    $options = get_option('cleeng_options');

    if (!isset($options['payment_method']) || $options['payment_method'] !== 'cleeng-only') {
        echo '<script src ="https://www.paypalobjects.com/js/external/dg.js" type="text/javascript"></script>';
    }
    echo    
    '<script type="text/javascript">
    // <![CDATA
    Cleeng_PluginPath = "' . CLEENG_WP_PLUGIN_PATH . '";
    // ]]>
    </script> ';
}

/**
 * Wordpress action.
 * Output javascript code setting Cleeng Plugin URL
 */
function cleeng_script_footer() {
    global $cleeng_user, $cleeng_content, $cleeng_preload_content;
    echo
    '<script type="text/javascript">
    // <![CDATA
    jQuery(function() {';
    if ( $cleeng_user ) {
        echo 'CleengWidget.userInfo = ' . json_encode($cleeng_user) , ";\n";
    }
    if ( $cleeng_content && count($cleeng_content) ) {
        echo 'CleengWidget.contentInfo = ' . json_encode($cleeng_content) , ";\n";
    }
    if (!$cleeng_preload_content) {
        echo "CleengWidget.getUserInfo();\n";
    }
    echo
        'CleengWidget.init();
    });
    // ]]>
    </script> ';

}

/**
 * Wordpress action.
 * Hook to Wordpress loop. It scans every post and searches for Cleeng
 * items. Then it fetches information about the content using WebAPI
 *
 * @global array $posts
 * @global array $cleeng_has_content
 * @global array $cleeng_content
 * @global array $cleeng_user
 * @global boolean $cleeng_preload_content
 * @global CleengClient $cleeng
 */
function cleeng_loop_start() {
    global $posts, $cleeng_content, $cleeng_preload_content, $cleeng, 
            $cleeng_has_content, $cleeng_user;

    $cleeng_content = array( );
    foreach ( $posts as $post ) {
        /* Quick search for cleeng content */
        if ( false === strpos( $post->post_content, '[cleeng_content' ) ) {
            continue;
        }

        $expr = '/\[cleeng_content(.*?[^\\\])\](.*?[^\\\])\[\/cleeng_content\]/is';
        preg_match_all( $expr, $post->post_content, $m );
        foreach ( $m[0] as $key => $content ) {
            $paramLine = $m[1][$key];
            $expr = '/(\w+)\s*=\s*(?:\"|&quot;)(.*?)(?<!\\\)(?:\"|&quot;)/si';
            preg_match_all( $expr, $paramLine, $mm );

            if ( ! isset( $mm[0] ) || ! count( $mm[0] ) ) {
                continue;
            }

            $params = array( );
            foreach ( $mm[1] as $key => $paramName ) {
                $params[$paramName] = $mm[2][$key];
            }
            if ( ! isset( $params['id'] ) ) {
                continue;
            }

            $content = array(
                'contentId' => $params['id'],
                'shortDescription' => @$params['description'],
                'price' => @$params['price'],
                'itemType' => 'article',
                'purchased' => false,
                'shortUrl' => '',
                'referred' => false,
                'referralProgramEnabled' => false,
                'referralRate' => 0,
                'rated' => false,
                'publisherId' => '000000000',
                'publisherName' => '',
                'averageRating' => 4,
                'canVote' => false,
                'currencySymbol' => '',
                'sync' => false
            );

            if ( isset( $params['referral'] ) ) {
                $content['referralProgramEnabled'] = true;
                $content['referralRate'] = $params['referral'];
            }

            if ( isset( $params['ls'] ) && isset( $params['le'] ) ) {
                $content['hasLayerDates'] = true;
                $content['layerStartDate'] = $params['ls'];
                $content['layerEndDate'] = $params['le'];
            }

            $cleeng_content[$params['id']] = $content;
        }
    }

    if ( count( $cleeng_content ) ) {
        $cleeng_has_content = true;

        /**
         * Compatibility with other plugins
         */
        // WP Super Cache, WP Total Cache - caching should be disabled for pages with Cleeng
        if (!defined('DONOTCACHEPAGE')) {
            define( 'DONOTCACHEPAGE', true );
        }
        /**
         * End of compatibility code
         */
        
    } else {
        return;
    }

    // we have found all cleeng items on current page. Now let's use Cleeng
    // API to get content information    
    if ( $cleeng_preload_content ) {

        try {
            if ($cleeng->isUserAuthenticated()) {
                $cleeng_user = $cleeng->getUserInfo();
            }
        } catch (Exception $e) {
            $cleeng_preload_content = false;
            return;
        }
        $ids = array_keys( $cleeng_content );

        $contentInfoIds = array();
        foreach ($ids as $key => $value) {
            if (is_numeric($value)) {
                $contentInfoIds[] = $value;
            }
        }
        if (count($contentInfoIds)) {
            try {
                $contentInfo = $cleeng->getContentInfo( $contentInfoIds );                
                foreach ( $contentInfo as $key => $val ) {

                    // don't load short desc. from platform, use one from tag
                    if ($key == 'shortDescription') {
                        continue;
                    }

                    $cleeng_content[$key] = $val;
                    $cleeng_content[$key]['sync'] = true;
                }
            } catch (Exception $e) {
            }
        }
    }
}

/**
 * Wordpress filter.
 * Replaces cleeng tags with cleeng layer markup in current post
 *
 * @global stdClass $post
 * @global array $cleeng_content
 * @param string $content
 * @return string
 */
function cleeng_add_layers( $content ) {
    global $post, $cleeng_content, $cleeng_preload_content, $cleeng;
    $expr = '/\[cleeng_content.*?id\s*=\s*(?:\"|&quot;)([\dt]+)(?:\"|&quot;).*?[^\\\]\](.*?[^\\\])\[\/cleeng_content\]/is';
    preg_match_all( $expr, $content, $m );

    if ( count( $m[1] ) ) {
        foreach ( $m[1] as $key => $contentId ) {
            $expr = '/\[cleeng_content.*?id\s*=\s*(?:\"|&quot;)' . $contentId . '(?:\"|&quot;).*?[^\\\]\](.*?[^\\\])\[\/cleeng_content\]/is';

            if ($contentId[0] == 't') {     // content was not created?
                if (current_user_can('edit_posts') || (defined('WP_DEBUG') && true == WP_DEBUG)) {
                    // give warning if in debug mode...
                    $msg = "<span style='color: red'>
                    <strong>Warning: Access to content fully blocked - Transactions can't be done.</strong><br />
                    Cleeng needs a reference of your protected content stored on the Cleeng server. Due to some reason
                    this is not properly saved. Please save this post/page again and make sure no errors are reported. [ERROR TEMPID]</span><br />";
                    $content = preg_replace($expr, $msg . ' $1', $content);
                } else {
                    // ...remove Cleeng tags if not
                    $content = preg_replace($expr, '$1', $content);
                }
                continue;
            }
            
            if (isset($cleeng_content[$contentId])) {

                if ($cleeng_preload_content && !$cleeng_content[$contentId]['sync']) {
                    $displayId = number_format((int)$contentId, 0, '', '.');
                    if (current_user_can('edit_posts') || (defined('WP_DEBUG') && true == WP_DEBUG)) {
                        $msg = "<span style='color: red'><strong>Warning: Access to content fully blocked - Transactions can't be done.</strong><br />
                                The protected content with ID $displayId is not known on the Cleeng servers. Please keep in mind that if you switched
                                server (In the Cleeng settings you can choose to connect to SANDBOX or PRODUCTION) you need to re-create the content,
                                as information is not exchanged between the two servers. [ERROR UNKOWNID]
                                </span><br />";

                        $content = preg_replace($expr, $msg . ' $1', $content);
                    } else {
                        $content = preg_replace($expr, '$1', $content);
                    }
                    continue;
                }

                $publisherInfo = '';
                if ($cleeng->isUserAuthenticated()) {
                    $user = $cleeng->getUserInfo();
                    if ($user['id'] == $cleeng_content[$contentId]['publisherId']) {
                        $publisherInfo = '<span class="cleeng-once" style="color: red">This article is revealed because you are logged in as its publisher.</span>';
                    }
                }

                $layer_markup = $publisherInfo
                              . cleeng_get_layer_markup(
                                        $post->ID,
                                        $m[2][$key],
                                        $cleeng_content[$contentId]
                                );
                $content = preg_replace( $expr,
                                str_replace('$', '&dollar;', $layer_markup),
                                $content );
                $content = str_replace('&dollar;', '$', $content);
            }
        }
    }
    return $content;
}
function getClassDisplayBlock($price, $itemType, $hasCookie, $hasSubscription, $contentId)
{
    global $cleeng;
    if ( $cleeng->isUserAuthenticated() ) {
        $info = $cleeng->getUserInfo();
        $hasCookie = true;
    } 
    
    

    if ($price == 0) {
        if ($itemType=='article') {
            $class = $hasCookie?'.read-for-free-'.$contentId:'.register-and-read-for-free-'.$contentId;
        } else if ($itemType=='video') {
            $class = $hasCookie?'.watch-for-free-'.$contentId:'.register-and-watch-for-free-'.$contentId;
        } else {
            $class = $hasCookie?'.access-for-free-'.$contentId:'.register-and-access-for-free-'.$contentId;            
        }
    } else {
        if ($hasCookie == true) {
            
            if ($price >= 0.99 || ($cleeng->isUserAuthenticated() && $info['freeContentViews']==0) ) {
                if ($itemType=='article') {
                    $class = '.buy-this-article-'.$contentId;

                } else if ($itemType=='video') {
                    $class = '.buy-this-video-'.$contentId;
                } else {
                    $class = '.buy-this-item-'.$contentId;           
                }     
            } else if($price < 0.99 && $cleeng->isUserAuthenticated() && $info['freeContentViews']>0) {
                if ($itemType=='article') {
                    $class = '.read-for-free-'.$contentId;
                } else if ($itemType=='video') {
                    $class = '.watch-for-free-'.$contentId;
                } else {
                    $class = '.access-for-free-'.$contentId;            
                }                   
            } else if($price < 0.99 ){
                if ($itemType=='article') {
                    $class = '.buy-this-article-'.$contentId;

                } else if ($itemType=='video') {
                    $class = '.buy-this-video-'.$contentId;
                } else {
                    $class = '.buy-this-item-'.$contentId;           
                }                    
            }
            
        } else {
            if ($price >= 0.99) {
                if ($itemType=='article') {

                    $class = '.buy-this-article-'.$contentId;

                } else if ($itemType=='video') {
                    $class = '.buy-this-video-'.$contentId;
                } else {
                    $class = '.buy-this-item-'.$contentId;           
                }     
            } else {            
                if ($itemType=='article') {
                    $class = '.register-and-read-for-free-'.$contentId;
                } else if ($itemType=='video') {
                    $class = '.register-and-watch-for-free-'.$contentId;
                } else {
                    $class = '.register-and-access-for-free-'.$contentId;            
                }       
            }
        }
    }
    return $class;
}

function setDisplayNone($contentId){
    $q = '.read-for-free-'.$contentId.', ';
    $q .= '.watch-for-free-'.$contentId.', ';
    $q .= '.access-for-free-'.$contentId.', ';
    $q .= '.register-and-read-for-free-'.$contentId.', ';
    $q .= '.register-and-watch-for-free-'.$contentId.', ';
    $q .= '.register-and-access-for-free-'.$contentId.', ';

    $q .= '.buy-this-article-'.$contentId.', ';
    $q .= '.buy-this-video-'.$contentId.', ';
    $q .= '.buy-this-item-'.$contentId.'{display:none}';
    return $q;
}


/**
 * Helper function
 * Outputs Cleeng Layer's HTML code
 */
function cleeng_get_layer_markup( $postId, $text, $content ) {
    global $cleeng;

    $options = get_option('cleeng_options');

    if (!isset($options['payment_method'])) {        
        $options['payment_method'] = 'cleeng-only';
    }

    $noCookie = (isset($_COOKIE['cleeng_user_auth']))?false:true;

    $hasCookie = (isset($_COOKIE['cleeng_user_auth']))?true:false;
    
    $auth = false;
    $userName = '';
    
    try {
        if ( $cleeng->isUserAuthenticated() ) {
            $info = $cleeng->getUserInfo();
            $userName = $info['name'];
            $auth = true;
        } 
    } catch (Exception $e) {
    }
    $referralRate = $purchased = $contentId = $publisherId = $publisherName = $itemType
             = $shortDescription = $averageRating = $price = $currencySymbol = $shortUrl
             = $referralProgramEnabled = $canVote = '';
    $subscriptionOffer = false;
    $subscriptionPrompt = 'Subscribe';
    extract( $content ); // contentId, shortDescription, price, purchased, shortUrl...    
    ob_start();
    
?>

    <?php if (!isset($options['show_prompt']) || $options['show_prompt']) : ?>
    <p class="cleeng-prompt"<?php if ($purchased) echo ' style="display:none"'; ?>>
        <span class="cleeng-firsttime"<?php if ($auth || !$noCookie) { echo  ' style="display:none"'; } ?>>
            <?php _e('The rest of this article is protected, use Cleeng to view it.', 'cleeng'); ?>
        </span>
        <span class="cleeng-nofirsttime"<?php if ($auth || $noCookie) { echo  ' style="display:none"'; } ?>>
            <?php _e('The rest of this article is protected, use Cleeng again to view it.', 'cleeng'); ?>
        </span>
        <span class="cleeng-auth"<?php if (!$auth) { echo  ' style="display:none"'; } ?>>
            <?php _e('The rest of this article is protected,', 'cleeng') ?>
            <span class="cleeng-username"><?php echo $userName ?></span>,
            <?php _e('click "buy" and view it instantly.', 'cleeng') ?>
        </span>
    </p>
    <?php endif ?>

    <div id="cleeng-layer-<?php echo $contentId ?>" class="cleeng-layer" <?php
    if ( $purchased ) {
        echo 'style="display:none"';
    }
?>>
    <div class="cleeng-layer-left"></div>


    <div class="cleeng-text">
        <div class="cleeng-publisher">
            <div class="cleeng-ajax-loader">&nbsp;</div>
            <img src="<?php echo $cleeng->getPublisherLogoUrl($publisherId); ?>"
                 alt="<?php echo $publisherName ?>"
                 title="<?php echo $publisherName ?>" />
        </div>
        <div class="cleeng-logo">
            <a href="http://cleeng.com/what-is-cleeng" target="_blank">
                <img src="<?php echo $cleeng->getLogoUrl( $contentId, 'cleeng-light' ) ?>" alt="Cleeng" />
            </a>
        </div>
        <div class="cleeng-noauth-bar"<?php
    if ( $auth ) {
        echo ' style="display:none"';
    } ?>>
            <span class="cleeng-welcome-firsttime"<?php if (!$noCookie) { echo ' style="display:none"'; } ?>>
                <?php _e('Already have a Cleeng account?', 'cleeng'); ?>
            </span>
            
            <span class="cleeng-welcome-nofirsttime"<?php if ($noCookie) { echo ' style="display:none"'; } ?>>
            <?php _e('Welcome back!', 'cleeng'); ?>
            </span>
            <a class="cleeng-hlink cleeng-login" href="javascript:">Log-in</a>
        </div>
        <div class="cleeng-auth-bar"<?php
             if ( ! $auth ) {
                 echo ' style="display:none"';
             }
?>>
            <a class="cleeng-hlink cleeng-logout" href="#"><?php _e('Logout', 'cleeng') ?></a>
            <?php
                echo sprintf(__('Welcome, <a class="cleeng-username" href="%s/my-account">%s</a>', 'cleeng'), $cleeng->getUrl(), $userName);
            ?>
        </div>
        <div class="cleeng-itemType cleeng-it-<?php echo $itemType ?>"></div>
        <h2 class="cleeng-description"><?php echo $shortDescription; ?></h2>
        <div class="cleeng-rating">
            <span><?php _e('Customer rating:', 'cleeng') ?></span>
            <div class="cleeng-stars cleeng-stars-<?php echo $averageRating ?>"></div>
        </div>

        <span class="cleeng-free-content-views" <?php echo 'style="display:none"' ?>>
            <?php _e('Good news! You still have <span></span> free purchase(s).', 'cleeng') ?>
        </span>
    </div>
    <div class="cleeng-text-bottom">
        <div class="cleeng-textBottom">
            <div class="cleeng-purchaseInfo-grad">
            </div>
            <div class="cleeng-purchaseInfo">
                <div class="cleeng-purchaseInfo-text">
                    
    
                    
                    <?php if ($options['payment_method'] == 'cleeng-only' || $price < 0.49) : ?>
                            
                            <?php 
                            
                            if($subscriptionOffer){
                                $middle = '';
                            } else{
                                $middle = 'middle';
                            }
                            
                            ?>
                    
                            <a class="cleeng-buy-wide button-small <?php echo $middle ?>  register-and-read-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>" href="#">
                                <?php _e('Register and read for free ', 'cleeng') ?>
                            </a>                                       

                            <a class="cleeng-buy-wide button-small <?php echo $middle ?>  register-and-watch-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>"  href="#">
                                <?php _e('Register and watch for free ', 'cleeng') ?>
                            </a>                                       

                            <a class="cleeng-buy-wide button-small <?php echo $middle ?>  register-and-access-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>"href="#">
                                <?php _e('Register and access for free ', 'cleeng') ?>
                            </a>                                       

                    
                            <a class="cleeng-buy-wide button-small <?php echo $middle ?>  read-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>"  href="#">
                                <?php _e('Read for free ', 'cleeng') ?>
                            </a>                                       

                            <a class="cleeng-buy-wide button-small <?php echo $middle ?>  watch-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>"   href="#">
                                <?php _e('Watch for free ', 'cleeng') ?>
                            </a>                                       

                            <a class="cleeng-buy-wide button-small <?php echo $middle ?>  access-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>" href="#">
                                <?php _e('Access for free ', 'cleeng') ?>
                            </a>                                       
                    
                            <?php 
                            
                            if($subscriptionOffer){
                                $button = 'button-small';
                            } else{
                                $button = 'button-small middle';
                            }
                            
                            ?>
                    
                            <a class="cleeng-buy-wide <?php echo $button ?>  buy-this-article-<?php echo $contentId ?>" href="#">
                                <?php _e('Buy this article ', 'cleeng') ?>
                                <?php echo $currencySymbol ?><span><?php echo number_format($price, 2); ?></span>
                            </a>                                       

                            <a class="cleeng-buy-wide <?php echo $button ?>  buy-this-video-<?php echo $contentId ?>"  href="#">
                                <?php _e('Buy this video ', 'cleeng') ?>
                                <?php echo $currencySymbol ?><span><?php echo number_format($price, 2); ?></span>
                            </a>                                       

                            <a  class="cleeng-buy-wide <?php echo $button ?>   buy-this-item-<?php echo $contentId ?>"  href="#">
                                <?php _e('Buy this item ', 'cleeng') ?>
                                <?php echo $currencySymbol ?><span><?php echo number_format($price, 2); ?></span>
                            </a>                                       

                    
                            <?php if($subscriptionOffer): ?>
                                <a id="cleeng-subscribe-<?php echo $contentId ?>" class="cleeng-subscribe" href=""  style="display:none"><?php echo $subscriptionPrompt ?></a>                           
                            <?php endif; ?>
                    
                            <?php $class = getClassDisplayBlock($price,$itemType, $hasCookie, $subscriptionOffer, $contentId) ?>

                                <style>
                                    <?php echo setDisplayNone($contentId); ?>
                                    <?php echo $class ?> {
                                        display:block;
                                    }
                                    <?php if ($subscriptionOffer) { ?>
                                    #cleeng-subscribe-<?php echo $contentId ?> {
                                        display:block !important;
                                    }
                                    <?php } ?>
                                </style>    
                                
                            <!--
                            <a class="cleeng-buy-wide cleeng-firsttime"<?php if (!$noCookie) { echo ' style="display:none"'; } ?> href="#">
                                <?php _e('To view this ', 'cleeng') ?>eif ($options['payment_method'] == 'paypal-only' && $price >= 0.49) : <?php _e($itemType, 'cleeng') ?>,<br />
                                <?php _e('Sign-up for free in 1-Click', 'cleeng') ?>
                            </a>                    
                    
                    
                            <a class="cleeng-buy-wide cleeng-nofirsttime"<?php if ($noCookie) { echo ' style="display:none"'; } ?> href="#">
                                <?php _e(sprintf('To view this %s,<br />Please sign-in', __($itemType, 'cleeng')), 'cleeng') ?>
                            </a>

                    
                            <?php if (round($price,2) != 0) : ?>
                                <div id="cleeng-price-<?php echo $contentId ?>" class="cleeng-price" style="display: none"><?php echo $currencySymbol ?><span><?php echo number_format($price, 2); ?></span></div>
                            <?php else : ?>
                                <div class="cleeng-price" style="display: none"><span><?php _e('Free', 'cleeng'); ?></span></div>
                            <?php endif ?>    
                        -->
                                
                                
                                

                    <?php elseif ($options['payment_method'] == 'paypal-only' && $price >= 0.49) : ?>

                            <div class="cleeng-price-paypal"><?php echo $currencySymbol ?><span><?php echo number_format($price, 2); ?></span></div>
                            <a href="#" class="cleeng-pay-with-paypal" id="cleeng-paypal-<?php echo $contentId ?>">
                                <img alt="<?php _e('Pay with <em>PayPal</em>'); ?>" src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_xpressCheckout.gif" />
                            </a>
                    <?php endif ?>  
                            
                            
                            
                </div>
            </div>
            <div class="cleeng-whatsCleeng">
                <?php
                    echo sprintf(__('What is <a href="%s/what-is-cleeng">Cleeng?</a>', 'cleeng'), $cleeng->getUrl());
                ?>
            </div>
        </div>
    </div>
    

    <div class="cleeng-layer-right"></div>
</div>
<script type="text/javascript">
    // <![CDATA[
    jQuery('#cleeng-layer-<?php echo $contentId ?>').data('postId', <?php echo $postId ?>);
    // ]]>
</script>
<div id="cleeng-nolayer-<?php echo $contentId ?>" class="cleeng-nolayer" <?php
             if ( ! $purchased ) {
                 echo 'style="display:none"';
             }
?>>
            <div class="cleeng-nolayer-top">
                <a href="http://cleeng.com">
                    <img src="<?php echo CLEENG_WP_PLUGIN_PATH ?>img/cleeng-small.png" alt="Cleeng: Instant access to quality content" />
                </a>
                <div class="cleeng-auth-bar">
                    <a class="cleeng-hlink cleeng-logout" href="#">
                        <?php _e('Logout', 'cleeng') ?>
                    </a>
                    <?php
                        echo sprintf(__('Welcome, <a class="cleeng-username" href="%s/my-account">%s</a>', 'cleeng'), $cleeng->getUrl(), $userName);
                    ?>
                </div>
            </div>

            <div class="cleeng-content">
<?php
             if ( $purchased ) {
                 echo $text;
             }
?>
         </div>

         <div class="cleeng-nolayer-bottom">
             
            <span class="cleeng-rate"<?php if ( !$canVote ) echo ' style="display:none"'; ?>>
                <?php _e('Rate:', 'cleeng') ?>
                <a href="#" class="cleeng-icon cleeng-vote-liked">&nbsp;</a>
                <a href="#" class="cleeng-icon cleeng-vote-didnt-like">&nbsp;</a>
            </span>
            <span class="cleeng-rating"<?php if ( $canVote ) echo ' style="display:none"'; ?>>
                <?php _e('Customer rating:', 'cleeng') ?>
                <span class="cleeng-stars cleeng-stars-<?php echo $averageRating ?>"></span>
            </span>
            <span class="cleeng-share">
                <?php _e('Share:', 'cleeng') ?>
                <a class="cleeng-icon cleeng-facebook" href="#">&nbsp;</a>
                <a class="cleeng-icon cleeng-twitter" href="#">&nbsp;</a>
                <a class="cleeng-icon cleeng-email" href="mailto:?subject=&amp;body=">&nbsp;</a>
                <span class="cleeng-referral-url-label">URL:</span>
                <span class="cleeng-referral-url"><?php echo empty($referralUrl)?$shortUrl:$referralUrl ?></span>
                <span class="cleeng-icon cleeng-copy">&nbsp;</span>
            </span>
            <span class="cleeng-referral-rate"<?php if ( ! $referralProgramEnabled ) echo ' style="display:none"'; ?>>
                <?php
                    echo sprintf(__('Earn: <span>%s%%</span> commission', 'cleeng'), round($referralRate*100));
                ?>
            </span>
          </div>
      </div>
<?php
    $cleengLayer = ob_get_contents();
    ob_end_clean();

    return $cleengLayer;
}
