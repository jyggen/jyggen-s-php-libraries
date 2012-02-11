jyggen's PHP Libraries
======================


API
---
*TODO*

A simple example. Create api.php in the web root:

	<?php
	require_once 'API.class.php';
	API::call($_SERVER['PATH_INFO']);

Rewrite any requests towards /api to api.php. By default the API class' looking for resources in ../api/, so your folder structure should look something like this:

	api/
		res_test.php
	public/
		api.php

Every resource should be located in api/ with the prefix res_. The file in the folder structure above (res_test.php) could look like this:

	<?php
	class ResourceTest extends API
	{

		// Every method should be prefixed mtd.
		public static function mtdView()
		{

			// Verify that an ID were supplied.
			if (self::argumentExists('id') === false) {

				// Return an error message and HTTP Code 400.
				self::response('Missing Argument \'id\'', 400);

			}

			// Random data to return.
			$data = array(
					 'name'   => 'jyggen',
					 'avatar' => false
					 'admin'  => true
					);

			self::response($data);

		}

	}

The valid request to this resource would be http://example.com/api/test/view/id:3.

Cache
-----

The plan is to move all cache-specific code from the database class into this and add support for more engines since webhosts usually don't have memcached.

Database
--------
*TODO*

A simple database query might look like this:

	Database::$dsn['hostname'] = 'localhost';
	Database::$dsn['database'] = 'my_application';
	Database::$dsn['username'] = 'root';
	Database::$dsn['password'] = 'mySeCr3tP@azzw0rd';

	$dbh  = Database::getInstance();
	$sql  = 'SELECT * FROM `users` WHERE `username` = ? LIMIT 1';
	$data = $dbh->query($sql, array('jyggen'), true);

	print 'Welcome' . $data['username'];