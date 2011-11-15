<?php
/**
 * Zeef lib
 * 
 * Zeef Service adapter abstract
 * 
 * @category	Zeef
 * @package		Zeef_Service
 * @subpackage	Zeef_Service_Adapter
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id$
 */
require_once 'Zend/Rest/Client.php';
abstract class Zeef_Service_Adapter_Abstract implements SplSubject
{
	/**
	 * Result formats
	 * @var String
	 */
	const FORMAT_XML    = 'xml';
	const FORMAT_JSON   = 'json';
	const FORMAT_OBJECT = 'object';

	/**
	 * symbol used to SESSION of COOKIE name
	 * @var string
	 */
    const SYMBOL_REQUEST_TOKEN = 'RTK';
    const SYMBOL_ACCESS_TOKEN  = 'ATK';
    
    /**
     * Authentication types
     * @var string
     */
    const AUTH_XAUTH = 'xAuth';
    const AUTH_OAUTH = 'OAuth';
    const AUTH_BASIC = 'Basic';
    
    /**
     * oAuth Config
     * @var Zend_Oauth_Config
     */
    protected $_oAuthConfig = null;
    
    /**
     * Current authentication to use
     * @var unknown_type
     */
    protected $_authMethod = self::AUTH_BASIC;
        
    /**
     * Service configuration
     * @var Array|Zend_Config
     */
    protected $_config = array();
    
    /**
     * required parameters of configuration
     * @var Array
     */
    protected $_RequiredOptions = array('baseUrl');
    
    /**
     * base url, feed path and action to form full request URL
     * @var String
     */
    protected $_baseUrl = '';    
    protected $_feedPath = '';
    protected $_action = '';
    
    /**
     * request options to build parameters of request URL 
     * @var Array
     */
    protected $_options = array();

    /**
     * HTTP Client used to query all web services
     * @var Zend_Rest_Client
     */
    protected $_rest = null;
    
    /**
     * number of times to retry
     * @var unknown_type
     */
    protected $_retry = 0;
    
    /**
     * Observers
     * @var Array
     */
	protected $_observers = array(); /** observers */

    /**
     * a successor to allow parent access the child object (e.g. Zeef_Service_Adapter_Sinat)
     * @var Zeef_Service_Adapter_Abstract
     */
    protected $_successor;
    
    /**
     * Constructor
     *
     * @param array|Zend_Config	$config
     */
    public function __construct($config)
    {
        /** Convert Zend_Config argument to a plain array. */
    	if (!is_array($config)) {
            if ($config instanceof Zend_Config) {
                $config = $config->toArray();
            } else {
                require_once dirname(dirname(__FILE__)) . '/Exception.php';
                throw new Zeef_Service_Exception('Adapter parameters must be in an array or a Zend_Config object');
            }
        }
        $this->_config = $config;

        /** separate the request options from config array */
        if (isset($config['options'])) {
        	$this->_options = $config['options'];
        }
        /** get auth method */
        if (isset($this->_options['authMethod'])) {
        	$this->_authMethod = $this->_options['authMethod'];
        }
		
        /** get REST Client */
        $this->_rest = new Zend_Rest_Client($config['baseUrl']);
    }
    
