<?php
/** @see Zend_Http_Client_Adapter_Interface */
require_once 'Zend/Http/Client/Adapter/Curl.php';

/**
 * Zeef lib
 *  
 * An adapter class for Zend_Http_Client based on the curl extension.
 * Curl requires libcurl. See for full requirements the PHP manual: http://php.net/curl
 *
 * @category	Zeef
 * @package		Zeef_Curl
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id: Zeef_MultiCurl.php,v 1.1 2009/10/22 06:25:01 Zephyr214@gmail.com Exp $
 */
class Zeef_Http_Client_Adapter_MultiCurl extends Zend_Http_Client_Adapter_Curl
{
	/**
	 * if set to true, the write() will only return the curl handle
	 * @var boolean
	 */
    public static $returnCurlHandle = false;
    
    /**
     * Adapter constructor
     *
     * Config is set using setConfig()
     *
     * @return void
     * @throws Zend_Http_Client_Adapter_Exception
     */    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * build curl resource or execution
     * 
     * @param	String	$url		url to request
     * @param	Array	$addOpt		addtional options for curl
     * @param	boolean	$post		post fields
     * @param	boolean	$exec		perform a curl session or just return it
     * @return	curl result or a curl session
     */
    protected function _curl($url, $addOpt = array(), $post = false, $exec = true)
    {
        $ch = curl_init();
        $opt = array(
            CURLOPT_URL               => $url,            
            CURLOPT_RETURNTRANSFER    => 1,
            CURLOPT_TIMEOUT           => 60,
            CURLOPT_CONNECTTIMEOUT    => 30,
            CURLINFO_HEADER_OUT       => 1,
        );

        //set additional options
        foreach ($addOpt as $k=>$v) {
        	$opt[$k] = $v;
        }
        if ($post) {
            $opt[CURLOPT_POST] = 1;
            $opt[CURLOPT_POSTFIELDS] = $post;
        }
        if (stripos($url, 'https://') === 0) {
            $opt[CURLOPT_SSL_VERIFYPEER] = 0; 
            $opt[CURLOPT_SSL_VERIFYHOST] = 0;            
        }
        curl_setopt_array($ch, $opt);
        if (!$exec) return $ch;
        
        //execute!
        $data = curl_exec($ch);
        $error = curl_error($ch);
        if (!$data && !empty($error)) {
            $this->_lastCurlErr = $data = $error;
        }
        
        $this->_lastCurlInfo = curl_getinfo($ch);
        curl_close($ch);
        return $data;
    } 
    
