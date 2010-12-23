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
wp_enqueue_script( 'jquery-ui-slider', CLEENG_WP_PLUGIN_PATH . 'js/ui.slider.min.js', array( 'jquery-ui-core' ), false, true );
wp_enqueue_script( 'jquery-ui-datepicker', CLEENG_WP_PLUGIN_PATH . 'js/ui.datepicker.min.js', array( 'jquery-ui-core' ), false, true );

add_action( "admin_head-post.php", 'cleeng_load_scripts' );
add_action( "admin_head-page.php", 'cleeng_load_scripts' );
add_action( "admin_head-post-new.php", 'cleeng_load_scripts' );
add_action( "admin_head-page-new.php", 'cleeng_load_scripts' );
add_action( 'save_post', 'cleeng_parse_post' );
add_action( 'admin_menu', 'cleeng_add_custom_box' );
add_action( 'admin_notices', 'cleeng_admin_notices' );

add_action('admin_menu', 'cleeng_plugin_menu');


function cleeng_settings_page() {
    ?>
<div class="wrap">
    <?php screen_icon(); ?>
    <h2>Cleeng For WordPress Settings</h2>
    <form method="post" action="options.php">
        <?php settings_fields('cleeng_settings'); ?>
        <?php do_settings_sections('cleeng'); ?>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
        </p>
    </form>
</div>
<?php
}

function cleeng_display_settings_description() {
    echo '<p>Some description</p>';
}

