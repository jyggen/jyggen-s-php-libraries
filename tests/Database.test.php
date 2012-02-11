<?php
require_once 'simpletest/autorun.php';
require_once '../Database.class.php';
require_once '../Cache.class.php';

class TestOfDatabase extends UnitTestCase {
	
	function testConnectionAndInstances() {
    
		Database::$dsn['hostname'] = 'localhost';
		Database::$dsn['database'] = 'jyggen_dev';
		Database::$dsn['username'] = 'jyggen';
		Database::$dsn['password'] = 'I"O$Ka7Be0N9';
		Database::$dsn['cachelvl'] = Database::CACHE_NORMAL;
		
		$dbh1 = Database::getInstance();
		$dbh2 = Database::getInstance();
		$dbh3 = &$dbh1;

		$this->assertIsA($dbh1, 'Database');
		$this->assertSame($dbh2, $dbh1);
		$this->assertSame($dbh3, $dbh2);

	}
	
	function testInsertDataAndSelect() {
		
		$dbh = Database::getInstance();
		
		$this->username = makeRandomString();
		$this->password = md5(makeRandomString());
		$this->eaddress = makeRandomString(7).'@example.com';

		$this->username2 = makeRandomString();
		$this->password2 = md5(makeRandomString());
		$this->eaddress2 = makeRandomString(7).'@example.com';
		
		$id   = $dbh->query('INSERT INTO test_table (name, password, email) VALUES (?, ?, ?)', array($this->username, $this->password, $this->eaddress));
		$data = $dbh->query('SELECT * FROM test_table WHERE name = ? LIMIT 1', $this->username, true, 10);

		$id2  = $dbh->query('INSERT INTO test_table (name, password, email) VALUES (?, ?, ?)', array($this->username2, $this->password2, $this->eaddress2));
		$dat2 = $dbh->query('SELECT * FROM test_table WHERE name = ?', array($this->username2), true, 10);
		
		$this->assertIdentical($data['name'], $this->username);
		$this->assertIdentical($data['id'], $id);
		$this->assertIdentical($dat2[0]['name'], $this->username2);
		$this->assertIdentical($dat2[0]['id'], $id2);
		$this->assertIdentical($data['email'], $this->eaddress);
		$this->assertIdentical($dat2[0]['email'], $this->eaddress2);
		$this->assertIdentical($data['password'], $this->password);
		$this->assertIdentical($dat2[0]['password'], $this->password2);
	
	}
	
	function testUpdateTable() {
		
		$dbh = Database::getInstance();
		
		$this->username2_new = makeRandomString();

		$row = $dbh->update('test_table', array('name' => $this->username2_new), array('name' => $this->username2));
		
		$this->assertIdentical($row, 1);
	
	}
	
	function testCountDataInTable() {
		
		$dbh = Database::getInstance();
		
		$num  = $dbh->count('test_table', array('name' => $this->username));
		$num2 = $dbh->count('test_table');
		$num3 = $dbh->recordExistsInDB('test_table', array('name' => $this->username));
		$num4 = $dbh->query('SELECT COUNT(*) FROM test_table WHERE name = ?', array($this->username));
		$num5 = $dbh->query('SELECT COUNT(*) FROM test_table');
		
		$this->assertIdentical($num, 1);
		$this->assertIdentical($num2, 2);
		$this->assertTrue($num3);
		$this->assertIdentical($num4, 1);
		$this->assertIdentical($num5, 2);

	}
	
	function testCacheAndKeys() {
		
		$dbh = Database::getInstance();
		
		$data   = $dbh->query('SELECT * FROM test_table WHERE name = ? LIMIT 1', array($this->username2_new), true, 10);
		$dat2   = $dbh->query('SELECT * FROM test_table WHERE name = ?', array($this->username2), true, 10);
		$exists = $dbh->cacheExists($dbh->getCacheID('SELECT * FROM test_table WHERE name = ? LIMIT 1', array($this->username2_new)));
		
		$this->assertIdentical($data['name'], $this->username2_new);
		$this->assertIdentical($dat2[0]['name'], $this->username2);
		$this->assertTrue($exists);

		Database::$dsn['cachelvl'] = Database::CACHE_AGGRESSIVE;
		$dbh->flushKey($dbh->getCacheID('SELECT * FROM test_table WHERE name = ? LIMIT 1'));

		$exists = $dbh->cacheExists($dbh->getCacheID('SELECT * FROM test_table WHERE name = ? LIMIT 1', array($this->username2_new)));
		$data   = $dbh->query('SELECT * FROM test_table WHERE name = ? LIMIT 1', array($this->username2_new), true, 10);
		$dat2   = $dbh->query('SELECT * FROM test_table WHERE name = ? LIMIT 1', array($this->username), true, 10);
		$dat3   = $dbh->query('SELECT * FROM test_table WHERE name = ? LIMIT 1', array($this->username), true, false);

		$this->assertFalse($exists);
		$this->assertIdentical($data['name'], $this->username2_new);
		$this->assertIdentical($dat2['name'], $this->username2_new);
		$this->assertIdentical($dat3['name'], $this->username);
	
	}

	function testInvalidColumnName() {
	
		$this->expectException('DatabaseException');
		$dbh = Database::getInstance();
		$dbh->query('SELECT invalidColumn FROM test_table');		
	
	}
	
	function testInvalidQuery() {
		
		$this->expectException('DatabaseException');
		$dbh = Database::getInstance();
		$dbh->query('SELECT * FORM test_table');
		
	}

	function testHighCountValue() {

		$dbh = Database::getInstance();

		for($i = 0; $i < 23; $i++) {

			$sql = 'INSERT INTO test_table (name, password, email) VALUES (?, ?, ?)';
			$dbh->query($sql, array( makeRandomString(), md5(makeRandomString()), makeRandomString(7).'@example.com'));

		}

		$num = $dbh->count('test_table');
		$this->assertIdentical($num, 25);

	}

	function testEmptyTableAndCount() {
		
		$dbh = Database::getInstance();
		
		$dbh->query('DELETE FROM test_table');
		$num = $dbh->count('test_table');
		
		$this->assertIdentical($num, 0);
	
	}

}

function makeRandomString($length = 12) {

	$string = '';

	for($i = 0; $i < $length; $i++) {

		$char    = chr(mt_rand(97, 122));
		$string .= (mt_rand(0,1)) ? strtoupper($char) : $char;

	}

	return $string;

}