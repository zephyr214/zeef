<?php
/** @see Zend_Uri_Http */
require_once 'Zend/Uri/Http.php';

/** @see Zend_Http_Client_Adapter_Interface */
require_once 'Zend/Http/Client/Adapter/Interface.php';

/**
 * Zeef lib
 *  
 * An adapter class for Zend_Http_Client based on the curl extension.
 * Curl requires libcurl. See for full requirements the PHP manual: http://php.net/curl
 *
 * @category	Zeef
 * @package		Zeef_Service
 * @subpackage	Zeef_Service_Adapter 
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id$
 */
class Zeef_MultiCurl
{
	
	
	
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
    protected function _multiCurl($chs, $hook = null)
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
}