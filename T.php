<?php
/** add path to Zend and Zeef to include path */
set_include_path(
	//dirname(dirname(__FILE__)) . '/Zendframework/v1.11.7' .  /** Zend lib */
	PATH_SEPARATOR . get_include_path()
);

/**
 * Sina Weibo
 *
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id: class.SinaWeibo.php,$Revision$ ,$Date$ Zephyr214@gmail.com Exp $
 */
class T
{
    /**
     * The default file name to log message
     * @var string
     */
    const LOG_FILE = 'error.log';
    
    /**
     * Sina Weibo service
     * @var Zeef_Service_Adapter_Sinat
     */
    protected $_service = null;
    
    /**
     * Zeef Logger to writes log messages
     * @var Zeef_Logger
     */
    protected $_logger = null;

    /**
     * if auto log the error/warnning/notice/ok message
     * @var boolean
     */    
    protected $_autoLog = false;
    
    /**
     * method name used to multi thread query
     * @var string
     */
    protected $_method = null;
    
    /**
     * options to pass to api query
     * @var	array
     */
    protected $_options = array();
    
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
		/** init SinaT service */
        require_once 'Service.php';
		$config = new Zend_Config_Ini(dirname(__FILE__) . '/Service/etc/services.ini', 'Sinat', true);
		$this->_service = Zeef_Service::factory($config);
		$this->_service->setSuccessor($this->_service);
        
    }
    
    /**
     * Send api query and get result
     * 
     * @param	Array	$options	options for build parameters of uri
     * @return	Array|Zeef_Service_Exception
     * @throws	Zeef_Service_Exception
     */
    protected function _apiQuery($options = array())
    {
        $options['curloptions'][CURLOPT_TIMEOUT] = 60;
        $options = array_merge($options, $this->_options);

        //query!
        try {
            $result = $this->_service->query($options);
    	} catch (Zeef_Service_Exception $result) {
    	    $this->_autoLog && $this->_logger->logError(
    	    	"Query: {$options['feedPath']}{$options['action']}, " . $result->getMessage()
    	    );
    	}
    	return $result;
    }
    
    /**
     * magic function __get
     * 
     * @param	string	$name
     * @return	T
     */
    public function __get($name)
    {
        $this->_method = $name;
        return $this;
    }
    
    /**
     * magic function __call
     * 
     * @method T setCallback()	setCallback(string $value) set callback url
     * 
     * @param	string	$method
     * @param	string	$params
     * @return	Array
     */    
    public function __call($method, $params)
    {
        if ($method != 'multiThread') {
            trigger_error('Call to undefined method ' . __CLASS__ . "::$method()", E_USER_ERROR);
        }
        
        if (!method_exists($this, $this->_method)) {
        	if (substr($method, 0, 3) != 'set') {
	            trigger_error('Call to undefined method ' . __CLASS__ . "::{$this->_method}()", E_USER_ERROR);
        	}
        	
        	/** set a value of an option */
        	$option = substr($method, 3);
        	$this->_options[$option] = array_shift($params);
        	return $this;
        }
        Zeef_Http_Client_Adapter_MultiCurl::$returnCurlHandle = true;
               
        //get results
        foreach ($params[0] as $key=>$params) {
            !is_array($params) and $params = array($params);
            $results[$key] = call_user_func_array(array($this, $this->_method), $params);
        }
        return $results;
    }
    
    /**
     * set number of thread count for muilt-thread
     * 
     * @param	integer	$limit
     * @return	T
     */
    public function setThreadCount($limit)
    {
        
        return $this;
    }
    
    /**
     * enable the auto log
     * 
     * @return	T
     */
    public function enableAutoLog()
    {
		/** init Zeef Logger */
		require_once 'Logger.php';
		$dataDir = dirname(dirname(__FILE__)) . '/data/sinabo/';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }		
		$this->_logger = new Zeef_Logger($dataDir . self::LOG_FILE);
		        
        $this->_autoLog = true;
        return $this;
    }
    
    /**
     * let's service to ONLY return the handle of curl not results
     * 
     * @return	T
     */
