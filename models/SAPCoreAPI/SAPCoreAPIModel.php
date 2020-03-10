<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/SAPClientModel.php';

/**
 * This implements the basic parameters to call all the API in SAPCoreAPI
 */
class SAPCoreAPIModel extends SAPClientModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_apiSetName = 'SAPCoreAPI'; // API set name
	}
}
