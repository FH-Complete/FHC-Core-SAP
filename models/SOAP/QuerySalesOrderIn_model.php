<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QuerySalesOrderIn
 */
class QuerySalesOrderIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QuerySalesOrderIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: findByElements
	 */
	public function findByElements($parameters)
	{
		return $this->_call('FindByElements', $parameters);
	}
}
