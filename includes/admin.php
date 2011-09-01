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
 * Administrator panel functionality
 */

if ( ! session_id() )
    session_start();

wp_enqueue_script( 'jquery-ui-dialog' );


global $wp_version;

/**
 * Cleeng For Wordpress comes with additional jQuery UI widgets: slider
 * and datepicker.
 * Up to version 3.1 Wordpress provides jQuery UI library 1.7.X, since 3.1
 * they moved to 1.8. We need to bundle both versions of widgets to provide
 * compatibility.
 */
if (version_compare($wp_version, '3.1', '<')) {
    wp_enqueue_script( 'jquery-ui-slider', CLEENG_WP_PLUGIN_PATH . 'js/ui.slider.min-1.7.3.js', array( 'jquery-ui-core' ), false, true );
    wp_enqueue_script( 'jquery-ui-datepicker', CLEENG_WP_PLUGIN_PATH . 'js/ui.datepicker.min-1.7.3.js', array( 'jquery-ui-core' ), false, true );
} else {
    wp_enqueue_script( 'jquery-ui-slider', CLEENG_WP_PLUGIN_PATH . 'js/ui.slider.min-1.8.10.js', array( 'jquery-ui-core' ), false, true );
    wp_enqueue_script( 'jquery-ui-datepicker', CLEENG_WP_PLUGIN_PATH . 'js/ui.datepicker.min-1.8.10.js', array( 'jquery-ui-core' ), false, true );
}
wp_enqueue_script( 'jquery-ui-timepicker', CLEENG_WP_PLUGIN_PATH . 'js/ui.timepicker.min.js', array( 'jquery-ui-datepicker' ), false, true );

wp_enqueue_script( 'functions', CLEENG_WP_PLUGIN_PATH . 'js/functions.js', array(), false, true );

add_action( "admin_head-post.php", 'cleeng_load_scripts' );
add_action( "admin_head-page.php", 'cleeng_load_scripts' );
add_action( "admin_head-post-new.php", 'cleeng_load_scripts' );
add_action( "admin_head-page-new.php", 'cleeng_load_scripts' );
add_action( 'save_post', 'cleeng_parse_post' );
add_action( 'admin_menu', 'cleeng_add_custom_box' );
add_action( 'admin_notices', 'cleeng_admin_notices' );

//add_action('admin_menu', 'cleeng_plugin_menu');
add_filter('plugin_action_links', 'cleeng_settings_link', 10, 2 );

add_action('admin_menu', 'cleeng_settings');


function cleeng_settings()
{
    register_setting( 'cleeng', 'cleeng_options');

    add_settings_section('cleeng_payment_method', __('Payment activation mechanism', 'cleeng'), 'cleeng_settings_payment_method_description', 'cleeng');
    add_settings_section('cleeng_prompt', __('Text above layer', 'cleeng'), 'cleeng_settings_prompt_description', 'cleeng');
    add_settings_section('cleeng_environment', __('Choose LIVE or SANDBOX', 'cleeng'), 'cleeng_settings_environment_description', 'cleeng');
    add_settings_field('environment', '', 'cleeng_settings_environment_render', 'cleeng', 'cleeng_environment');
    add_settings_field('show_prompt', '', 'cleeng_settings_show_prompt_render', 'cleeng', 'cleeng_prompt');
    add_settings_field('payment_method', '', 'cleeng_settings_payment_method_render', 'cleeng', 'cleeng_payment_method');

    add_menu_page(__('Cleeng', 'cleeng'), __('Cleeng', 'cleeng'), false, 'cleeng-menu', 'cleeng', CLEENG_WP_PLUGIN_PATH.'/img/cleengit-small.png');
    add_submenu_page( 'cleeng-menu', __('What is Cleeng?', 'cleeng'),__('What is Cleeng?', 'cleeng'), 'manage_options', 'cleeng/what-is-cleeng', 'cleeng_page_what_is_cleeng');
    
    
    add_submenu_page( 'cleeng-menu', __('Quick-start guide', 'cleeng'), __('Quick-start guide', 'cleeng'), 'manage_options', 'cleeng/quick-start-guide', 'cleeng_page_quick_start_guide');
    add_submenu_page( 'cleeng-menu', __('Settings to manage', 'cleeng'), __('Settings to manage', 'cleeng'), 'manage_options', 'cleeng/settings', 'cleeng_page_settings');


}
add_action( 'admin_menu' , 'admin_menu_new_items' );
function admin_menu_new_items() {
    global $submenu;
    $submenu["cleeng-menu"][] = array( __('<div class="external support">Support & FAQ</div>', 'cleeng'), 'manage_options' , 'https://support.cleeng.com/home' ); 
    $submenu["cleeng-menu"][] = array( __('<div class="external monetization">Monetization tips</div>', 'cleeng'), 'manage_options' , 'http://monetizecontent.org' ); 
    $submenu["cleeng-menu"][] = array( __('<div class="external demos">Demos</div>', 'cleeng'), 'manage_options' , 'http://cleeng.com/features/demos' ); 
}  


