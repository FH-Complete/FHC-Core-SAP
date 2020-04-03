<?php

require_once 'SAPCoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManageCustomerIn
 */
class ManageCustomerIn_model extends SAPCoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'ManageCustomerIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function:
	 */
	public function maintainBundle_V1($parameters)
	{
		return $this->_call('MaintainBundle_V1', $parameters);
	}
}

