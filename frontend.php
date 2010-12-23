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

/**
 * If set to true, plugin will fetch content information on server side,
 * before page is displayed.
 */
$cleeng_preload_content = true;

/**
 * Will be set to true if any cleeng items should be displayed in The Loop
 */
$cleeng_has_content = false;

wp_enqueue_script( 'CleengFEWidgetWP', CLEENG_WP_PLUGIN_PATH . 'js/CleengFEWidgetWP.js', array( 'jquery' ) );
wp_enqueue_style( 'cleengFEWidget', CLEENG_WP_PLUGIN_PATH . 'css/cleengFEWidget.css' );

add_action( 'wp_print_footer_scripts', 'cleeng_autologin_script' );
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
    global $cleeng;

    if ( $cleeng_has_content && ! $cleeng->isUserAuthenticated() ) {
        echo '<script type="text/javascript" src="'
        . $cleeng->getAutologinScriptUrl()
        . '"></script>';
    }
}

/**
 * Wordpress action.
 * Output javascript code setting Cleeng Plugin URL
 */
function cleeng_script_head() {
    echo '
    <script type="text/javascript">
        Cleeng_PluginPath = "' . CLEENG_WP_PLUGIN_PATH . '";
    </script>
	';
}

/**
 * Wordpress action.
 * Hook to Wordpress loop. It scans every post and searches for Cleeng
 * items. Then it fetches information about the content using WebAPI
 *
 * @global array $posts
 * @global array $cleeng_content
 * @global boolean $cleeng_preload_content
 * @global CleengClient $cleeng
 */
