<?php

// Responsible Person for SalesOrders
$config['payments_responsible_party'] = array(
	'gmbh' => '23',	// admin project unit responsible
	'fh' => '23',	// lehre project unit responsible
);

//
$config['payments_personal_ressource'] = array(
	'gmbh' => '200000',	// admin project unit responsible
	'fh' => '100200',	// lehre project unit responsible
);

$config['payments_sales_unit_gmbh'] = '200000';
$config['payments_sales_unit_custom'] = '100003';

// Do not create Payments from a StudySemester with a start date
// after this date - because there are no projects available
$config['payments_studiensemester_start_max_date'] = '2021-01-01';
