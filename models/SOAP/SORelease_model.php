<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name QuerySalesOrderIn
 */
class SORelease_model extends SOAPClientModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'SO_Release'; // service name
		$this->_apiSetName = 'CustomAPI'; // API set name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function: FinishFullfillment
	 */
	public function FinishFulfilmentProcessingOfAllItems($parameters)
	{
		return $this->_call('FinishFulfilmentProcessingOfAllItems', $parameters);
	}

	/**
	 * SOAP function: FinishFullfillment
	 */
	public function Release($parameters)
	{
		return $this->_call('SO_Release', $parameters);
	}
}
