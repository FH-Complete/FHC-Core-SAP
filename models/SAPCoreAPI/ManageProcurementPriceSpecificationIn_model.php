<?php

require_once 'SAPCoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManageProcurementPriceSpecificationIn
 */
class ManageProcurementPriceSpecificationIn_model extends SAPCoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'ManageProcurementPriceSpecificationIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: MaintainBundle
	 */
	public function maintainBundle($parameters)
	{
		return $this->_call('MaintainBundle', $parameters);
	}

	/**
	 * SOAP function: Read
	 */
	public function read($parameters)
	{
		return $this->_call('Read', $parameters);
	}
}

