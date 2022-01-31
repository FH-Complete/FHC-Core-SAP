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
		$this->logInfo('Start data synchronization with SAP ByD: create employees');

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

	public function update()
	{
		$this->logInfo('Start data synchronization with SAP ByD: update employees');

		// Gets the oldest job
		$oldestJob = $this->getOldestJob(SyncEmployeesLib::SAP_EMPLOYEES_UPDATE);

		if (isError($oldestJob))
		{
			$this->logError(getCode($oldestJob).': '.getError($oldestJob), SyncEmployeesLib::SAP_EMPLOYEES_UPDATE);
		}
		else
		{
			// Starts the update using only the oldest job
			$syncResult = $this->syncemployeeslib->update(mergeUidArray(getData($oldestJob)));

			// Log the result
			if (isError($syncResult))
			{
				// Save all the errors
				$errors = getError($syncResult);

				// If it is NOT an array...
				if (isEmptyArray($errors))
				{
					// ...then convert it to an array
					$errors = array($errors);
				}
				// otherwise it is already an array

				// For each error found
				foreach ($errors as $error)
				{
					$this->logError(getCode($syncResult).': '.$error);
				}
			}
			else
			{
				$this->logInfo(getData($syncResult));
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($oldestJob), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($oldestJob)) $this->updateJobsQueue(SyncEmployeesLib::SAP_EMPLOYEES_UPDATE, getData($oldestJob));
		}

		$this->logInfo('End data synchronization with SAP ByD: update employees');
	}

	public function updateEmployeesWorkAgreement()
	{
		$this->logInfo('Start data synchronization with SAP ByD: update employees workagreement');

		// Gets the oldest job
		$oldestJob = $this->getOldestJob(SyncEmployeesLib::SAP_EMPLOYEES_WORK_AGREEMENT_UPDATE);

		if (isError($oldestJob))
		{
			$this->logError(getCode($oldestJob).': '.getError($oldestJob), SyncEmployeesLib::SAP_EMPLOYEES_WORK_AGREEMENT_UPDATE);
		}
		else
		{
			// Starts the update using only the oldest job
			$syncResult = $this->syncemployeeslib->updateEmployeeWorkAgreement(mergeUidArray(getData($oldestJob)));

			// Log the result
			if (isError($syncResult))
			{
				// Save all the errors
				$errors = getError($syncResult);

				// If it is NOT an array...
				if (isEmptyArray($errors))
				{
					// ...then convert it to an array
					$errors = array($errors);
				}
				// otherwise it is already an array

				// For each error found
				foreach ($errors as $error)
				{
					$this->logError(getCode($syncResult).': '.$error);
				}
			}
			else
			{
				$this->logInfo(getData($syncResult));
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($oldestJob), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($oldestJob)) $this->updateJobsQueue(SyncEmployeesLib::SAP_EMPLOYEES_WORK_AGREEMENT_UPDATE, getData($oldestJob));
		}

		$this->logInfo('End data synchronization with SAP ByD: update employees workagreement');
	}

}

