<?php

// Enable/disable warnings in employees synchronization
$config['sap_employees_warnings_enabled'] = false;

// contract types that have to be synced
$config['fhc_contract_types'] = array('103', '109', '111');

// Block list of employees that have _not_ to be synced
// NOTE: keep at least one element otherwise the query will fail
$config['sap_employees_blacklist'] = array('');

// the employees have to be synced X days before they start
$config['sap_sync_employees_x_days_before_start'] = '14';

// Nach wie viel X Tagen soll das Enddatum gesetzt werden
$config['sap_sync_employees_x_days_after_end'] = '30';