<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Employee web service
 */
class Employee_model extends ODATAClientModel
{
	const URI_PREFIX = 'odata/analytics/ds/Hcmempb.svc/';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_apiSetName = 'technical';
	}

	// --------------------------------------------------------------------------------------------
	// Public methods GET API calls

	/**
	 * 
	 */
	public function getAllEmployees()
	{
		return $this->_call(
			self::URI_PREFIX.'Hcmempb',
			ODATAClientLib::HTTP_GET_METHOD,
			array(
				'$select' => 'C_EeId,C_BusinessUserId'
			)
		);
	}
}

