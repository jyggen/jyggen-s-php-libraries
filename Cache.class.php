<?php
class Cache {
	
	static public $instance;
	
	static public function getInstance() {
	
		if(self::$instance === NULL OR !self::$instance instanceof Cache) {
		
			self::$instance = new self();
		}
		
		return self::$instance;
		
	}

}