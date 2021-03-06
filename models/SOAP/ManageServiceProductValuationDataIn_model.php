<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManageServiceProductValuationData
 */
class ManageServiceProductValuationDataIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'ManageServiceProductValuationDataIn'; // service name
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
}

