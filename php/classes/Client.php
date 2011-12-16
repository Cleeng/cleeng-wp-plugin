<?php
/**
 * Copyright (c) 2011, DG2ALL B.V (http://dg2all.com)
 * All rights reserved.
 *
 * LICENSE
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the DG2ALL B.V nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL COPYRIGHT HOLDER BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @package CleengClient
 * @copyright Copyright (c) 2011 DG2ALL B.V (http://dg2all.com)
 * @license New BSD License
 * 
 * @version 1.1.2
 */

/**
 * Simple class for handling Cleeng Client's exceptions
 */
class CleengClientException extends Exception
{
    
}

/**
 * CleengClient - gateway for accessing Cleeng Platform API
 *
 * @uses CleengClientException
 */
class Cleeng_Client
{
    /**
     * Platform URLs
     */
    const SANDBOX = 'sandbox.cleeng.com';
    const PRODUCTION = 'cleeng.com';

    /**
     * Base URL to Cleeng server
     * @var string
     */
    protected $_platformUrl = 'cleeng.com';

    /**
     * Name of the cookie that is used for storing OAuth token
     * @var string
     */
    protected $_cookieName = 'CleengClientAccessToken';

    /**
     * Client identifier
     * @var string
     */
    protected $_clientId;

    /**
     * Client secret password
     * @var string
     */
    protected $_clientSecret;

    /**
     * URL that user will be redirected to after loging in/purchasing content
     * @var string
     */
    protected $_callbackUrl;

    /**
     * If set to true, Cleeng Platform website will appear with compact layout
     *
     * @var boolean
     */
    protected $_popupWindowMode = true;

    /**
     *
     * @var string
     */
    protected $_accessToken;

    /**
     * Default cookie save path
     * @var string
     */
    protected $_cookiePath = '/';

    /**
     * Information about currently authenticated user
     * @var array
     */
    protected $_userInfo;

    /**
     * Information about user purchase summary
     * @var array
     */
    protected $_purchaseSummary;
    
    /**
     * Default sale content conditions
     * @var array 
     */
    protected $_defaultConditions;
    
    /**
     * Raw output from Cleeng API - can be used for debugging purposes
     * @var string
     */
    protected $_apiOutputBuffer;

    /**
     * Check if CleengClient is compatible with current environment
     *
     * @throws CleengClientException if not compatible
     */
    public static function checkCompatibility()
    {
        if (version_compare(PHP_VERSION, '5.1.0') == -1) {
            throw new CleengClientException('Cleeng requires PHP version 5.1 or higher');
        }
        if (!function_exists('curl_init')) {
            throw new CleengClientException('Cleeng needs the CURL PHP extension.');
        }
        if (!function_exists('json_decode')) {
            throw new CleengClientException('Cleeng needs the JSON PHP extension.');
        }
    }

