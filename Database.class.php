<?php
class Database extends PDO
{

	static  $connKey = false;
	static  $settings = array();
	static  $instance = false;

	public  $cached      = 0;
	public  $connections = 0;
	public  $queries     = 0;

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
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
				)
			);

		} catch (PDOException $e) {

			trigger_error($e->getMessage(), E_USER_ERROR);
			exit(1);

		}

		if (!array_key_exists('cachelvl', self::$settings)) {

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

		if (!empty(self::$settings)) {

			if (array_key_exists('database', self::$settings)
				&& array_key_exists('hostname', self::$settings)
				&& array_key_exists('username', self::$settings)
				&& array_key_exists('password', self::$settings)
			) {

				$key = md5(serialize(self::$settings));

				if (!self::$instance || $key != self::$connKey) {

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

		}

    }

	public function flush($delay = 0)
	{

		return $this->_cache->flush($delay);

	}

	public function flushKey($key, $delay = 0)
	{

		return $this->_cache->delete($key, $delay);

	}

	public function cacheExists($key)
	{

		return (!($result = $this->_cache->get($key, MEMCACHE_COMPRESSED)))
				? false
				: true;

	}

	public function load($key)
	{

		$this->cached++;
		return json_decode(
			gzinflate($this->_cache->get($key, MEMCACHE_COMPRESSED)),
			true
		);

	}

	public function save($key, $data, $ttl = 0)
	{

		$data = json_encode($data);
		$data = gzdeflate($data, 9);

		if (mb_strlen($data, 'UTF-8') < 1048576) {

			if (!$this->_cache->set($key, $data, MEMCACHE_COMPRESSED, $ttl)) {

				trigger_error('Could not cache '.$key, E_USER_NOTICE);
				return false;

			} else {

				return true;

			}

		} else {

			$this->error('Could not cache '.$key.' (1MB limit)');
			return false;

		}

	}

	public function query($sql, $parameters=array(), $return=false, $ttl=300)
	{

		$sql      = trim($sql);
		$cacheID = $this->getCacheID($sql, $parameters);

		if ($return === false
			OR $ttl === false
			OR !$this->cacheExists($cacheID)
		) {

			if ($sth = parent::prepare($sql)) {

				if (!$sth->execute($parameters)) {

					$this->error($sth->errorInfo());

				} else {

					$this->queries++;

					if ($return === true) {

						$data = (substr($sql, -7) == 'LIMIT 1')
							? $sth->fetch(parent::FETCH_ASSOC)
							: $sth->fetchAll(parent::FETCH_ASSOC);

						if ($ttl !== false) {

							$this->save($cacheID, $data, $ttl);

						}

					}

					return ($return === true)
						? $data
						: ((substr($sql, 0, 6) == 'SELECT')
							? (int)$sth->fetchColumn()
							: $sth->rowCount());

				}

			} else {

				$this->error(parent::errorInfo());

			}

		} else {

			return $this->load($cacheID);

		}

	}

	public function update($table, $params, $where)
	{

		$sql = 'UPDATE `' . $table . '` SET' . "\n";

		foreach ($params as $k => $v) {

			$sql .= '`' . $k . '` = ?,' . "\n";

		}

		$sql  = substr($sql, 0, -2);
		$sql .= "\n" . 'WHERE ';

		foreach ($where as $k => $v) {

			$sql .= '`' . $k . '` = ? AND' . "\n";

		}

		$sql    = substr($sql, 0, -5);
		$sql   .= "\n" . 'LIMIT 1';
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

	public function count($table, $params = array())
	{

		$sql = 'SELECT COUNT(*) FROM `' . $table .'`';

		if (!empty($params)) {

			$sql .= ' WHERE ';

			foreach ($params as $key => $value) {

				$sql   .= '`' . $key . '` = ? AND';
				$args[] = $value;

			}

			$sql = substr($sql, 0, -4);

		}

		return (!empty($params))
			? $this->query($sql, $args)
			: $this->query($sql);

	}

	public function recordExistsInDB($table, $params)
	{

		$num = $this->count($table, $params);
		return ($num != 0) ? true : false;

	}

	public function getCacheID($query, $parameters = array())
	{

		$query = preg_replace('/\s+/', ' ', $query);
		$query = str_replace(array('( ', ' )'), array('(', ')'), $query);

		switch(self::$settings['cachelvl']) {

		case 0:
		default:
			return md5(uniqid());
			break;

		case 1:
			return md5(
				json_encode(
					array(
						'query'      => $query,
						'parameters' => $parameters
					)
				)
			);
			break;

		case 2:
			return md5($query);
			break;

		}

	}

	protected function error($info)
	{

		throw new Exception($info[2]);

	}

}