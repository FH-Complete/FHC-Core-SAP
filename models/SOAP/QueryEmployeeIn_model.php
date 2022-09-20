<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QueryEmployeeIn
 */
class QueryEmployeeIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QueryEmployeeIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: FindByIdentification
	 */
	public function findByIdentification($parameters)
	{
		return $this->_call('FindByIdentification', $parameters);
	}

	public function findBasicDataByIdentification($parameters)
	{
		return $this->_call('FindBasicDataByIdentification', $parameters);
	}
}

