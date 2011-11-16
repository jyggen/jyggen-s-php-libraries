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

    public static function autoload($name)
    {

        if (0 !== strpos($name, 'res_')) {

            return;

        } else {

			include_once self::$_path;

		}

    }

    public static function call($path, $response = 'json', $post = null)
    {

        self::$_response = $response;

        if ($post == null && isset($_POST) && !empty($_POST)) {

            $post = $_POST;

        } elseif ($post == null) {

            $post = false;

        }

        return self::handleRequest(explode('/', substr($path, 1)), $post);

    }

    public static function response($msg, $code=200)
	{

        switch(self::$_response) {

		case 'array':
            return self::responseArray($msg);
            break;

        case 'json':
		default:
			return self::responseJson($msg, $code);
            break;

        }

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

		case 500:
			$code      = 500;
			$codeTitle = 'Internal Server Error';
			break;

		default:
			trigger_error('Undefined HTTP Response ('.$code.')', E_USER_NOTICE);
			$code      = 500;
			$codeTitle = 'Internal Server Error';
			break;

        }

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

    protected static function handleRequest($req, $post = false)
    {

        $root = realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/../api');

        if (!isset($req[0])
			OR empty($req[0])
			OR !isset($req[1])
			OR empty($req[1])
		) {

            self::response('Invalid Request', 400);

        }

        self::$_resource = 'res_'.basename($req[0]);
        self::$_method   = 'mtd_'.basename($req[1]);
        self::$_path     = realpath($root.'/'.self::$_resource.'.php');

        unset($req[0]);
        unset($req[1]);

        if (!self::parseArguments($req)) {

			self::response('Invalid Arguments', 400);

		}

        if (!self::parseData($post)) {

            self::response('Invalid Data', 400);

		}

		$resPath = substr(self::$_path, 0, -(strlen(self::$_resource) + 5));

        if (!file_exists(self::$_path) OR $resPath != $root) {

            self::response('Invalid Resource', 400);

		}

        if (!is_callable(array(self::$_resource, self::$_method))) {

            self::response('Invalid Method', 400);

		}

        return call_user_func(array(self::$_resource, self::$_method));

    }

    protected static function parseArguments($args)
    {

        $args = array_filter($args);

        if (!empty($args)) {

            foreach ($args as $arg) {

                @list($key, $value) = explode(':', $arg);

                if (!isset($key)
					OR empty($key)
					OR !isset($value)
					OR empty($key)
				) {

                    return false;

				}

                self::$_args[$key] = $value;

            }

        } else {

            self::$_args = null;

        }

        return true;

    }

    protected static function parseData($args)
    {

        if ($args == false) {

            return true;

        } else {

            $args = array_filter($args);

            if (!empty($args)) {

                foreach ($args as $key => $value) {

                    if (!isset($value)
						OR empty($value)
						OR !isset($key)
						OR empty($key)
					) {

                        return false;

					}

                    self::$_data[$key] = $value;

                }

            } else {

                self::$_data = null;

            }

            return true;

        }

    }

    protected static function getDatabase()
    {

        return (!self::$_db)
			? self::$_db = Database::getInstance()
			: self::$_db;

    }

    protected static function argumentExists($arg)
    {

        if (!is_array(self::$_args)) {

            return false;

		}

        return array_key_exists($arg, self::$_args);

    }

    protected static function dataExists($key)
    {

        if (!is_array(self::$_data)) {

            return false;

		}

        return array_key_exists($key, self::$_data);

    }

    protected static function convertByte($bytes)
    {

        $size = $bytes / 1024;

        if ($size < 1024) {

            $size  = number_format($size, 2);
            $size .= ' KB';

        } else {

            if ($size / 1024 < 1024) {

                $size  = number_format($size / 1024, 2);
                $size .= ' MB';

            } else if ($size / 1024 / 1024 < 1024) {

                $size  = number_format($size / 1024 / 1024, 2);
                $size .= ' GB';

            }

        }

        return $size;

    }

    protected static function orderBySubkey(&$array, $key, $asc=SORT_ASC)
    {

        $sortFlags = array(SORT_ASC, SORT_DESC);

        if (!in_array($asc, $sortFlags)) {

            throw new Exception('sort flag only accepts SORT_ASC or SORT_DESC');

		}

        $cmp = function(array $a, array $b) use ($key, $asc, $sortFlags) {

            if (!is_array($key)) {

				if (!isset($a[$key]) || !isset($b[$key])) {

					throw new Exception('sort on non-existent keys');

				}

				if ($a[$key] == $b[$key]) {

					return 0;

				}

				return ($asc==SORT_ASC xor $a[$key] < $b[$key]) ? 1 : -1;

			} else {

				foreach ($key as $subKey => $subAsc) {

					if (!in_array($subAsc, $sortFlags)) {

						$subKey = $subAsc;
						$subAsc = $asc;

					}

                    if (!isset($a[$subKey]) || !isset($b[$subKey])) {

						throw new Exception('sort on non-existent keys');

					}

					if ($a[$subKey] == $b[$subKey]) {

						continue;

					}

					if ($subAsc == SORT_ASC
						xor $a[$subKey] < $b[$subKey]
					) {

						return 1;

					} else {

						return -1;

					}

				}

                return 0;

            }

        };

        usort($array, $cmp);

    }

    protected static function calculateAverage($arr)
    {

        $count = count($arr);
        $total = 0;

        foreach ($arr as $value) {

            $total = $total + $value;

        }

        $average = ($total/$count);

        return $average;

    }

}