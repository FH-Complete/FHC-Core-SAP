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

		// Loads SyncUsersLib
                $this->load->library('extensions/FHC-Core-SAP/SyncUsersLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * This method is called to synchronize new users with SAP Business by Design
	 */
	public function createUsers()
	{
		$this->logInfo('Start new users data synchronization with SAP ByD');

		$lastJobs = $this->getLastJobs(SyncUsersLib::SAP_USERS_CREATE);
		if (isError($lasJobs))
		{
			$this->logError('An error occurred while creating new users in SAP', getError($lastJobs));
		}
		else
		{
			$syncResult = $this->syncuserslib->createUsers($this->_mergeUsersArray(getData($lastJobs)));
			if (isError($syncResult))
			{
				$this->logError('An error occurred while creating new users in SAP', getError($syncResult));
			}
		}

		$this->logInfo('End new users data synchronization with SAP ByD');
	}

	/**
	 * This method is called to synchronize updated users data with SAP Business by Design
	 */
	public function updateUsers()
	{
		$this->logInfo('Start updated users data synchronization with SAP ByD');

		$lastJobs = $this->getLastJobs(SyncUsersLib::SAP_USERS_UPDATE);
		if (isError($lasJobs))
		{
			$this->logError('An error occurred while updating users data in SAP', getError($lastJobs));
		}
		else
		{
			$syncResult = $this->syncuserslib->updateUsers($this->_mergeUsersArray(getData($lastJobs)));
			if (isError($syncResult))
			{
				$this->logError('An error occurred while updating users data in SAP', getError($syncResult));
			}
		}

		$this->logInfo('End updated users data synchronization with SAP ByD');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 *
	 */
	private function _mergeUsersArray($jobs)
	{
		$mergedUsersArray = array();

		foreach ($jobs as $job)
		{
			$mergedUsersArray = array_merge($mergedUsersArray, json_decode($job->input));
		}

		return $mergedUsersArray;
	}
}

