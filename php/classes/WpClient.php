<?php

Cleeng_Core::load('Cleeng_Client');


class Cleeng_WpClient extends Cleeng_Client
{
    protected $backendCookieName = 'CleengBackendAccessToken';

    public function loadAccessToken()
    {
        if (!is_admin()) {
            return parent::loadAccessToken();
        }

        if (isset($_COOKIE[$this->backendCookieName])) {            
            return $_COOKIE[$this->backendCookieName];
        }

        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();

            $token = get_user_meta($user->ID, '_cleeng_publisher_token', true);
            return $token;
        } else {
            return null;
        }
    }
}