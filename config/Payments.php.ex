<?php

// Enable/disable warnings in payments synchronization
$config['payments_warnings_enabled'] = false;

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

// Incoming/outgoing grant
$config['payments_incoming_outgoing_grant'] = 'ZuschussIO';

// International office sales unit party id (aka organization unit id)
$config['payments_international_office_sales_unit_party_id'] = '100097';

// Credit memo types read from database
$config['payments_booking_type_organizations'] = array('ZuschussIO', 'Leistungsstipendium');

// Couples of Buchungstyp_kurzbz => cost center used in the FH payments when is not wanted
// the linked cost centers from database
$config['payments_fh_cost_centers_buchung'] = array(
	//[Buchungstyp_kurzbz => cost center, zahlungsbedingung => 1001 (Zahlbar sofort ohne Abzug), mahnsperre => 1]
	'EBCL_001' => array('kostenstelle' => '100100', 'zahlungsbedingung' => '1001', 'mahnsperre' => '1'),
);

//sonstige gutschriften
$config['payments_other_credits'] = array(
	'ZuschussIO' => array('GLAccountOtherLiabilities' => 'Z-2311'),
	'Leistungsstipendium' => array('GLAccountOtherLiabilities' => 'Z2302')
);

$config['payments_other_credits_company'] = '100000';
