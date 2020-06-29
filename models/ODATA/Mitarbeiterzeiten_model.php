<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Mitarbeiterzeiten web service
 */
class Mitarbeiterzeiten_model extends ODATAClientModel
{
    const URI_PREFIX = 'odata/analytics/ds/Hcmtlmu01.svc/';

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
	public function getMitarbeiterzeiten()
	{
		return $this->_call(
            self::URI_PREFIX.'Hcmtlmu01?$select=ID,C_EmployeeUuid,C_StartDate,C_StartTime,C_EndTime,C_WorkDescription,C_TmitTypcode&$format=json',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}
}