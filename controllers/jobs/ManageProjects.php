<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to create or update projects in SAP Business by Design
 */
class ManageProjects extends JOB_Controller
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
	 * Updates and creates projects on SAP side
	 */
	public function sync()
	{
		$this->logInfo('Start projects synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync();

		// If an error occurred then log it
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
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
		}

		$this->logInfo('End projects synchronization with SAP ByD');
	}
}

