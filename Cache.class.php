<?php
/**
 * Part of jyggen's PHP Libraries.
 *
 * Cache class.
 *
 * @package jyggen's PHP Libraries
 * @license MIT License
 * @copyright 2012 Jonas Stendahl
 * @link http://jyggen.com
 */

abstract class Cache
{

	/**
	 * Load a specific cache engine. This is the only way to initiate this class.
	 *
	 * @return	object
	 */
	final static public function loadEngine($name)
	{

		$enginePath = __DIR__.'/cache/'.$name.'.php';

		if (file_exists($enginePath) === true) {

			include_once $enginePath;

			$className = $name.'Engine';

			if (class_exists($className) === true) {

				$engine = new $className;
				return $engine;

			} else {

				throw new CacheException('Unable to load engine '.$name);

			}

		} else {

			throw new CacheException('Unknown engine '.$name);

		}//end if

	}

	/**
	 * Delete the whole cache.
	 *
	 * @param	integer	number of seconds to delay the removal. may not work in
	 *					every cache engine.
	 * @return	void
	 */
	public function flush($delay)
	{
		
	}

	/**
	 * Delete the cache for a specific key.
	 *
	 * @param	string	key to delete
	 * @param	integer	number of seconds to delay the removal. may not work in
	 *					every cache engine.
	 * @return	void
	 */
	public function flushKey($key, $delay)
	{

	}

	/**
	 * Check if a specific key exists in the cache.
	 *
	 * @param	string	key to check
	 * @return	boolean
	 */
	public function exists($key)
	{

	}

	/**
	 * Retrieve a key's cached data.
	 *
	 * @param	string	key to retrieve
	 * @return	mixed
	 */
	public function get($key)
	{

	}

	/**
	 * Cache data to a specific key.
	 *
	 * @param	string	cache key
	 * @param   mixed	data to save
	 * @param   integer	how long the cache should be valid in seconds
	 * @return	mixed
	 */
	public function set($key, $data, $ttl)
	{

	}

}

class CacheException extends Exception
{

}