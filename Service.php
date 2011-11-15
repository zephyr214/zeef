<?php
/**
 * Zeef lib
 * 
 * @category	Zeef
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id$
 */

/** @see Zend_Loader */
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);

/**
 * Zeef_Service
 * 
 * e.g.
 * 	$config = new Zend_Config_Ini('etc/services.ini', 'Sinat', true);
 * 	$service = Zeef_Service::factory($config);
 */
class Zeef_Service
{
    /**
     * factory service
     * 
     * @param	String|Zend_Config	$adapter	adapter name or the Zend Config that contains adapter name
     * @param	Zend_Config			$config		Zend Config array pass to adapter class constructor
     * @return	Zeef_Service_Adapter_Abstract
     * @throws	Exception
     */
    public static function factory($adapter = null, $config = array())
    {
        /** get config from 2nd paramater */
    	if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        }
        
        /** get adapter name and config object from 1st paramater */
        if ($adapter instanceof Zend_Config) {
            if (isset($adapter->params)) {
                $config = $adapter->params->toArray();
            }
            if (isset($adapter->adapter)) {
                $adapter = (string) $adapter->adapter;
            }
        }
        
        /** throw exceptions */
        if (!is_array($config)) {
            $this->_throw('Adapter parameters must be in an array or a Zend_Config object');
        }
        if (!is_string($adapter) || empty($adapter)) {
            $this->_throw('Adapter name must be specified in a string');
        }
        
        /** form full adapter class name */
        $namespace = 'Zeef_Service_Adapter';
        if (isset($config['adapterNamespace'])) {
            if ($config['adapterNamespace'] != '') {
                $namespace = $config['adapterNamespace'];    
            }
            unset($config['adapterNamespace']);
        }
        
        /** get instance of this adapter */
        $adapterName = strtolower("{$namespace}_{$adapter}");
        $adapterName = str_replace(' ', '_', ucwords(str_replace('_', ' ', $adapterName)));
        Zend_Loader::loadClass($adapterName);
        $serviceAdapter = new $adapterName($config);

        /** Verify that the object created is a descendent of the abstract adapter type */
        if (!$serviceAdapter instanceof Zeef_Service_Adapter_Abstract) {
            $this->_throw("Adapter class '$adapterName' does not extend Zeef_Service_Adapter_Abstract");
        }

        return $serviceAdapter;
    }
    
    /**
     * throw exception
     * 
     * @param	String	$message
     * @return	Object	$this
     * @see		Zeef_Service_Adapter_Abstract
     */
    private final function _throw($message)
    {
        require_once dirname(__FILE__) . '/Service/Exception.php';
        throw new Zeef_Service_Exception($message);
        return $this;
    }
}
?>