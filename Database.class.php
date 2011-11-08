<?php
class Database extends PDO {

	static  $conn_key = false;
	static  $settings = array();
	static  $instance = false;

	public  $cached      = 0;
	public  $connections = 0;
	public  $queries     = 0;

	private $cache = false;

	const CACHE_NONE       = 0;
	const CACHE_NORMAL     = 1;
	const CACHE_AGGRESSIVE = 2;

	public function __construct() {

		try {

			$dsn = 'mysql:dbname=' . self::$settings['database'] . ';host=' . self::$settings['hostname'] . ';charset=UTF-8;';

			parent::__construct($dsn, self::$settings['username'], self::$settings['password'], array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
			));

		} catch (PDOException $e) {

			trigger_error($e->getMessage(), E_USER_ERROR);
			exit(1);

		}

		if(!array_key_exists('cachelvl', self::$settings))
			self::$settings['cachelvl'] = self::CACHE_NORMAL;
		
		$this->cache = new Memcache;
		$this->cache->connect('localhost', 11211) or trigger_error('Couldn\'t connect to Cache Server.', E_USER_ERROR);
		$this->connections++;

	}

	public static function getInstance() {

		if(!empty(self::$settings)) {
			if(array_key_exists('database', self::$settings) &&
				array_key_exists('hostname', self::$settings) &&
				array_key_exists('username', self::$settings) &&
				array_key_exists('password', self::$settings)) {

				$key = md5(serialize(self::$settings));

				if(!self::$instance || $key != self::$conn_key) {

					self::$conn_key = $key;
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

	public function flush($delay = 0) {

		return $this->cache->flush($delay);

	}

	public function flushKey($key, $delay = 0) {

		return $this->cache->delete($key, $delay);

	}

	public function cacheExists($key) {

		return (!($result = $this->cache->get($key, MEMCACHE_COMPRESSED))) ? false : true;

	}

	public function load($key) {

		$this->cached++;
		return json_decode(gzinflate($this->cache->get($key, MEMCACHE_COMPRESSED)), true);

	}

	public function save($key, $data, $ttl = 0) {

		$data = json_encode($data);
		$data = gzdeflate($data, 9);
		
		if(mb_strlen($data, 'UTF-8') < 1048576) {

			if(!$this->cache->set($key, $data, MEMCACHE_COMPRESSED, $ttl)) {

				trigger_error('Could not cache '.$key, E_USER_NOTICE);
				return false;

			} else return true;
		
		} else {
		
			trigger_error('Could not cache '.$key.', cache size is limited to 1MB', E_USER_NOTICE);
			return false;
		
		}
		
	}

	public function query($sql, $parameters = array(), $return = false, $ttl = 300) {

		$this->queries++;

		$sql      = trim($sql);
		$cache_id = $this->getCacheID($sql, $parameters);

		if($return === false OR $ttl === false OR !$this->cacheExists($cache_id)) {
			
			if($sth = parent::prepare($sql)) {

				if(!$sth->execute($parameters))
					$this->error($sth->errorInfo());

				if($return === true) {

					$data = (substr($sql, -7) == 'LIMIT 1')
						? $sth->fetch(parent::FETCH_ASSOC)
						: $sth->fetchAll(parent::FETCH_ASSOC);

					if($ttl !== false)
						$this->save($cache_id, $data, $ttl);

				}

				return ($return === true) ? $data : $sth->rowCount();

			} else $this->error(parent::errorInfo());

		} else {

			return $this->load($cache_id);

		}

	}

	public function update($table, $params, $where) {

		$sql = 'UPDATE `' . $table . '` SET' . "\n";

		foreach($params as $k => $v)
			$sql .= '`' . $k . '` = ?,' . "\n";

		$sql  = substr($sql, 0, -2);
		$sql .= "\n" . 'WHERE ';

		foreach($where as $k => $v)
			$sql .= '`' . $k . '` = ? AND' . "\n";

		$sql    = substr($sql, 0, -5);
		$sql   .= "\n" . 'LIMIT 1';
		$temp   = $params;
		$params = array();

		foreach($temp as $v)
			$params[] = $v;

		$temp   = array_merge($params, $where);
		$params = array();

		foreach($temp as $v)
			$params[] = $v;

		$rows = $this->query($sql, $params);
		
		return $rows;

	}

	public function count($table, $params = array()) {

		$sql = 'SELECT COUNT(*) as total FROM `' . $table .'`';

		if(!empty($params)) {

			$sql .= ' WHERE ';

			foreach($params as $key => $value)
				$sql .= '`' . $key . '` = ? AND';

			$sql = substr($sql, 0, -4);

		}

		$sth = parent::prepare($sql);

		if($sth) {

			if(!empty($params)) {
	
				$i = 1;
				foreach($params as $param) {
				
					$sth->bindParam($i, $param);
					$i++;
					
				}

			}

			if(!$sth->execute())
				$this->error($sth->errorInfo());

			return (int)$sth->fetchColumn();

		} else $this->error(parent::errorInfo());

	}
	
	public function recordExistsInDB($table, $params) {
	
		$num = $this->count($table, $params);
		return ($num != 0) ? true : false;
	
	}
	
	public function getCacheID($query, $parameters = array()) {
		
		switch(self::$settings['cachelvl']) {
		
			case 0:
			default:
				return md5(uniqid());
				break;

			case 1:
				return md5(json_encode(array('query' => $query, 'parameters' => $parameters)));
				break;

			case 2:
				return md5($query);
				break;
				
		}
		
	}
	
	protected function getParameters($parameters, $keep_cachable = TRUE) {
	
		$parameters_array = array();
		if(!empty($parameters)) {
			foreach($parameters as $key => $value) {
				
				if(!is_numeric($key)) {
				
					echo $key . '<br>';
					
					if(!is_array($value)) {
						
					} else {
					
						if(!array_key_exists('cached', $value) OR $value['cache'] == FALSE) {
						
							$parameters_array[] = $value['value'];
						
						} elseif($keep_cachable) {
							
							$parameters_array[] = $value['value'];
							
						}
					
					}
					
				} else {
				
					$parameters_array[] = $value;
				
				}

			}
		}

		return $parameters_array;
	
	}
	
	protected function error($info) {

		trigger_error($info[2], E_USER_ERROR);
		exit(1);

	}

}