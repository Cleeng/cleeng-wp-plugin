<?php

/**
 * Admin: display Cleeng menu etc.
 */
class Cleeng_Admin
{

    /**
     * List of messages to be displayed
     * @var array
     */
    protected $messages = array();

    /**
     * List of error messages to be displayed
     * @var array
     */
    protected $error_messages = array();

    /**
     * Add message to be displayed on top of admin page
     *
     * @param string $message
     */
    public function message($message)
    {
        if (!count($this->messages) && !count($this->error_messages)) {
            add_action('admin_notices', array($this, 'action_admin_notices'));
        }
        $this->messages[] = $message;
    }

    /**
     * Persist message in $_SESSION so that it will be displayed after
     * redirecting user.
     *
     * @param string $message
     */
    public function session_message($message)
    {
        if (!isset($_SESSION['cleeng_messages'])) {
            $_SESSION['cleeng_messages'] = array();
        }
        $_SESSION['cleeng_messages'][] = $message;
    }

    /**
     * Add message to be displayed on top of admin page
     *
     * @param string $message
     */
    public function error_message($message)
    {
        if (!count($this->messages) && !count($this->error_messages)) {
            add_action('admin_notices', array($this, 'action_admin_notices'));
        }
        $this->error_messages[] = $message;
    }

    /**
     * Persist error message in $_SESSION so that it will be displayed after
     * redirecting user.
     *
     * @param string $message
     */
    public function session_error_message($message)
    {
        if (!isset($_SESSION['cleeng_errors'])) {
            $_SESSION['cleeng_errors'] = array();
        }
        $_SESSION['cleeng_errors'][] = $message;
    }

    /**
     * Setup backend functionality of Cleeng For WordPress
     */
    public function setup()
    {
        $options = Cleeng_Core::get_config();
        // Setup admin menu
        add_action('admin_menu', array($this, 'action_admin_menu'));

        // Backend CSS
        wp_enqueue_style('cleengBEWidget.css', CLEENG_PLUGIN_URL . 'css/cleengBEWidget.css');

        wp_enqueue_script('CleengClient', 'https://' . $options['platformUrl'] . '/js-api/client.js');

        // register admin_init action hook
        add_action('admin_init', array($this, 'action_admin_init'));

        // post list
        add_action('load-edit.php', array($this, 'post_list'));

        // setup editor
        add_action('load-post.php', array($this, 'setup_editor'));
        add_action('load-post-new.php', array($this, 'setup_editor'));
        add_action('load-page.php', array($this, 'setup_editor'));
        add_action('load-page-new.php', array($this, 'setup_editor'));

        // "Settings" link in "plugins" menu
        $this_plugin = plugin_basename(realpath(dirname(__FILE__) . '/../../cleengWP.php'));
        add_filter('plugin_action_links_' . $this_plugin, array($this, 'action_plugin_action_links'), 10, 2);

        // in_admin_footer action handler - pass appSecureKey to CleengWidget JS object
        add_action( 'in_admin_footer', array($this, 'action_in_admin_footer') );

        // init session if it is not started yet
        if (!session_id()) {
            session_start();
        }

        // display messages saved in $_SESSION
        if (isset($_SESSION['cleeng_messages'])) {
            foreach (
                $_SESSION['cleeng_messages'] as $msg
            ) {
                $this->message($msg);
            }
            unset($_SESSION['cleeng_messages']);
        }
        // display errors saved in $_SESSION
        if (isset($_SESSION['cleeng_errors'])) {
            foreach (
                $_SESSION['cleeng_errors'] as $err
            ) {
                $this->error_message($err);
            }
            unset($_SESSION['cleeng_errors']);
        }
        add_action('wp_dashboard_setup', array($this, 'dashboard'));
        add_action('plugins_loaded', array($this, 'plugins'));

    }

    /**
     * Renders Cleeng backend javascript
     */
    public function render_javascript()
    {
        $cleeng = Cleeng_Core::load('Cleeng_WpClient');
        $options = Cleeng_Core::get_config();

        echo '<script type="text/javascript" language="javascript">//<![CDATA[
                var Cleeng_PluginPath = "' . CLEENG_PLUGIN_URL . '";
                CleengClient.init({
                    "channelURL": Cleeng_PluginPath + "/channel.html",
                     "appId": "' . $options['appId'] . '",
                     "token": "' . $cleeng->getAccessToken() . '",
                     "tokenCookieName" : "CleengBackendAccessToken"

                 });
              // ]]>
             </script>';
        echo '<script src="' . CLEENG_PLUGIN_URL . 'js/CleengBEWidgetWP.js" type="text/javascript"></script>';
    }

