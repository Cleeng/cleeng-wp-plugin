<?php

class Cleeng_Frontend
{

    /**
     * Cleeng content is available on current site?
     * 
     * @var bool
     */
    protected $cleeng_has_content;

    /**
     * Currently authenticated user
     * @var array
     */
    protected $cleeng_user;

    /**
     * List of Cleeng content to be rendered
     *
     * @var array
     */
    protected $cleeng_content;

    /**
     * Whether to call getContentInfo API when page is loaded or not.
     * Right now it won't work if set to false.
     *
     * @var bool
     */
    protected $cleeng_preload_content = true;

    /**
     * Setup frontent
     */
    public function setup()
    {
        $options = Cleeng_Core::get_config();

        // Javascripts and styles
        wp_enqueue_script( 'ZeroClipboard', CLEENG_PLUGIN_URL . 'js/ZeroClipboard.js', array( 'jquery' ) );

        if (defined('WPLANG') && WPLANG) {
            $clientLang = WPLANG;
        } else {
            $clientLang = 'en_US';
        }

        wp_enqueue_script( 'CleengClient', 'https://' . $options['platformUrl'] . '/js-api/client.' . $clientLang . '.js' );

        wp_enqueue_script( 'CleengFEWidgetWP', CLEENG_PLUGIN_URL . 'js/CleengFEWidgetWP.js', array( 'jquery' ) );
        wp_enqueue_style( 'cleengFEWidget', CLEENG_PLUGIN_URL . 'css/cleengFEWidget.css' );

        // register action hooks
        add_action( 'wp_print_footer_scripts', array($this, 'action_wp_print_footer_scripts') );
        add_action( 'wp_footer', array($this, 'action_wp_footer') );
        add_action( 'wp_head', array($this, 'action_wp_head') );
        add_action( 'loop_start', array($this, 'action_loop_start') );

        // register the_content filter - it will parse posts and look for Cleeng tags
        add_filter( 'the_content', array($this, 'cleeng_add_layers'), 100 );
    }

    /**
     * Wordpress action.
     * Output <script> markup for autologin functionality
     *
     * @global boolean $this->cleeng_has_content
     * @global CleengClient $cleeng
     */
    public function action_wp_print_footer_scripts() {
        $cleeng = Cleeng_Core::load('Cleeng_Client');

        if ( $this->cleeng_has_content && ! $this->cleeng_user ) {
            /*echo '<script type="text/javascript" src="'
            . $cleeng->getAutologinScriptUrl()
            . '"></script>';*/
        }

    }

    public function action_wp_head() {
        $options = Cleeng_Core::get_config();

        if ($options['payment_method'] === 'paypal-only') {
            echo '<script src ="https://www.paypalobjects.com/js/external/dg.js" type="text/javascript"></script>';
        }
        echo
        '<script type="text/javascript">
        // <![CDATA
            var Cleeng_PluginPath = "' . CLEENG_PLUGIN_URL . '";
            CleengClient.init({
                "channelURL": Cleeng_PluginPath + "/channel.html",
                 "appId": "' . $options['appId'] . '"
             });
        // ]]>
        </script> ';
    }

    /**
     * Wordpress action.
     * Output javascript code setting Cleeng Plugin URL
     */
    public function action_wp_footer() {
        echo
        '<script type="text/javascript">
        // <![CDATA
        jQuery(function() { ';
        if ( $this->cleeng_user ) {
            echo 'CleengWidget.userInfo = ' . json_encode($this->cleeng_user) , ";\n";
        }
        if ( $this->cleeng_content && count($this->cleeng_content) ) {
            echo 'CleengWidget.contentInfo = ' . json_encode($this->cleeng_content) , ";\n";
        }
        if (!$this->cleeng_preload_content) {
            echo "CleengWidget.getUserInfo();\n";
        }
        echo
            'CleengWidget.init();
        });
        // ]]>
        </script> ';
        ?>

<div class="cleeng_overlay_content" id="what-is-cleeng" style="display: none">
    <div class="cleeng_overlay_header"><?php echo __('Cleeng in 1 minute', 'cleeng'); ?></div>

    <iframe id="cleeng-movie" style="display:none;" width="410" height="230" frameborder="0" webkitAllowFullScreen allowFullScreen></iframe>
    <div id="cleeng-play-movie">&nbsp;</div>
    <ul>
        <li><?php echo __('Instant delivery & access', 'cleeng'); ?></li>
        <li><?php echo __('All your content in 1 place', 'cleeng'); ?></li>
        <li><?php echo __('Safe & secure', 'cleeng'); ?>
        <img style="vertical-align: top;margin:5px 0 0 10px" src="<?php echo CLEENG_PLUGIN_URL ?>img/lock2.png">
        </li>
        <li><img style="vertical-align: middle;width:175px;" src="<?php echo CLEENG_PLUGIN_URL ?>img/payment-methods-small.png"></li>
        <li><?php echo __('Your privacy is protected', 'cleeng'); ?></li>
    </ul>
</div>
<script type="text/javascript">
jQuery(function() {
    jQuery('#cleeng-play-movie').click(function() {
        jQuery(this).hide();
        jQuery('#cleeng-movie').attr('src', 'http://player.vimeo.com/video/19256404?title=0&byline=0&portrait=0&color=0fa343&autoplay=1');
        jQuery('#cleeng-movie').show();
        return false;
    });
});
</script>

        <?php
    }

