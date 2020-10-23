<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Projects web service
 */
class RPFINGLAU08_Q0002_model extends ODATAClientModel
{
	const URI_PREFIX = 'odata/ana_businessanalytics_analytics.svc/';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_apiSetName = 'business';
	}

	// --------------------------------------------------------------------------------------------
	// Public methods GET API calls

	/**
	 *
	 */
	public function getByCustomer($studentId, $companyIds)
	{
		return $this->_call(
			self::URI_PREFIX.'RPFINGLAU08_Q0002QueryResults',
			ODATAClientLib::HTTP_GET_METHOD,
			array(
				'$select' => 'COFF_BUSPARTNER,TOFF_BUSPARTNER,TIM_SUB_TYPE_C,TACCDOCTYPE,TBUS_PART_UUID,CCINHUUID,FCOPEN_CURRLIT,TGLACCT,TDEBITCREDIT,TIM_OP_IT_STAT',
				'$filter' => '('.filter($companyIds, 'PARA_COMPANY', 'eq', 'or').') and '.filter(array($studentId), 'COFF_BUSPARTNER', 'eq','or'),
				'$format' => 'json'
			)
		);
	}
}
