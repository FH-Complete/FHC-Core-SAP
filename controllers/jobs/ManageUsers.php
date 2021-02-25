<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update users in SAP Business by Design
 */
class ManageUsers extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
                $this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncUsersLib
                $this->load->library('extensions/FHC-Core-SAP/SyncUsersLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * This method is called to synchronize new users with SAP Business by Design
	 * Wrapper method for _manageUsers
	 */
	public function create()
	{
		$this->logInfo('Start data synchronization with SAP ByD: create');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs(SyncUsersLib::SAP_USERS_CREATE);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), SyncUsersLib::SAP_USERS_CREATE);
		}
		else
		{
			// Get all the jobs in the queue
			$syncResult = $this->syncuserslib->create(mergeUsersPersonIdArray(getData($lastJobs)));

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
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);
			
			if (hasData($lastJobs)) $this->updateJobsQueue(SyncUsersLib::SAP_USERS_CREATE, getData($lastJobs));
		}

		$this->logInfo('End data synchronization with SAP ByD: create');
	}

	/**
	 * This method is called to synchronize updated users data with SAP Business by Design
	 * Wrapper method for _manageUsers
	 */
	public function update()
	{
		$this->logInfo('Start data synchronization with SAP ByD: update');

		// Gets the oldest job
		$oldestJob = $this->getOldestJob(SyncUsersLib::SAP_USERS_UPDATE);
		if (isError($oldestJob))
		{
			$this->logError(getCode($oldestJob).': '.getError($oldestJob), SyncUsersLib::SAP_USERS_UPDATE);
		}
		else
		{
			// Starts the update using only the oldest job
			$syncResult = $this->syncuserslib->update(mergeUsersPersonIdArray(getData($oldestJob)));

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
			
			if (hasData($oldestJob)) $this->updateJobsQueue(SyncUsersLib::SAP_USERS_UPDATE, getData($oldestJob));
		}

		$this->logInfo('End data synchronization with SAP ByD: update');
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a user with the given email
	 * and then returns the raw SOAP result
	 */
	public function getUserByEmail($email)
	{
		var_dump($this->syncuserslib->getUserByEmail(urldecode($email)));
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a user with the given id
	 * and then returns the raw SOAP result
	 */
	public function getUserById($id)
	{
		var_dump($this->syncuserslib->getUserById(urldecode($id)));
	}
}

