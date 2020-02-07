<?php

$config['fhc_sap_default_ldap_password'] = 'guessit';

$config['fhc_sap_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

$config['fhc_sap_apikey_name'] = 'IDM-API-KEY'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_sap_connections'] = array(
	'DEFAULT' => array(
		'protocol' => 'https', // ssl by default... better!
	    'host' => 'foster.technikum-wien.at', // FHC-SAP server name
	    'path' => '', // usually this is the path for REST API
		'username' => 'system', // basic HTTP authentication username
		'password' => '123456', // basic HTTP authentication password
		'apikey' => '123456'
	)
);
