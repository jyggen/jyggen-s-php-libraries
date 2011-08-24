<?php
class Database extends PDO {

	private $cache       = false;
	public  $cached      = 0;
	public  $connections = 0;
	static  $conn_key    = false;
	static  $settings    = array();
	static  $instance    = false;
	public  $queries     = 0;

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
		$this->cache->flush($delay);
	}
	public function cacheExists($key) {
		return ($cache = $this->cache->get($key)) ? true : 
false;
	}
	public function load($key) {
		$this->cached++;
		return $this->cache->get($key);
	}
	public function save($key, $data, $ttl = 0) {
		$this->cache->set($key, $data, $ttl);
	}
	public function query($sql, $parameters=array(), $return=false, 
$ttl=300) {
		
		$this->queries++;
		
		$cache_id = md5(json_encode(array('query' => $sql, 
'parameters' => $parameters)));
		$cache = $this->cache->get($cache_id);
		
		if($return === false || $cache === false) {
			$sth = parent::prepare($sql);
			if($sth) {
				if(!$sth->execute($parameters))
					$this->error($sth->errorInfo());
				if($return === true) {
					$data = (substr($sql, -7) == 
'LIMIT 1')
							? 
$sth->fetch(parent::FETCH_ASSOC)
							: $sth->fetchAll(parent::FETCH_ASSOC);
					if($ttl !== false)
						$this->cache->set($cache_id, 
$data, $ttl);
				}
				return ($return === true) ? $data : 
true;
			} else $this->error(parent::errorInfo());
		} elseif($return === true) {
			$this->cached++;
			return $cache;
		} else {
			return false;
		}
	}
	public function update($table, $params, $where) {
		$sql = 'UPDATE `' . $table . '` SET' . "\n";
		foreach($params as $k => $v)
			$sql .= '`' . $k . '` = ?,' . "\n";
		$sql = substr($sql, 0, -2);
		$sql .= "\n" . 'WHERE ';
		foreach($where as $k => $v)
			$sql .= '`' . $k . '` = ? AND' . "\n";
		$sql = substr($sql, 0, -5);
		$sql .= "\n" . 'LIMIT 1';
		$temp = $params;
		$params = array();
		foreach($temp as $v)
			$params[] = $v;
		$temp = array_merge($params, $where);
		$params = array();
		foreach($temp as $v)
			$params[] = $v;
		$this->query($sql, $params);
	}
	public function count($table, $params = array()) {
		$sql = 'SELECT COUNT(*) as total FROM `' . $table .'`';
		if(!empty($params)) {
			$sql .= ' WHERE ';
			foreach($params as $key => $value) {
				$sql .= '`' . $key . '` = ? AND';
			}
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
			return $sth->fetchColumn();
		} else $this->error(parent::errorInfo());
	}
	public function recordExistsInDB($table, $params) {
		$num = $this->count($table, $params);
		return ($num != 0) ? true : false;
	}
	private function error($info) {
		trigger_error($info[2], E_USER_ERROR);
		exit();
	}
}
