<?php
/**
 * Zeef lib
 * 
 * Zeef Service Sina T adapter
 *
 * @category	Zeef
 * @package		Zeef_Service
 * @subpackage	Zeef_Service_Adapter 
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id: Service.php,v 1.1 2009/10/22 06:25:01 Zephyr214@gmail.com Exp $
 */
class Zeef_Service_Adapter_Sinat extends Zeef_Service_Adapter_Abstract
{
    /**
     * url for oAuth
     * @var string
     */
    const OAUTH_URL = 'http://api.t.sina.com.cn/oauth';
    
    /**
     * result type returned
     * @var string
     */
    protected $_format;
	
	/**
	 * Constructs a new Sina weibo web service client
	 * 
	 * @param	String $appKey	Developer's Sina weibo app key
	 * @return	Viod
	 */
	public function __construct($config)
	{
	    parent::__construct($config);
	    $this->setSuccessor($this);
	    $this->_checkRequiredOptions($this->_config);

	    /** other settings */
		if (isset($this->_config['retry'])) {
    		$this->_retry = min($this->_config['retry'], $this->_retry);
    	}
		if (isset($this->_config['format'])) {
			$this->setDefaultFormat($this->_config['format']);
		}
	}
	
	/**
	 * set default format of result
	 * 
	 * @param	String	$format	result format to query
	 * @return	Object	$this
	 * @throws	Zend_Service_Exception
	 */
	public function setDefaultFormat($format)
	{
		if (empty($format)) {
		    $format = self::FORMAT_JSON;    
		}
		
		/** validate query type */
		$validFormat = array(self::FORMAT_XML, self::FORMAT_JSON);
		if (!in_array($format, $validFormat)) {
			require_once 'Zend/Service/Exception.php';
			throw new Zend_Service_Exception("Invalid Query Type, It should be one of " . implode(', ', $validFormat));
		}
		$this->_format = (string) $format;
		
		return $this;
	}
    
    /**
     * select an API
     * 
     * @param	String	$feedPath	apiName
     * @param	String	$action		feed action
     * @return	Object	$this
     * @throws	Zeef_Service_Sinat_Exception	Thrown when feed path or action user selected does not exist
     */
    protected function _selectApi($feedPath, $action)
    {
        /** throw exceptions if no this kind of api and action */
        $feedName = trim($feedPath, '/');
        if (!isset($this->_config['feeds'][$feedName])) {
            $this->throws(sprintf('The feed path "%s" you selected does not exist', $feedName));
        }
        if (!isset($this->_config['feeds'][$feedName][$action])) {
            $this->throws(sprintf('The Action "%s" you selected "%s" does not exist', $action, $feedName));
        }
        
        /** set options */
        foreach ($this->_config['feeds'][$feedName][$action] as $k=>$v) {
            if (in_array($k, array('feedPath', 'action')) && !empty($v)) {
                $$k = $v;
            } else {
                $this->_options[$k] = $v;
            }                        
        }

        $this->_feedPath = $feedPath;
        $this->_action = "{$action}.{$this->_format}";
    }
    
    /**
     * query!
     *
     * @param	Array	$userOptions	special options user provided for this query
     * @return	Zeef_Service_Resultset
     */
    public function query($userOptions = array())
    {
        $this->_prepareOptions($userOptions);
    	$this->_setHttpConfig()->_validateOptions();
	    $response = $this->_getResponse();
	    if (is_resource($response)) {
	        return $response;
	    }
    	$results = $this->_fetchResult($response);
return $results;
    	

		/** build result object */
		require_once "Zeef/Service/SinaT/ResultSet.php";
		$class = 'Zeef_Service_SinaT_ResultSet';
		$results = new $class($result);
    	
    	return $results;
    }
        	
