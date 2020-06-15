<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QueryServiceProductValuationDataIn
 */
class QueryServiceProductValuationDataIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QueryServiceProductValuationDataIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: FindByElements
	 */
	public function findByElements($parameters)
	{
		return $this->_call('FindByElements', $parameters);
	}
}
