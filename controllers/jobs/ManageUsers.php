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
		$this->_manageUsers(SyncUsersLib::SAP_USERS_CREATE, 'create');
	}

	/**
	 * This method is called to synchronize updated users data with SAP Business by Design
	 * Wrapper method for _manageUsers
	 */
	public function update()
	{
		$this->_manageUsers(SyncUsersLib::SAP_USERS_UPDATE, 'update');
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

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Performs data synchronization with SAP, depending on the first parameter can create or update users
	 */
	private function _manageUsers($jobType, $operation)
	{
		$this->logInfo('Start data synchronization with SAP ByD: '.$operation);

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			// Create/update users on SAP side
			if ($jobType == SyncUsersLib::SAP_USERS_CREATE)
			{
				$syncResult = $this->syncuserslib->create(mergeUsersPersonIdArray(getData($lastJobs)));
			}
			else
			{
				$syncResult = $this->syncuserslib->update(mergeUsersPersonIdArray(getData($lastJobs)));
			}

			if (isError($syncResult))
			{
				$this->logError(getCode($syncResult).': '.getError($syncResult));
			}
			else
			{
				// If non blocking errors are present...
				if (hasData($syncResult))
				{
					if (!isEmptyArray(getData($syncResult)))
					{
						// ...then log them all as warnings
						foreach (getData($syncResult) as $nonBlockingError)
						{
							$this->logWarning($nonBlockingError);
						}
					}
					// Else if it a single message log it as info
					elseif (!isEmptyString(getData($syncResult)))
					{
						$this->logInfo(getData($syncResult));
					}
				}

				// Update jobs properties values
				updateJobs(
					getData($lastJobs), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date("Y-m-d H:i:s")) // Job properties new values
				);
				
				if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: '.$operation);
	}
}