//    public function handleGet()
//    {
//        $this->_service->onlyReturnHandle = true;
//        return $this;
//    }
    
    /**
     * Check if the user is authenticated
     *
     * @param	String	$user		user to authenticate
     * @param	String	$password	password for this user
     * @param	String	$appKey		a new app key with this user
     * @return	Exception|Array
     */
    public function accountVerifyCredentials($user, $password, $appKey)
    {
        if (!isset($appKey) || empty($appKey)) {
        	$message = 'App key required!';
            $this->_autoLog && $this->_logger->logError("Failed to authenticate for user: '$user', $message");
            return new Zeef_Service_Sinat_Exception($message);
        }
        
        //get result from SinaT service
		$options = array(
			'appKey'	=> $appKey,
			'user'	    => $user,
			'password'	=> $password,
		    'feedPath'	=> '/account/',
		    'action'	=> 'verify_credentials'
        );
		$result = $this->_apiQuery($options);
        return $result;
    }
    
    /**
     * Return following list with the latest status of each following
     * 
     * @param	Integer|String	$user	User ID (int64) or nickname
     * @param	Integer			$cursor	Used for page request. Passes -1 for requesting the 1st page
     * @param	Integer			$count	Count of weibo returned for one page
     * @return	Exception|Array
     */
    public function statusesFriends($user, $cursor = '', $count = '')
    {
        $userParamName = is_numeric($user) ? 'user_id' : 'screen_name';
        $options = array(
            $userParamName  => $user,
            'feedPath'		=> '/statuses/',
            'action'		=> 'friends'
        );
        !empty($cursor) and $options['cursor'] = $cursor;
        !empty($count) and $options['count'] = $count;
        
        return $this->_apiQuery($options);
    }
    
    /**
     * Return following list with the latest status of each following
     * 
     * @param	Integer|String	$user	User ID (int64) or nickname
     * @param	Integer			$cursor	Used for page request. Passes -1 for requesting the 1st page
     * @param	Integer			$count	Count of weibo returned for one page
     * @return	Exception|Array
     */
    public function statusesFollowers($user, $cursor = '', $count = '')
    {
        $userParamName = is_numeric($user) ? 'user_id' : 'screen_name';
        $options = array(
            $userParamName  => $user,
            'feedPath'		=> '/statuses/',
            'action'		=> 'followers'
        );
        !empty($cursor) and $options['cursor'] = $cursor;
        !empty($count) and $options['count'] = $count;
        
        return $this->_apiQuery($options);
    }
    
    /**
     * Returns a list of IDs that user follows
     * 
     * @param	Integer|String	$user	User ID (int64) or nickname
     * @param	Integer			$cursor	Used for page request. Passes -1 for requesting the 1st page
     * @param	Integer			$count	Count of weibo returned for one page
     * @return	Exception|Array
     */
    public function friendsIds($user, $cursor = '', $count = '')
    {
        $userParamName = is_numeric($user) ? 'user_id' : 'screen_name';
        $options = array(
            $userParamName  => $user,
            'feedPath'		=> '/friends/',
            'action'		=> 'ids'
        );    
        !empty($cursor) and $options['cursor'] = $cursor;
        !empty($count) and $options['count'] = $count;
        
        return $this->_apiQuery($options);
    }
    
    /**
     * Returns the user��s followers ID list. 
     * 
     * @param	Integer|String	$user	User ID (int64) or nickname
     * @param	Integer			$cursor	Used for page request. Passes -1 for requesting the 1st page
     * @param	Integer			$count	Count of weibo returned for one page
     * @return	Exception|Array
     */
    public function followersIds($user, $cursor = '', $count = '')
    {
        $userParamName = is_numeric($user) ? 'user_id' : 'screen_name';
        $options = array(
            $userParamName  => $user,
            'feedPath'		=> '/followers/',
            'action'		=> 'ids'
        );
        !empty($cursor) and $options['cursor'] = $cursor;
        !empty($count) and $options['count'] = $count;
        
        return $this->_apiQuery($options);
    }
    
    /**
     * UnFollows a user. Returns the befriended user��s profile when successful. 
     * 
     * @param	Integer|String	$user	User ID (int64) or nickname
     * @param	Integer			$cursor	Used for page request. Passes -1 for requesting the 1st page
     * @param	Integer			$count	Count of weibo returned for one page
     * @return	Exception|Array
     */
    public function friendshipsDestroy($user)
    {
        $userParamName = is_numeric($user) ? 'user_id' : 'screen_name';
        $options = array(
            $userParamName  => $user,
            'feedPath'		=> '/friendships/',
            'action'		=> 'destroy'
        );
        
        return $this->_apiQuery($options);        
    }

    public function statusesUpdate($content)
    {
        $options = array(
            'status'        => $content,
            'feedPath'		=> '/statuses/',
            'action'		=> 'update'
        );
        
        return $this->_apiQuery($options);        
    }
    
    
}
