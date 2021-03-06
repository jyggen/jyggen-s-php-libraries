<?php
/**
 * Part of jyggen's PHP Libraries.
 *
 * Database class.
 *
 * @package jyggen's PHP Libraries
 * @license MIT License
 * @copyright 2012 Jonas Stendahl
 * @link http://jyggen.com
 */

class Database extends PDO
{

	public static $connKey  = false;
	public static $dsn      = array();
	public static $settings = array();
	public static $instance = null;

	public $cached      = 0;
	public $connections = 0;
	public $queries     = 0;

	protected $_cache = false;

	const CACHE_NONE       = 0;
	const CACHE_NORMAL     = 1;
	const CACHE_AGGRESSIVE = 2;

	/**
	 * Creates a new connection towards the database
	 * and the specified cache engine.
	 *
	 * @return	void
	 */
	public function __construct()
	{

		try {

			$dsn = sprintf(
				'mysql:dbname=%s;host=%s;charset=utf8;',
				self::$dsn['database'],
				self::$dsn['hostname']
			);

			parent::__construct(
				$dsn,
				self::$dsn['username'],
				self::$dsn['password'],
				array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
			);

		} catch (PDOException $e) {

			throw new DatabaseException($e->getMessage());

		}//end try

		if (array_key_exists('cachelvl', self::$dsn) === false) {

			self::$dsn['cachelvl'] = self::CACHE_NORMAL;

		}

		try {

			// Legacy fallback to prevent old applications from crashing.
			if (class_exists('Cache') === false) {

				include_once __DIR__.'/Cache.class.php';

			}

			$this->_cache = Cache::loadEngine('Memcached');
			$this->connections++;

		} catch (Exception $e) {

			throw new DatabaseException($e->getMessage());

		}

	}

	/**
	 * Tries to retrieve any existing connection to
	 * the database. Otherwise it'll create a new one
	 * based on the settings (hopefully) supplied earlier.
	 *
	 * @return	object
	 */
	public static function getInstance()
	{

		// Legacy fallback. Old code might still use $settings instead of $dsn.
		if (empty(self::$settings) === false && empty(self::$dsn) === true) {

			self::$dsn = &self::$settings;

		}

		// Check if DSN is empty.
		if (empty(self::$dsn) === true) {

			throw new DatabaseException('No DSN values supplied');

		}

		// Check if database is missing from DSN.
		if (array_key_exists('database', self::$dsn) === false) {

			throw new DatabaseException('Missing DSN value: database');

		}

		// Check if hostname is missing from DSN.
		if (array_key_exists('hostname', self::$dsn) === false) {

			throw new DatabaseException('Missing DSN value: hostname');

		}

		// Check if username is missing from DSN.
		if (array_key_exists('username', self::$dsn) === false) {

			throw new DatabaseException('Missing DSN value: username');

		}

		// Check if password is missing from DSN.
		if (array_key_exists('password', self::$dsn) === false) {

			throw new DatabaseException('Missing DSN value: password');

		}

		// Generate an unique DSN key.
		$key = md5(serialize(self::$dsn));
		$ok  = self::$instance instanceof Database;

		// Create a new instance if we don't have one.
		if (self::$instance === true || $ok === false || $key !== self::$connKey) {

			self::$connKey  = $key;
			self::$instance = new self();

		}

		return self::$instance;

    }

	/**
	 * Remove all data from the cache.
	 *
	 * @param	integer	number of seconds to delay the removal
	 * @return	void
	 */
	public function flush($delay=0)
	{

		$this->_cache->flush($delay);

	}

	/**
	 * Remove a specific key from the cache.
	 *
	 * @param	string	key to remove
	 * @param	integer	number of seconds to delay the removal
	 * @return	void
	 */
	public function flushKey($key, $delay=0)
	{

		$this->_cache->flushKey($key, $delay);

	}

	/**
	 * Check if a specific key exists in the cache.
	 *
	 * @param	string	key to check
	 * @return	boolean
	 */
	public function cacheExists($key)
	{

		$exists = $this->_cache->exists($key);
		return $exists;

	}

