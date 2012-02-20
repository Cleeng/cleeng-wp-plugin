<?php

/**
 * Installer - migrates database if necessary
 */
class Cleeng_Installer
{

    /**
     * Activation hook - checks if environment is compatible, migrate db if necessary
     *
     * Affected rows in options table:
     *      cleeng_options
     *      cleeng_rss
     * Affected tables:
     *      cleeng_content
     */
    public function activate()
    {
        Cleeng_Core::load('Cleeng_WpClient');
        Cleeng_Client::checkCompatibility();
        $this->migrate_database();
        //$cleengAdmin = Cleeng_Core::load('Cleeng_Admin');
        //$cleengAdmin->message("Cleeng for WordPress installed.");
    }

    /**
     * Deactivation hook - clear database from Cleeng stuff
     *
     * Affected rows in options table:
     *      cleeng_options
     *      cleeng_rss
     * Affected tables:
     *      cleeng_content
     */
    public function deactivate()
    {
        global $wpdb;
        /* @var wpdb $wpdb */

        delete_metadata('user', 0, '_cleeng_publisher_token', '', true);

        delete_option('cleeng_rss');
        delete_option('cleeng_options');

        $table_name = $wpdb->prefix . "cleeng_content";
        $wpdb->query("DROP TABLE $table_name");
    }

    /**
     * Update database to latest version
     *
     * Affected rows in options table:
     *      cleeng_options
     *      cleeng_rss
     * Affected tables:
     *      cleeng_content
     */
    public function migrate_database($options = null)
    {
        global $wpdb;

        // options
        if (!$options) {
            // no cleeng_options
            $config = array_merge(
                Cleeng_Core::get_config(),
                array('db_version' => Cleeng_Core::DATABASE_VERSION)
            );
            update_option('cleeng_options', $config);
        } elseif (!isset($options['db_version']) || $options['db_version'] < Cleeng_Core::DATABASE_VERSION) {
            // cleeng_options are present, but in invalid version
            $options['db_version'] = Cleeng_Core::DATABASE_VERSION;
            $config = array_merge(Cleeng_Core::get_config(), $options);
            update_option('cleeng_options', $config);
        }

        // rss cache
        $rss = get_option('cleeng_rss');
        if (!$rss) {
            update_option('cleeng_rss',
                array(
                    'rss_cached_at' => 0,
                    'rss_cache' => null
                ));
        }

        $table_name = $wpdb->prefix . "cleeng_content";

        $sql = "CREATE TABLE $table_name (
          content_id int(11) PRIMARY KEY,
          publisher_id int(11) NOT NULL,
          page_title varchar(255) NOT NULL,
          price float(18,2),
          currency char(8),
          currency_symbol char(8),
          short_description varchar(120),
          referral_rate float(18,4),
          short_url varchar(200),
          item_type varchar(50),
          referral_program_enabled tinyint(1),
          subscription_offer tinyint(1),
          subscription_prompt varchar(64)
        );";


        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function register_client_app()
    {
        $client = Cleeng_Core::load('Cleeng_WpClient');
        $desc = "Automatically created WordPress appliation.";
        $ret = $client->registerClientApp(get_bloginfo(), $desc, get_option('siteurl'));
        if ($ret['success']) {
            return $ret;
        }
        return null;
    }
}