    /**
     * prepare request options for sending to Sina T
     *
     * @param	Array		$options			options user provided to query
     * @param	Array		$defaultOptions		default options for this service
     * @return	Array		options to send request
     */
    protected function _prepareOptions($userOptions = array())
    {
        if (!isset($userOptions['feedPath']) || !isset($userOptions['action'])) {
            $this->throws("Option array must have a key for 'feedPath' and 'action'.");
        }
        $this->_selectApi($userOptions['feedPath'], $userOptions['action']);
        unset($userOptions['feedPath'], $userOptions['action']);
        
        //authorization options
    	if ((boolean) $this->_options['auth']) {
            $authOptions = array();
    	    if ($this->_authMethod == self::AUTH_OAUTH) {
    	        if (isset($_COOKIE['CBK'])) {
    	            $callback = urldecode(base64_decode($_COOKIE['CBK']));
    	        } else {
    	            $callback = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    	            setcookie('CBK', base64_encode(urlencode($callback)), time() + 3600*24*7); //7 days for expire
    	        }
    	        
    	        $authOptions = array(
        			'callbackUrl'	=> !empty($userOptions['callback']) ? $userOptions['callback'] : $callback,
        			'siteUrl'		=> self::OAUTH_URL,
        			'consumerKey'	=> $this->_config['appKey'],
        			'consumerSecret'=> $this->_config['appSecret'],
        	        'requestMethod' => Zend_Http_Client::GET
        		);
    	    }
    	    $this->_authorize($authOptions);
    	}    	
        
        $this->_options = array_merge($this->_options, $userOptions);
    }

    /**
     * valid options
     *
     * @param	Array	$options
     * @return	Zeef_Service_Abstract
     * @throws	Zeef_Service_Exception
     */
    protected function _validateOptions()
    {
    	/** check required options */
        if (!empty($this->_options['requiredOptions'])) {
            $requiredOptions = explode(',', $this->_options['requiredOptions']);
        }
		if ($this->_authMethod == self::AUTH_BASIC) {
		    $requiredOptions[] = 'source';
		}
    	if (!empty($requiredOptions)) {
            foreach ($requiredOptions as $option) {
                if (!empty($option) && !array_key_exists($option, $this->_options)) {
                    $this->throws("Option array must have a key for '{$option}'");
                }
            }
    	}

        /** remove invalid options */
        if (!empty($this->_options['validOptions'])) {
            $validOptions = explode(',', $this->_options['validOptions']);
        	$this->_compareOptions($this->_options, $validOptions);
        }
    	
    	return $this;
    }
    	
    /**
     * Check if response is an error
     *
     * @param  DOMDocument $dom		DOM Object representing the result XML
     * @return void
     * @throws Zeef_Service_Sinat_Exception	Thrown when the result from Sina T is an error
     */
    protected static function _checkErrors($dom)
    {
		//@todo Unfinished!
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('yjp', 'urn:yahoo:jp');

        if ($xpath->query('//yjp:Error')->length >= 1) {
            $message = $xpath->query('//yjp:Error/yjp:Message/text()')->item(0)->data;
            $this->throws($message);
        }
    }	
	
    /**
     * fetch result from response body
     * 
     * @param	String	$responseBody	body of response
     * @return	DOMDocument|Object|JSON
     */
	protected function _fetchResult($response)
	{
	    switch ($this->_format) {
			case self::FORMAT_XML:
			    if (!$response || $response->getStatus != 200) {
			        return new DOMDocument();
			    }
		        $responseBody = $response->getBody();
				$dom = new DOMDocument();
        		@$dom->loadXML($responseBody);
        		self::_checkErrors($dom);
        		return $dom;
			case self::FORMAT_JSON:
			    if (!$response || $response->getStatus() != 200) {
			        return array();
			    }
		        $responseBody = $response->getBody();
				$result = Zend_Json_Decoder::decode($responseBody);
				if (key_exists('error_code', $result)) {
					$this->throws($result['error']);
				}
				return $result;
			case self::FORMAT_OBJECT:
			    if (!$response || $response->getStatus != 200) {
			        return new DOMDocument(); //@todo build a empty object
			    }
		        $responseBody = $response->getBody();			    
				$result = unserialize($responseBody);
				if (key_exists('error_code', $result)) {
					$this->throws($result['error']);
				}
				return $result;
		}
		return $response->getBody();		
	}
	
    /**
     * throw an Sinat exception
     * 
     * @param	String	$message
     * @return	Object	$this
     * @see		Zeef_Service_Adapter_Exception
     */
    public function throws($message)
    {
        require_once dirname(__FILE__) . '/Exception.php';
        throw new Zeef_Service_Sinat_Exception($message);
        return $this;
    }
}
