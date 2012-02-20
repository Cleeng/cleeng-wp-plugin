<?php
/**
 *
 */
class Cleeng_Editor
{
    public function setup()
    {
        global $wp_version;

        // add UI dialog
        wp_enqueue_script( 'jquery-ui-dialog' );
        /**
         * Cleeng For Wordpress comes with additional jQuery UI widgets: slider
         * and datepicker.
         * Up to version 3.1 Wordpress provides jQuery UI library 1.7.X, since 3.1
         * they moved to 1.8. We need to bundle both versions of widgets to provide
         * compatibility.
         */
        if (version_compare($wp_version, '3.1', '<')) {
            wp_enqueue_script( 'jquery-ui-slider', CLEENG_PLUGIN_URL . 'js/ui.slider.min-1.7.3.js', array( 'jquery-ui-core' ), false, true );
            wp_enqueue_script( 'jquery-ui-datepicker', CLEENG_PLUGIN_URL . 'js/ui.datepicker.min-1.7.3.js', array( 'jquery-ui-core' ), false, true );
        } else {
            wp_enqueue_script( 'jquery-ui-slider', CLEENG_PLUGIN_URL . 'js/ui.slider.min-1.8.10.js', array( 'jquery-ui-core' ), false, true );
            wp_enqueue_script( 'jquery-ui-datepicker', CLEENG_PLUGIN_URL . 'js/ui.datepicker.min-1.8.10.js', array( 'jquery-ui-core' ), false, true );
        }
        wp_enqueue_script( 'jquery-ui-timepicker', CLEENG_PLUGIN_URL . 'js/ui.timepicker.min.js', array( 'jquery-ui-datepicker' ), false, true );

        // enqueue Cleeng For WordPress backend styles
        wp_enqueue_style( 'jquery-ui-1.8.2.custom.css', CLEENG_PLUGIN_URL . 'css/south-street/jquery-ui-1.8.2.custom.css' );
        wp_enqueue_style( 'cleengBEWidget.css', CLEENG_PLUGIN_URL . 'css/cleengBEWidget.css' );

        // add "Cleeng Content Widget" to "edit page" and "edit post"
        add_meta_box( 'cleengwpplugin_sectionid', __( '<span>Cleeng Content Widget</span>', 'cleeng' ),
                array($this, 'editor_meta_box'), 'page', 'side' );
        add_meta_box( 'cleengwpplugin_sectionid', __( '<span>Cleeng Content Widget</span>', 'cleeng' ),
                array($this, 'editor_meta_box'), 'post', 'side' );

        // add javascript code to <HEAD> section
        $admin = Cleeng_Core::load('Cleeng_Admin');
        add_action( "admin_head-post.php", array($admin, 'render_javascript') );
        add_action( "admin_head-page.php", array($admin, 'render_javascript') );
        add_action( "admin_head-post-new.php", array($admin, 'render_javascript') );
        add_action( "admin_head-page-new.php", array($admin, 'render_javascript') );

        // save_post action handler - responsible for parsing posts and synchronizing
        // content with Cleeng platform
        add_action( 'save_post', array($this, 'action_save_post') );

        // in_admin_footer action handler - renders "Edit Cleeng Content" dialog window
        add_action( 'in_admin_footer', array($this, 'action_in_admin_footer') );
    }

    /**
     *
     * @param array $content
     * @return string
     */
    function format_open_tag( $content ) {
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
    function action_save_post( $postId ) {
        global $wpdb;

        $cleeng = Cleeng_Core::load('Cleeng_WpClient');

        $errors = array();

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

        $cleeng_content = $this->get_cleeng_content($post_content);

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
            Cleeng_Core::load('Cleeng_Admin')
                    ->session_error_message(__( 'You have to be authenticated to Cleeng Platform before saving Cleeng Content.' ));
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
                $errors[] = $e->getMessage();
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
                $errors[] = array( $e->getMessage() );
            }
            foreach ( $tempKeys as $key => $tempId ) {
                if ( isset( $result[$key]['contentId'] ) && $result[$key]['contentId'] ) {
                    $create[$key]['contentId'] = $result[$key]['contentId'];
                    $my_post->post_content = preg_replace( '/\[cleeng_content[^\[\]]+?id=(?:\"|&quot;)'
                                    . $tempId . '(?:\"|&quot;)[^\[\]]+?]/',
                                    $this->format_open_tag( $create[$key] ),
                                    $my_post->post_content );
                }
            }
            $_POST['content'] = $my_post->post_content;

            $wpdb->update( $wpdb->posts, array( 'post_content' => $my_post->post_content ), array( 'ID' => $postId ) );
            /** @var $wpdb wpdb */
        }

        $cleengContentIds = array();
        foreach ( $result as $content ) {
            if ( ! $content['contentSaved'] ) {
                foreach ( $content['errors'] as $err ) {
                    $errors += $err;
                }
            }
            $cleengContentIds[] = $content['contentId'];
        }

        // clear cleeng_content table 
        $wpdb->query("DELETE FROM " . $wpdb->prefix . "cleeng_content WHERE content_id IN (" . implode(',' ,$cleengContentIds) . ")");

        if ( count( $errors ) ) {
            $msg = __('Unable to save Cleeng content:', 'cleeng')
                 . '</p><ul><li>' . implode('</li><li>', $errors) . '</li></ul><p>';

            Cleeng_Core::load('Cleeng_Admin')
                    ->session_error_message($msg);
        }
    }

    /**
     * Display "Cleeng Widget" box
     */
    function editor_meta_box() {
        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
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
                    <a style="float:right" class="CleengWidget-auth-link button-primary" id="cleeng-login" href="#"><?php _e('Log in','cleeng') ?></a>
                    <br/><br/>
                    <div style="margin-left: 20px;"><?php _e( 'Or
                        <a href="#" id="cleeng-register-publisher">register</a>
                        as publisher if you are new to us.', 'cleeng' ) ?></div>
                </div>
            <div id="cleeng-notPublisher" style="display:none;">
<?php _e( 'You need to have a Publisher account before using this widget.', 'cleeng' ) ?>
            <a target="_blank" id="cleeng-register-publisher2" href="<?php echo $cleeng->getUrl() . '/publisher-registration/popup/1' ?>">
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

    public function action_in_admin_footer()
    {
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

}