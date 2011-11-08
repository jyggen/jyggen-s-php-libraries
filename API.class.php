<?php
spl_autoload_register('API::autoload');

class API {
	
	protected static $args, $db, $method, $path, $resource, $data, $response;
	
	public static function autoload($name) {
		
		if (0 !== strpos($name, 'res_')) {
		
			return;
			
		} else require_once self::$path;

	
	}

	public static function call($path, $response = 'json', $post = null) {
		
		self::$response = $response;

		if($post == null && isset($_POST) && !empty($_POST)) {
			
			$post = $_POST;
			
		} elseif($post == null) {
		
			$post = false;
		
		}
		
		return self::handle_request(explode('/', substr($path, 1)), $post);
		
	}
	
	public static function response($msg, $code=200) {
		
		switch(self::$response) {
			
			case 'array':
				return self::response_array($msg);
				break;

			case 'json':
			default:
				return self::response_json($msg, $code);
				break;
			
		}
		
	}
	
	protected static function response_json($msg, $code) {
	
		$success = false;
				
		switch($code) {
			
			case 200:
				$success   = true;
				$codeTitle = 'OK';
				break;
			
			case 400:
				$codeTitle = 'Bad Request';
				break;
			
			case 500:
				$code      = 500;
				$codeTitle = 'Internal Server Error';
				break;
				
			default:
				trigger_error('Undefined HTTP Response Code (' . $code . ')', E_USER_NOTICE);
				$code      = 500;
				$codeTitle = 'Internal Server Error';
				break;
		
		}
		
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		header($_SERVER['SERVER_PROTOCOL'].' '.$code.' '.$codeTitle);
		header('Status: '.$code.' '.$codeTitle);

		print json_encode(array(
			'success' => $success,
			'data'    => $msg,
		));
		
		exit;
	
	}
	
	protected static function response_array($msg) {
	
		return $msg;
	
	}
	
	protected static function handle_request($req, $post = false) {
		
		$root = realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/../api');

		if(!isset($req[0]) OR empty($req[0]) OR !isset($req[1]) OR empty($req[1]))
			self::response('Invalid Request', 400);

		self::$resource = 'res_'.basename($req[0]);
		self::$method   = 'mtd_'.basename($req[1]);
		self::$path     = realpath($root.'/'.self::$resource.'.php');
		
		unset($req[0]);
		unset($req[1]);
		
		if(!self::parse_arguments($req))
			self::response('Invalid Arguments', 400);
			
		if(!self::parse_data($post))
			self::response('Invalid Data', 400);
		
		if(!file_exists(self::$path) OR (substr(self::$path, 0, -(strlen(self::$resource)+5)) != $root))
			self::response('Invalid Resource', 400);

		if(!is_callable(array(self::$resource, self::$method)))
			self::response('Invalid Method', 400);

		return call_user_func(array(self::$resource, self::$method));
		
	}
	
	protected static function parse_arguments($args) {
		
		$args = array_filter($args);
		
		if(!empty($args)) {
			
			foreach($args as $arg) {
			
				@list($key, $value) = explode(':', $arg);
				
				if(!isset($key) OR empty($key) OR !isset($value) OR  empty($key))
					return false;
				
				self::$args[$key] = $value;
			
			}
			
		} else {
			
			self::$args = null;
			
		}
		
		return true;
		
	}
	
	protected static function parse_data($args) {
		
		if($args == false) {
			
			return true;
		
		} else {
			
			$args = array_filter($args);
			
			if(!empty($args)) {
			
				foreach($args as $key => $value) {
					
					if(!isset($value) OR empty($value) OR !isset($key) OR  empty($key))
						return false;
					
					self::$data[$key] = $value;
				
				}
				
			} else {
				
				self::$data = null;
				
			}
		
			return true;
			
		}
		
	}
	
	protected static function get_database() {
		
		return (!self::$db) ? self::$db = Database::getInstance() : self::$db;
	
	}
	
	protected static function argument_exists($arg) {
		
		if(!is_array(self::$args))
			return false;
		
		return array_key_exists($arg, self::$args);
		
	}
	
	protected static function data_exists($key) {
		
		if(!is_array(self::$data))
			return false;
		
		return array_key_exists($key, self::$data);
		
	}
	
	protected static function convert_byte($bytes) {
		
		$size = $bytes / 1024;
		
		if($size < 1024) {

			$size  = number_format($size, 2);
			$size .= ' KB';
		
		} else {
			
			if($size / 1024 < 1024) {

				$size  = number_format($size / 1024, 2);
				$size .= ' MB';

			} else if ($size / 1024 / 1024 < 1024) {

				$size  = number_format($size / 1024 / 1024, 2);
				$size .= ' GB';
				
			} 

		}
		
		return $size;
			
	}
		
	protected static function order_by_subkey(&$array, $key, $asc=SORT_ASC) {

		$sort_flags = array(SORT_ASC, SORT_DESC); 

		if(!in_array($asc, $sort_flags))
			throw new InvalidArgumentException('sort flag only accepts SORT_ASC or SORT_DESC'); 

		$cmp = function(array $a, array $b) use ($key, $asc, $sort_flags) { 
			if(!is_array($key)) { //just one key and sort direction 
				if(!isset($a[$key]) || !isset($b[$key])) { 
					throw new Exception('attempting to sort on non-existent keys'); 
				} 
				if($a[$key] == $b[$key]) return 0; 
				return ($asc==SORT_ASC xor $a[$key] < $b[$key]) ? 1 : -1; 
			} else { //using multiple keys for sort and sub-sort 
				foreach($key as $sub_key => $sub_asc) { 
					//array can come as 'sort_key'=>SORT_ASC|SORT_DESC or just 'sort_key', so need to detect which 
					if(!in_array($sub_asc, $sort_flags)) { $sub_key = $sub_asc; $sub_asc = $asc; } 
					//just like above, except 'continue' in place of return 0 
					if(!isset($a[$sub_key]) || !isset($b[$sub_key])) { 
						throw new Exception('attempting to sort on non-existent keys'); 
					} 
					if($a[$sub_key] == $b[$sub_key]) continue; 
					return ($sub_asc==SORT_ASC xor $a[$sub_key] < $b[$sub_key]) ? 1 : -1; 
				} 
				return 0; 
			} 
		}; 
		usort($array, $cmp); 

	}
	
	protected static function calculate_average($arr) {
		
		$count = count($arr); //total numbers in array
		$total = 0;
		
		foreach ($arr as $value) {
		
			$total = $total + $value; // total value of array numbers
		
		}
		
		$average = ($total/$count); // get average value
		
		return $average;
		
	}
	
}