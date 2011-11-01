<?php
/**
 * Zeef lib
 * 
 * @category	Zeef
 * @package		Zeef_Service
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id: Tencent.php,v 1.1 2009/10/22 06:25:01 Zephyr214@gmail.com Exp $
 */
class Zeef_Service_Tencent extends Zeef_Service_Adapter_Abstract
{
	/**
	 * Constructs a new Sina weibo web service client
	 * 
	 * @param	String $appKey	Developer's Sina weibo app key
	 * @return	Viod
	 */
	public function __construct($config)
	{
	    
	}
	    
    /**
     * prepare request options
     *
     * @param	String		$query
     * @param	Array		$options
     * @param	Array		$defaultOptions
     * @return	Zeef_Service_Abstract
     */
    protected function _prepareOptions($options = array(), $defaultOptions = array())
    {
        
    }

    /**
     * valid options
     *
     * @param	Array	$options
     * @return	Zeef_Service_Abstract
     * @throws	Zeef_Service_Exception
     */
    protected function _validate($options)
    {
        
    }

    /**
     * query!
     *
     * @param	Array	$options
     * @return	Zeef_Service_Resultset
     */
    public function query($options = array())
    {
        
    }
}