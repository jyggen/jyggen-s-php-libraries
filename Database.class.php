<?php
class Database extends PDO
{

	public static $connKey = false;
	public static $settings = array();
	public static $instance = false;

	public $cached      = 0;
	public $connections = 0;
	public $queries     = 0;

	protected $_cache = false;

	const CACHE_NONE       = 0;
	const CACHE_NORMAL     = 1;
	const CACHE_AGGRESSIVE = 2;

	public function __construct()
	{

		try {

			$dsn = sprintf(
				'mysql:dbname=%s;host=%s;charset=UTF-8;',
				self::$settings['database'],
				self::$settings['hostname']
			);

			parent::__construct(
				$dsn,
				self::$settings['username'],
				self::$settings['password'],
				array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
			);

		} catch (PDOException $e) {

			trigger_error($e->getMessage(), E_USER_ERROR);
			exit(1);

		}//end try

		if (array_key_exists('cachelvl', self::$settings) === false) {

			self::$settings['cachelvl'] = self::CACHE_NORMAL;

		}

		try {

			$this->_cache = new Memcache;
			$this->_cache->connect('localhost', 11211);
			$this->connections++;

		} catch (Exception $e) {

			$this->error($e->getMessage());

		}

	}

	public static function getInstance()
    {

		if (empty(self::$settings) === false) {

			if (array_key_exists('database', self::$settings) === true
				&& array_key_exists('hostname', self::$settings) === true
				&& array_key_exists('username', self::$settings) === true
				&& array_key_exists('password', self::$settings) === true
			) {

				$key = md5(serialize(self::$settings));

				if (self::$instance === false || $key !== self::$connKey) {

					self::$connKey = $key;
					self::$instance = new self();

				}

				return self::$instance;

			} else {

				trigger_error('Missing connection settings', E_USER_ERROR);
				exit(1);

			}

		} else {

			trigger_error('Missing connection settings', E_USER_ERROR);
			exit(1);

		}//end if

    }

	public function flush($delay=0)
	{

		$this->_cache->flush($delay);

	}

	public function flushKey($key, $delay=0)
	{

		$this->_cache->delete($key, $delay);

	}

	public function cacheExists($key)
	{

		$success = $this->_cache->get($key, MEMCACHE_COMPRESSED);

		return ($success === false) ? false : true;

	}

	public function load($key)
	{

		$this->cached++;
		$json = json_decode(
			gzinflate($this->_cache->get($key, MEMCACHE_COMPRESSED)),
			true
		);

		return $json;

	}

	public function save($key, $data, $ttl=0)
	{

		$data = json_encode($data);
		$data = gzdeflate($data, 9);

		if (mb_strlen($data, 'UTF-8') < 1048576) {

			$cache = $this->_cache->set($key, $data, MEMCACHE_COMPRESSED, $ttl);

			if ($cache === false) {

				trigger_error('Could not cache '.$key, E_USER_NOTICE);
				return false;

			} else {

				return true;

			}

		} else {

			$this->error('Could not cache '.$key.' (1MB limit)');
			return false;

		}//end if

	}

	public function query($sql, $parameters=null, $return=false, $ttl=300)
	{

		$sql      = trim($sql);
		$cacheID = $this->getCacheID($sql, $parameters);

		if ($return === false
			|| $ttl === false
			|| $this->cacheExists($cacheID) === false
		) {

			$sth = parent::prepare($sql);

			if ($sth !== false) {

				if ($parameters === null) {

					$parameters = array();

				}

				if ($sth->execute($parameters) === false) {

					$this->error($sth->errorInfo());

				} else {

					$this->queries++;

					if ($return === true) {

						if (substr($sql, -7) === 'LIMIT 1') {

							$data = $sth->fetch(parent::FETCH_ASSOC);

						} else {

							$data = $sth->fetchAll(parent::FETCH_ASSOC);

						}

						if ($ttl !== false) {

							$this->save($cacheID, $data, $ttl);

						}

						return $data;

					} else {

						if (substr($sql, 0, 6) === 'SELECT') {

							$num = (int) $sth->fetchColumn();

						} else {

							$num = $sth->rowCount();

						}

						return $num;

					}

				}//end if

			} else {

				$this->error(parent::errorInfo());

			}//end if

		} else {

			$data = $this->load($cacheID);
			return $data;

		}//end if

	}

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

	public function recordExistsInDB($table, $params)
	{

		$num = $this->count($table, $params);
		return ($num !== 0) ? true : false;

	}

	public function getCacheID($query, $parameters=array())
	{

		$query = preg_replace('/\s+/', ' ', $query);
		$query = str_replace(array('( ', ' )'), array('(', ')'), $query);

		switch(self::$settings['cachelvl']) {

			case 0:
			default:
				$hash = md5(uniqid());
				return $hash;
			break;

			case 1:
				$hash = md5(
					json_encode(
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

	protected function error($info)
	{

		throw new Exception($info[2]);

	}

}