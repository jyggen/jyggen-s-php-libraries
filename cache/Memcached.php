<?php
/**
 * Part of jyggen's PHP Libraries.
 *
 * MemcachedEngine class.
 *
 * @package jyggen's PHP Libraries
 * @license MIT License
 * @copyright 2012 Jonas Stendahl
 * @link http://jyggen.com
 */

class MemcachedEngine extends Cache
{

	protected $engine = null;

	public function __construct() {

		$this->engine = new Memcache;
		$this->engine->connect('localhost', 11211);

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

		$this->engine->flush($delay);

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

		$this->engine->delete($key, $delay);

	}

	/**
	 * Check if a specific key exists in the cache.
	 *
	 * @param	string	key to check
	 * @return	boolean
	 */
	public function exists($key)
	{

		$exists = $this->engine->get($key, MEMCACHE_COMPRESSED);

		if ($exists === false) {

			return false;

		} else {

			return true;

		}

	}

	/**
	 * Retrieve a key's cached data.
	 *
	 * @param	string	key to retrieve
	 * @return	mixed
	 */
	public function get($key)
	{

		$data = $this->engine->get($key, MEMCACHE_COMPRESSED);
		$data = gzinflate($data);
		$data = unserialize($data);

		return $data;

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


		$data = serialize($data);
		$data = gzdeflate($data, 9);

		if (mb_strlen($data, 'UTF-8') < 1048576) {

			$cache = $this->engine->set($key, $data, MEMCACHE_COMPRESSED, $ttl);

			if ($cache === false) {

				throw new CacheException('Could not cache .'.$key);

			} else {

				return true;

			}

		} else {

			throw new CacheException('Could not cache '.$key.' (1MB limit)');

		}//end if

	}

}