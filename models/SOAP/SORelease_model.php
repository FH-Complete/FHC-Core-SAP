<?php

require_once 'CustomAPIModel.php';

/**
 * This implements all the calls for:
 * API set name CustomAPI
 * Service name SO_Release
 */
class SORelease_model extends CustomAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'SO_Release'; // service name
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