    /**
     * multi thread for cURL
     * 
     * @param	Array	$chs	chs to execute
     * @param	String	$hook	the name of a call back function to handle the results 
     */
    public static function multiExec($chs, $hook = null)
    {
        $mh = curl_multi_init();        
        foreach ($chs as $ch) {
            curl_multi_add_handle($mh, $ch);
        }        
        $active = null;
        do {
            curl_multi_exec($mh, $active);
        } while ($active);
        
        //fetch result
        $res = array();
        foreach ($chs as $k=>$ch) {
            $data = curl_multi_getcontent($ch);
            if (empty($data)) {
                $res[$k] = "CURL_ERR: " . curl_error($ch);
            } else {
                $res[$k] = null === $hook ? $data : $this->$hook($data, $k);
            }
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $res;
    }	

    /**
     * Send request to the remote server
     *
     * @param  string        $method
     * @param  Zend_Uri_Http $uri
     * @param  float         $http_ver
     * @param  array         $headers
     * @param  string        $body
     * @return string        $request
     * @throws Zend_Http_Client_Adapter_Exception If connection fails, connected to wrong host, no PUT file defined, unsupported method, or unsupported cURL option
     */
    public function write($method, $uri, $httpVersion = 1.1, $headers = array(), $body = '')
    {
        // Make sure we're properly connected
        if (!$this->_curl) {
            require_once 'Zend/Http/Client/Adapter/Exception.php';
            throw new Zend_Http_Client_Adapter_Exception("Trying to write but we are not connected");
        }

        if ($this->_connected_to[0] != $uri->getHost() || $this->_connected_to[1] != $uri->getPort()) {
            require_once 'Zend/Http/Client/Adapter/Exception.php';
            throw new Zend_Http_Client_Adapter_Exception("Trying to write but we are connected to the wrong host");
        }

        // set URL
        curl_setopt($this->_curl, CURLOPT_URL, $uri->__toString());

        // ensure correct curl call
        $curlValue = true;
        switch ($method) {
            case Zend_Http_Client::GET:
                $curlMethod = CURLOPT_HTTPGET;
                break;

            case Zend_Http_Client::POST:
                $curlMethod = CURLOPT_POST;
                break;

            case Zend_Http_Client::PUT:
                // There are two different types of PUT request, either a Raw Data string has been set
                // or CURLOPT_INFILE and CURLOPT_INFILESIZE are used.
                if(is_resource($body)) {
                    $this->_config['curloptions'][CURLOPT_INFILE] = $body;
                }
                if (isset($this->_config['curloptions'][CURLOPT_INFILE])) {
                    // Now we will probably already have Content-Length set, so that we have to delete it
                    // from $headers at this point:
                    foreach ($headers AS $k => $header) {
                        if (preg_match('/Content-Length:\s*(\d+)/i', $header, $m)) {
                            if(is_resource($body)) {
                                $this->_config['curloptions'][CURLOPT_INFILESIZE] = (int)$m[1];
                            }
                            unset($headers[$k]);
                        }
                    }

                    if (!isset($this->_config['curloptions'][CURLOPT_INFILESIZE])) {
                        require_once 'Zend/Http/Client/Adapter/Exception.php';
                        throw new Zend_Http_Client_Adapter_Exception("Cannot set a file-handle for cURL option CURLOPT_INFILE without also setting its size in CURLOPT_INFILESIZE.");
                    }

                    if(is_resource($body)) {
                        $body = '';
                    }

                    $curlMethod = CURLOPT_PUT;
                } else {
                    $curlMethod = CURLOPT_CUSTOMREQUEST;
                    $curlValue = "PUT";
                }
                break;

            case Zend_Http_Client::DELETE:
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue = "DELETE";
                break;

            case Zend_Http_Client::OPTIONS:
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue = "OPTIONS";
                break;

            case Zend_Http_Client::TRACE:
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue = "TRACE";
                break;

            case Zend_Http_Client::HEAD:
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue = "HEAD";
                break;

            default:
                // For now, through an exception for unsupported request methods
                require_once 'Zend/Http/Client/Adapter/Exception.php';
                throw new Zend_Http_Client_Adapter_Exception("Method currently not supported");
        }
        if(is_resource($body) && $curlMethod != CURLOPT_PUT) {
            require_once 'Zend/Http/Client/Adapter/Exception.php';
            throw new Zend_Http_Client_Adapter_Exception("Streaming requests are allowed only with PUT");
        }

        // get http version to use
        $curlHttp = ($httpVersion == 1.1) ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0;

        // mark as HTTP request and set HTTP method
        curl_setopt($this->_curl, $curlHttp, true);
        curl_setopt($this->_curl, $curlMethod, $curlValue);

        if($this->out_stream) {
            // headers will be read into the response
            curl_setopt($this->_curl, CURLOPT_HEADER, false);
            curl_setopt($this->_curl, CURLOPT_HEADERFUNCTION, array($this, "readHeader"));
            // and data will be written into the file
            curl_setopt($this->_curl, CURLOPT_FILE, $this->out_stream);
        } else {
            // ensure headers are also returned
            curl_setopt($this->_curl, CURLOPT_HEADER, true);

            // ensure actual response is returned
            curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
        }

        // set additional headers
        $headers['Accept'] = '';
        curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $headers);

        /**
         * Make sure POSTFIELDS is set after $curlMethod is set:
         * @link http://de2.php.net/manual/en/function.curl-setopt.php#81161
         */
        if ($method == Zend_Http_Client::POST) {
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $body);
        } elseif ($curlMethod == CURLOPT_PUT) {
            // this covers a PUT by file-handle:
            // Make the setting of this options explicit (rather than setting it through the loop following a bit lower)
            // to group common functionality together.
            curl_setopt($this->_curl, CURLOPT_INFILE, $this->_config['curloptions'][CURLOPT_INFILE]);
            curl_setopt($this->_curl, CURLOPT_INFILESIZE, $this->_config['curloptions'][CURLOPT_INFILESIZE]);
            unset($this->_config['curloptions'][CURLOPT_INFILE]);
            unset($this->_config['curloptions'][CURLOPT_INFILESIZE]);
        } elseif ($method == Zend_Http_Client::PUT) {
            // This is a PUT by a setRawData string, not by file-handle
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $body);
        }

        // set additional curl options
        if (isset($this->_config['curloptions'])) {
            foreach ((array)$this->_config['curloptions'] as $k => $v) {
                if (!in_array($k, $this->_invalidOverwritableCurlOptions)) {
                    if (curl_setopt($this->_curl, $k, $v) == false) {
                        require_once 'Zend/Http/Client/Exception.php';
                        throw new Zend_Http_Client_Exception(sprintf("Unknown or erroreous cURL option '%s' set", $k));
                    }
                }
            }
        }

        // send the request or return the handle
        if (self::$returnCurlHandle) return $this->_curl;
        $response = curl_exec($this->_curl);
        
        // if we used streaming, headers are already there
        if(!is_resource($this->out_stream)) {
            $this->_response = $response;
        }

        $request  = curl_getinfo($this->_curl, CURLINFO_HEADER_OUT);
        $request .= $body;

        if (empty($this->_response)) {
            require_once 'Zend/Http/Client/Exception.php';
            throw new Zend_Http_Client_Exception("Error in cURL request: " . curl_error($this->_curl));
        }

        // cURL automatically decodes chunked-messages, this means we have to disallow the Zend_Http_Response to do it again
        if (stripos($this->_response, "Transfer-Encoding: chunked\r\n")) {
            $this->_response = str_ireplace("Transfer-Encoding: chunked\r\n", '', $this->_response);
        }

        // Eliminate multiple HTTP responses.
        do {
            $parts  = preg_split('|(?:\r?\n){2}|m', $this->_response, 2);
            $again  = false;

            if (isset($parts[1]) && preg_match("|^HTTP/1\.[01](.*?)\r\n|mi", $parts[1])) {
                $this->_response    = $parts[1];
                $again              = true;
            }
        } while ($again);

        // cURL automatically handles Proxy rewrites, remove the "HTTP/1.0 200 Connection established" string:
        if (stripos($this->_response, "HTTP/1.0 200 Connection established\r\n\r\n") !== false) {
            $this->_response = str_ireplace("HTTP/1.0 200 Connection established\r\n\r\n", '', $this->_response);
        }

        return $request;
    }
    
}