    /**
     * Class constructor. Can be used to pass options such as
     * callback URL, client ID or client secret
     * @param array $config
     */
    public function __construct($options = null)
    {
        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Set multiple options
     * @param array $options
     */
    public function setOptions($options)
    {
        if (!is_array($options)) {
            throw new CleengClientException('Config must be an array.');
        }
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    /**
     * Set an option
     * @param string $name
     * @param mixed $value
     * @return CleengClient $this provides fluent interface
     */
    public function setOption($name, $value)
    {
        $propName = '_' . $name;
        if (!property_exists($this, $propName)) {
            return;
        }
        $methodName = 'set' . ucfirst($name);
        if (method_exists($this, $methodName)) {
            $this->$methodName($name, $value);
        } else {
            $this->$propName = $value;
        }
        return $this;
    }

    /**
     * Returns given option value
     * @param string $name
     * @return mixed
     */
    public function getOption($name)
    {
        $propName = '_' . $name;
        if (!property_exists($this, $propName)) {
            return;
        }
        $methodName = 'get' . ucfirst($name);
        if (method_exists($this, $methodName)) {
            return $this->$methodName($name);
        } else {
            return $this->$propName;
        }
    }

    /**
     * Set access token
     * @param string $token OAuth access token
     */
    public function setAccessToken($token)
    {
        $this->_accessToken = $token;
    }

    /**
     * Returns access token.
     * @return string
     */
    public function getAccessToken()
    {
        if (null === $this->_accessToken) {            
            $this->_accessToken = $this->loadAccessToken();
        }
        return $this->_accessToken;
    }

    /**
     * Returns true if user is authorized in Cleeng Website
     * @return bool true if success
     */
    public function isUserAuthenticated()
    {        
        try {
            $userInfo = (bool)$this->getUserInfo();
        } catch (Exception $e) {
            $userInfo = null;
        }
        return ((bool)$this->getAccessToken() && $userInfo);
    }

    /**
     * Redirects user to Cleeng website where he is asked to authorize widget
     * 
     * @param array $extraParams additional parameters passed to Cleeng platform
     */
    public function authenticate($extraParams = array())
    {
        $clientId = $this->getOption('clientId');
        $clientSecret = $this->getOption('clientSecret');
        $callbackUrl = $this->getOption('callbackUrl');

        if (!$clientId || !$clientSecret || !$callbackUrl) {
            throw new CleengClientException('Following options are required for authentication process: clientId, clientSecret, callbackUrl.');
        }

        $url = $this->getUrl('oauth') . '/authenticate?';
        $params = array(
            'client_id' => $clientId,
            'response_type' => 'code',            
            'redirect_uri' => $callbackUrl
        );
        if ($this->getOption('popupWindowMode')) {
            $params['popup'] = 1;
        }
        $params = array_merge($params, $extraParams);
        header('Location:' . $url . http_build_query($params, '', '&'));
        exit;
    }
    
    /**
     * Redirect user to "register publisher" screen. After finishing registration
     * user will be redirected back, so that he can be automatically logged in.
     */
    public function registerPublisher()
    {
        $this->authenticate(array('publisher_registration' => 1));
    }

    /**
     * Logout (simply destroys access token)
     */
    public function logout()
    {
        $this->callApi('logout');
        $this->destroyAccessToken();
        $this->setAccessToken(null);
    }

    /**
     * Redirect user to Cleeng purchaseContent page
     * @param int $contentId
     */
    public function purchaseContent($contentId)
    {
        // filter $contentId
        $contentId = (int)$contentId;

        $url = $this->getUrl('oauth') . '/authenticate?';
        $params = array(
            'client_id' => $this->getOption('clientId'),
            'response_type' => 'code',
            'purchase_content_id' => $contentId,
            'redirect_uri' => $this->getOption('callbackUrl')
        );
        if ($this->isUserAuthenticated()) {
            // User will get new token after purchasing content,
            // but we need to pass old one to check if the same
            // user is authenticated on widet and on Cleeng Website
            $params['oauth_token'] = $this->getAccessToken();    
        }
        if ($this->getOption('popupWindowMode')) {
            $params['popup'] = 1;
        }
        header('Location:' . $url . http_build_query($params, '', '&'));
        exit;
    }

    /**
     * Redirect user to Cleeng subscribe page
     * @param int $contentId
     */
    public function subscribe($publisherId)
    {
        // filter $contentId
        $publisherId = (int)$publisherId;

        $url = $this->getUrl('oauth') . '/authenticate?';
        $params = array(
            'client_id' => $this->getOption('clientId'),
            'response_type' => 'code',
            'subscription_publisher_id' => $publisherId,
            'redirect_uri' => $this->getOption('callbackUrl')
        );
        if ($this->isUserAuthenticated()) {
            // User will get new token after purchasing content,
            // but we need to pass old one to check if the same
            // user is authenticated on widet and on Cleeng Website
            $params['oauth_token'] = $this->getAccessToken();
        }
        if ($this->getOption('popupWindowMode')) {
            $params['popup'] = 1;
        }
        header('Location:' . $url . http_build_query($params, '', '&'));
        exit;
    }

    /**
     * Handle OAuth callback (usually simply retrieve access token)
     */
    public function processCallback()
    {
        $clientId = $this->getOption('clientId');
        $clientSecret = $this->getOption('clientSecret');
        $callbackUrl = $this->getOption('callbackUrl');

        if (!$clientId || !$clientSecret || !$callbackUrl) {
            throw new CleengClientException('Following options are required for authentication process: clientId, clientSecret, callbackUrl.');
        }

        if (isset($_REQUEST['code'])) {
            $url = $this->getUrl('oauth') . '/token';
            $params = array(
                'client_id' => $this->getOption('clientId'),
                'client_secret' => $this->getOption('clientSecret'),
                'grant_type' => 'authorization-code',
                'code' => $_REQUEST['code'],
//                'redirect_uri' => $this->getOption('callbackUrl')
            );

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));

            /**
             * TODO: validate certificate
             */
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);            

            $buffer = curl_exec($ch);
            $this->_apiOutputBuffer = $buffer;

            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code !== 200 && $code !== 302) {                
                if ($code == 0) {
                    throw new CleengClientException("CURL error: " . curl_error($ch));
                } else {
                    throw new CleengClientException("Authorization error ($code).");
                }
            }

