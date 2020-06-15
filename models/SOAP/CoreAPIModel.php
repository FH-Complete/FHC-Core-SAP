<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/SOAPClientModel.php';

/**
 * This implements the basic parameters to call all the API in CoreAPI
 */
abstract class CoreAPIModel extends SOAPClientModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_apiSetName = 'CoreAPI'; // API set name
	}
}

