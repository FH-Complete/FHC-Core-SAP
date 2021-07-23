<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to import employees data from SAP Business by Design
 */
class ManageEmployees extends JQW_Controller
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

	/**
	 * 
	 */
	public function create()
	{
		//$this->logInfo('Start data synchronization with SAP ByD: create employees');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs(SyncEmployeesLib::SAP_EMPLOYEES_CREATE);
		$syncResult = $this->syncemployeeslib->create(mergeUidArray(getData($lastJobs)));

		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), SyncEmployeesLib::SAP_EMPLOYEES_CREATE);
		}
		else
		{
			// Get all the jobs in the queue
			$syncResult = $this->syncemployeeslib->create(mergeUidArray(getData($lastJobs)));

			// Log the result
			if (isError($syncResult))
			{
				$this->logError(getCode($syncResult).': '.getError($syncResult));
			}
			else
			{
				$this->logInfo(getData($syncResult));
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);
			
			if (hasData($lastJobs)) $this->updateJobsQueue(SyncEmployeesLib::SAP_EMPLOYEES_CREATE, getData($lastJobs));
		}

		$this->logInfo('End data synchronization with SAP ByD: create employees');
	}

}

