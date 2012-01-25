jyggen's PHP Libraries
======================


API
---
*TODO*

Cache
-----
*TODO*

Database
--------
*TODO*

A simple database query might look like this:

	Database::$settings['hostname'] = 'localhost';
	Database::$settings['database'] = 'my_application';
	Database::$settings['username'] = 'root';
	Database::$settings['password'] = 'mySeCr3tP@azzw0rd';

	$dbh  = Database::getInstance();
	$sql  = 'SELECT * FROM `users` WHERE `username` = ? LIMIT 1';
	$data = $dbh->query($sql, array('jyggen'), true);

	print 'Welcome' . $data['username'];