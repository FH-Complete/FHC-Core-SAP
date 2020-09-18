<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to import employees data from SAP Business by Design
 */
class ManageEmployees extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
		$this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncEmployeesLib
		$this->load->library('extensions/FHC-Core-SAP/SyncEmployeesLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * 
	 */
	public function importEmployeeIDs()
	{
		$this->logInfo('Start employee ids import from SAP ByD');

		// Import SAP employee ids
		$importResult = $this->syncemployeeslib->importEmployeeIDs();

		// Log the result
		if (isError($importResult))
		{
			$this->logError(getCode($importResult).': '.getError($importResult));
		}
		else
		{
			$this->logInfo(getData($importResult));
		}

		$this->logInfo('End employee ids import from SAP ByD');
	}
}

