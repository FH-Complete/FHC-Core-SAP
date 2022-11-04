<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QueryDocumentOutputRequestIn
 */
class QueryDocumentOutputRequestIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'QueryDocumentOutputRequestIn'; // service name
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

	/**
	 *
	 */
	public function readOutputPDF($parameters)
	{
		return $this->_call('ReadOutputPDF', $parameters);
	}
}

