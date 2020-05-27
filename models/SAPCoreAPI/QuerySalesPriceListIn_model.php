<?php

require_once 'SAPCoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QuerySalesPriceListIn
 */
class QuerySalesPriceListIn_model extends SAPCoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QuerySalesPriceListIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: FindByTypeCodeAndPropertyIDAndPropertyValue
	 */
	public function findByTypeCodeAndPropertyIDAndPropertyValue($parameters)
	{
		return $this->_call('FindByTypeCodeAndPropertyIDAndPropertyValue', $parameters);
	}
}