	/**
	 * Authorization to consumer
	 * 
	 * @param	Array	$authOptions			Authorization options
	 * @param	Array	$requestOptions			request options
	 * @return	Zeef_Service_Adapter_Abstract
	 */
    protected function _authorize($authOptions)
    {
    	switch ($this->_authMethod) {
    	    case self::AUTH_BASIC:
                $this->_options['source'] = $this->_config['appKey'];
                $this->_options['curloptions'][CURLOPT_USERPWD] = "{$this->_config['user']}:{$this->_config['password']}";
    	        break;
    	    case self::AUTH_OAUTH:
                require_once 'Zend/Oauth/Consumer.php';
        		$consumer = new Zend_Oauth_Consumer($authOptions);
//echo "<pre>";
//print_r($authOptions);
//print_r($_COOKIE);exit;
        		//use existing access token
                if (isset($_COOKIE[self::SYMBOL_ACCESS_TOKEN])) {
                    require_once 'Zend/Oauth/Config.php';
                    $this->_oAuthConfig = new Zend_Oauth_Config($authOptions);
                    break;
                }
        
                //get access token or request token
                $expire = time() + 3600*24*30;
        		if (!empty($_GET) && isset($_COOKIE[self::SYMBOL_REQUEST_TOKEN])) {
        			$accessToken = $consumer->getAccessToken($_GET, unserialize($_COOKIE[self::SYMBOL_REQUEST_TOKEN]));
        			setcookie(self::SYMBOL_ACCESS_TOKEN, serialize($accessToken), $expire);
        			setcookie(self::SYMBOL_REQUEST_TOKEN, '', time()-1);
        			header('Location: ' . $authOptions['callbackUrl']);
        		} else {
        			$requestToken = $consumer->getRequestToken();
        			setcookie(self::SYMBOL_REQUEST_TOKEN, serialize($requestToken), $expire);
        			$consumer->redirect(null, $requestToken);
        		}
    	        break;
    	    case self::AUTH_XAUTH:
    	        $this->_throw(self::AUTH_XAUTH . ' is currentlly unspportted.');
    	        break;
    	}
    	
		return $this;
    }
    
    /**
     * prepare request options
     *
     * @param	String		$query
     * @param	Array		$options
     * @param	Array		$defaultOptions
     * @return	Zeef_Service_Abstract
     */
    abstract protected function _prepareOptions($userOptions = array());

    /**
     * valid options
     *
     * @param	Array	$options
     * @return	Zeef_Service_Abstract
     * @throws	Zeef_Service_Exception
     */
    abstract protected function _validateOptions();

    /**
     * query!
     *
     * @param	Array	$options
     * @return	Zeef_Service_Resultset
     */
    abstract public function query($userOptions = array());    
    
    /**
     * Get Response from specified url
     *
     * @param array $options
     * @return string XML
     */
    protected function _getResponse()
    {
        //set header 'Authorization' for OAuth
        if (isset($_COOKIE[self::SYMBOL_ACCESS_TOKEN])) {
            $accessToken = unserialize($_COOKIE[self::SYMBOL_ACCESS_TOKEN]);
            $this->_oAuthConfig->setToken($accessToken);
            
            $uri = $this->_rest->getUri();
            $uri->setPath($this->_feedPath . $this->_action);
            $oauthHeaderValue = $accessToken->toHeader($uri->__toString(), $this->_oAuthConfig, $this->_options);
            $this->_rest->getHttpClient()->setHeaders('Authorization', $oauthHeaderValue);
        }
        
        //return the curl handle
//        $httpAdapter = $this->_rest->getHttpClient()->getAdapter();
//        if ($this->_service->returnCurlHandle && $httpAdapter instanceof Zeef_Http_Client_Adapter_MultiCurl) {
//                $this->_service->returnCurlHandle = false;
//                return $httpAdapter->getHandle();
//        }
        
        //sending request!
        do {
    		$response = $this->_rest->restPOST($this->_feedPath . $this->_action, $this->_options);
        	if (!$response->isError()) return $response;
        	
            /** retry if errors */
			if ($response->getStatus() == 503 && $this->_retry > 0) {
        		usleep(100000); //100 millisecond
        		continue;
        	}
        	
        	/** throw exception */
        	require_once 'Zend/Controller/Request/Http.php';
            require_once 'Zend/Service/Exception.php';
        	$request = new Zend_Controller_Request_Http();
        	$url = 'http://' . $request->getServer('SERVER_NAME') . $request->getServer('REQUEST_URI');
            throw new Zend_Service_Exception(
				'An error occurred sending request. Status code: ' . $response->getStatus() . PHP_EOL .
				"Url: {$this->_rest->getUri()->__toString()}?" . http_build_query($this->_options) . " and request url: $url"
			);
        } while ($this->_retry-- > 0 && $response->isError());
            	
        return $response;
    }    
    