    /**
     * Wordpress action.
     * Hook to Wordpress loop. It scans every post and searches for Cleeng
     * items. Then it fetches information about the content using WebAPI
     *
     * @global array $posts
     */
    public function action_loop_start() {
        global $posts;

        $cleeng = Cleeng_Core::load('Cleeng_Client');

        $this->cleeng_content = array( );
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

                $this->cleeng_content[$params['id']] = $content;
            }
        }

        if ( count( $this->cleeng_content ) ) {
            $this->cleeng_has_content = true;

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
        if ( $this->cleeng_preload_content ) {

            try {
                if ($cleeng->isUserAuthenticated()) {
                    $this->cleeng_user = $cleeng->getUserInfo();
                }
            } catch (Exception $e) {
                $this->cleeng_preload_content = false;
                return;
            }
            $ids = array_keys( $this->cleeng_content );

            $contentInfoIds = array();
            foreach ($ids as $key => $value) {
                if (is_numeric($value)) {
                    $contentInfoIds[] = $value;
                }
            }
            if (count($contentInfoIds)) {
                try {
//                    throw new Exception("test");
                    $contentInfo = $cleeng->getContentInfo( $contentInfoIds );
                    
                    foreach ( $contentInfo as $key => $val ) {

                        if (!is_array($val)) {
                            continue;
                        }

                        $this->cleeng_content[$key] = $val;
                        $this->cleeng_content[$key]['sync'] = true;
                    }
                } catch (Exception $e) {
                    $this->cleeng_preload_content = false;
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
        global $post;
        $cleeng = Cleeng_Core::load('Cleeng_Client');
        $expr = '/\[cleeng_content.*?id\s*=\s*(?:\"|&quot;)([\dt]+)(?:\"|&quot;).*?[^\\\]\](.*?[^\\\])\[\/cleeng_content\]/is';
        preg_match_all( $expr, $content, $m );

        if ( count( $m[1] ) ) {
            foreach ( $m[1] as $key => $contentId ) {
                $expr = '/\[cleeng_content.*?id\s*=\s*(?:\"|&quot;)' . $contentId . '(?:\"|&quot;).*?[^\\\]\](.*?[^\\\])\[\/cleeng_content\]/is';

                if ($contentId[0] == 't') {     // content was not created?
                    if (current_user_can('edit_posts') || (defined('WP_DEBUG') && true == WP_DEBUG)) {
                        // give warning if in debug mode...
                        $msg = "<span style='color: red'>
                        <strong>Warning: access to protected section is blocked - transactions can't be done with Cleeng</strong>.
                        Please save this post/page again and make sure no errors are reported. (This warning is only visible
                        to you, because you are logged into WP admin.)
                        </span><br />";
                        $content = preg_replace($expr, $msg . ' $1', $content);
                    } else {
                        // ...remove Cleeng tags if not
                        $content = preg_replace($expr, '$1', $content);
                    }
                    continue;
                }

                if (isset($this->cleeng_content[$contentId])) {

                    if ($this->cleeng_preload_content && !$this->cleeng_content[$contentId]['sync']) {
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
                        if ($user['id'] == $this->cleeng_content[$contentId]['publisherId']) {
                            $publisherInfo = '<span class="cleeng-once" style="color: red">This article is revealed because you are logged in as its publisher.</span>';
                        }
                    }

                    $layer_markup = $publisherInfo
                                  . $this->get_layer_markup(
                                            $post->ID,
                                            $m[2][$key],
                                            $this->cleeng_content[$contentId]
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
        $cleeng = Cleeng_Core::load('Cleeng_Client');

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
function get_layer_markup( $postId, $text, $content ) {
    
        $cleeng = Cleeng_Core::load('Cleeng_Client');

        $options = Cleeng_Core::get_config();

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
                <?php _e('This article is exclusive, use Cleeng to view it in full.', 'cleeng'); ?>
            </span>
            <span class="cleeng-nofirsttime"<?php if ($auth || $noCookie) { echo  ' style="display:none"'; } ?>>
                <?php _e('The rest of this article is exclusive, use Cleeng again to view it.', 'cleeng'); ?>
            </span>
            <span class="cleeng-auth"<?php if (!$auth) { echo  ' style="display:none"'; } ?>>
                <?php _e('The rest of this article is exclusive,', 'cleeng') ?>
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
        <div class="cleeng-protected-content">Exclusive content</div>
        <div class="cleeng-layer-left"></div>

        <div class="cleeng-text">
            <?php /*
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
            */ ?>
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

            <?php /*
            <div class="cleeng-itemType cleeng-it-<?php echo $itemType ?>"></div>
            */ ?>
            <div class="cleeng-publisher">
                <div class="cleeng-ajax-loader">&nbsp;</div>
                <img src="<?php echo $cleeng->getPublisherLogoUrl($publisherId); ?>"
                     alt="<?php echo $publisherName ?>"
                     title="<?php echo $publisherName ?>" />
            </div>


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
                                    $middle = 'cleeng-middle';
                                }
                                ?>

                                <div class="cleeng-button <?php echo $middle ?>  register-and-read-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>">
                                    <?php _e('Register and read for free ', 'cleeng') ?>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  register-and-watch-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>">
                                    <?php _e('Register and watch for free ', 'cleeng') ?>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  register-and-access-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>">
                                    <?php _e('Register and access for free ', 'cleeng') ?>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  read-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>">
                                    <?php _e('Read for free ', 'cleeng') ?>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  watch-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>">
                                    <?php _e('Watch for free ', 'cleeng') ?>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  access-for-free-<?php echo $contentId ?> by-free-<?php echo $contentId ?>">
                                    <?php _e('Access for free ', 'cleeng') ?>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  buy-this-article-<?php echo $contentId ?>">
                                    <?php _e('Buy this article ', 'cleeng') ?>
                                    <span class="cleeng-price"><?php echo $currencySymbol ?><?php echo number_format($price, 2); ?></span>
                                </div>

                                <div class="cleeng-button <?php echo $middle ?>  buy-this-video-<?php echo $contentId ?>">
                                    <?php _e('Buy this video ', 'cleeng') ?>
                                    <span class="cleeng-price"><?php echo $currencySymbol ?><?php echo number_format($price, 2); ?></span>
                                </div>

                                <div  class="cleeng-button <?php echo $middle ?>   buy-this-item-<?php echo $contentId ?>">
                                    <?php _e('Buy this item ', 'cleeng') ?>
                                    <span class="cleeng-price"><?php echo $currencySymbol ?><?php echo number_format($price, 2); ?></span>
                                </div>

                                <div id="cleeng-subscribe-<?php echo $contentId ?>" class="cleeng-subscribe cleeng-button"
                                     style="display:<?php echo $subscriptionOffer?'block':'none' ?>"><?php echo $subscriptionPrompt ?>
                                </div>

                                <?php $class = $this->getClassDisplayBlock($price,$itemType, $hasCookie, $subscriptionOffer, $contentId) ?>
                                    <style>
                                        <?php echo $this->setDisplayNone($contentId); ?>
                                        <?php echo $class ?> {
                                            display:block;
                                        }
                                    </style>

                        <?php elseif ($options['payment_method'] == 'paypal-only' && $price >= 0.49) : ?>
                                <div class="cleeng-price-paypal"><?php echo $currencySymbol ?><span><?php echo number_format($price, 2); ?></span></div>
                                <a href="#" class="cleeng-pay-with-paypal" id="cleeng-paypal-<?php echo $contentId ?>">
                                    <img alt="<?php _e('Pay with PayPal'); ?>" src="<?php echo CLEENG_PLUGIN_URL ?>img/btn_xpressCheckout.gif" />                                    
                                </a>
                                <?php if($content['subscriptionOffer']): ?>
                                <div class="prompt" style="float:left; text-align: center;">
                                    <div style="float:left;margin-left:5px;color:#666"><?php _e('Or') ?>, &nbsp;</div>
                                    <div id="cleeng-subscribe-<?php echo $contentId ?>" class="cleeng-subscribe cleeng-a" style="float:left;">
                                        <?php echo $subscriptionPrompt ?>
                                    </div>
                                    
                                </div>    
                                <?php endif; ?>
                        <?php endif ?>

                    </div>
                </div>
                <div class="cleeng-whatsCleeng" style="width:171px">
                     <div style="cursor:pointer;width:155px;line-height:12px;background: url('<?php echo $cleeng->getLogoUrl( $contentId, 'cleeng-small' )?>') no-repeat right top" href="<?php echo $cleeng->getUrl() ?>/what-is-cleeng">Powered by</div>    
                     
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
                        <img src="<?php echo CLEENG_PLUGIN_URL ?>img/cleeng-small.png" alt="Cleeng: Instant access to quality content" />
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

                <span  class="cleeng-rate"<?php if ( !$canVote ) echo ' style="display:none"'; ?>>
                    <?php _e('Rate:', 'cleeng') ?>
                    <a href="#" class="cleeng-icon cleeng-vote-liked">&nbsp;</a>
                    <a href="#" class="cleeng-icon cleeng-vote-didnt-like">&nbsp;</a>
                </span>
                <span style="float:left" class="cleeng-rating"<?php if ( $canVote ) echo ' style="display:none"'; ?>>
                    <!--<?php _e('Customer rating:', 'cleeng') ?>-->
                    <span class="cleeng-stars cleeng-stars-<?php echo $averageRating ?>"></span>
                </span>
                <span class="cleeng-share">
                    <!--<?php _e('Share:', 'cleeng') ?>-->
                    <a class="cleeng-icon cleeng-facebook" href="#">&nbsp;</a>
                    <a class="cleeng-icon cleeng-twitter" href="#">&nbsp;</a>
                    <a class="cleeng-icon cleeng-email" href="mailto:?subject=&amp;body=">&nbsp;</a>
                    <!--<span class="cleeng-referral-url-label">URL:</span>-->
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

}