<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update projects in SAP Business by Design
 */
class ManageProjects extends JQW_Controller
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
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all projects
	 */
	public function getProjects()
	{
		var_dump($this->syncprojectslib->getProjects());
	}
	
	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get a project with the given ID
	 */
	public function getProjectById($id)
	{
		var_dump($this->syncprojectslib->getProjectById($id));
	}
	
	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all tasks for a project with the given ID
	 */
	public function getProjectTasks($id)
	{
		var_dump($this->syncprojectslib->getProjectTasks($id));
	}

	/**
	 * Wrapper method for _manageProjects
	 */
	public function create()
	{
		$this->_manageProjects(SyncProjectsLib::SAP_PROJECTS_CREATE, 'create');
	}

	/**
	 * Wrapper method for _manageProjects
	 */
	public function update()
	{
		$this->_manageProjects(SyncProjectsLib::SAP_PROJECTS_UPDATE, 'update');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Performs data synchronization with SAP, depending on the first parameter can create or update projects
	 */
	private function _manageProjects($jobType, $operation)
	{
		$this->logInfo('Start data synchronization with SAP ByD: '.$operation);

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError('An error occurred while '.$operation.'ing projects in SAP', getError($lastJobs));
		}
		elseif (hasData($lastJobs))
		{
			// Create/update projects on SAP side
			if ($jobType == SyncProjectsLib::SAP_PROJECTS_CREATE)
			{
				$syncResult = $this->syncprojectslib->create();
			}
			else
			{
				$syncResult = $this->syncprojectslib->update();
			}

			if (isError($syncResult))
			{
				$this->logError('An error occurred while '.$operation.'ing users in SAP', getError($syncResult));
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