function cleeng_page_what_is_cleeng()
{
    require dirname(__FILE__) . '/pages/what_is_cleeng.php';
}

function cleeng_page_quick_start_guide()
{
    require dirname(__FILE__) . '/pages/quick_start_guide.php';
}

function cleeng_page_settings()
{
 
    
    require dirname(__FILE__) . '/pages/settings.php';
}


function cleeng_page_demos()
{
        global $cleeng;
?>  
<script type="text/javascript">
    window.location = '<?php echo $cleeng->getUrl() ?>/features/demos';
</script>   
<?php    
}

/**
 * Display "settings" link next to "deactivate"
 *
 * @param array $links
 * @param string $file
 * @return array
 */
function cleeng_settings_link($links, $file)
{
    $this_plugin = plugin_basename(realpath(dirname(__FILE__) . '/../cleengWP.php'));
    if ($file == $this_plugin) {
        $settings_link = '<a href="options-general.php?page=cleeng">' . __("Settings", "cleeng") . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}

/**
 * Available settings in Options page:
 * - whether to display prompt message just before the layer
 * - which environment should be used: production or sandbox?
 */

function cleeng_settings_page() {
    global $cleeng;
    $noCookie = (isset($_COOKIE['cleeng_user_auth']))?false:true;
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
    cleeng_load_scripts();
    ?>
<div class="wrap">
    <h2><?php _e('Cleeng For WordPress Settings'); ?></h2>
    <div class="cleeng-noauth" <?php if ($auth) { echo 'style="display:none"'; } ?>>
        <h3><?php _e('Register with Cleeng') ?></h3>
        <p class="cleeng-firsttime" <?php if (!$noCookie) { echo 'style="display:none"'; } ?>><?php _e('Sign up with Cleeng to protect your content.', 'cleeng') ?></p>        
        <p class="cleeng-nofirsttime" <?php if ($noCookie) { echo 'style="display:none"'; } ?>><?php _e('Welcome, you need to log-in to protect your content.', 'cleeng') ?></p>        
        <a class="button-secondary" id="cleeng-login" href="<?php echo $cleeng->getUrl() ?>/login">Log-in</a>
        <a class="button-secondary" id="cleeng-register-publisher" href="<?php echo $cleeng->getUrl() ?>/publisher-registration"><?php _e('Register as publisher', 'cleeng') ?></a>
    </div>
    <div class="cleeng-auth" <?php if (!$auth) { echo 'style="display:none"'; } ?>>
        <h3><?php echo sprintf(__('Welcome, <span id="cleeng-username">%s</span>', 'cleeng'), $userName); ?></h3>
        
        <div id="cleeng-auth-options">
            <ul>
                <li>
                    &bull; <a target="_blank" href="<?php echo $cleeng->getUrl() ?>/my-account/sales-report"><?php _e('Sales report', 'cleeng') ?></a>
                </li>
                <li>
                    &bull; <a target="_blank" href="<?php echo $cleeng->getUrl() ?>/my-account/settings"><?php _e('Your settings', 'cleeng') ?></a>
                </li>
                <li>
                    &bull; <a id="cleeng-logout" href="#"><?php _e('Logout from Cleeng', 'cleeng') ?></a>
                </li>
            </ul>
        </div>
        <div id="cleeng-notPublisher" style="display:none;">
            <?php _e('You need to have a Publisher account before using this widget. Please upgrade your account:', 'cleeng') ?>
            <a target="_blank" href="<?php echo $cleeng->getUrl() ?>/edit-profile/upgrade/1"><?php _e('Become publisher', 'cleeng') ?></a>
        </div>
    </div>
    <form method="post" action="options.php">
        <?php settings_fields('cleeng'); ?>
        <?php do_settings_sections('cleeng'); ?>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
        </p>
    </form>
</div>
<?php
}

/**
 * Wordpress action.
 * Load Cleeng javascript files
 * @global CleengClient $cleeng
 */
function cleeng_load_scripts() {    
    global $cleeng;
    echo '<script type="text/javascript" language="javascript">//<![CDATA[
                Cleeng_PluginPath = "' . CLEENG_WP_PLUGIN_PATH . '";
         // ]]>
         </script>';
    echo '<script src="' . CLEENG_WP_PLUGIN_PATH . 'js/CleengBEWidgetWP.js" type="text/javascript"></script>';
    if ( ! $cleeng->isUserAuthenticated() ) {        
        echo '<script src="' . $cleeng->getAutologinScriptUrl() . '" type="text/javascript"></script>';
    }
    add_action( 'in_admin_footer', 'cleeng_content_form' );
}

/**
 *
 * @param array $content
 * @return string
 */
function cleeng_format_open_tag( $content ) {
    $str = '[cleeng_content'
            . ' id="' . $content['contentId'] . '"'
            . ' description="' . addslashes($content['shortDescription']) . '"'
            . ' price="' . $content['price'] . '"';
    if ( $content['referralProgramEnabled'] ) {
        $str .= ' referral="' . $content['referralRate'] . '"';
    }
    if ( $content['hasLayerDates'] ) {
        $str .= ' ls="' . $content['layerStartDate'] . '"'
                . ' le="' . $content['layerStartDate'] . '"';
    }
    $str .= ']';
    return $str;
}


function get_cleeng_content($post_content) {
    $cleeng_content = array( );

    $expr = '/\[cleeng_content(.*?[^\\\])\](.*?[^\\\])\[\/cleeng_content\]/is';
    preg_match_all( $expr, $post_content, $matched_content );

    foreach ( $matched_content[0] as $key => $content ) {
        $paramLine = $matched_content[1][$key];
        
        $expr = '/(\w+)\s*=\s*(?:\"|&quot;)(.*?)(?<!\\\)(?:\"|&quot;)/si';
        preg_match_all( $expr, $paramLine, $m );
        if ( !isset( $m[0] ) || !$m[0] ) {            
            continue;
        }

        $a = array(
            'id' => 0,
            'price' => 0,
            'type' => 'article',
            'description' => '',
            'ls' => null,
            'le' => null,
            't' => null,
            'referral' => null
        );
        foreach ( $m[1] as $key => $paramName ) {
            $a[$paramName] = $m[2][$key];
        }

        $c = array(
            'contentId' => $a['id'],
            'price' => floatval( $a['price'] ),
            'itemType' => $a['t']?$a['t']:'article',
            'shortDescription' => html_entity_decode( stripslashes( $a['description'] ) )
        );

        if ( $a['ls'] && $a['le'] ) {
            $c['hasLayerDates'] = true;

            $c['layerStartDate'] = $a['ls'];
            $c['layerEndDate'] = $a['le'];
        } else {
            $c['hasLayerDates'] = false;
        }
        if ( $a['referral'] ) {
            $c['referralProgramEnabled'] = true;
            $c['referralRate'] = (float) $a['referral'];
        } else {
            $c['referralProgramEnabled'] = false;
        }
        $cleeng_content[] = $c;
    }
    return $cleeng_content;
}
/**
 * Search post for Cleeng items, and save them.
 * @global CleengClient $cleeng
 * @global array $cleeng_content
 * @global wpdb $wpdb
 * @param int $postId
 */
function cleeng_parse_post( $postId ) {
    global $cleeng, $cleeng_content, $wpdb;

    $my_post = get_post( $postId );
    if ( wp_is_post_revision( $my_post )
            || wp_is_post_autosave( $my_post )
            || $my_post->post_status == 'draft'
            || $my_post->post_status == 'auto-draft'
            || $my_post->post_status == 'trash'
    ) {
        return;
    }

    $post_content = $my_post->post_content;
    
    $cleeng_content = get_cleeng_content($post_content);
    
    
    $update = array( );
    $create = array( );
    $tempKeys = array( );    
    foreach ( $cleeng_content as $key => $content ) {
        $content['url'] = get_permalink( $my_post->ID );
        $content['pageTitle'] = $my_post->post_title . ' | ' . get_bloginfo();
        if ( substr( $content['contentId'], 0, 1 ) == 't' ) {
            $tempKeys[$key] = $content['contentId'];
            unset( $content['contentId'] );
            $create[$key] = $content;
        } else {
            $update[$key] = $content;
        }
    }

    if ( ! $cleeng->isUserAuthenticated() && (count( $create ) || count( $update )) ) {
        $_SESSION['cleeng_errors'] = array( __( 'You have to be authenticated to Cleeng Platform before saving Cleeng Content.' ) );
        return;
    }

    $result = array( );
    if ( count( $update ) ) {

        $update_normalized = $update;
        foreach ($update_normalized as $key => $val) {
            if (strlen($val['shortDescription']) >= 110) {
                $update_normalized[$key]['shortDescription'] = substr($val['shortDescription'], 0, 100) . '...';
            }
        }

        try {
            $result += $cleeng->updateContent( $update_normalized );
        } catch ( Exception $e ) {
            $_SESSION['cleeng_errors'] = array( $e->getMessage() );
        }
    }
    if ( count( $create ) ) {
        try {

            $create_normalized = $create;
            foreach ($create_normalized as $key => $val) {
                if (strlen($val['shortDescription']) >= 110) {
                    $create_normalized[$key]['shortDescription'] = substr($val['shortDescription'], 0, 100) . '...';
                }
            }

            $result += $cleeng->createContent( $create_normalized );
        } catch ( Exception $e ) {
            $_SESSION['cleeng_errors'] = array( $e->getMessage() );
        }
        foreach ( $tempKeys as $key => $tempId ) {
            if ( isset( $result[$key]['contentId'] ) && $result[$key]['contentId'] ) {
                $create[$key]['contentId'] = $result[$key]['contentId'];
                $my_post->post_content = preg_replace( '/\[cleeng_content[^\[\]]+?id=(?:\"|&quot;)'
                                . $tempId . '(?:\"|&quot;)[^\[\]]+?]/',
                                cleeng_format_open_tag( $create[$key] ),
                                $my_post->post_content );
            }
        }
        $_POST['content'] = $my_post->post_content;

        $wpdb->update( $wpdb->posts, array( 'post_content' => $my_post->post_content ), array( 'ID' => $postId ) );
    }

    $errors = array( );
    foreach ( $result as $content ) {
        if ( ! $content['contentSaved'] ) {
            foreach ( $content['errors'] as $err ) {
                $errors += $err;
            }
        }
    }
    if ( count( $errors ) ) {
        $_SESSION['cleeng_errors'] = $errors;
    }
}

/**
 * Wordpress action.
 * Displays errors or warnings.
 */
function cleeng_admin_notices() {
    if ( isset( $_SESSION['cleeng_errors'] ) ) {
        echo '<div class="error cleeng_error"><p>';
        _e( 'Unable to save Cleeng content:', 'cleeng' );
        echo '</p><ul>';

        foreach ( $_SESSION['cleeng_errors'] as $err ) {
            echo '<li>', print_r( $err, true ), '</li>';
        }

        echo '</ul></p></div>';
        unset( $_SESSION['cleeng_errors'] );
    }
}

/**
 * Cleeng Widget Box
 */
function cleeng_add_custom_box() {
    wp_enqueue_style( 'jquery-ui-1.8.2.custom.css', CLEENG_WP_PLUGIN_PATH . 'css/south-street/jquery-ui-1.8.2.custom.css' );
    wp_enqueue_style( 'cleengBEWidget.css', CLEENG_WP_PLUGIN_PATH . 'css/cleengBEWidget.css' );

    if ( function_exists( 'add_meta_box' ) ) {
        add_meta_box( 'cleengwpplugin_sectionid', __( '<span>Cleeng Content Widget</span>', 'cleeng' ),
                'cleeng_inner_custom_box', 'page', 'side' );
        add_meta_box( 'cleengwpplugin_sectionid', __( '<span>Cleeng Content Widget</span>', 'cleeng' ),
                'cleeng_inner_custom_box', 'post', 'side' );
    } else {
        add_action( 'dbx_post_advanced', 'cleeng_old_custom_box' );
        add_action( 'dbx_page_advanced', 'cleeng_old_custom_box' );
    }
}

/* Prints the inner fields for the custom post/page section */

function cleeng_inner_custom_box() {
    global $cleeng;
?>
    <div>
        <div id="cleeng-ajaxErrorWindow" title="Cleeng: Request Error" style="display:none">
            <h3><?php _e( 'An error occured while processing your request', 'cleeng' ) ?></h3>
            <div id="cleeng-ajaxErrorDetails"></div>
        </div>
        <div>
            <div id="cleeng-connecting"><?php _e( 'Connecting to Cleeng Platform...', 'cleeng' ) ?></div>
            <div style="display:none;">
                <?php
                    echo sprintf(__('Welcome, <a href="%s/my-account" id="cleeng-username" target="_blank" title="Visit my account"></a>', 'cleeng'), $cleeng->getUrl());
                ?>
                <a class="CleengWidget-auth-link" id="cleeng-logout" href="#"><?php _e( 'Log out', 'cleeng' ) ?></a>
            </div>
            <div style="display:none;">
                <?php _e('Thanks for using <strong class="cleeng-name">Cleeng</strong>.<br /><br />Please log in to protect your content.', 'cleeng') ?>
                <br /> <br /> 
                    <a style="margin-left:200px;" class="CleengWidget-auth-link button-primary" id="cleeng-login" href="#"><?php _e('Log in','cleeng') ?></a>
                    <br/><br/>
                    <div style="margin-left: 20px;"><?php _e( 'Or 
                        <a class="publisher-account" href="http://staging.cleeng.com/publisher-registration/popup/1">register</a> 
                        as publisher if you are new to us.', 'cleeng' ) ?></div>
                </div>
            <div id="cleeng-notPublisher" style="display:none;">
<?php _e( 'You need to have a Publisher account before using this widget.', 'cleeng' ) ?>
            <a target="_blank" href="<?php echo $cleeng->getUrl() . '/edit-profile/upgrade/1' ?>">
<?php _e( 'Please upgrade your account here', 'cleeng' ) ?></a>.
        </div>
    </div>
    <div id="cleeng-auth-options">
        <div id="cleeng-ContentList">
            <h4><?php _e( 'Content on the current page:', 'cleeng' ) ?></h4>
            <ul>

            </ul>
        </div>
        <h4 id="cleeng_SelectionError" style="color:red; display:none"><?php _e( 'Please make a selection first!', 'cleeng' ) ?></h4>
        <h4 id="cleeng_NoContent"><?php _e( 'Just select the content you want to protect in the editing window and press below green button.', 'cleeng' ) ?></h4>
        <div style="text-align:center;">
            <button id="cleeng-createContent" type="button" class="fg-button ui-state-default ui-corner-all">
<?php _e( 'Create Cleeng Content from selection.', 'cleeng' ) ?>
            </button>
        </div>
    </div>
</div>
<?php
        }

// end of function cleengwpplugin_inner_custom_box()

        function cleeng_content_form() {
            global $cleeng;
?>
            <div id="cleeng-contentForm" title="Cleeng: Create new content element" style="display:none;">
                <form action="" method="post" style="position: relative;">
                    <fieldset>
                        <label class="cleeng-ContentForm-wide" for="cleeng-ContentForm-Description">
<?php _e( 'Description', 'cleeng' ) ?>
                            (<span id="cleeng-ContentForm-DescriptionCharsLeft">110</span> <?php _e( 'characters left', 'cleeng' ) ?>)
                        </label>
                        <textarea class="cleeng-ContentForm-wide" rows="2" cols="50" name="CleengWidget-ContentForm-Description" id="cleeng-ContentForm-Description" class="text ui-widget-content ui-corner-all">
                        </textarea>
                        <label class="cleeng-ContentForm-wide" for="cleeng-ContentForm-Price"><?php _e( 'Price:', 'cleeng' ) ?> <span class="cleeng-currency-symbol">$</span><span id="cleeng-ContentForm-PriceValue">0.00</span></label>
                        <input style="display:none" type="text" name="CleengWidget-ContentForm-Price" id="cleeng-ContentForm-Price" value="" class="text ui-widget-content ui-corner-all" />
                        <div id="cleeng-ContentForm-PriceSlider"></div>
                        <label class="cleeng-ContentForm-wide" for="cleeng-ContentForm-ItemType"><?php _e( 'Item type:', 'cleeng' ) ?></label>
                        <select id="cleeng-ContentForm-ItemType">
                            <option value="article"><?php _e('Article', 'cleeng') ?></option>
                            <option value="chart"><?php _e('Chart', 'cleeng') ?></option>
                            <option value="download"><?php _e('File', 'cleeng') ?></option>
                            <option value="image"><?php _e('Image', 'cleeng') ?></option>
                            <option value="table"><?php _e('Spreadsheet', 'cleeng') ?></option>
                            <option value="video"><?php _e('Video', 'cleeng') ?></option>
                        </select>
                        <br />
                        <input type="checkbox" id="cleeng-ContentForm-LayerDatesEnabled" />
                        <label for="cleeng-ContentForm-LayerDatesEnabled"><?php _e( 'Enable layer dates.', 'cleeng' ) ?></label>
            <div id="cleeng-ContentForm-LayerDates">
<?php _e( 'from:', 'cleeng' ) ?> <input type="text" id="cleeng-ContentForm-LayerStartDate"
                         name="layerStartDate" value="<?php echo date( 'Y-m-d' ) ?>" />
<?php _e( 'to:', 'cleeng' ) ?> <input type="text" id="cleeng-ContentForm-LayerEndDate"
                         name="layerEndDate" value="<?php echo date( 'Y-m-d', time() + 3600 * 24 * 7 ) ?>" />
            </div>
            <input type="checkbox" id="cleeng-ContentForm-ReferralProgramEnabled" />
            <label for="cleeng-ContentForm-ReferralProgramEnabled"><?php echo __( 'Enable referral program' ) ?></label>
            <br />
            <label class="cleeng-ContentForm-wide" for="cleeng-ContentForm-ReferralRate"><?php _e( 'Referral rate:', 'cleeng' ) ?> <span id="cleeng-ContentForm-ReferralRateValue">5</span>%</label>
            <input style="display:none" type="text" name="CleengWidget-ContentForm-ReferralRate" id="cleeng-ContentForm-ReferralRate" value="" class="text ui-widget-content ui-corner-all" />
            <div id="cleeng-ContentForm-ReferralRateSlider"></div>
        </fieldset>
    </form>
    <div id="cleeng-contentForm-info"></div>
</div>
<?php
            }
            
