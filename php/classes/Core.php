<?php

class Cleeng_Core
{

    const DATABASE_VERSION = 1;

    /**
     * Configuration injected to each created class
     * @var array
     */
    protected static $config = array(

        // platformUrl, clientId and clientSecret are essential for connecting with Cleeng Platform API
        'platformUrl' =>  'cleeng.com',
        'clientId' => '992580aa70b4',
        'clientSecret' => '19e53488afa1921403c8',

        // following options determine how layer should look & behave
        'payment_method' => 'cleeng-only',   // cleeng-only or paypal-only
        'show_prompt' => true
    );

    /**
     * list of loaded Cleeng_* classes
     * @var array
     */
    protected static $loaded_classes = array();

    /**
     * @var Cleeng_Core
     */
    protected static $instance;


    /**
     * Return singleton instance
     *
     * @static
     * @return Cleeng_Core
     */
    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load Cleeng_* class
     *
     * @throws Exception
     * @param $class_name
     * @return mixed loaded class
     */
    public static function load($class_name)
    {
        if (!isset(self::$loaded_classes[$class_name])) {
            $class_file = str_replace('Cleeng/', dirname(__FILE__) . '/', strtr($class_name, '_', '/')) . '.php';
            require_once $class_file;
            if (!class_exists($class_name)) {
                throw new Exception("Unable to load class: $class_name");
            }

            // create new instance of given class, inject global Cleeng configuration
            self::$loaded_classes[$class_name] = new $class_name(self::$config);
        }

        return self::$loaded_classes[$class_name];
    }

    /**
     * Return Cleeng For WordPress configuration
     *
     * @static
     * @return array
     */
    public static function get_config()
    {
        return self::$config;
    }

    /**
     * Plugin activation hook
     *
     * @static
     */
    public static function activate()
    {
        $installer = self::load('Cleeng_Installer');
        $installer->activate();
    }
    /**
     * Plugin deactivation hook
     *
     * @static
     */
    public static function deactivate()
    {
        $installer = self::load('Cleeng_Installer');
        $installer->deactivate();
    }

    /**
     * Plugin entry point
     *
     * Use:
     *      Cleeng_Core::get_instance()->setup();
     *
     * @return void
     */
    public function setup()
    {
        $options = get_option('cleeng_options');
        
        if (!$options || !isset($options['db_version']) || $options['db_version'] < self::DATABASE_VERSION) {
            self::load('Cleeng_Installer')->migrate_database();
            $options = get_option('cleeng_options'); // reload options
        }

        self::$config = array_merge(self::$config, $options);

        if (!is_admin()) {
            $frontend = self::load('Cleeng_Frontend');
            $frontend->setup();
        } else {
            $admin = self::load('Cleeng_Admin');
            $admin->setup();
        }

    }


}