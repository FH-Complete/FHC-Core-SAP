<?php

// Users payment company ids (required)
$config['users_payment_company_ids'] = array(
	'fhtw' => '100000',	// company id for fhtw
	'gmbh' => '200000'	// company id for gmbh
);

// Payment data AccountDeterminationDebtorGroupCode
$config['users_account_determination_debtor_group_code'] = 'Z401';

// Block list of courses of users that have _not_ to be loaded
// NOTE: keep at least one element otherwise the query will fail
$config['users_block_list_courses'] = array(-1);

