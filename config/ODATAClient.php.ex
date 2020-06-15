<?php

$config['odata_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['odata_connections'] = array(
	'DEFAULT' => array(
		'protocol' => 'https', // ssl by default... better!
		'host' => 'odata.technikum-wien.at', // server name
		'path' => '', // usually this is the path for ODATA API
		'username' => 'username', // basic HTTP authentication username
		'password' => '123456' // basic HTTP authentication password
	)
);

