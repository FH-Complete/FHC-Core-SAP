<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

class SyncProjects extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'basis/projekt:r',
				'getSyncedFHProject' => 'basis/projekt:r',
				'getSyncedSAPProject' => 'basis/projekt:r',
				'loadFUEPhases' => 'basis/projekt:r',
				'loadSAPPhases' => 'basis/projekt:r',
				'syncProjects' => 'basis/projekt:rw',
				'desyncProjects' => 'basis/projekt:rw',
				'syncProjectphases' => 'basis/projekt:rw',
				'desyncProjectphases' => 'basis/projekt:rw',
				'createFUEProject' => 'basis/projekt:rw',
				'createFUEPhase' => 'basis/projekt:rw',
				'getSAPProjectOE' => 'basis/projekt:r',
				'checkPhaseHasTimerecordings' => 'basis/projekt:rw',
				'checkProjectHasTimerecordings' => 'basis/projekt:rw'
			)
		);

		// Load models
		$this->load->model('project/Projekt_model', 'ProjektModel');
		$this->load->model('project/Projektphase_model', 'ProjektphaseModel');
		$this->load->model('ressource/Zeitaufzeichnung_model', 'ZeitaufzeichnungModel');
		$this->load->model('extensions/FHC-Core-SAP/SAPProjectsTimesheets_model', 'SAPProjectsTimesheetsModel');
		$this->load->model('extensions/FHC-Core-SAP/ProjectsTimesheetsProject_model', 'ProjectsTimesheetsProjectModel');

		// Load libraries
		$this->load->library('WidgetLib');

		// Load language phrases
		$this->loadPhrases(
			array(
				'ui',
				'lehre'
			)
		);

		$this->_setAuthUID(); // sets property uid

		$this->setControllerId(); // sets the controller id
	}

	public function index()
	{
		$this->load->view('extensions/FHC-Core-SAP/projects/syncProjects.php');
	}

	// Get the synchronized FH project of the given SAP project.
	public function getSyncedFHProject()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');

		$this->ProjektModel->addJoin('sync.tbl_projects_timesheets_project', 'projekt_id');

		$result = $this->ProjektModel->loadWhere(array(
			'projects_timesheet_id' => $projects_timesheet_id
		));

		if($result = getData($result)[0])
		{
			return $this->outputJsonSuccess(array(
				'projekt_id' => $result->projekt_id,
				'projekt_kurzbz' => $result->projekt_kurzbz,
				'titel' => $result->titel,
				'beschreibung' => $result->beschreibung
			));
		}
	}

	// Get the synchronized SAP project of the given FH project.
	public function getSyncedSAPProject()
	{
		$projekt_id = $this->input->post('projekt_id');

		$this->SAPProjectsTimesheetsModel->addJoin('sync.tbl_projects_timesheets_project', 'projects_timesheet_id');

		$result = $this->SAPProjectsTimesheetsModel->loadWhere(array(
			'projekt_id' => $projekt_id,
			'project_task_id' => NULL
		));

		if($result = getData($result)[0])
		{
			return $this->outputJsonSuccess(array(
				'projects_timesheet_id' => $result->projects_timesheet_id,
				'project_id' => $result->project_id,
				'name' => $result->name
			));
		}
	}

	public function getSAPProjectOE()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');

		$this->SAPProjectsTimesheetsModel->addJoin('sync.tbl_sap_organisationsstruktur', 'responsible_unit = tbl_sap_organisationsstruktur.oe_kurzbz_sap');

		$result = $this->SAPProjectsTimesheetsModel->loadWhere(array(
			'projects_timesheet_id' => $projects_timesheet_id
		));

		if($result = getData($result)[0])
		{
			return $this->outputJsonSuccess(array(
				'oe_kurzbz' => $result->oe_kurzbz
			));
		}

	}

	// Load all SAP phases of the given SAP project.
	public function loadSAPPhases()
	{
		$project_id = $this->input->post('project_id');

		$result = $this->ProjectsTimesheetsProjectModel->getSAPPhases_withSyncStatus($project_id);

		if(hasData($result))
		{
			if($retval = getData($result))
			{
				return $this->outputJsonSuccess($retval);
			}
			else
			{
				return $this->outputJsonError($retval);
			}
		}
		else
		{
			// return null if project has no phases
			return $this->outputJsonSuccess(null);
		}
	}

	// Load all FH phases of the given FH project.
	public function loadFUEPhases()
	{
		$projekt_kurzbz = $this->input->post('projekt_kurzbz');

		$result = $this->ProjectsTimesheetsProjectModel->getFUEPhases_withSyncStatus($projekt_kurzbz);

		if(hasData($result))
		{
			if($retval = getData($result))
			{
				return $this->outputJsonSuccess($retval);
			}
			else
			{
				return $this->outputJsonError($retval);
			}
		}
		else
		{
			// return null if project has no phases
			return $this->outputJsonSuccess(null);
		}
	}

	// Synchronize SAP and FH projects.
	public function syncProjects()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');
		$projekt_id = $this->input->post('projekt_id');

		// Check, if given projects are already synced
		$isSynced_SAPProject = $this->ProjectsTimesheetsProjectModel->isSynced_SAPProject($projects_timesheet_id);
		$isSynced_FUEProject = $this->ProjectsTimesheetsProjectModel->isSynced_FUEProject($projekt_id);

		if ($isSynced_SAPProject || $isSynced_FUEProject)
		{
			return $this->outputJsonSuccess(null); // return null if already synced
		}

		// Synchronize SAP and FUE projects
		$result = $this->ProjectsTimesheetsProjectModel->insert(array(
				'projects_timesheet_id' => $projects_timesheet_id,
				'projekt_id' => $projekt_id,
				'projektphase_id' => NULL
			)
		);

		if(isSuccess($result))
		{
			return $this->outputJsonSuccess(true);
		}
		else
		{
			return $this->outputJsonError('Projekt konnte nicht verknüpft werden.');
		}
	}
	
	// Desynchronize SAP and FH projects.
	public function desyncProjects()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');
		$project_id = $this->input->post('project_id');
	
		// Check, if project is already synced
		$isSynced_SAPProject = $this->ProjectsTimesheetsProjectModel->isSynced_SAPProject($projects_timesheet_id);
		
		if (!$isSynced_SAPProject)
		{
			$this->terminateWithJsonError('Das ausgewählte SAP Projekt ist nicht verknüpft.');
		}
		
		// Get phases of the given project
		$result = $this->SAPProjectsTimesheetsModel->getAllPhasesFromProject($project_id);
		
		// If phases are present
		if (hasData($result))
		{
			$phases_projects_timesheet_id_arr = array_column(getData($result), 'projects_timesheet_id');
			
			// Desync phases
			$result = $this->ProjectsTimesheetsProjectModel
				->desyncByProjectsTimesheetIds($phases_projects_timesheet_id_arr);
			
			if (isError($result))
			{
				$this->terminateWithJsonError(
					'Projektentknüpfung abgebrochen, da synchronisierte Phasen zu diesem Projekt nicht entknüpft werden konnten.'
				);
			}
		}
		
		// Then desync projects
		$result = $this->ProjectsTimesheetsProjectModel->loadWhere(array(
			'projects_timesheet_id' => $projects_timesheet_id
		));
		
		if (!hasData($result))
		{
			$this->terminateWithJsonError('Verknüpfung konnte nicht gefunden werden.');
		}
		
		$result = $this->ProjectsTimesheetsProjectModel->delete($result->retval[0]->projects_timesheets_project_id);

		if (isSuccess($result))
		{
			$this->outputJsonSuccess(true);
		}
		else
		{
			$this->terminateWithJsonError('Verknüpfung konnte nicht gelöscht werden.');
		}
	}

	// Synchronize SAP and FH projectphases.
	public function syncProjectphases()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');   // = SAP Phase
		$project_id = $this->input->post('project_id');
		$projekt_id = $this->input->post('projekt_id');
		$projektphase_id = $this->input->post('projektphase_id');

		// Check, if projects of the given phases are already synced
		$result = $this->SAPProjectsTimesheetsModel->getProject($project_id);
		if (!$retval = getData($result)[0])
		{
			return $this->outputJsonError('Fehler beim Ermitteln des entsprechenden SAP Projekts.');
		}

		$sap_project_projects_timesheet_id = $retval->projects_timesheet_id;
		$isSynced_SAPProject = $this->ProjectsTimesheetsProjectModel->isSynced_SAPProject($sap_project_projects_timesheet_id);
		$isSynced_FUEProject = $this->ProjectsTimesheetsProjectModel->isSynced_FUEProject($projekt_id);

		if (!$isSynced_SAPProject || !$isSynced_FUEProject)
		{
			return $this->outputJsonError('Bitte synchronisieren Sie erst die Projekte');
		}

		// Check, if phases are already synced
		$isSynced_SAPProjectphase = $this->ProjectsTimesheetsProjectModel->isSynced_SAPProjectphase($projects_timesheet_id);
		$isSynced_FUEProjectphase = $this->ProjectsTimesheetsProjectModel->isSynced_FUEProjectphase($projektphase_id);

		if ($isSynced_SAPProjectphase || $isSynced_FUEProjectphase)
		{
			return $this->outputJsonSuccess(null); // return null if already synced
		}

		// Synchronize SAP and FUE projectphases
		$result = $this->ProjectsTimesheetsProjectModel->syncProjectphases(
			$projects_timesheet_id,
			$projekt_id,
			$projektphase_id
		);

		if(isSuccess($result))
		{
			return $this->outputJsonSuccess(true);
		}
		else
		{
			return $this->outputJsonError('Phase konnte nicht verknüpft werden.');
		}
	}
	
	// Desnynchronize SAP and FH projectphases.
	public function desyncProjectphases()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');
		
		// Check, if projects of the given phases are already synced
		$result = $this->ProjectsTimesheetsProjectModel->isSynced_SAPProjectphase($projects_timesheet_id);
		
		if (!$result)
			$this->terminateWithJsonError('SAP Projektphase ist nicht verknüpft');
		
		$result = $this->ProjectsTimesheetsProjectModel->loadWhere(array('projects_timesheet_id' => $projects_timesheet_id));
		
		if (!$retval = getData($result)[0])
			$this->terminateWithJsonError('Verknüpfung konnte nicht gefunden werden.');
		
		$result = $this->ProjectsTimesheetsProjectModel->delete($retval->projects_timesheets_project_id);
		
		if(isSuccess($result))
			$this->outputJsonSuccess($retval);
		else
			$this->terminateWithJsonError('Verknüpfung konnte nicht gelöscht werden.');
	}

	// Create new FH project like the given SAP project. Synchronize them too.
	public function createFUEProject()
	{
		$projects_timesheet_id = $this->input->post('projects_timesheet_id');
		$oe_kurzbz = $this->input->post('oe_kurzbz');

		if (isEmptyString($oe_kurzbz))
		{
			return $this->outputJsonError('Bitte wählen Sie eine Organisationseinheit');
		}

		// Check, if given project is already synced
		$isSynced_SAPProject = $this->ProjectsTimesheetsProjectModel->isSynced_SAPProject($projects_timesheet_id);

		if ($isSynced_SAPProject)
		{
			return $this->outputJsonError('Bitte synchronisieren Sie erst die Projekte'); // return null if already synced
		}

		// Get SAP project data
		$result = $this->SAPProjectsTimesheetsModel->load($projects_timesheet_id);

		if (!$retval = getData($result)[0])
		{
			return $this->outputJsonError('SAP Projekt konnte nicht gefunden werden.');
		}
		
		// Check, if FUE project already exists
		$result = $this->ProjektModel->load($retval->project_id);

		if (hasData($result))
		{
			$this->terminateWithJsonError('FH Projekt mit gleicher Projektkurzbezeichnung bereits vorhanden.');
		}

		// Create FUE project
		$result = $this->ProjektModel->insert(
			array(
				'projekt_kurzbz' => $retval->project_id,
				'titel' => $retval->name,
				'beginn' => $retval->start_date,
				'ende' => $retval->end_date,
				'beschreibung' => $retval->name,
				'oe_kurzbz' => $oe_kurzbz
			)
		);

		if (isError($result))
		{
			return $this->outputJsonError('FH-Projekt konnte nicht angelegt werden.');
		}

		// Get returning projekt_id of created FUE project
		$projekt_id = $result->retval;
		$titel = $retval->name;
		$projekt_kurzbz = $retval->project_id;

		// Synchronize SAP and FUE project
		$result = $this->ProjectsTimesheetsProjectModel->insert(array(
				'projects_timesheet_id' => $projects_timesheet_id,
				'projekt_id' => $projekt_id,
				'projektphase_id' => NULL
			)
		);

		if(isSuccess($result))
		{
			return $this->outputJsonSuccess(array(
				'projekt_id' => $projekt_id,
				'projekt_kurzbz' => $projekt_kurzbz,
				'titel' => $titel
			));
		}
		else
		{
			return $this->outputJsonError('Projekte konnten nicht verknüpft werden.');
		}
	}

	// Create new FH phase(s) like the given SAP phase(s). Synchronize them too.
	public function createFUEPhase()
	{
		$projects_timesheet_id_arr = $this->input->post('projects_timesheet_id');   // array of SAP phases

	    if (!isEmptyArray($projects_timesheet_id_arr))
	    {
	    	// Loop through given projectphases
		    foreach ($projects_timesheet_id_arr as $projects_timesheet_id)
		    {
			    // Check, if SAP projectphase is already synced with FH projectphase
			    $isSynced_SAPPhase = $this->ProjectsTimesheetsProjectModel
				    ->isSynced_SAPProjectphase($projects_timesheet_id);

			    if ($isSynced_SAPPhase)
			    {
				    $this->terminateWithJsonError(
				    	'Phase kann nicht neu erstellt werden, da sie bereits synchronisiert ist.'
				    );
			    }

			    // Get SAP projectphase
			    $result = $this->SAPProjectsTimesheetsModel->load($projects_timesheet_id);
			    
			    if (!hasData($result))
			    {
				    $this->outputJsonError('Fehler beim Ermitteln der SAP Phase.');
			    }
			    
			    $sapPhase = getData($result)[0];

			    // Get SAP project
			    $result = $this->SAPProjectsTimesheetsModel->getProject( $sapPhase->project_id);

			    if (!hasData($result))
			    {
				    $this->outputJsonError('Fehler beim Ermitteln des entsprechenden SAP Projekts.');
			    }
			    
			    $sapProject = getData($result)[0];

			    // Get synced FH project
			    $this->ProjektModel->addSelect('projekt_kurzbz, projekt_id');
			    $this->ProjektModel->addJoin('sync.tbl_projects_timesheets_project', 'projekt_id');

			    $result = $this->ProjektModel->loadWhere(array(
				    'projects_timesheet_id' => $sapProject->projects_timesheet_id
			    ));

			    // Check, if SAP and FH projects are synced yet
			    if (!hasData($result))
			    {
				    $this->terminateWithJsonError('Bitte synchronisieren Sie erst die Projekte.');
			    }
			    
			    $fhProjekt = getData($result)[0];
			
			    // Check if FH Projectphase already exists
			    // Note: If FH projectphase had been created, then desynced, it exists and should not be created again.
			    $result = $this->ProjektphaseModel->loadWhere(array(
			    	'projekt_kurzbz' => $fhProjekt->projekt_kurzbz,
				    'bezeichnung' => mb_substr($sapPhase->name, 0, 32)
			    ));

			    if (hasData($result))
			    {
				    $this->terminateWithJsonError('FH Phase mit gleicher Bezeichnung bereits vorhanden.');
			    }

			    // Create FUE phase to corresponding FUE project
			    // -----------------------------------------------------------------------------------------------------
			    $result = $this->ProjektphaseModel->insert(array(
					    'projekt_kurzbz' => $fhProjekt->projekt_kurzbz,
					    'bezeichnung' => mb_substr($sapPhase->name, 0, 32),
					    'beschreibung' => $sapPhase->name,
					    'start' => $sapPhase->start_date,
					    'ende' => $sapPhase->end_date,
					    'typ' => 'Projektphase'
				    )
			    );

			    if (isError($result))
			    {
				    $this->terminateWithJsonError('FH-Projektphase '. $sapPhase->name. ' konnte nicht neu angelegt werden.');
			    }

			    // Get projektphase_id of created FUE phase
			    $projektphase_id = $result->retval;

			    // Synchronize SAP and FUE projectphases
			    // -----------------------------------------------------------------------------------------------------
			    $result = $this->ProjectsTimesheetsProjectModel->syncProjectphases(
				    $projects_timesheet_id,
				    $fhProjekt->projekt_id,
				    $projektphase_id
			    );

			    if(isSuccess($result))
			    {
				    $json []= (array(
					    'projects_timesheet_id' => $projects_timesheet_id,
					    'projektphase_id' => $projektphase_id,
					    'bezeichnung' => $sapPhase->name
				    ));
			    }
			    else
			    {
				    $this->terminateWithJsonError('FH-Projektphase '. $sapPhase->name. ' konnte nicht verknüpft werden.');
			    }
	    	}

		    // Output json to ajax
		    if (isset($json) && !isEmptyArray($json))
		    {
			    $this->outputJsonSuccess($json);
		    }
		    else
		    {
			    $this->outputJsonError('Fehler beim Erstellen der FH-Projektphasen');
		    }
	    }
	}
	
	/**
	 * Check if phase already has time recordings.
	 */
	public function checkPhaseHasTimerecordings()
	{
		$projektphase_id = $this->input->post('projektphase_id');
		
		// Check if project has timerecordings
		$this->ZeitaufzeichnungModel->addSelect('1');
		$this->ZeitaufzeichnungModel->addLimit(1);
		$result = $this->ZeitaufzeichnungModel->loadWhere(array('projektphase_id' => $projektphase_id));
		
		if (isError($result))
		{
			$this->terminateWithJsonError('Prüfung auf Zeitbuchungen war fehlerhaft.');
		}
		
		// Return true, if phase has timerecordings. Otherwise false.
		$this->outputJsonSuccess(hasData($result));
	}
	
	/**
	 * Check if project already has time recordings.
	 */
	public function checkProjectHasTimerecordings()
	{
		$projekt_kurzbz = $this->input->post('projekt_kurzbz');
		
		// Check if project has timerecordings
		$this->ZeitaufzeichnungModel->addSelect('1');
		$this->ZeitaufzeichnungModel->addLimit(1);
		$result = $this->ZeitaufzeichnungModel->loadWhere(array('projekt_kurzbz' => $projekt_kurzbz));
	
		if (isError($result))
		{
			$this->terminateWithJsonError('Prüfung auf Zeitbuchungen war fehlerhaft.');
		}
		
		// Return true, if project has timerecordings. Otherwise false.
		$this->outputJsonSuccess(hasData($result));
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Retrieve the UID of the logged user and checks if it is valid
	 */
	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid) show_error('User authentification failed');
	}
}
