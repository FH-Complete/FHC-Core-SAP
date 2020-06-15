<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManageServiceProductIn
 */
class ManageServiceProductIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'ManageServiceProductIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function:
	 */
	public function maintainBundle_V1($parameters)
	{
		return $this->_call('MaintainBundle_V1', $parameters);
	}
}

