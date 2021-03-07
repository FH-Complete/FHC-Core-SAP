<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to synchronize banks data from SAP Business by Design
 */
class ManageBanks extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
		$this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncBanksLib
		$this->load->library('extensions/FHC-Core-SAP/SyncBanksLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all the banks data
	 */
	public function getAllBanks()
	{
		var_dump($this->syncbankslib->getAllBanks());
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all active banks
	 */
	public function getActiveBanks()
	{
		var_dump($this->syncbankslib->getActiveBanks());
	}

	/**
	 * Save SAP banks data into the sync table
	 */
	public function syncActiveBanks()
	{
		$this->logInfo('Start active banks synchronization with SAP ByD');

		// Synchronize active banks
		$syncResult = $this->syncbankslib->syncActiveBanks();

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End active banks synchronization with SAP ByD');
	}
}

