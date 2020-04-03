<?php

require_once 'SAPCoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QueryCustomerIn
 */
class QueryCustomerIn_model extends SAPCoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QueryCustomerIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: FindByCommunicationData
	 */
	public function findByCommunicationData($parameters)
	{
		return $this->_call('FindByCommunicationData', $parameters);
	}
}

