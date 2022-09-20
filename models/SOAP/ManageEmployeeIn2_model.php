<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManageEmployeeIn2
 */
class ManageEmployeeIn2_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'ManageEmployeeIn2'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function:
	 */
	public function maintainBundle($parameters)
	{
		return $this->_call('MaintainBundle', $parameters);
	}
}