	/**
	 * Retrieve a key's cached data.
	 *
	 * @param	string	key to retrieve
	 * @return	mixed
	 */
	public function load($key)
	{

		$this->cached++;
		$data = $this->_cache->get($key);
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
	public function save($key, $data, $ttl=0)
	{

		$success = $this->_cache->set($key, $data, $ttl);
		return $success;

	}

	/**
	 * Tries to retrieve any existing connection to
	 * the database. Otherwise it'll create a new one
	 * based on the settings (hopefully) supplied earlier.
	 *
	 * @param	string	sql query
	 * @param	mixed	single parameter, array of parameters or null
	 * @param	boolean	if true, return data or row count
	 * @param	integer	number of seconds to cache the query
	 * @return	mixed
	 */
	public function query($sql, $parameters=null, $return=false, $ttl=300)
	{

		self::paramToArray($parameters);

		$sql     = trim($sql);
		$cacheID = $this->getCacheID($sql, $parameters);

		// Check if the cached data should be returned.
		if ($return === false
			|| $ttl === false
			|| $this->cacheExists($cacheID) === false
		) {

			$sth = parent::prepare($sql);

			if ($sth !== false) {

				if ($sth->execute($parameters) === false) {

					$this->error($sth->errorInfo());

				} else {

					$this->queries++;

					if ($return === true) {

						$data = self::getReturnData($sth, $sql);

						if ($ttl !== false) {

							$this->save($cacheID, $data, $ttl);

						}

						return $data;

					} else {

						$num = self::getReturnValue($sth, $sql);
						return $num;

					}//end if

				}//end if

			} else {

				$this->error(parent::errorInfo());

			}//end if

		// Return cached data.
		} else {

			$data = $this->load($cacheID);
			return $data;

		}//end if

	}

	/**
	 * Update a table.
	 *
	 * @param	string	key to retrieve
	 * @param   array   fields to update (field1 => val1, field2 => val2)
	 * @param   array   where arguments (field1 => val1, field2 => val2)
	 * @return	array
	 */
	public function update($table, $params, $where)
	{

		$sql = 'UPDATE `'.$table.'` SET'."\n";

		foreach ($params as $k => $v) {

			$sql .= '`'.$k.'` = ?,'."\n";

		}

		$sql  = substr($sql, 0, -2);
		$sql .= "\n".'WHERE ';

		foreach ($where as $k => $v) {

			$sql .= '`'.$k.'` = ? AND'."\n";

		}

		$sql    = substr($sql, 0, -5);
		$sql   .= "\n".'LIMIT 1';
		$temp   = $params;
		$params = array();

		foreach ($temp as $v) {

			$params[] = $v;

		}

		$temp   = array_merge($params, $where);
		$params = array();

		foreach ($temp as $v) {

			$params[] = $v;

		}

		$rows = $this->query($sql, $params);

		return $rows;

	}

	/**
	 * Count number of rows in a table.
	 *
	 * @param	string	table name
	 * @param	array	where parameters (field1 => val1, field2 => val2)
	 * @return	integer
	 */
	public function count($table, $params=array())
	{

		$sql = 'SELECT COUNT(*) FROM `'.$table.'`';

		if (empty($params) === false) {

			$sql .= ' WHERE ';

			foreach ($params as $key => $value) {

				$sql   .= '`'.$key.'` = ? AND';
				$args[] = $value;

			}

			$sql = substr($sql, 0, -4);

		}

		if (empty($params) === false) {

			$data = $this->query($sql, $args);
			return $data;

		} else {

			$data = $this->query($sql);
			return $data;

		}

	}

	/**
	 * Check if the specified record exists in the table.
	 *
	 * @param	string	table name
	 * @param   array	where parameters (field1 => val1, field2 => val2)
	 * @return	boolean
	 */
	public function recordExistsInDB($table, $params)
	{

		$num = $this->count($table, $params);
		return ($num !== 0) ? true : false;

	}

	/**
	 * Get the CacheID for a query.
	 *
	 * @param	string	query string
	 * @param   array	query parameters
	 * @return	string
	 */
	public function getCacheID($query, $parameters=array())
	{

		$query = preg_replace('/\s+/', ' ', $query);
		$query = str_replace(array('( ', ' )'), array('(', ')'), $query);

		switch(self::$dsn['cachelvl']) {

			case 0:
			default:
				$hash = md5(uniqid());
				return $hash;
			break;

			case 1:
				$hash = md5(
					serialize(
						array(
						 'query'      => $query,
						 'parameters' => $parameters,
						)
					)
				);
				return $hash;
			break;

			case 2:
				$hash = md5($query);
				return $hash;
			break;

		}//end switch

	}

	/**
	 * Throw an exception.
	 *
	 * @param	string	error message
	 * @return	void
	 */
	protected function error($info)
	{

		throw new DatabaseException($info[2]);

	}

	/**
	 * Make sure that the parameters var is an array.
	 *
	 * @param	mixed	query parameters
	 * @return	void
	 */
	protected function paramToArray(&$parameters)
	{

		if ($parameters === null) {

			$parameters = array();

		} else if (is_array($parameters) === false) {

			$parameters = array($parameters);

		}

		foreach ($parameters as &$param) {

			$check = $param instanceof SimpleXMLElement;

			if ($check === true) {

				$param = (string) $param;

			}

		}

	}

	/**
	 * Return rows affected by the latest query, or the ID if it's an insert query.
	 *
	 * @param	object	PDO::prepare object
	 * @param	string  SQL query
	 * @return	integer
	 */
	protected function getReturnValue($sth, $sql)
	{

		// Return row count(?) on SELECT query.
		if (substr($sql, 0, 6) === 'SELECT') {

			$num = (int) $sth->fetchColumn();

		// Return ID on INSERT query.
		} else if (substr($sql, 0, 6) === 'INSERT') {

			$num = parent::lastInsertId();

		// Return row count.
		} else {

			$num = $sth->rowCount();

		}

		return $num;

	}

	/**
	 * Fetch the data of the latest insert query. On LIMIT 1 queries
	 * it will return an associative array while any other query will
	 * return a numeric array with associative arrays inside.
	 *
	 * @param	object	PDO::prepare object
	 * @param	string  SQL query
	 * @return	array
	 */
	protected function getReturnData($sth, $sql)
	{

		if (substr($sql, -7) === 'LIMIT 1') {

			$data = $sth->fetch(parent::FETCH_ASSOC);

		} else {

			$data = $sth->fetchAll(parent::FETCH_ASSOC);

		}

		return $data;

	}

}

class DatabaseException extends Exception
{

}