            $params = json_decode($buffer, true);

            if (isset($params['access_token'])) {
                $this->storeAccessToken($params['access_token'],
                                        time()+$params['expires_in']);
                $this->_accessToken = $params['access_token'];
            }
        }
    }

    /**
     * Load access token from cookie
     * This method can be overwritten in child class if (for example) someone would
     * like to store access token in DB
     *
     * @return string|null
     */
    public function loadAccessToken()
    {
        // read access token from cookie?
        if (isset($_COOKIE[$this->_cookieName])) {
            return $_COOKIE[$this->_cookieName];
        }
        return null;

    }

    /**
     * Save acess token in cookie.
     * This method can be overwritten in child class if (for example) someone would
     * like to store access token in DB
     *
     * @param string $accessToken
     * @param int $expires
     */
    public function storeAccessToken($accessToken, $expires)
    {
        setcookie($this->_cookieName, $accessToken, $expires, $this->_cookiePath);
    }

    /**
     * Removes cookie with access token
     */
    public function destroyAccessToken()
    {
        setcookie($this->_cookieName,
                  $this->getAccessToken(),
                  time()-1000,
                  $this->_cookiePath);
    }

    /**
     * Return platform URL
     * 
     * @param string $type
     * @return string URL
     */
    public function getUrl($type=null)
    {
        switch ($type) {
            case 'oauth':
                return 'https://' . $this->_platformUrl . '/oauth';
            case 'logo':
                return 'http://' . $this->_platformUrl . '/logo';
            case 'publisher_logo':
                return 'http://' . $this->_platformUrl . '/media/users';
            case 'api':
                return 'https://api.' . $this->_platformUrl . '/json';
            case 'autologin':
                return 'https://' . $this->_platformUrl . '/autologin/autologin.js';
            default:
                return 'http://' . $this->_platformUrl;
        }
    }

    /**
     * Returns logo url
     *
     * @param integer $contentId
     * @param string $type
     * @param string $unused
     *
     * @internal param string $size
     * @return string
     */
    public function getLogoUrl($contentId, $type='cleeng-light', $unused='500')
    {        
        $params = 'contentId=' . (int)$contentId;        
        if ($this->isUserAuthenticated()) {
            $params .= '&amp;oauth_token=' . urlencode($this->getAccessToken());
        }
        return $this->getUrl('logo') . '/' . $type . '-' . $unused . '.png?' . $params;
    }

    /**
     * Returns publisher's logo url
     * @param integer $publisherId
     * @return string
     */
    public function getPublisherLogoUrl($publisherId)
    {
        $params = 'contentId=' . (int)$publisherId;        
        return $this->getUrl('publisher_logo')
                . '/' . implode('/',str_split($publisherId, 3))
                . '_mini.png';
    }

    /**
     * Return URL to autologin javascript file
     * @return string
     */
    public function getAutologinScriptUrl()
    {
        return $this->getUrl('autologin');
    }

    /**
     * Helper function for calling Cleeng API
     *
     * @param string $method method to call
     * @param array $params method parameters (see API documentation)
     * @return array
     */
    public function callApi($method, $params = array())
    {
        $urlParams = array();
        if ($token = $this->getAccessToken()) {
            $urlParams['oauth_token'] = $token;
        }
        $ch = curl_init($this->getUrl('api') . '?' . http_build_query($urlParams, '', '&'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

        /**
         * TODO: Validate certificate
         */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);

        $jsonParams = array(
            'method' => $method,
            'params' => $params,
            'id' => 1
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonParams));
        $buffer = curl_exec($ch);
        $this->_apiOutputBuffer = $buffer;
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code !== 200 && $code !== 302) {
            if ($code !== 200 && $code !== 302) {                
                if ($code == 0) {
                    throw new CleengClientException("CURL error: " . curl_error($ch));
                } else {
                    throw new CleengClientException("Authorization error ($code).");
                }
            }
        }

        if ($buffer) {
            $bufferArray = @json_decode($buffer, true);

            if (!is_array($bufferArray)) {
                throw new CleengClientException('Server error: ' . $buffer);
            }

            return $bufferArray['result'];
        } else {
            throw new CleengClientException('Unable to call remote procedure.');
        }
    }

    /**
     * Returns raw output from last API call.
     * Function can be used for debugging purposes.
     * @return string
     */
    public function getApiOutputBuffer()
    {
        return $this->_apiOutputBuffer;
    }

    /**
     * Cleeng API calls
     *
     * Following methods reflect Cleeng APIs.
     */

    /**
     * Returns information about currently authenticated user
     * @return array|null
     */
    public function getUserInfo()
    {
        if (null === $this->_userInfo) {
            if (!$this->getAccessToken()) {
                throw new CleengClientException('Method getUserInfo() requires valid access token.');
            }
            
            $ret = $this->callApi('getUserInfo');

            if ($ret['success'] == false) {

                /**
                 * Don't throw exception when user is not authorized - just return null.
                 * This will prevent throwing exception when switching servers,
                 * for example from sandbox to production
                 *
                 * TODO: This behaviour will likely change (we need to come with
                 * some consistent solution).
                 */
                if ($ret['errorCode'] == 'ERR_NO_AUTH') {
                    return null;
                }

                throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
            }
            $this->_userInfo = $ret['userInfo'];
        }
        return $this->_userInfo;
    }

    /*
     * Return user purchase summary info
     * @return array|null
     */
    public function getPurchaseSummary()
    {
        if (null === $this->_purchaseSummary) {
            if (!$this->getAccessToken()) {
                throw new CleengClientException('Method getPurchaseSummary() requires valid access token.');
            }

            $ret = $this->callApi('getPurchaseSummary');

            if ($ret['success'] === false) {

                /**
                 * Don't throw exception when user is not authorized - just return null.
                 * This will prevent throwing exception when switching servers,
                 * for example from sandbox to production
                 *
                 * TODO: This behaviour will likely change (we need to come with
                 * some consistent solution).
                 */
                if ($ret['errorCode'] == 'ERR_NO_AUTH') {
                    return null;
                }

                throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
            }
            $this->_purchaseSummary = $ret['purchaseSummary'];
        }
        return $this->_purchaseSummary;
    }
    
    public function getContentDefaultConditions()
    {
        if (null === $this->_defaultConditions) {
            if (!$this->getAccessToken()) {
                throw new CleengClientException('Method getContentDefaultConditions() requires valid access token.');
            }
            $ret = $this->callApi('getContentDefaultConditions');
            if ($ret['success'] === false) {

                /**
                 * Don't throw exception when user is not authorized - just return null.
                 * This will prevent throwing exception when switching servers,
                 * for example from sandbox to production
                 *
                 * TODO: This behaviour will likely change (we need to come with
                 * some consistent solution).
                 */
                if ($ret['errorCode'] == 'ERR_NO_AUTH') {
                    return null;
                }

                throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
            }
            $this->_defaultConditions = $ret['defaultConditions'];
            
        }
        return $this->_defaultConditions;
        
    }

    /**
     * Check if user has purchased given content
     * @param array $ids
     * @return bool
     */
    public function isContentPurchased($ids)
    {
        if (!$this->isUserAuthenticated()) {
            throw new CleengClientException('Method isContentPurchased() requires authenticated user.');
        }

        if (!is_array($ids)) {
            throw new CleengClientException('Method isContentPurchased() requires array of content IDs as its argument.');
        }

        $ret = $this->callApi('isContentPurchased', array($ids));

        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret;
    }

    /**
     * Updates existing digital content
     * @param array $contentData
     * @return integer
     */
    public function updateContent($contentData)
    {
        if (!$this->isUserAuthenticated()) {
            throw new CleengClientException('Method updateContent() requires authenticated user with publisher account.');
        }

        if (!is_array($contentData)) {
            throw new CleengClientException('Method updateContent() requires array of content as its argument.');
        }

        $ret = $this->callApi('updateContent', array($contentData));
        if ($ret['success'] == false) {            
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret['content'];
    }

    /**
     * Creates existing digital content
     * @param array $contentData
     * @return integer
     */
    public function createContent($contentData)
    {
        if (!$this->isUserAuthenticated()) {
            throw new CleengClientException('Method createContent() requires authenticated user with publisher account.');
        }

        if (!is_array($contentData)) {
            throw new CleengClientException('Method createContent() requires array of content as its argument.');
        }

        $ret = $this->callApi('createContent', array($contentData));
        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret['content'];
    }

    /**
     * Removes contents
     * @param array $ids
     * @return integer
     */
    public function removeContent($ids)
    {
        if (!$this->isUserAuthenticated()) {
            throw new CleengClientException('Method removeContent() requires authenticated user with publisher account.');
        }

        if (!is_array($ids)) {
            throw new CleengClientException('Method removeContent() requires array of content IDs as its argument.');
        }

        $ret = $this->callApi('removeContent', array($ids));

        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return true;
    }

    /**
     * Returns information (like rating) about multiple contents
     * 
     * @param $ids
     * @return array
     *
     */
    public function getContentInfo($ids)
    {
        $ret = $this->callApi('getContentInfo', array($ids));
        if (!is_array($ids)) {
            throw new CleengClientException('Method getContentInfo() requires array of content IDs as its argument.');
        }
        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret['contentInfo'];
    }

    /**
     * Returns information (like rating) about multiple contents
     *
     * @todo: This method will likely be removed as it seems that nobody needs it :)
     *
     * @param $ids
     *
     * @return array
     */
    public function getContentDescription($ids)
    {
        if (!is_array($ids)) {
            throw new CleengClientException('Method getContentDescription() requires array of content IDs as its argument.');
        }
        $ret = $this->callApi('getContentDescription', array($ids));
        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret['contentDescription'];
    }

    /**
     * Tell platform whether user liked content or not
     *
     * @param integer $contentId
     * @param boolean $liked
     * @return boolean
     */
    public function vote($contentId, $liked)
    {
        $ret = $this->callApi('vote', array($contentId, $liked));        
        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret['voted'];
    }

    /**
     * Tell platform that user clicked on "cleeng it" link, so that it can
     * increase content rating.
     *
     * @param integer $contentId
     * @return boolean
     */
    public function referContent($contentId)
    {
        $ret = $this->callApi('referContent', array($contentId));
        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return $ret['referred'];
    }

    /**
     * Automatically authenticate user if he is logged on the platform.
     * This required $sessionId and $key params, which are obtained
     * from cleeng.com/autologin/autologin.js Javascript file.
     *
     * @param string $sessionId
     * @param string $key
     * @return bool true if succeeded
     */
    public function autologin($sessionId, $key)
    {
        $ret = $this->callApi('autologin', array($this->getOption('clientId'), $sessionId, $key));
        
        if ($ret['valid']) {
            $_REQUEST['code'] = $ret['code'];
            $this->processCallback();

            if ($this->isUserAuthenticated()) {
                return true;
            }
        }
        return false;        
    }

    /**
     *
     * @param string $paypalToken
     * @return bool
     */
    public function initDigitalGoodsPayment($paypalToken)
    {
        $ret = $this->callApi('initDigitalGoodsPayment', array($paypalToken));
        if ($ret['success'] == false) {
            throw new CleengClientException('WebAPI Error: ' . $ret['errorDescription']);
        }
        return true;
    }

    public function registerClientApp($title, $description, $url)
    {
        $ret = $this->callApi('registerClientApp', array($title, $description, $url));
        return $ret;
    }

}
