<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update services in SAP Business by Design
 */
class ManageServices extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
		$this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncServicesLib
		$this->load->library('extensions/FHC-Core-SAP/SyncServicesLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * This method is called to synchronize new servicess with SAP Business by Design
	 * Wrapper method for _manageServices
	 */
	public function create()
	{
		$this->_manageServices(SyncServicesLib::SAP_SERVICES_CREATE, 'create');
	}

	/**
	 * This method is called to synchronize updated services data with SAP Business by Design
	 * Wrapper method for _manageServices
	 */
	public function update()
	{
		$this->_manageServices(SyncServicesLib::SAP_SERVICES_UPDATE, 'update');
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a service with the given description
	 * and then returns the raw SOAP result
	 */
	public function getServiceByDescription($description)
	{
		var_dump($this->syncserviceslib->getServiceByDescription(urldecode($description)));
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a service with the given id
	 * and then returns the raw SOAP result
	 */
	public function getServiceById($id)
	{
		var_dump($this->syncserviceslib->getServiceById(urldecode($id)));
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Performs data synchronization with SAP, depending on the first parameter can create or update services
	 */
	private function _manageServices($jobType, $operation)
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
			if ($jobType == SyncServicesLib::SAP_SERVICES_CREATE)
			{
				$syncResult = $this->syncserviceslib->create(mergeUsersPersonIdArray(getData($lastJobs)));
			}
			else
			{
				$syncResult = $this->syncserviceslib->update(mergeUsersPersonIdArray(getData($lastJobs)));
			}

			// Log result
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

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('End data synchronization with SAP ByD: '.$operation);
	}
}