function cleeng_loop_start() {
    global $posts, $cleeng_content, $cleeng_preload_content, $cleeng, $cleeng_has_content;

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
            $expr = '/(\w+)\s*=\s*\"(.*?[^\\\])\"/si';
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
                'shortDescription' => @$params['shortDescription'],
                'price' => @$params['price'],
                'type' => 'article',
                'purchased' => false,
                'shortUrl' => '',
                'referred' => false,
                'referralProgramEnabled' => false,
                'rated' => false
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
        // WP Super Cache - should be disabled for pages with Cleeng
        define( 'DONOTCACHEPAGE', true );

        /**
         * End of compatibility code
         */
    } else {
        return;
    }

    // we have found all cleeng items on current page. Now let's use Cleeng
    // API to get content information    
    if ( $cleeng_preload_content ) {
        $ids = array_keys( $cleeng_content );
        $contentInfo = $cleeng->getContentInfo( $ids );
        foreach ( $contentInfo as $key => $val ) {
            $cleeng_content[$key] = $val;
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
    global $post, $cleeng_content;
    $expr = '/\[cleeng_content.*?id\s*=\s*\"(\d+)\".*?[^\\\]\](.*?[^\\\])\[\/cleeng_content\]/is';
    preg_match_all( $expr, $content, $m );

    if ( count( $m[1] ) ) {
        foreach ( $m[1] as $key => $contentId ) {

            $expr = '/\[cleeng_content.*?id\s*=\s*\"' . $contentId . '\".*?[^\\\]\].*?[^\\\]\[\/cleeng_content\]/is';
            $content = preg_replace( $expr,
                            cleeng_get_layer_markup(
                                    $post->ID,
                                    $m[2][$key],
                                    $cleeng_content[$contentId]
                            ),
                            $content );
        }
    }
    return $content;
}

/**
 * Helper function
 * Outputs Cleeng Layer's HTML code
 */
function cleeng_get_layer_markup( $postId, $text, $content ) {
    global $cleeng;

    if ( $cleeng->isUserAuthenticated() ) {
        $info = $cleeng->getUserInfo();
        $userName = $info['name'];
        $auth = true;
    } else {
        $auth = false;
        $userName = '';
    }
    extract( $content ); // contentId, shortDescription, price, purchased, shortUrl...
    ob_start();
?>
    <div id="cleeng-layer-<?php echo $contentId ?>" class="cleeng-layer" <?php
    if ( $purchased ) {
        echo 'style="display:none"';
    }
?>>
        <div class="cleeng-logo">
            <img src="<?php echo $cleeng->getLogoUrl( $contentId, 'cleeng-tagline', 630 ) ?>" alt="Cleeng" />
    </div>
    <div class="cleeng-text">
        <div class="cleeng-itemType"></div>
        <div class="cleeng-title"><?php echo the_title() . ' | ' . get_bloginfo( 'title' ) ?></div>
        <h2 class="cleeng-description"><?php echo $shortDescription ?></h2>
        <div class="cleeng-noauth"<?php
    if ( $auth ) {
        echo ' style="display:none"';
    } ?>>
            <a class="cleeng-hlink cleeng-login" href="javascript:">Log-in</a>
            or
            <a class="cleeng-hlink" target="blank" href="<?php echo $cleeng->getUrl() . '/register' ?>">Register</a>
            to reveal this content
        </div>
        <div class="cleeng-auth"<?php
             if ( ! $auth ) {
                 echo ' style="display:none"';
             }
?>>
            Welcome, <a target="_blank" class="cleeng-username" href="<?php echo $cleeng->getUrl() . '/my-account' ?>"><?php echo $userName ?></a>.
            <a class="cleeng-hlink cleeng-logout" href="#">Logout</a>
            <span class="cleeng-free-content-views">
                You have <span></span> free purchase left (out of 5).
            </span>
        </div>
        <div class="cleeng-rating">
            Cleeng user rating <a class="cleeng-hlink" target="_blank" href="#" title="This is the average rating from Cleeng users who bought this content.">?</a>
            <div class="cleeng-stars cleeng-stars-0"></div>
        </div>

        <div class="cleeng-textBottom">
            <div class="cleeng-purchaseInfo">
                <img class="cleeng-shoppingCart" src="<?php echo CLEENG_WP_PLUGIN_PATH ?>img/shopping-cart.png" alt="Shopping cart" />
                <a class="cleeng-buy" href="#"><img src="<?php echo CLEENG_WP_PLUGIN_PATH ?>img/buy-button.png" width="96" height="39" alt="Buy" /></a>
                <div class="cleeng-price"><?php echo $currencySymbol ?><span><?php echo $price; ?></span></div>
            </div>
            <div class="cleeng-whatsCleeng">
                What is <a href="http://cleeng.com">Cleeng?</a>
            </div>
        </div>
    </div>
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
                <img src="<?php echo CLEENG_WP_PLUGIN_PATH ?>img/cleeng-small.png" alt="Cleeng: Instant access to quality content" />
                <div class="cleeng-auth">
                    Welcome, <a class="cleeng-username" href="<?php echo $cleeng->getUrl() . '/my-account' ?>"><?php echo $userName ?></a>.
                    <a class="cleeng-hlink cleeng-logout" href="#">Logout</a>
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
             <div class="cleeng-rating">
                 Cleeng user rating:
             </div>
             <div class="cleeng-stars cleeng-stars-0"></div>

             <div class="cleeng-share"<?php
             if ( ! $referred ) {
                 echo 'style="display:none"';
             }
?>>
            <a class="cleeng-facebook" href="#">
                <img src="<?php echo CLEENG_WP_PLUGIN_PATH . 'img/facebook.png' ?>"  alt="Share on Facebook" title="Share on Facebook" />
            </a>
            <a class="cleeng-twitter" href="#">
                <img src="<?php echo CLEENG_WP_PLUGIN_PATH . 'img/twitter.png' ?>"  alt="Share on Twitter" title="Share on Twitter" />
              </a>
              &nbsp;
              <span>Referral URL:</span>
              <span class="cleeng-referral-url"><?php echo $shortUrl ?></span>
             </div>
             <a href="#" class="cleeng-vote-liked"<?php
             if ( ! $canVote ) {
                 echo ' style="display:none"';
             } ?>>
            <img src="<?php echo CLEENG_WP_PLUGIN_PATH . 'img/up.png' ?>" alt="Click here if you liked this content" title="Click here if you liked this content"/>
        </a>
        <a href="#" class="cleeng-vote-didnt-like"<?php
              if ( ! $canVote ) {
                  echo ' style="display:none"';
              } ?>>
               <img src="<?php echo CLEENG_WP_PLUGIN_PATH . 'img/down.png' ?>" alt="Click here if you didn't like this content" title="Click here if you didn't like this content"/>
           </a>
           <span class="cleeng-referral-rate"<?php if ( ! $referralProgramEnabled )
                  echo ' style="display:none"'; ?>>
               Referral rate:
               <span><?php if ( $referralProgramEnabled )
                  echo round( $referralRate ) . '%'; ?></span>
                      </span>
                      <a href="#" class="cleeng-it"<?php
              if ( $referred ) {
                  echo ' style="display:none"';
              }
?>>
                          Cleeng It!
                      </a>
                  </div>
              </div>
<?php
              $cleengLayer = ob_get_contents();
              ob_end_clean();

              return $cleengLayer;
          }