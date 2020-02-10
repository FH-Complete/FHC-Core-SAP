<?php

$config['fhc_sap_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_sap_connections'] = array(
	'DEFAULT' => array(
		'wsdl' => '', // URI to the WSDL
		'options' => array(
			'login' => 'me', // basic HTTP authentication username
			'password' => 'guess it' // basic HTTP authentication password
		)
	)
);
