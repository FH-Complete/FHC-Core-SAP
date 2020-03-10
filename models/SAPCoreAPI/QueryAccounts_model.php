<?php

require_once 'SAPCoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QueryAccounts
 */
class QueryAccounts_model extends SAPCoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QueryAccounts'; // service name
	}

	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 * SOAP function: FindByElements
	 *
	 * Required parameters:
	 *	- Parameter 1
	 *	- Parameter 2
	 *	- Parameter 3
	 *
	 * Optional parameters:
	 *	- Parameter 1
	 *	- Parameter 2
	 *	- Parameter 3
	 */
	public function findByElementsByFamilyName($familyName)
	{
		return $this->_call(
			'FindByElements',
			array(
				'SelectionByFamilyName' => $familyName
			)
		);
	}
}
