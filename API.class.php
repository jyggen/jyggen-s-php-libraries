<?php
spl_autoload_register('API::autoload');

class API
{

    protected static $_args;
	protected static $_db;
	protected static $_method;
	protected static $_path;
	protected static $_resource;
	protected static $_data;
	protected static $_response;
	protected static $_respath;

    public static function autoload($name)
    {

        if (0 !== strpos($name, 'res_')) {

            return;

        } else {

			include_once self::$_path;

		}

    }

    public static function call($path, $response='json', $post=null)
    {

        self::$_response = $response;

        if ($post === null && isset($_POST) === true && empty($_POST) === false) {

            $post = $_POST;

        } else if ($post === null) {

            $post = false;

        }

		$response = self::handleRequest(explode('/', substr($path, 1)), $post);
        return $response;

    }

    public static function response($msg, $code=200)
	{

        switch(self::$_response) {

			case 'array':
				$response = self::responseArray($msg);
			break;

			case 'json':
			default:
				$response = self::responseJson($msg, $code);
			break;

        }

		return $response;

    }

    protected static function responseJson($msg, $code)
    {

        $success = false;

		switch($code) {

			case 200:
				$success   = true;
				$codeTitle = 'OK';
			break;

			case 400:
				$codeTitle = 'Bad Request';
			break;

			case 401:
				$codeTitle = 'Unauthorized';
			break;

			case 500:
				$code      = 500;
				$codeTitle = 'Internal Server Error';
			break;

			default:
				trigger_error('Undefined HTTP Response ('.$code.')', E_USER_NOTICE);
				$code      = 500;
				$codeTitle = 'Internal Server Error';
			break;

        }//end switch

        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json');
        header($_SERVER['SERVER_PROTOCOL'].' '.$code.' '.$codeTitle);
        header('Status: '.$code.' '.$codeTitle);

        print json_encode(
			array(
			 'success' => $success,
			 'data'    => $msg,
			)
		);

        exit;

    }

    protected static function responseArray($msg)
    {

        return $msg;

    }

    protected static function handleRequest($req, $post=false)
    {

        $root = realpath(dirname($_SERVER['SCRIPT_FILENAME']).'/../api');

        if (isset($req[0]) === false
			|| empty($req[0]) === true
			|| isset($req[1]) === false
			|| empty($req[1]) === true
		) {

            self::response('Invalid Request', 400);

        }

		self::$_respath  = 'res_'.basename($req[0]);
        self::$_resource = 'Resource'.ucwords(basename($req[0]));
        self::$_method   = 'mtd'.ucwords(basename($req[1]));
        self::$_path     = realpath($root.'/'.self::$_respath.'.php');

        unset($req[0]);
        unset($req[1]);

        if (self::parseArguments($req) === false) {

			self::response('Invalid Arguments', 400);

		}

        if (self::parseData($post) === false) {

            self::response('Invalid Data', 400);

		}

		$resPath = substr(self::$_path, 0, -(strlen(self::$_respath) + 5));

        if (file_exists(self::$_path) === false || $resPath !== $root) {

            self::response('Invalid Resource', 400);

		}

		include_once self::$_path;

        if (is_callable(array(self::$_resource, self::$_method) === false)) {

            self::response('Invalid Method', 400);

		}

        $response = call_user_func(array(self::$_resource, self::$_method));
		return $response;

    }

    protected static function parseArguments($args)
    {

        $args = array_filter($args);

        if (empty($args) === false) {

            foreach ($args as $arg) {

				$list = explode(':', $arg);

				if (isset($list[0]) === true) {

					$key = $list[0];

				} else {

					$key = array();

				}

				if (isset($list[1]) === true) {

					$value = $list[1];

				} else {

					$value = array();

				}

                if (isset($key) === false
					|| empty($key) === true
					|| isset($value) === false
					|| empty($key) === true
				) {

                    return false;

				}

                self::$_args[$key] = $value;

            }//end foreach

        } else {

            self::$_args = null;

        }//end if

        return true;

    }

    protected static function parseData($args)
    {

        if ($args === false) {

            return true;

        } else {

            $args = array_filter($args);

            if (empty($args) === false) {

                foreach ($args as $key => $value) {

                    if (isset($value) === false
						|| empty($value) === true
						|| isset($key) === false
						|| empty($key) === true
					) {

                        return false;

					}

                    self::$_data[$key] = $value;

                }

			} else {

                self::$_data = null;

            }//end if

            return true;

        }//end if

    }