    /**
     * Hook for load-post.php, load-post-new.php, load-page.php, load-page-new.php
     * actions.
     */
    public function setup_editor()
    {
        Cleeng_Core::load('Cleeng_Editor')->setup();
    }

    /**
     * admin_init action hook
     *
     * Registers "cleeng_options" setting
     */
    public function action_admin_init()
    {
        register_setting('cleeng', 'cleeng_options');
    }

    /**
     * in_admin_footer action hook
     *
     * Passes appSecureKey to CleengWidget
     */
    public function action_in_admin_footer()
    {
        if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
            $options = Cleeng_Core::get_config();
            echo '<script type="text/javascript">';
            echo "\nCleengWidget.appSecureKey = '{$options["appSecureKey"]}';\n</script>";
        }
    }

    /**
     * Display messages
     */
    public function action_admin_notices()
    {
        if (count($this->messages)) {
            echo '<div class="updated">';
            echo '<p>' . implode('</p></p>', $this->messages) . '</p>';
            echo '</div>';
        }
        if (count($this->error_messages)) {
            echo '<div class="error cleeng_error">';
            echo '<p>' . implode('</p></p>', $this->error_messages) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Add Cleeng menu pages
     */
    public function action_admin_menu()
    {
        global $submenu;

        add_menu_page(
            __('Cleeng', 'cleeng'), __('Cleeng', 'cleeng'), false, 'cleeng-menu',
            'cleeng', CLEENG_PLUGIN_URL . '/img/cleengit-small.png'
        );

        add_submenu_page(
            'cleeng-menu', __('What is Cleeng?', 'cleeng'), __('What is Cleeng?', 'cleeng'),
            'manage_options', 'cleeng/what-is-cleeng', array($this, 'page_what_is_cleeng')
        );
        add_submenu_page(
            'cleeng-menu', __('Quick-start guide', 'cleeng'), __('Quick-start guide', 'cleeng'),
            'manage_options', 'cleeng/quick-start-guide', array($this, 'page_quickstart')
        );
        add_submenu_page(
            'cleeng-menu', __('Settings to manage', 'cleeng'), __('Settings to manage', 'cleeng'),
            'manage_options', 'cleeng/settings', array($this, 'page_settings')
        );

        $submenu["cleeng-menu"][] = array(
            __('<div class="external support">Support & FAQ</div>', 'cleeng'),
            'manage_options', 'https://support.cleeng.com/home'
        );
        $submenu["cleeng-menu"][] = array(
            __('<div class="external monetization">Monetization tips</div>', 'cleeng'),
            'manage_options', 'http://monetizecontent.org'
        );
        $submenu["cleeng-menu"][] = array(
            __('<div class="external demos">Demos</div>', 'cleeng'),
            'manage_options', 'http://cleeng.com/features/demos'
        );
    }

    /**
     * Display "Settings" next to "Deactivate" in plugin list
     *
     * @param $links list of links already displayed in plugin row
     * @return array updated list of links
     */
    public function action_plugin_action_links($links)
    {
        $settings_link = '<a href="admin.php?page=cleeng/settings">' . __("Settings", "cleeng") . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Render "Settings to manage" page
     */
    public function page_settings()
    {
        $this->render_javascript();
        Cleeng_Core::load('Cleeng_Page_Settings')->render();
    }

    /**
     * Render "What is Cleeng" page
     */
    public function page_what_is_cleeng()
    {
        $this->render_javascript();
        Cleeng_Core::load('Cleeng_Page_WhatIsCleeng')->render();
    }

    /**
     * Render "Quickstart" page
     */
    public function page_quickstart()
    {
        $this->render_javascript();
        Cleeng_Core::load('Cleeng_Page_Quickstart')->render();
    }

    //
    public function post_list()
    {
        $list = Cleeng_Core::load('Cleeng_PostList');
        $list->setup();
    }


    /**
     * add Dashboard Widget Sell your content
     */
    function dashboard()
    {
        $syc = Cleeng_Core::load('Cleeng_Dashboard');
        $syc->setup();
    }
    
    function plugins()
    {
        $syc = Cleeng_Core::load('Cleeng_Plugins');
        $syc->setup();
    }


}