    /**
     * Set config for http client
     * 
     * @param	array	$options
     * @return	Zeef_Service_Abstract
     */
    protected function _setHttpConfig()
    {
    	/** get config for http client */
    	$httpConfig = array();
    	if (isset($this->_options['curloptions'])) {
        	$httpConfig['curloptions'] = $this->_options['curloptions'];
        	unset($this->_options['curloptions']);
    	}
    	if (isset($this->_options['adapter'])) {
    	    /** if this is my own connection adapters */
    	    if (false !== stripos($this->_options['adapter'], '_Http_Client_Adapter_')) {
    	        $httpConfig['adapter'] = $this->_options['adapter'];
    	    } else {
    		    $httpConfig['adapter'] = 'Zend_Http_Client_Adapter_' . $this->_options['adapter'];
    	    }
    		unset($this->_options['adapter']);
        }
        
        /** init http client */
        $this->_rest->getHttpClient()->resetParameters();
        if (!empty($httpConfig)) {
        	$this->_rest->getHttpClient()->setConfig($httpConfig);
        }     
        return $this;   
    }
    
    /**
     * Check for config Params that are mandatory.
     * Throw exceptions if any are missing.
     *
     * @param	Array		$config
     * @return	Object		$this
     * @throws	Zend_Db_Adapter_Exception
     */
    protected function _checkRequiredOptions($config)
    {
        foreach ($this->_RequiredOptions as $required) {
	        if (array_key_exists($required, $config)) continue;	            
            $this->_throw("Configuration array must have a key for '{$required}'");
        }
        
        return $this;
    }    
    
    /**
     * Utility function to check for a difference between two arrays,
     * and unset the difference
     *
     * @param  array $options      User specified options
     * @param  array $validOptions Valid options
     * @return void
     */
    protected function _compareOptions(& $options, $validOptions)
    {
        $difference = array_diff(array_keys($options), $validOptions);
        if ($difference) {
        	foreach ($difference as $diff) {
        		unset($options[$diff]);
        	}
        }
    }

    /**
     * throw exception
     * 
     * @param	String	$message
     * @return	Object	$this
     * @see		Zeef_Service_Adapter_Exception
     */
    protected function _throw($message)
    {
        require_once dirname(__FILE__) . '/Exception.php';
        throw new Zeef_Service_Adapter_Exception($message);
        return $this;
    }
    
    /**
     * set the successor
     * 
     * @param	Zeef_Service_Adapter_Abstract	$successor
     * @return	Object	$this
     */
    public function setSuccessor(Zeef_Service_Adapter_Abstract $successor)
    {
    	$this->_successor = $successor;
    	return $this;
    }
    
    /**
     * attach a observer
     *
     * @param	SplObserver $observer  new observer to attach
     * @return	Object		$this
     */
    public function attach(SplObserver $observer)
    {
    	if ($observer instanceof SplObserver) {
    		$this->_observers[] = $observer;
    	}
    	
    	return $this;
    }
    
    /**
     * detach a existing observer
     *
     * @param	SplObserver $observer  existing observer to detach
     * @return	Object		$this
     */
    public function detach(SplObserver $observer)
    {
    	if (!$observer instanceof SplObserver) return $this;
    	if ($key = array_search($observer, $this->_observers, true)) {
    		unset($this->_observers[$key]);
    	}
    	
    	return $this;
    }
    
    /**
     * Notify all observers.
     *
     * @param	Void
     * @return	Object		$this
     */    
    public function notify()
    {
    	foreach ($this->_observers as $observer) {
    		if (!$observer instanceof SplObserver) continue;
    		$observer->update($this);
    	}
    	
    	return $this;
    }

    
    
}