    protected static function getDatabase()
    {

		if (self::$_db === null) {

			self::$_db = Database::getInstance();

		}

		return self::$_db;

    }

    protected static function argumentExists($arg)
    {

        if (is_array(self::$_args) === false) {

            return false;

		}

		$result = array_key_exists($arg, self::$_args);
        return $result;

    }

    protected static function dataExists($key)
    {

        if (is_array(self::$_data) === false) {

            return false;

		}

        $result = array_key_exists($key, self::$_data);
		return $result;

    }
	
	protected static function convertByte($bytes=0, $decimals=0)
	{

		$quant = array(
				  'TB' => 1099511627776,
				  'GB' => 1073741824,
				  'MB' => 1048576,
				  'kB' => 1024,
				  'B ' => 1,
				 );

		foreach ($quant as $unit => $mag) {

			if (doubleval($bytes) >= $mag) {

				$bytes = sprintf('%01.'.$decimals.'f', ($bytes / $mag)).' '.$unit;
				return $bytes;
			
			}

		}

		return false;

	}

    protected static function orderBySubkey(&$array, $key, $asc=SORT_ASC)
    {

        $sortFlags = array(
		              SORT_ASC,
		              SORT_DESC,
		             );

        if (in_array($asc, $sortFlags) === false) {

            throw new Exception('sort flag only accepts SORT_ASC or SORT_DESC');

		}

		$arr = $array;

        usort(
			$arr,
			function(array $a, array $b) use ($key, $asc, $sortFlags) {

				if (is_array($key) === false) {

					if (isset($a[$key]) === false || isset($b[$key]) === false) {

						throw new Exception('sort on non-existent keys');

					} else if ($a[$key] === $b[$key]) {

						return 0;

					} else if (($asc === SORT_ASC ^ $a[$key] < $b[$key])) {

						return 1;

					} else {

						return -1;

					}

				} else {

					foreach ($key as $subKey => $subAsc) {

						if (in_array($subAsc, $sortFlags) === false) {

							$subKey = $subAsc;
							$subAsc = $asc;

						}

						if (isset($a[$subKey]) === false
							|| isset($b[$subKey]) === false
						) {

							throw new Exception('sort on non-existent keys');

						}

						if ($a[$subKey] === $b[$subKey]) {

							continue;

						}

						if (($subAsc === SORT_ASC ^ $a[$subKey] < $b[$subKey])) {

							return 1;

						} else {

							return -1;

						}

					}//end foreach

					return 0;

				}//end if

			}
		);

    }

    protected static function calculateAverage($array)
    {
		
		$count = count($array);
		
		if ($count === 0) {
		
			return 0;
		
		}

		$avg = (array_sum($array) / $count);
		return $avg;

    }

	protected static function getElapsedTime($ptime)
	{

		if (is_numeric($ptime) === false) {

			$ptime = strtotime($ptime);

		}

		$etime = (time() - $ptime);

		if ($etime < 1) {

			return '0 seconds';

		}

		$a = array(
			  31104000 => array(
						   'year',
						   'years',
						  ),
			  2592000  => array(
						   'month',
						   'months',
						  ),
			  86400    => array(
						   'day',
						   'days',
						  ),
			  3600     => array(
						   'hour',
						   'hours',
						  ),
			  60       => array(
						   'minute',
						   'minutes',
						  ),
			  1        => array(
						   'second',
						   'seconds',
						  ),
			 );

		foreach ($a as $secs => $str) {

			$d = ($etime / $secs);

			if ($d >= 1) {

				$r = round($d);

				if ($r > 1) {

					$str = $str[1];

				} else {

					$str = $str[0];

				}

				return $r.' '.$str;

			}

		}//end foreach

	}

	protected static function ip($default='0.0.0.0')
	{

		if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) === true) {

			return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];

		}

		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) === true) {

			return $_SERVER['HTTP_X_FORWARDED_FOR'];

		}

		if (isset($_SERVER['HTTP_CLIENT_IP']) === true) {

			return $_SERVER['HTTP_CLIENT_IP'];

		}

		if (isset($_SERVER['REMOTE_ADDR']) === true) {

			return $_SERVER['REMOTE_ADDR'];

		}

		return $default;

	}

	protected static function guid()
	{

		if (isset($_SERVER['HTTP_USER_AGENT']) === true) {

			$guid = hash_hmac('sha256', self::ip(), $_SERVER['HTTP_USER_AGENT']);

		} else {

			$guid = hash('sha256', self::ip());

		}

		return $guid;
		
	}

}
