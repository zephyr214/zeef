<?php
/**
 * Zeef lib
 * 
 * @category	Zeef
 * @package		Zeef_Service
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id: ResultSet.php,v 1.1 2009/10/22 06:25:01 Zephyr214@gmail.com Exp $
 */
class Zeef_Service_ResultSet implements SeekableIterator, Serializable
{
	public function seek ($position) {}

	public function current () {}

	public function next () {}

	public function key () {}

	public function valid () {}

	public function rewind () {}

	public function serialize () {}

	public function unserialize ($serialized) {}	
}