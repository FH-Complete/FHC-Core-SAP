<?php

$config['odata_max_number_results'] = 99999; // max number of results provided by the odata api

$config['odata_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['odata_connections'] = array(
	'DEFAULT' => array(
		'apiSetName0' => array(
			'protocol' => 'https', // ssl by default... better!
			'host' => 'odata.technikum-wien.at', // server name
			'path' => '', // usually this is the path for ODATA API
			'username' => 'username0', // basic HTTP authentication username
			'password' => '123456' // basic HTTP authentication password
		),
		'apiSetName1' => array(
			'protocol' => 'https', // ssl by default... better!
			'host' => 'odata.technikum-wien.at', // server name
			'path' => '', // usually this is the path for ODATA API
			'username' => 'username1', // basic HTTP authentication username
			'password' => '123456' // basic HTTP authentication password
		)
	)
);

