<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to get all projects and their tasks from SAP Business by Design and store their ids in FHC database
 */
class ManageProjectsTimesheets extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
                $this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncProjectsLib
                $this->load->library('extensions/FHC-Core-SAP/SyncProjectsLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get all projects and their tasks from SAP Business by Design and store their ids in FHC database
	 */
	public function import()
	{
		$this->logInfo('Start projects timesheets synchronization with SAP ByD');

		// Import SAP projects ids
		$importResult = $this->syncprojectslib->import();

		// Log result
		if (isError($importResult))
		{
			$this->logError(getCode($importResult).': '.getError($importResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($importResult));
		}

		$this->logInfo('End projects timesheets synchronization with SAP ByD');
	}

	/**
	 * 
	 */
	public function importEmployees()
	{
		$this->logInfo('Start projects timesheets employees synchronization with SAP ByD');

		// Import SAP projects ids
		$importResult = $this->syncprojectslib->importEmployees();

		// If an error occurred then log it
		if (isError($importResult))
		{
			$this->logError(getCode($importResult).': '.getError($importResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($importResult));
		}

		$this->logInfo('End projects timesheets employees synchronization with SAP ByD');
	}
}

