<?php
class Cache
{

	static public $instance;

	static protected $_driver;

	static public function getInstance()
	{

		if (self::$instance === null
			|| self::$instance instanceof Cache === false
		) {

			self::$instance = new self();

		}

		return self::$instance;

	}

}