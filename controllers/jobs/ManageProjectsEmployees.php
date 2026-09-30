<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class ManageProjectsEmployees extends JOB_Controller
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


	public function sync($id = null)
	{
		$this->logInfo('Start projects employees synchronization with SAP ByD');

		$importResult = $this->syncprojectslib->syncProjectsEmployees($id);

		if (isError($importResult))
		{
			$this->logError(getCode($importResult).': '.getError($importResult));
		}
		else
		{
			$this->logInfo(getData($importResult));
		}

		$this->logInfo('End projects employees synchronization with SAP ByD');
	}
}