function cleeng_plugin_menu() {
    add_submenu_page('options-general.php', __('Cleeng For WordPress Settings'), __('Cleeng'), 'manage_options', 'cleeng_settings', 'cleeng_settings_page');
    register_setting( 'cleeng_options', 'cleeng_options', 'cleeng_options_validate' );

    add_settings_section('cleeng_main', 'Display Settings', 'cleeng_display_settings_description', 'cleeng');
    add_settings_field('cleeng_widget_width', 'Widget width (in pixels):', 'intval', 'cleeng', 'cleeng_main');

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
            . ' description="' . $content['shortDescription'] . '"'
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
    $cleeng_content = array( );

    $expr = '/\[cleeng_content(.*[^\\\])?\](.*[^\\\])?\[\/cleeng_content\]/is';
    preg_match_all( $expr, $post_content, $m );
    foreach ( $m[0] as $key => $content ) {
        $paramLine = $m[1][$key];

        $expr = '/(\w+)\s*=\s*\"(.*?[^\\\])\"/si';
        preg_match_all( $expr, $paramLine, $m );
        if ( ! isset( $m[0] ) || ! count( $m[0] ) ) {
            continue;
        }

        $a = array(
            'id' => 0,
            'price' => 0,
            'type' => 'article',
            'description' => '',
            'ls' => null,
            'le' => null,
            'referral' => null
        );
        foreach ( $m[1] as $key => $paramName ) {
            $a[$paramName] = $m[2][$key];
        }

        $c = array(
            'contentId' => $a['id'],
            'price' => floatval( $a['price'] ),
            'itemType' => $a['type'],
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
        try {
            $result += $cleeng->updateContent( $update );
        } catch ( Exception $e ) {
            $_SESSION['cleeng_errors'] = array( $e->getMessage() );
        }
    }
    if ( count( $create ) ) {
        try {
            $result += $cleeng->createContent( $create );
        } catch ( Exception $e ) {
            $_SESSION['cleeng_errors'] = array( $e->getMessage() );
        }
        foreach ( $tempKeys as $key => $tempId ) {
            if ( isset( $result[$key]['contentId'] ) && $result[$key]['contentId'] ) {
                $create[$key]['contentId'] = $result[$key]['contentId'];
                $my_post->post_content = preg_replace( '/\[cleeng_content[^\[\]]+?id="'
                                . $tempId . '"[^\[\]]+?]/',
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
        echo __( 'Unable to save Cleeng content:' );
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
        add_meta_box( 'cleengwpplugin_sectionid', __( '<span>Cleeng Content Widget</span>', 'cleengwpplugin_textdomain' ),
                'cleeng_inner_custom_box', 'page', 'side' );
        add_meta_box( 'cleengwpplugin_sectionid', __( '<span>Cleeng Content Widget</span>', 'cleengwpplugin_textdomain' ),
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
            <h3><?php echo __( 'An error occured while processing your request' ) ?></h3>
            <div id="cleeng-ajaxErrorDetails"></div>
        </div>
        <div>
            <div id="cleeng-connecting"><h3><?php echo __( 'Connecting to Cleeng Platform...' ) ?></h3></div>
            <div style="display:none;">
<?php echo __( 'Welcome' ) ?>, <a href="<?php echo $cleeng->getUrl() . '/my-account' ?>" id="cleeng-username" target="_blank" title="Visit my account"></a>
                <a class="CleengWidget-auth-link" id="cleeng-logout" href="#"><?php echo __( 'Log out' ) ?></a>
            </div>
            <div style="display:none;">
                <a class="CleengWidget-auth-link" id="cleeng-login" href="#">
<?php echo __( 'Authenticate with Cleeng Platform' ) ?>
                </a>
            </div>
            <div id="cleeng-notPublisher" style="display:none;">
<?php echo __( 'You need to have a Publisher account bfore using this widget.' ) ?>
            <a target="_blank" href="<?php echo $cleeng->getUrl() . '/my-account/upgrade' ?>">
<?php echo __( 'Please upgrade your account here' ) ?></a>.
        </div>
    </div>
    <div id="cleeng-auth-options">
        <div id="cleeng-ContentList">
            <h4><?php echo __( 'Content on the current page' ) ?>:</h4>
            <ul>

            </ul>
        </div>
        <h4 id="cleeng_SelectionError" style="color:red; display:none"><?php echo __( 'Please make a selection first!' ) ?></h4>
        <h4 id="cleeng_NoContent"><?php echo __( 'Just select the content you want to protect in the editing window and press below green button.' ) ?></h4>
        <div style="text-align:center;">
            <button id="cleeng-createContent" type="button" class="fg-button ui-state-default ui-corner-all">
<?php echo __( 'Create Cleeng Content from selection.' ) ?>
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
                        <label class="cleeng-ContentForm-wide" for="CleengWidget-ContentForm-Description">
<?php echo __( 'Description' ) ?>
                            (<span id="cleeng-ContentForm-DescriptionCharsLeft">110</span> <?php echo __( 'characters left' ) ?>)
                        </label>
                        <textarea class="cleeng-ContentForm-wide" rows="2" cols="50" name="CleengWidget-ContentForm-Description" id="cleeng-ContentForm-Description" class="text ui-widget-content ui-corner-all">
                        </textarea>
                        <label class="cleeng-ContentForm-wide" for="CleengWidget-ContentForm-Price"><?php echo __( 'Price' ) ?>: $<span id="cleeng-ContentForm-PriceValue">0.00</span></label>
                        <input style="display:none" type="text" name="CleengWidget-ContentForm-Price" id="cleeng-ContentForm-Price" value="" class="text ui-widget-content ui-corner-all" />
                        <div id="cleeng-ContentForm-PriceSlider"></div>
                        <input type="checkbox" id="cleeng-ContentForm-LayerDatesEnabled" />
                        <label for="CleengWidget-ContentForm-LayerDatesEnabled"><?php echo __( 'Enable layer dates.' ) ?></label>
            <div id="cleeng-ContentForm-LayerDates">
<?php echo __( 'from' ) ?>: <input type="text" id="cleeng-ContentForm-LayerStartDate"
                         name="layerStartDate" value="<?php echo date( 'Y-m-d' ) ?>" />
<?php echo __( 'to' ) ?>: <input type="text" id="cleeng-ContentForm-LayerEndDate"
                         name="layerEndDate" value="<?php echo date( 'Y-m-d', time() + 3600 * 24 * 7 ) ?>" />
            </div>
            <input type="checkbox" id="cleeng-ContentForm-ReferralProgramEnabled" />
            <label for="CleengWidget-ContentForm-ReferralProgramEnabled"><?php echo __( 'Enable referral program' ) ?></label>
            <br />
            <label class="cleeng-ContentForm-wide" for="CleengWidget-ContentForm-ReferralRate"><?php echo __( 'Referral rate' ) ?>: <span id="cleeng-ContentForm-ReferralRateValue">5</span>%</label>
            <input style="display:none" type="text" name="CleengWidget-ContentForm-ReferralRate" id="cleeng-ContentForm-ReferralRate" value="" class="text ui-widget-content ui-corner-all" />
            <div id="cleeng-ContentForm-ReferralRateSlider"></div>
        </fieldset>
    </form>
    <div id="cleeng-contentForm-info"></div>
</div>
<?php
            }

            