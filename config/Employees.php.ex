<?php

// Enable/disable warnings in employees synchronization
$config['sap_employees_warnings_enabled'] = false;
$config['fhc_contract_types'] = array('103', '109');

// Block list of employees that have not to be synced
// NOTE: keep at least one element otherwise the query will fail
$config['sap_employees_blacklist'] = array('');