<?php
class Cache
{

	static public $instance;

	static protected $_driver;

	static public function getInstance()
	{

		if (self::$instance === null OR !self::$instance instanceof Cache) {

			self::$instance = new self();

		}

		return self::$instance;

	}

}