<?php
/**
 * Zeef lib
 * 
 * @category	Zeef
 * @package		Zeef_Service
 * @author		Zephyr Wu<zephyr214@gmail.com>
 * @copyright	Copyright (c) 2010-2011 Zeef. (http://www.Zeef.com)
 * @license		New BSD License
 * @version		SVN: $Id$
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