<?php
/**
 * Zeef lib
 * 
 * @category	Zeef
 * @package		Zeef_Service
 * @author		Zephyr Wu
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id: Service.php,v 1.1 2009/10/22 06:25:01 Zephyr214@gmail.com Exp $
 */
class Zeef_Service_Result
{
    const NAME_SPACE = 'ns';
    /**
     * Result fields
     *
     * @var array
     */
    protected $_fields = array();

    /**
     * field mapping
     *
     * @var array
     */
    protected $_fieldMap = array();

    protected $_attributes = array();

    /**
     * REST response fragment for the result
     *
     * @var DOMElement
     */
    protected $_result;

    /**
     * Object for XPath queries
     *
     * @var DOMXPath
     */
    protected $_xpath;

    /**
     * Web result namespace
     *
     * @var string
     */
    protected $_namespace = '';

    protected $_data = array();

    /**
     * Initializes the result
     *
     * @param  DOMElement $result
     * @return void
     */
    public function __construct(DOMElement $result)
    {
        // default fields for all search results:
        $fields = array('Name' , 'Url' , 'Description');

        // merge w/ child's fields
        $this->_fields = array_merge($fields, $this->_fields);

        $this->_xpath = new DOMXPath($result->ownerDocument);
        $this->_xpath->registerNamespace(self::NAME_SPACE, $this->_namespace);

        foreach ($this->_fields as $f) {
            $readField = isset($this->_fieldMap[$f]) ? $this->_fieldMap[$f] : $f;
            if (in_array($readField, $this->_attributes) && $result->hasAttribute($readField)) {
                $this->{$f} = trim($result->getAttribute($readField));
            } else {
                if (empty($this->_namespace)) {
                    $query = "./{$readField}/text()";
                } else {
                    $query = "./" . self::NAME_SPACE . ":{$readField}/text()";
                }
                $node = $this->_xpath->query($query, $result);
                if ($node->length == 1) {
                    $this->{$f} = trim($node->item(0)->data);
                } else {
                    $this->{$f} = null;
                }
            }
        }

        $this->_result = $result;
    }

    /**
     * to Array
     * 
     * @return array
     */
    public function toArray()
    {
        return $this->_data;
    }

    /* magic methods */
    public function __get($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : NULL;
    }

    public function __set($name, $value)
    {
        $this->_data[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    public function __unset($name)
    {
        unset($this->_data[$name]);
    }
}