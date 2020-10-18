<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncProjectsLib
{
	// Indexes used to access to the configuration array
	const PROJECT_ID_FORMATS = 'project_id_formats';
	const PROJECT_NAME_FORMATS = 'project_name_formats';
	const PROJECT_STRUCTURES = 'project_structures';
	const PROJECT_UNIT_RESPONSIBLES = 'project_unit_responsibles';
	const PROJECT_PERSON_RESPONSIBLES = 'project_person_responsibles';
	const PROJECT_TYPES = 'project_types';
	const PROJECT_MAX_NUMBER_COST_CENTERS = 'project_max_number_cost_centers';
	const PROJECT_PERSON_RESPONSIBLE_CUSTOM = 'project_person_responsible_custom';
	const PROJECT_TYPE_CUSTOM = 'project_type_custom';
	const PROJECT_CUSTOM_ID_FORMAT = 'project_custom_id_format';
	const PROJECT_MANAGE_PURCHASE_ORDER_ENABLED = 'project_manage_purchase_order_enabled';

	// Project types
	const ADMIN_PROJECT = 'admin';
	const LEHRE_PROJECT = 'lehre';
	const LEHRGAENGE_PROJECT = 'lehrgaenge';

	// SAP ByD logic errors
	const PROJECT_EXISTS_ERROR = 'PRO_CMN_SHRD:003';
	const PARTECIPANT_PROJ_EXISTS_ERROR = 'PRO_CMN_PROJ:010';
	const PARTECIPANT_TASK_EXISTS_ERROR = 'PRO_CMN_ESRV:010';
	const RELEASE_PROJECT_ERROR = 'CM_DS_APPL_ERROR:000';
	const PROJECT_EMPLOYEE_NOT_EXISTS = 'PRO_PROJ_TEMPLATE:030';

	// Employee types
	const EMPLOYEE_VAUE = 'Mitarbeiter';
	const LEADER_VAUE = 'Leitung';

	// Project type
	const ALL = 'all';
	const ADMIN = 'admin';
	const LEHRE = 'lehre';
	const LEHRGAENGE = 'lehrgaenge';
	const CUSTOM = 'custom';

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads the LogLib with the needed parameters to log correctly from this library
		$this->_ci->load->library(
			'LogLib',
			array(
				'classIndex' => 3,
				'functionIndex' => 3,
				'lineIndex' => 2,
				'dbLogType' => 'job', // required
				'dbExecuteUser' => 'Cronjob system',
				'requestId' => 'JOB',
				'requestDataFormatter' => function($data) {
					return json_encode($data);
				}
			)
		);

		// Loads model ProjectsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Projects_model', 'ProjectsModel');
		// Loads model EmployeeModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Employee_model', 'EmployeeModel');
		// Loads model ManagePurchaseOrderIn
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManagePurchaseOrderIn_model', 'ManagePurchaseOrderInModel');

		// Loads the StudiensemesterModel
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		// Loads the Projekt_model
		$this->_ci->load->model('project/Projekt_model', 'ProjektModel');
		// Loads the Projektphase_model
		$this->_ci->load->model('project/Projektphase_model', 'ProjektphaseModel');
		// Loads the Projekt_ressource_model
		$this->_ci->load->model('project/Projekt_ressource_model', 'ProjektRessourceModel');
		// Loads the Ressource_model
		$this->_ci->load->model('project/Ressource_model', 'RessourceModel');
		// Loads MessageTokenModel
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');

		// Loads model SAPMitarbeiterModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPMitarbeiter_model', 'SAPMitarbeiterModel');
		// Loads model SAPServicesModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPServices_model', 'SAPServicesModel');
		// Loads model SAPProjectsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjects_model', 'SAPProjectsModel');
		// Loads model SAPProjectsCostcentersModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjectsCostcenters_model', 'SAPProjectsCostcentersModel');
		// Loads model SAPProjectsCoursesModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjectsCourses_model', 'SAPProjectsCoursesModel');
		// Loads model SAPProjectsTimesheets_model
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjectsTimesheets_model', 'SAPProjectsTimesheetsModel');

		// Loads Projects configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Projects');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Create new projects for the current study semester
	 */
	public function sync($type, $studySemester = null)
	{
		$currentOrNextStudySemesterResult = null;

		// If a study semester was given as parameter
		if (!isEmptyString($studySemester))
		{
			// Get info about the provided study semester
			$currentOrNextStudySemesterResult = $this->_ci->StudiensemesterModel->loadWhere(
				array(
					'studiensemester_kurzbz' => $studySemester
				)
			);
		}
		else // otherwise get the last or current one
		{
			// Get the last or current studysemester
			$currentOrNextStudySemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();
		}

		// If an error occurred while getting the study semester return it
		if (isError($currentOrNextStudySemesterResult)) return $currentOrNextStudySemesterResult;

		// If a study semester was found
		if (hasData($currentOrNextStudySemesterResult))
		{
			// Last or current study semester
			$currentOrNextStudySemester = getData($currentOrNextStudySemesterResult)[0]->studiensemester_kurzbz;

			// Project structures are optionals and are used to create tasks for a project
			$projectStructures = $this->_ci->config->item(self::PROJECT_STRUCTURES);
			// Project ID format
			$projectIdFormats = $this->_ci->config->item(self::PROJECT_ID_FORMATS);
			// Project names format
			$projectNameFormats = $this->_ci->config->item(self::PROJECT_NAME_FORMATS);
			// Project unit responsibles
			$projectUnitResponsibles = $this->_ci->config->item(self::PROJECT_UNIT_RESPONSIBLES);
			// Project person responsibles
			$projectPersonResponsibles = $this->_ci->config->item(self::PROJECT_PERSON_RESPONSIBLES);
			// Project types
			$projectTypes = $this->_ci->config->item(self::PROJECT_TYPES);

			// Get study semester start date
			$studySemesterStartDate = getData($currentOrNextStudySemesterResult)[0]->start;
			// Get study semester start date in timestamp format
			$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', getData($currentOrNextStudySemesterResult)[0]->start.' 00:00:00');
			$studySemesterStartDateTS = $dateTime->getTimestamp(); // project start date

			// Get study semester end date
			$studySemesterEndDate = getData($currentOrNextStudySemesterResult)[0]->ende;
			// Get study semester end date in timestamp format
			$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', getData($currentOrNextStudySemesterResult)[0]->ende.' 00:00:00');
			$studySemesterEndDateTS = $dateTime->getTimestamp(); // project end date

			// If it is requested a full sync or only for admin
			if ($type == self::ALL || $type == self::ADMIN)
			{
				// Create admin project
				$createResult = $this->_syncAdminProject(
					$currentOrNextStudySemester,
					$projectStructures,
					$projectIdFormats,
					$projectNameFormats,
					$projectUnitResponsibles,
					$projectPersonResponsibles,
					$projectTypes,
					$studySemesterStartDate,
					$studySemesterEndDate,
					$studySemesterStartDateTS,
					$studySemesterEndDateTS
				);
				if (isError($createResult)) return $createResult;
			}

			// If it is requested a full sync or only for lehre
			if ($type == self::ALL || $type == self::LEHRE)
			{
				// Create lehre project
				$createResult = $this->_syncLehreProject(
					$currentOrNextStudySemester,
					$projectIdFormats,
					$projectNameFormats,
					$projectUnitResponsibles,
					$projectPersonResponsibles,
					$projectTypes,
					$studySemesterStartDateTS,
					$studySemesterEndDateTS
				);
				if (isError($createResult)) return $createResult;
			}

			// If it is requested a full sync or only for lehrgaenge
			if ($type == self::ALL || $type == self::LEHRGAENGE)
			{
				// Create lehrgaenge projects
				$createResult = $this->_syncLehrgaengeProject(
					$currentOrNextStudySemester,
					$projectIdFormats,
					$projectNameFormats,
					$projectUnitResponsibles,
					$projectPersonResponsibles,
					$projectTypes,
					$studySemesterStartDateTS,
					$studySemesterEndDateTS,
					$studySemesterStartDate,
					$studySemesterEndDate
				);
				if (isError($createResult)) return $createResult;
			}

			// If it is requested a full sync or only for custom
			if ($type == self::ALL || $type == self::CUSTOM)
			{
				// Create custom projects
				$createResult = $this->_syncCustomProject(
					$currentOrNextStudySemester,
					$studySemesterStartDateTS,
					$studySemesterEndDateTS
				);
				if (isError($createResult)) return $createResult;
			}

			// If here everything went fine
			return success('All projects were successfully synchronized');
		}
		else
		{
			return success('No study semesters configured in database');
		}
	}

	/**
	 * Return the raw result of projekt/ProjectCollection
	 */
	public function getProjects()
	{
		return $this->_ci->ProjectsModel->getProjects();
	}

	/**
	 * Return the raw result of projekt/ProjectCollection and all projects tasks
	 */
	public function getProjectsAndTasks()
	{
		return $this->_ci->ProjectsModel->getProjectsAndTasks();
	}

	/**
	 * Return the raw result of projekt/ProjectCollection('$id')
	 */
	public function getProjectById($id)
	{
		return $this->_ci->ProjectsModel->getProjectById($id);
	}

	/**
	 * Return the raw result of projekt/ProjectCollection('$id')/ProjectTask
	 */
	public function getProjectTasks($id)
	{
		return $this->_ci->ProjectsModel->getProjectTasks($id);
	}

	/**
	 * Return the raw result of projekt/ProjectTaskCollection('$id')/ProjectTaskService
	 */
	public function getProjectTaskService($id)
	{
		return $this->_ci->ProjectsModel->getProjectTaskService($id);
	}

	/**
	 * Get all projects and their tasks from SAP Business by Design and store their ids in FHC database
	 */
	public function import()
	{
		// Get all projects and related tasks
		$projectsResult = $this->_ci->ProjectsModel->getProjectsAndTasks();

		// If an error occurred then return the error
		if (isError($projectsResult)) return $projectsResult;

		// If projects are present
		if (hasData($projectsResult))
		{
			// For each project found
			foreach (getData($projectsResult) as $project)
			{
				// Check if the project is already present with the SAP project object id
				// NOTE: project_task_object_id have to be null to retrieve a single record
				$sapProjectsTimesheetsResult = $this->_ci->SAPProjectsTimesheetsModel->loadWhere(
					array(
						'project_object_id' => $project->ObjectID,
						'project_task_object_id' => null
					)
				);

				// If error occurred return database error
				if (isError($sapProjectsTimesheetsResult)) return $sapProjectsTimesheetsResult;

				// Convert the start date from SAP date to timestamp
				$startDate = $project->PlannedStartDateTime;
				if ($startDate != null) $startDate = date('Y-m-d H:i:s', toTimestamp($startDate));

				// Convert the end date from SAP date to timestamp
				$endDate = $project->PlannedEndDateTime;
				if ($endDate != null) $endDate = date('Y-m-d H:i:s', toTimestamp($endDate));

				// If already present then update
				if (hasData($sapProjectsTimesheetsResult))
				{
					// Data from dabase
					$sapProjectTimesheet = getData($sapProjectsTimesheetsResult)[0];

					// Updates the already present record with data from SAP only if they differ
					if ($project->ProjectID != $sapProjectTimesheet->project_id
						|| $startDate != $sapProjectTimesheet->start_date
						|| $endDate != $sapProjectTimesheet->end_date
						|| $project->ProjectLifeCycleStatusCode != $sapProjectTimesheet->status)
					{
						// If the project id changed, then update all the records where the old project id is present
						if ($project->ProjectID != $sapProjectTimesheet->project_id)
						{
							$updateResult = $this->_ci->SAPProjectsTimesheetsModel->renameProjectId(
								$sapProjectTimesheet->project_id,
								$project->ProjectID
							);

							// If error occurred during update return database error
							if (isError($updateResult)) return $updateResult;
						}

						// Updates everything except obejcts id and sets updateamum with the current date
						// NOTE: there is no need to update the project id because was update before
						$updateResult = $this->_ci->SAPProjectsTimesheetsModel->update(
							$sapProjectTimesheet->projects_timesheet_id,
							array(
								'start_date' => $startDate,
								'end_date' => $endDate,
								'status' => $project->ProjectLifeCycleStatusCode,
								'updateamum' => 'NOW()'
							)
						);

						// If error occurred during update return database error
						if (isError($updateResult)) return $updateResult;
					}
					// else continue to the next one
				}
				else // otherwise insert
				{
					// Add entry database into sync table for projects timesheets
					$insertResult = $this->_ci->SAPProjectsTimesheetsModel->insert(
						array(
							'project_id' => $project->ProjectID,
							'project_object_id' => $project->ObjectID,
							'start_date' => $startDate,
							'end_date' => $endDate,
							'status' => $project->ProjectLifeCycleStatusCode
						)
					);

					// If error occurred during insert return database error
					if (isError($insertResult)) return $insertResult;
				}

				// If this project has tasks and more then one
				// NOTE: one of the tasks it's the project itself
				if (!isEmptyArray($project->ProjectTask) && count($project->ProjectTask) > 1)
				{
					// For each task found except the firt one -> project itself
					foreach ($project->ProjectTask as $projectTask)
					{
						// If the current task is the project itself then skip to the next one
						if ($project->ProjectID == $projectTask->ID) continue;

						// Check if the task is already present with the SAP project object id and SAP task object id
						$sapProjectsTaskTimesheetsResult = $this->_ci->SAPProjectsTimesheetsModel->loadWhere(
							array(
								'project_object_id' => $project->ObjectID,
								'project_task_object_id' => $projectTask->ObjectID
							)
						);

						// If error occurred return database error
						if (isError($sapProjectsTaskTimesheetsResult)) return $sapProjectsTaskTimesheetsResult;

						// Convert the start date from SAP date to timestamp
						$startDate = $projectTask->StartDateTime;
						if ($startDate != null) $startDate = date('Y-m-d H:i:s', toTimestamp($startDate));
		
						// Convert the end date from SAP date to timestamp
						$endDate = $projectTask->EndDateTime;
						if ($endDate != null) $endDate = date('Y-m-d H:i:s', toTimestamp($endDate));

						// If already present then update
						if (hasData($sapProjectsTaskTimesheetsResult))
						{
							// Data from database
							$sapProjectTaskTimesheet = getData($sapProjectsTaskTimesheetsResult)[0];

							// Updates the already present record with data from SAP only if they differ
							if ($projectTask->ID != $sapProjectTaskTimesheet->project_task_id
								|| $startDate != $sapProjectTaskTimesheet->start_date
								|| $endDate != $sapProjectTaskTimesheet->end_date
								|| $projectTask->LifeCycleStatusCode != $sapProjectTaskTimesheet->status)
							{
								// Updates everything except obejcts id and sets updateamum with the current date
								// NOTE: there is no need to update the project id because was update before
								$updateResult = $this->_ci->SAPProjectsTimesheetsModel->update(
									$sapProjectTaskTimesheet->projects_timesheet_id,
									array(
										'project_task_id' => $projectTask->ID,
										'start_date' => $startDate,
										'end_date' => $endDate,
										'status' => $projectTask->LifeCycleStatusCode,
										'updateamum' => 'NOW()'
									)
								);

								// If error occurred during update return database error
								if (isError($updateResult)) return $updateResult;
							}
							// else continue with the next one
						}
						else // otherwise insert
						{
							// Add entry database into sync table for projects timesheets
							$insertResult = $this->_ci->SAPProjectsTimesheetsModel->insert(
								array(
									'project_id' => $project->ProjectID,
									'project_object_id' => $project->ObjectID,
									'project_task_id' => $projectTask->ID,
									'project_task_object_id' => $projectTask->ObjectID,
									'start_date' => $startDate,
									'end_date' => $endDate,
									'status' => $projectTask->LifeCycleStatusCode
								)
							);

							// If error occurred during insert return database error
							if (isError($insertResult)) return $insertResult;
						}
					}
				}
			}
		}
		else
		{
			return success('No projects are present on SAP ByD');
		}

		return success('All project have been imported successfully');
	}

	/**
	 * 
	 */
	public function importEmployees()
	{
		$dbModel = new DB_Model();

		// Gets all the records from sync.tbl_projects_timesheets_project that link a project to a phase or a task to a project
		// Aka all those records which break the rule 0
		$rule0BreakerResults = $dbModel->execReadOnlyQuery('
			SELECT ptp.projects_timesheet_id,
				ptp.projekt_id,
				ptp.projektphase_id
			  FROM sync.tbl_projects_timesheets_project ptp
			  JOIN sync.tbl_sap_projects_timesheets spt USING(projects_timesheet_id)
			 WHERE (ptp.projektphase_id IS NULL AND spt.project_task_object_id IS NOT NULL)
			    OR (ptp.projektphase_id IS NOT NULL AND spt.project_task_object_id IS NULL)
		');

		// If an error occurred then return it
		if (isError($rule0BreakerResults)) return $rule0BreakerResults;

		// Stores link breakers id to not load them later
		// NOTE: the -1 value is not to have the query to fail and does not exists in database
		$breakersArray = array(-1);

		// Logs the breakers and store their ids to avoid lo load them later
		if (hasData($rule0BreakerResults))
		{
			// For each breaker
			foreach (getData($rule0BreakerResults) as $breaker)
			{
				$this->_ci->loglib->logWarningDB(
					'Rule 0 breaker record found: '.$breaker->projects_timesheet_id.', '.$breaker->projekt_id.', '.$breaker->projektphase_id
				);
				$breakersArray[] = $breaker->projects_timesheet_id;
			}
		}
		// else no breakers were found

		// Loads all the linked projects and tasks except the link breakers
		$linkedProjectsResult = $dbModel->execReadOnlyQuery('
			SELECT ptp.projekt_id,
				ptp.projektphase_id,
				pt.project_id,
				pt.project_object_id,
				pt.project_task_id,
				pt.project_task_object_id
			  FROM sync.tbl_projects_timesheets_project ptp
			  JOIN sync.tbl_sap_projects_timesheets pt USING(projects_timesheet_id)
			 WHERE ptp.projects_timesheet_id NOT IN ?
			   AND NOW() - pt.end_date::timestamptz <= INTERVAL \'1 year\'
		      ORDER BY pt.project_object_id, pt.project_task_object_id
		', array($breakersArray));

		// If error occurred then return the error
		if (isError($linkedProjectsResult)) return $linkedProjectsResult;

		// If linked projects are presents
		if (hasData($linkedProjectsResult))
		{
			// For each linked project found
			foreach (getData($linkedProjectsResult) as $linkedProject)
			{
				// If a FHC project was linked to a SAP project
				if ($linkedProject->projektphase_id == null)
				{
					// Get the project leader
					$sapLeaderId = null; // sap leader id by default leader is not present
					$fhcLeaderUid = null; // fhc leader uid by default leader is not present

					// Get the project tasks
					$projectTaskResults = $this->_ci->ProjectsModel->getProjectsAndTasks(array($linkedProject->project_object_id));

					// If an error occurred then return it
					if (isError($projectTaskResults)) return $projectTaskResults;

					// If SAP returned something usable
					if (hasData($projectTaskResults))
					{
						// Get the project
						$project = getData($projectTaskResults)[0];

						// If it is set the ProjectTask property and it is a valid array with at least one element
						if (isset($project->ProjectTask) && !isEmptyArray($project->ProjectTask))
						{
							// Loop on the project tasks
							foreach ($project->ProjectTask as $projectTask)
							{
								// If the current task is the project itself
								if ($linkedProject->project_id == $projectTask->ID)
								{
									// If is set the ResponsibleEmployeeID property and it is a valid string
									if (isset($projectTask->ResponsibleEmployeeID)
										&& !isEmptyString($projectTask->ResponsibleEmployeeID))
									{
										$sapLeaderId = $projectTask->ResponsibleEmployeeID;
									}
								}
							}
						}
					}

					// If no leader is assigned to this project
					if ($sapLeaderId == null)
					{
						$this->_ci->loglib->logWarningDB('No leader assigned in SAP for project: '.$linkedProject->projekt_id);
					}

					// If a leader was assigned to this project in SAP
					if ($sapLeaderId != null)
					{
						// Load the FHC uid
						$fhcLeaderUidResult = $this->_ci->SAPMitarbeiterModel->loadWhere(array('sap_eeid' => $sapLeaderId));

						// If an error occurred then return it
						if (isError($fhcLeaderUidResult)) return $fhcLeaderUidResult;

						// If a FHC uid was found and is it valid
						if (hasData($fhcLeaderUidResult) && !isEmptyString(getData($fhcLeaderUidResult)[0]->mitarbeiter_uid))
						{
							$fhcLeaderUid = getData($fhcLeaderUidResult)[0]->mitarbeiter_uid;
						}
						else // otherwise log it
						{
							$this->_ci->loglib->logWarningDB(
								'Leader '.$sapLeaderId.' is assigned in SAP to project '.
								$linkedProject->projekt_id.' but is not syncd'
							);
						}
					}

					// Get all the partecipant for this project from SAP
					$projectPartecipantResult = $this->_ci->ProjectsModel->getProjectsAndPartecipants(array($linkedProject->project_object_id));

					// If it is an error then return it
					if (isError($projectPartecipantResult)) return $projectPartecipantResult;

					// If no project were found in SAP log and continue to the next one, should not happen
					if (!hasData($projectPartecipantResult))
					{
						$this->_ci->loglib->logWarningDB('No project found in SAP with id: '.$linkedProject->project_id);
						continue;
					}

					// Get the project and partecipant object
					$projectPartecipant = getData($projectPartecipantResult)[0];

					// If is it set the property ProjectParticipant, and it is a valid and not empty array, of object project
					if (isset($projectPartecipant->ProjectParticipant)
						&& !isEmptyArray($projectPartecipant->ProjectParticipant))
					{
						// For each SAP partecipant
						foreach ($projectPartecipant->ProjectParticipant as $partecipant)
						{
							// If it is set the property EmployeeID, and it is valid, of the partecipant object
							if (isset($partecipant->EmployeeID) && !isEmptyString($partecipant->EmployeeID))
							{
								// Function covered by this user in this project
								$userFunction = self::EMPLOYEE_VAUE; // by default it is a worker

								// If it is the leader of this project
								if ($partecipant->EmployeeID == $sapLeaderId) $userFunction = self::LEADER_VAUE;

								// Get or create the ressource
								$ressourceResult = $this->_getOrCreateRessource($partecipant->EmployeeID);

								// If an error occurred then return it
								if (isError($ressourceResult)) return $ressourceResult;
								// If was not possible to get or create the ressource then continue to the next one
								// NOTE: warnings have been logged in _getOrCreateRessource
								if (!hasData($ressourceResult)) continue;

								// Get the ressource_id
								$ressource_id = getData($ressourceResult);

								// Get the projekt_kurzbz from fue.tbl_projekt
								$projektResult = $this->_ci->ProjektModel->loadWhere(
									array(
										'projekt_id' => $linkedProject->projekt_id
									)
								);

								// If error occurred then return the error
								if (isError($projektResult)) return $projektResult;

								// If the project is present in database
								if (hasData($projektResult))
								{
									// FHC Project pk
									$projekt_kurzbz = getData($projektResult)[0]->projekt_kurzbz;

									// Check if not already present in fue.tbl_projekt_ressource for projects
									$checkResult = $this->_ci->ProjektRessourceModel->loadWhere(
										array(
											'projekt_kurzbz' => $projekt_kurzbz,
											'ressource_id' => $ressource_id,
											'funktion_kurzbz' => $userFunction
										)
									);

									// If error occurred then return the error
									if (isError($checkResult)) return $checkResult;

									// If _not_ present then is possible to insert without errors
									if (!hasData($checkResult))
									{
										// Insert data into fue.tbl_projekt_ressource for project
										$insertResult = $this->_ci->ProjektRessourceModel->insert(
											array(
												'projekt_kurzbz' => $projekt_kurzbz,
												'beschreibung' => 'Assigned via SAP to this project',
												'ressource_id' => $ressource_id,
												'funktion_kurzbz' => $userFunction
											)
										);

										// If error occurred then return the error
										if (isError($insertResult)) return $intertResult;
									}
									// else skip to the next one
								}
								// else the linked project does not exists, should never be the case because of the foreign key
							}
							else // log it and continue to the next one
							{
								$this->_ci->loglib->logWarningDB(
									'Partecipant object without employee id for project: '.$linkedProject->project_id
								);
								continue;
							}
						}
					}
					else // otherwise log it and continue to the next one
					{
						$this->_ci->loglib->logWarningDB(
							'Project without partecipants: '.$linkedProject->project_id
						);
						continue;
					}
				}
				else // otherwise if a SAP task was linked to a FHC phase
				{
					// Gets the task service from SAP for the given task object id
					$taskServiceResult = $this->_ci->ProjectsModel->getProjectTaskService($linkedProject->project_task_object_id);

					// If an error occurred then return the error
					if (isError($taskServiceResult)) return $taskServiceResult;

					// If there are no services for this task
					if (!hasData($taskServiceResult))
					{
						$this->_ci->loglib->logWarningDB('No services found in SAP for task: '.$linkedProject->project_task_id);
						continue;
					}

					// For each retrived task service. One for each service/employee assigned to this task
					foreach (getData($taskServiceResult) as $task)
					{
						// If the service is present but without employee
						if (isEmptyString($task->AssignedEmployeeID))
						{
							$this->_ci->loglib->logWarningDB('Found a service without emplyee for task: '.$linkedProject->project_task_id);
							continue;
						}

						// Get or create the ressource
						$ressourceResult = $this->_getOrCreateRessource($task->AssignedEmployeeID);

						// If an error occurred then return it
						if (isError($ressourceResult)) return $ressourceResult;
						// If was not possible to get or create the ressource then continue to the next one
						// NOTE: warnings have been logged in _getOrCreateRessource
						if (!hasData($ressourceResult)) continue;

						// Get the ressource_id
						$ressource_id = getData($ressourceResult);

						// Check if not already present in fue.tbl_projekt_ressource for project phase
						$checkResult = $this->_ci->ProjektRessourceModel->loadWhere(
							array(
								'projektphase_id' => $linkedProject->projektphase_id,
								'ressource_id' => $ressource_id,
								'funktion_kurzbz' => self::EMPLOYEE_VAUE
							)
						);

						// If error occurred then return the error
						if (isError($checkResult)) return $checkResult;

						// If _not_ present then is possible to insert without errors
						if (!hasData($checkResult))
						{
							// Gets the task from SAP for the given task object id
							$taskResult = $this->_ci->ProjectsModel->getTask($linkedProject->project_task_object_id);

							// If an error occurred then return the error
							if (isError($taskResult)) return $taskResult;

							// If this task exists in SAP, should never happen the other way because was previously checked
							if (hasData($taskResult))
							{
								// Insert data into fue.tbl_projekt_ressource for project phase
								$insertResult = $this->_ci->ProjektRessourceModel->insert(
									array(
										'projektphase_id' => $linkedProject->projektphase_id,
										'beschreibung' => 'Assigned via SAP to this phase',
										'ressource_id' => $ressource_id,
										'funktion_kurzbz' => self::EMPLOYEE_VAUE
									)
								);

								// If error occurred then return the error
								if (isError($insertResult)) return $intertResult;
							}
							// else skip to the next one
						}
						// else skip to the next one
					}
				}
			}
		}
		else // otherwise return a success
		{
			return success('No projects are linked');
		}

		// If everything was fine
		return success('All employees for linked project have been imported successfully');
	}

	/**
	 * 
	 */
	public function importProjectsDates()
	{
		$dbModel = new DB_Model();

		// Gets all the records from sync.tbl_projects_timesheets_project that link a project to a phase or a task to a project (rule 0)
		$rule0BreakerResults = $dbModel->execReadOnlyQuery('
			SELECT ptp.projects_timesheet_id,
				ptp.projekt_id,
				ptp.projektphase_id
			  FROM sync.tbl_projects_timesheets_project ptp
			  JOIN sync.tbl_sap_projects_timesheets spt USING(projects_timesheet_id)
			 WHERE (ptp.projektphase_id IS NULL AND spt.project_task_object_id IS NOT NULL)
			    OR (ptp.projektphase_id IS NOT NULL AND spt.project_task_object_id IS NULL)
		');

		// If an error occurred then return it
		if (isError($rule0BreakerResults)) return $rule0BreakerResults;

		// Stores link breakers id to not load them later
		// NOTE: the -1 value is not to have the query to fail and does not exists in database
		$breakersArray = array(-1);

		// Logs the breakers and store their ids to avoid lo load them later
		if (hasData($rule0BreakerResults))
		{
			// For each breaker
			foreach (getData($rule0BreakerResults) as $breaker)
			{
				$this->_ci->loglib->logWarningDB(
					'Rule 0 breaker record found: '.$breaker->projects_timesheet_id.', '.$breaker->projekt_id.', '.$breaker->projektphase_id
				);
				$breakersArray[] = $breaker->projects_timesheet_id;
			}
		}
		// else no breakers were found

		// Loads all the linked projects and tasks except the link breakers
		$linkedProjectsResult = $dbModel->execReadOnlyQuery('
			SELECT ptp.projekt_id,
				ptp.projektphase_id,
				pt.project_id,
				pt.project_object_id,
				pt.project_task_id,
				pt.project_task_object_id
			  FROM sync.tbl_projects_timesheets_project ptp
			  JOIN sync.tbl_sap_projects_timesheets pt USING(projects_timesheet_id)
			 WHERE ptp.projects_timesheet_id NOT IN ?
		      ORDER BY pt.project_object_id, pt.project_task_object_id
		', array($breakersArray));

		// If error occurred then return the error
		if (isError($linkedProjectsResult)) return $linkedProjectsResult;

		// If linked projects are presents
		if (hasData($linkedProjectsResult))
		{
			// For each linked project found
			foreach (getData($linkedProjectsResult) as $linkedProject)
			{
				// If a FHC project was linked to a SAP project
				if ($linkedProject->projektphase_id == null)
				{
					// Get the project data from SAP
					$projectResult = $this->_ci->ProjectsModel->getProjects(array($linkedProject->project_object_id));

					// If an error occurred then return it
					if (isError($projectResult)) return $projectResult;

					// If no data are found in SAP
					if (!hasData($projectResult))
					{
						$this->_ci->loglib->logWarningDB('Project not found in SAP: '.$linkedProject->project_id);
						continue;
					}

					// SAP project
					$project = getData($projectResult)[0];

					// Checks if the project exists
					$projektResult = $this->_ci->ProjektModel->loadWhere(
						array(
							'projekt_id' => $linkedProject->projekt_id
						)
					);

					// If error occurred then return the error
					if (isError($projektResult)) return $projektResult;

					// If the project is present in database, should not happen because of the foreign key
					if (!hasData($projektResult))
					{
						$this->_ci->loglib->logWarningDB('Project not found in database: '.$linkedProject->project_id);
						continue;
					}

					// FHC project
					$projekt = getData($projektResult)[0];

					// Update project start and end date
					$updateResult = $this->_ci->ProjektModel->update(
						array(
							'projekt_kurzbz' => $projekt->projekt_kurzbz
						),
						array(
							'beginn' => date('Y-m-d', toTimestamp($project->PlannedStartDateTime)),
							'ende' => date('Y-m-d', toTimestamp($project->PlannedEndDateTime))
						)
					);

					// If error occurred then return the error
					if (isError($updateResult)) return $updateResult;
				}
				else // otherwise if a SAP task was linked to a FHC phase
				{
					// Get the project data from SAP
					$projectTaskResult = $this->_ci->ProjectsModel->getTask($linkedProject->project_task_object_id);

					// If an error occurred then return it
					if (isError($projectTaskResult)) return $projectTaskResult;

					// If no data are found in SAP
					if (!hasData($projectTaskResult))
					{
						$this->_ci->loglib->logWarningDB('Task not found in SAP: '.$linkedProject->project_task_id);
						continue;
					}

					// SAP project task
					$projectTask = getData($projectTaskResult);

					// Checks if the project phase exists
					$projektPhaseResult = $this->_ci->ProjektphaseModel->loadWhere(
						array(
							'projektphase_id' => $linkedProject->projektphase_id
						)
					);

					// If error occurred then return the error
					if (isError($projektPhaseResult)) return $projektPhaseResult;

					// If the project is present in database, should not happen because of the foreign key
					if (!hasData($projektPhaseResult))
					{
						$this->_ci->loglib->logWarningDB('Project phase not found in database: '.$linkedProject->project_task_id);
						continue;
					}

					// FHC project
					$projektPhase = getData($projektPhaseResult)[0];

					// Update project phase start and end date
					$updateResult = $this->_ci->ProjektphaseModel->update(
						array(
							'projektphase_id' => $projektPhase->projektphase_id
						),
						array(
							'start' => date('Y-m-d', toTimestamp($projectTask->StartDateTime)),
							'ende' => date('Y-m-d', toTimestamp($projectTask->EndDateTime))
						)
					);

					// If error occurred then return the error
					if (isError($updateResult)) return $updateResult;
				}
			}
		}
		else // otherwise return a success
		{
			return success('No projects are linked');
		}

		// If everything was fine
		return success('All dates for linked project have been imported successfully');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods
	
	/**
	 *
	 */
	private function _getOrCreateRessource($sapEmployeeId)
	{
		// The ressource id to be used to link the employee to the FHC project
		$ressource_id = null;

		$dbModel = new DB_Model();

		// Loads the resource id that should be added in fue.tbl_projektphase
		$ressourceResult = $dbModel->execReadOnlyQuery('
			SELECT r.ressource_id
			  FROM fue.tbl_ressource r
			  JOIN sync.tbl_sap_mitarbeiter sm USING(mitarbeiter_uid)
			 WHERE sm.sap_eeid = ?
		', array($sapEmployeeId));

		// If error occurred then return the error
		if (isError($ressourceResult)) return $ressourceResult;

		// If the ressource was found
		if (hasData($ressourceResult))
		{
			$ressource_id = getData($ressourceResult)[0]->ressource_id;
		}
		else // otherwise add the new ressource
		{
			// Loads data of the new ressource
			$ressourceResult = $dbModel->execReadOnlyQuery('
				SELECT p.nachname,
					p.vorname,
					b.uid
				  FROM public.tbl_person p
				  JOIN public.tbl_benutzer b USING (person_id)
				  JOIN sync.tbl_sap_mitarbeiter sm ON (sm.mitarbeiter_uid = b.uid)
				 WHERE sm.sap_eeid = ?
			', array($sapEmployeeId));

			// If error occurred then return the error
			if (isError($ressourceResult)) return $ressourceResult;

			// If no data have been found log it and continue to the next one
			if (!hasData($ressourceResult))
			{
				$this->_ci->loglib->logWarningDB('SAP Employee '.$sapEmployeeId.' not found in database');
			}
			else
			{
				// New resource
				$newRessource = getData($ressourceResult)[0];

				// Insert the new resource in database
				$ressourceInsertResult = $this->_ci->RessourceModel->insert(
					array(
						// Description format: <surname> <name>
						'bezeichnung' => $newRessource->nachname.' '.$newRessource->vorname,
						'beschreibung' => 'Added by SAP Project import job',
						'mitarbeiter_uid' => $newRessource->uid,
						'insertamum' => 'NOW()',
						'insertvon' => 'SAP Project import job'
					)
				);

				// If error then return it
				if (isError($ressourceInsertResult)) return $ressourceInsertResult;

				// Get the new ressource_id 
				$ressource_id = getData($ressourceInsertResult);
			}
		}

		return success($ressource_id);
	}

	/**
	 *
	 */
	private function _syncLehreProject(
		$studySemester,
		$projectIdFormats,
		$projectNameFormats,
		$projectUnitResponsibles,
		$projectPersonResponsibles,
		$projectTypes,
		$studySemesterStartDateTS,
		$studySemesterEndDateTS
	)
	{
		$projectId = strtoupper(sprintf($projectIdFormats[self::LEHRE_PROJECT], $studySemester)); // project id
		$type = $projectTypes[self::LEHRE_PROJECT]; // Project type
		$unitResponsible = $projectUnitResponsibles[self::LEHRE_PROJECT]; // project unit responsible
		$personResponsible = $projectPersonResponsibles[self::LEHRE_PROJECT]; // project person responsible

		// Create the project on ByD
		$createProjectResult = $this->_ci->ProjectsModel->create(
			$projectId,
			$type,
			$unitResponsible,
			$personResponsible,
			$studySemesterStartDateTS,
			$studySemesterEndDateTS
		);

		// If an error occurred while creating the project on ByD, and the error is not project already exists, then return the error
		if (isError($createProjectResult) && getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR) return $createProjectResult;

		$projectObjectId = null;

		// If the projects is not alredy present it is _not_ needed to sync the database
		if (getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR)
		{
			// Add entry database into sync table for projects
			$insertResult = $this->_ci->SAPProjectsModel->insert(
				array(
					'project_id' => $projectId,
					'project_object_id' => getData($createProjectResult)->ObjectID,
					'studiensemester_kurzbz' => $studySemester
				)
			);

			// If error occurred during insert return database error
			if (isError($insertResult)) return $insertResult;

			$projectObjectId = getData($createProjectResult)->ObjectID;
		}
		else // otherwise get the project info from sync table
		{
			$projectResult = $this->_ci->SAPProjectsModel->loadWhere(
				array(
					'project_id' => $projectId,
					'studiensemester_kurzbz' => $studySemester
				)
			);

			// If an error occurred while getting project info from database return the error itself
			if (isError($projectResult)) return $projectResult;
			// If no data found with these parameters
			if (!hasData($projectResult)) return error($projectId.' project is present in SAP but _not_ in sync.tbl_sap_projects');

			$projectObjectId = getData($projectResult)[0]->project_object_id; // store the project object id
		}

		// If was not possible to find a valid project object id
		if (isEmptyString($projectObjectId)) return error('Was _not_ possible to find a valid lehre project object id');

		// Update project ProjectTaskCollection name
		$projectName = sprintf($projectNameFormats[self::LEHRE_PROJECT], $studySemester);
		$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
			$projectObjectId,
			$projectName
		);

		// If an error occurred while creating the project on ByD return the error
		if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

		// Set the project as active
		$setActiveResult = $this->_ci->ProjectsModel->setActive($projectObjectId);

		// If an error occurred while setting the project as active on ByD
		// and not because the project was alredy released then return the error
		if (isError($setActiveResult)
			&& getCode($setActiveResult) != self::RELEASE_PROJECT_ERROR)
		{
			return $setActiveResult;
		}

		$dbModel = new DB_Model();

		// Loads teachers
		$lehreEmployeesResult = $dbModel->execReadOnlyQuery('
			SELECT lm.mitarbeiter_uid,
				b.person_id,
				(SUM(lm.semesterstunden) * 1.5) AS planned_work,
				(SUM(lm.semesterstunden) * 1.5) AS commited_work,
				\'0\' AS ma_soll_stunden,
				\'0\' AS lehre_grobplanung
			  FROM lehre.tbl_lehreinheitmitarbeiter lm
			  JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id)
			  JOIN lehre.tbl_lehrveranstaltung lv USING(lehrveranstaltung_id)
			  JOIN public.tbl_studiengang s USING(studiengang_kz)
			  JOIN public.tbl_benutzer b ON(b.uid = lm.mitarbeiter_uid)
			  JOIN public.tbl_mitarbeiter m USING(mitarbeiter_uid)
			 WHERE l.studiensemester_kurzbz = ?
			   AND s.typ IN (\'b\', \'m\')
			   AND m.fixangestellt = TRUE
			   AND m.personalnummer > 0
		      GROUP BY lm.mitarbeiter_uid, b.person_id
		', array($studySemester));

		// If error occurred while retrieving teachers from database then return the error
		if (isError($lehreEmployeesResult)) return $lehreEmployeesResult;

		// If teachers are present
		if (hasData($lehreEmployeesResult))
		{
			// For each employee
			foreach (getData($lehreEmployeesResult) as $lehreEmployee)
			{
				// Add the employee to this project
				$addEmployeeResult = $this->_addEmployeeToProject(
					$lehreEmployee,
					$projectObjectId,
					$studySemesterStartDateTS,
					$studySemesterEndDateTS
				);

				// If an error occurred then return it
				if (isError($addEmployeeResult)) return $addEmployeeResult;
			}
		}

		return success('Project lehre synchronization ended successfully');
	}

	/**
	 *
	 */
	private function _syncLehrgaengeProject(
		$studySemester,
		$projectIdFormats,
		$projectNameFormats,
		$projectUnitResponsibles,
		$projectPersonResponsibles,
		$projectTypes,
		$studySemesterStartDateTS,
		$studySemesterEndDateTS,
		$studySemesterEndDate,
		$studySemesterStartDate
	)
	{
		$type = $projectTypes[self::LEHRGAENGE_PROJECT]; // Project type
		$unitResponsible = $projectUnitResponsibles[self::LEHRGAENGE_PROJECT]; // project unit responsible
		$personResponsible = $projectPersonResponsibles[self::LEHRGAENGE_PROJECT]; // project person responsible

		$dbModel = new DB_Model();

		// Loads all the courses
		$coursesResult = $dbModel->execReadOnlyQuery('
			SELECT s.studiengang_kz,
				UPPER(s.typ || s.kurzbz) AS name,
				s.oe_kurzbz
			  FROM public.tbl_studiengang s
			 WHERE s.studiengang_kz < 0
		      ORDER BY name
		');

		// If error occurred while retrieving courses from database then return the error
		if (isError($coursesResult)) return $coursesResult;
		if (!hasData($coursesResult)) return success('No courses found in database');

		// For each course found in database
		foreach (getData($coursesResult) as $course)
		{
			$projectId = strtoupper(sprintf($projectIdFormats[self::LEHRGAENGE_PROJECT], $course->name, $studySemester)); // project id

			// Create the project on ByD
			$createProjectResult = $this->_ci->ProjectsModel->create(
				$projectId,
				$type,
				$unitResponsible,
				$personResponsible,
				$studySemesterStartDateTS,
				$studySemesterEndDateTS
			);

			// If an error occurred while creating the project on ByD
			if (isError($createProjectResult))
			{
				// ...and the error is _not_ project already exists, then return the error
				if (getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR)
				{
					return $createProjectResult;
				}
				else // if non blocking error then log it
				{
					$this->_ci->loglib->logWarningDB(getError($createProjectResult));
				}
			}

			$projectObjectId = null;

			// If the projects is not alredy present it is _not_ needed to sync the database
			if (getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR)
			{
				// Add entry database into sync table for projects
				$insertResult = $this->_ci->SAPProjectsCoursesModel->insert(
					array(
						'project_id' => $projectId,
						'project_object_id' => getData($createProjectResult)->ObjectID,
						'studiensemester_kurzbz' => $studySemester,
						'studiengang_kz' => $course->studiengang_kz
					)
				);

				// If error occurred during insert return database error
				if (isError($insertResult)) return $insertResult;

				$projectObjectId = getData($createProjectResult)->ObjectID;
			}
			else
			{
				$projectResult = $this->_ci->SAPProjectsCoursesModel->loadWhere(
					array(
						'project_id' => $projectId,
						'studiensemester_kurzbz' => $studySemester,
						'studiengang_kz' => $course->studiengang_kz
					)
				);

				// If an error occurred while getting project info from database return the error itself
				if (isError($projectResult)) return $projectResult;
				// If no data found with these parameters
				if (!hasData($projectResult)) return error($projectId.' project is present in SAP but _not_ in sync.tbl_sap_projects_courses');

				$projectObjectId = getData($projectResult)[0]->project_object_id; // store the project object id
			}

			// If was not possible to find a valid project object id
			if (isEmptyString($projectObjectId)) return error('Was _not_ possible to find a valid lehrgaenge project object id');

			// Update project ProjectTaskCollection name
			$projectName = sprintf($projectNameFormats[self::LEHRGAENGE_PROJECT], $course->name, $studySemester);
			$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
				$projectObjectId,
				$projectName
			);

			// If an error occurred while creating the project on ByD return the error
			if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

			// Set the project as active
			$setActiveResult = $this->_ci->ProjectsModel->setActive($projectObjectId);

			// If an error occurred while setting the project as active on ByD
			// and not because the project was alredy released then return the error
			if (isError($setActiveResult)
				&& getCode($setActiveResult) != self::RELEASE_PROJECT_ERROR)
			{
				return $setActiveResult;
			}

			// Loads employees for this course, study semester and their organization unit
			$courseEmployeesResult = $dbModel->execReadOnlyQuery('
				SELECT lm.mitarbeiter_uid,
					b.person_id,
					(SUM(lm.semesterstunden) * 1.5) AS planned_work,
					(SUM(lm.semesterstunden) * 1.5) AS commited_work,
					\'0\' AS ma_soll_stunden,
					\'0\' AS lehre_grobplanung,
					bf.oe_kurzbz
				  FROM lehre.tbl_lehreinheitmitarbeiter lm
				  JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id)
				  JOIN lehre.tbl_lehrveranstaltung lv USING(lehrveranstaltung_id)
				  JOIN public.tbl_studiengang s USING(studiengang_kz)
				  JOIN public.tbl_benutzer b ON(b.uid = lm.mitarbeiter_uid)
			  	  JOIN public.tbl_mitarbeiter m USING(mitarbeiter_uid)
				  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
				 WHERE l.studiensemester_kurzbz = ?
				   AND s.studiengang_kz = ?
			   	   AND m.fixangestellt = TRUE
				   AND m.personalnummer > 0
				   AND b.aktiv = TRUE
				   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
				   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
				   AND bf.funktion_kurzbz = \'oezuordnung\'
			      GROUP BY lm.mitarbeiter_uid, b.person_id, bf.oe_kurzbz
			      ORDER BY lm.mitarbeiter_uid
			', array($studySemester, $course->studiengang_kz, $studySemesterEndDate, $studySemesterStartDate));

			// If error occurred while retrieving course employee from database then return the error
			if (isError($courseEmployeesResult)) return $courseEmployeesResult;

			// If employees are present for this course
			if (hasData($courseEmployeesResult))
			{
				// For each employee
				foreach (getData($courseEmployeesResult) as $courseEmployee)
				{
					// Add the employee to this project
					$addEmployeeResult = $this->_addEmployeeToProject(
						$courseEmployee,
						$projectObjectId,
						$studySemesterStartDateTS,
						$studySemesterEndDateTS
					);

					// If an error occurred then return it
					if (isError($addEmployeeResult)) return $addEmployeeResult;

					// If config entry is true and it is the case then perform a call to ManagePurchaseOrderIn
					if ($this->_ci->config->item(self::PROJECT_MANAGE_PURCHASE_ORDER_ENABLED) === true)
					{
						$purchaseOrder = $this->_purchaseOrder();

						// If error occurred then return the error
						if (isError($purchaseOrder)) return $purchaseOrder;
					}
				}
			}
		}

		return success('Project lehrgaenge synchronization ended successfully');
	}

	/**
	 *
	 */
	private function _purchaseOrder($courseEmployee, $course)
	{
		// Get the root organization unit for the employee
		$employeeOURootResult = $this->_ci->MessageTokenModel->getOERoot($courseEmployee->oe_kurzbz);

		// If an error occurred then return it
		if (isError($employeeOURootResult)) return $employeeOURootResult;

		// Get the root organization unit for the course
		$courseOURootResult = $this->_ci->MessageTokenModel->getOERoot($course->oe_kurzbz);

		// If an error occurred then return it
		if (isError($courseOURootResult)) return $courseOURootResult;

		// If no root organization unit found for the employee
		if (!hasData($employeeOURootResult)) 
		{
			$this->_ci->loglib->logWarningDB(
				'No root organization unit found for employee: '.$courseEmployee->mitarbeiter_uid
			);
		}
		// If no root organization unit found for the course
		elseif (!hasData($courseOURootResult)) 
		{
			$this->_ci->loglib->logWarningDB(
				'No root organization unit found for course: '.$course->name
			);
		}
		else // otherwise
		{
			// Employee root organization unit
			$employeeOURoot = getData($employeeOURootResult)[0]->oe_kurzbz;

			// Course root organization unit
			$courseOURoot = getData($courseOURootResult)[0]->oe_kurzbz;

			// If the employee belongs to an organization other than that of the project
			if ($employeeOURoot != $courseOURoot)
			{
				// Place the purchase order
				$purchaseOrder = $this->_ci->ManagePurchaseOrderInModel->purchaseOrderMaintainBundle(
					array(
						// Data
					)
				);

				// If error occurred then return the error
				if (isError($purchaseOrder)) return $purchaseOrder;
			}
		}

		return success('Purchase order successfully placed');
	}

	/**
	 *
	 */
	private function _syncAdminProject(
		$studySemester,
		$projectStructures,
		$projectIdFormats,
		$projectNameFormats,
		$projectUnitResponsibles,
		$projectPersonResponsibles,
		$projectTypes,
		$studySemesterStartDate,
		$studySemesterEndDate,
		$studySemesterStartDateTS,
		$studySemesterEndDateTS
	)
	{
		$projectId = strtoupper(sprintf($projectIdFormats[self::ADMIN_PROJECT], $studySemester)); // project id
		$type = $projectTypes[self::ADMIN_PROJECT]; // Project type
		$unitResponsible = $projectUnitResponsibles[self::ADMIN_PROJECT]; // project unit responsible
		$personResponsible = $projectPersonResponsibles[self::ADMIN_PROJECT]; // project person responsible

		// Create the project on ByD
		$createProjectResult = $this->_ci->ProjectsModel->create(
			$projectId,
			$type,
			$unitResponsible,
			$personResponsible,
			$studySemesterStartDateTS,
			$studySemesterEndDateTS
		);

		// If an error occurred while creating the project on ByD, and the error is not project already exists, then return the error
		if (isError($createProjectResult) && getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR) return $createProjectResult;

		$projectObjectId = null;

		// If the projects is not alredy present it is _not_ needed to sync the database
		if (getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR)
		{
			// Add entry database into sync table for projects
			$insertResult = $this->_ci->SAPProjectsModel->insert(
				array(
					'project_id' => $projectId,
					'project_object_id' => getData($createProjectResult)->ObjectID,
					'studiensemester_kurzbz' => $studySemester
				)
			);

			// If error occurred during insert return database error
			if (isError($insertResult)) return $insertResult;

			$projectObjectId = getData($createProjectResult)->ObjectID; // store the project object id
		}
		else // otherwise retrieves the project object id from database
		{
			$projectResult = $this->_ci->SAPProjectsModel->loadWhere(array('project_id' => $projectId, 'studiensemester_kurzbz' => $studySemester));

			// If an error occurred while getting project info from database return the error itself
			if (isError($projectResult)) return $projectResult;
			// If no data found with these parameters
			if (!hasData($projectResult)) return error($projectId.' project is present in SAP but _not_ in sync.tbl_sap_projects');

			$projectObjectId = getData($projectResult)[0]->project_object_id; // store the project object id
		}

		// If was not possible to get a valid project object id
		if (isEmptyString($projectObjectId)) return error('Was not possible to get the project object id for project: '.$projectId);

		// Update project ProjectTaskCollection name
		$projectName = sprintf($projectNameFormats[self::ADMIN_PROJECT], $studySemester);
		$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
			$projectObjectId,
			$projectName
		);

		// If an error occurred while creating the project on ByD return the error
		if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

		// If the projects does not exist then update the time recording attriute
		if (getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR)
		{
			// Update the time recording attribute of the project
			$setTimeRecordingOffResult = $this->_ci->ProjectsModel->setTimeRecordingOff($projectObjectId);

			// If an error occurred while setting the project time recording
			if (isError($setTimeRecordingOffResult)) return $setTimeRecordingOffResult;
		}

		// Set the project as active
		$setActiveResult = $this->_ci->ProjectsModel->setActive($projectObjectId);

		// If an error occurred while setting the project as active on ByD
		// and not because the project was alredy released then return the error
		if (isError($setActiveResult)
			&& getCode($setActiveResult) != self::RELEASE_PROJECT_ERROR)
		{
			if (getCode($setActiveResult) != self::RELEASE_PROJECT_ERROR)
			{
				return $setActiveResult;
			}
			else // otherwise log it
			{
				$this->_ci->loglib->logWarningDB(getError($setActiveResult));
			}
		}

		// If structure is present
		if (isset($projectStructures[self::ADMIN_PROJECT]))
		{
			$dbModel = new DB_Model();

			// Loads all the active cost centers
			$costCentersResult = $dbModel->execReadOnlyQuery('
				SELECT so.oe_kurzbz,
					so.oe_kurzbz_sap
				  FROM public.tbl_mitarbeiter m
				  JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
				  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
				  JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz)
				 WHERE bf.funktion_kurzbz = \'oezuordnung\'
				   AND b.aktiv = TRUE
				   AND m.personalnummer > 0
				   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
				   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
				   AND so.oe_kurzbz_sap NOT LIKE \'2%\'
				   AND so.oe_kurzbz_sap NOT IN (\'100000\', \'LPC\', \'LEHRGANG\')
				   AND so.oe_kurzbz NOT IN (
					WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz) as
					(
						SELECT oe_kurzbz, oe_parent_kurzbz
						  FROM public.tbl_organisationseinheit
						 WHERE oe_kurzbz = \'gmbh\'
					     UNION ALL
						SELECT o.oe_kurzbz, o.oe_parent_kurzbz
						  FROM public.tbl_organisationseinheit o, oes
						 WHERE o.oe_parent_kurzbz = oes.oe_kurzbz
					)
					SELECT oe_kurzbz
					  FROM oes
				      GROUP BY oe_kurzbz
				   )
			      GROUP BY so.oe_kurzbz, so.oe_kurzbz_sap
			', array($studySemesterEndDate, $studySemesterStartDate));

			// If error occurred while retrieving const centers from database then return the error
			if (isError($costCentersResult)) return $costCentersResult;

			// For each cost center
			foreach (getData($costCentersResult) as $costCenter)
			{
				$countStructures = 0;

				// For each project task in the structure
				foreach ($projectStructures[self::ADMIN_PROJECT] as $taskFormatName)
				{
					$countStructures++; // count the number of structures for each task type

					// Check if this cost center is already present in SAP looking in the sync table
					$syncCostCenterResult = $this->_ci->SAPProjectsCostcentersModel->loadWhere(
						array(
							'project_task_id' => $projectId.'-'.$countStructures,
							'studiensemester_kurzbz' => $studySemester,
							'oe_kurzbz_sap' => $costCenter->oe_kurzbz_sap
						)
					);

					// If error occurred then return it
					if (isError($syncCostCenterResult)) return $syncCostCenterResult;

					$taskObjectId = null;

					// If is _not_ present then create it
					if (!hasData($syncCostCenterResult))
					{
						// Create a task for this project
						$createTaskResult = $this->_ci->ProjectsModel->createTask(
							$projectObjectId,
							substr(sprintf($taskFormatName, $costCenter->oe_kurzbz),0,40),
							$costCenter->oe_kurzbz_sap
						);

						// If an error occurred while creating the project task on ByD return the error
						if (isError($createTaskResult)) return $createTaskResult;

						// Add entry database into sync table for projects
						$insertResult = $this->_ci->SAPProjectsCostcentersModel->insert(
							array(
								'project_id' => $projectId,
								'project_object_id' => $projectObjectId,
								'project_task_id' => getData($createTaskResult)->ID,
								'project_task_object_id' => getData($createTaskResult)->ObjectID,
								'studiensemester_kurzbz' => $studySemester,
								'oe_kurzbz_sap' => $costCenter->oe_kurzbz_sap
							)
						);

						// If error occurred while saving into database then return the error
						if (isError($insertResult)) return $insertResult;

						$taskObjectId = getData($createTaskResult)->ObjectID;
					}
					else // otherwise get the task object id from database
					{
						$taskObjectId = getData($syncCostCenterResult)[0]->project_task_object_id;
					}

					// If was _not_ possible to get a valid task object id
					if (isEmptyString($taskObjectId)) return error('Was _not_ possible to retrieve a valid task object id');

					// Loads employees for this cost center
					$costCenterEmployeesResult = $dbModel->execReadOnlyQuery('
						SELECT m.mitarbeiter_uid,
							b.person_id,
							(
								SELECT SUM(wochenstunden) * 15
								  FROM public.tbl_benutzerfunktion bfws
								 WHERE bfws.uid = m.mitarbeiter_uid
								   AND (bfws.datum_von IS NULL OR bfws.datum_von <= ?)
								   AND (bfws.datum_bis IS NULL OR bfws.datum_bis >= ?)
							) as planned_work
						  FROM public.tbl_mitarbeiter m
						  JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
						  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
						  JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz)
						 WHERE bf.funktion_kurzbz = \'oezuordnung\'
						   AND b.aktiv = TRUE
						   AND m.fixangestellt = TRUE
						   AND m.personalnummer > 0
						   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
						   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
						   AND so.oe_kurzbz_sap = ?
					', array(
						$studySemesterEndDate,
						$studySemesterStartDate,
						$studySemesterEndDate,
						$studySemesterStartDate,
						$costCenter->oe_kurzbz_sap
					));

					// If error occurred while retrieving const center employee from database then return the error
					if (isError($costCenterEmployeesResult)) return $costCenterEmployeesResult;

					// If employees are present for this cost center
					if (hasData($costCenterEmployeesResult))
					{
						// For each employee
						foreach (getData($costCenterEmployeesResult) as $costCenterEmployee)
						{
							// Add the employee to this project
							$addEmployeeResult = $this->_addEmployeeToProject(
								$costCenterEmployee,
								$projectObjectId,
								$studySemesterStartDateTS,
								$studySemesterEndDateTS
							);

							// If an error occurred then return it
							if (isError($addEmployeeResult)) return $addEmployeeResult;
						}
					}
				}
			}
		}
		else
		{
			return error('No admin structure defined in config file');
		}

		return success('Project admin synchronization ended successfully');
	}

	/**
	 *
	 */
	private function _syncCustomProject(
		$studySemester,
		$studySemesterStartDateTS,
		$studySemesterEndDateTS
	)
	{
		// Project person responsible
		$personResponsible = $this->_ci->config->item(self::PROJECT_PERSON_RESPONSIBLE_CUSTOM);
		// Project type
		$type = $this->_ci->config->item(self::PROJECT_TYPE_CUSTOM);

		$dbModel = new DB_Model();

		// Loads all the custom projects
		$customResult = $dbModel->execReadOnlyQuery('
			SELECT UPPER(s0.typ || s0.kurzbz) AS project_id,
				UPPER(s0.typ || s0.kurzbz) AS name,
				(
					SELECT so.oe_kurzbz_sap
					  FROM sync.tbl_sap_organisationsstruktur so
					 WHERE so.oe_kurzbz = \'tlc\'
				) AS unit_responsible,
				s0.studiengang_kz
			  FROM public.tbl_studiengang s0
			 WHERE s0.studiengang_kz IN(10002, 10026, 10005, 10025)
			 UNION
			SELECT UPPER(s1.typ || s1.kurzbz) AS project_id,
				UPPER(s1.typ || s1.kurzbz) AS name,
				(
					SELECT so.oe_kurzbz_sap
					  FROM sync.tbl_sap_organisationsstruktur so
					 WHERE so.oe_kurzbz = \'Auslandsbuero\'
				) AS unit_responsible,
				s1.studiengang_kz
			  FROM public.tbl_studiengang s1
			 WHERE s1.studiengang_kz IN(10006)
		');

		// If error occurred while retrieving custom projects from database then return the error
		if (isError($customResult)) return $customResult;
		if (!hasData($customResult)) return success('No custom projects found in database');

		// For each custom project found in database
		foreach (getData($customResult) as $customProject)
		{
			// Project id
			$projectId = strtoupper(
				sprintf(
					$this->_ci->config->item(self::PROJECT_CUSTOM_ID_FORMAT),
					str_replace(' ', '-', $customProject->project_id), // replace blanks with scores
					$studySemester
				)
			);

			// Create the project on ByD
			$createProjectResult = $this->_ci->ProjectsModel->create(
				$projectId,
				$type,
				$customProject->unit_responsible,
				$personResponsible,
				$studySemesterStartDateTS,
				$studySemesterEndDateTS
			);

			// If an error occurred while creating the project on ByD, and the error is not project already exists, then return the error
			if (isError($createProjectResult) && getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR) return $createProjectResult;

			$projectObjectId = null;

			// If the projects is not alredy present it is _not_ needed to sync the database
			if (getCode($createProjectResult) != self::PROJECT_EXISTS_ERROR)
			{
				// Add entry database into sync table for projects
				$insertResult = $this->_ci->SAPProjectsCoursesModel->insert(
					array(
						'project_id' => $projectId,
						'project_object_id' => getData($createProjectResult)->ObjectID,
						'studiensemester_kurzbz' => $studySemester,
						'studiengang_kz' => $customProject->studiengang_kz
					)
				);

				// If error occurred during insert return database error
				if (isError($insertResult)) return $insertResult;

				$projectObjectId = getData($createProjectResult)->ObjectID;
			}
			else
			{
				$projectResult = $this->_ci->SAPProjectsCoursesModel->loadWhere(
					array(
						'project_id' => $projectId,
						'studiensemester_kurzbz' => $studySemester,
						'studiengang_kz' => $customProject->studiengang_kz
					)
				);

				// If an error occurred while getting project info from database return the error itself
				if (isError($projectResult)) return $projectResult;
				// If no data found with these parameters
				if (!hasData($projectResult)) return error($projectId.' project is present in SAP but _not_ in sync.tbl_sap_projects_courses');

				$projectObjectId = getData($projectResult)[0]->project_object_id; // store the project object id
			}

			// If was not possible to find a valid project object id
			if (isEmptyString($projectObjectId)) return error('Was _not_ possible to find a valid lehrgaenge project object id');

			// Update project ProjectTaskCollection name
			$projectName = sprintf('%s %s', $customProject->name, $studySemester);
			$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
				$projectObjectId,
				$projectName
			);

			// If an error occurred while creating the project on ByD return the error
			if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

			// Set the project as active
			$setActiveResult = $this->_ci->ProjectsModel->setActive($projectObjectId);

			// If an error occurred while setting the project as active on ByD
			// and not because the project was alredy released then return the error
			if (isError($setActiveResult)
				&& getCode($setActiveResult) != self::RELEASE_PROJECT_ERROR)
			{
				return $setActiveResult;
			}

			// Loads employees for this custom project
			$customEmployeesResult = $dbModel->execReadOnlyQuery('
				SELECT lm.mitarbeiter_uid,
					b.person_id,
					(SUM(lm.semesterstunden) * 1.5) AS planned_work,
					(SUM(lm.semesterstunden) * 1.5) AS commited_work,
					\'0\' AS ma_soll_stunden,
					\'0\' AS lehre_grobplanung
				  FROM lehre.tbl_lehreinheitmitarbeiter lm
				  JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id)
				  JOIN lehre.tbl_lehrveranstaltung lv USING(lehrveranstaltung_id)
				  JOIN public.tbl_studiengang s USING(studiengang_kz)
				  JOIN public.tbl_benutzer b ON(b.uid = lm.mitarbeiter_uid)
			  	  JOIN public.tbl_mitarbeiter m USING(mitarbeiter_uid)
				 WHERE l.studiensemester_kurzbz = ?
				   AND s.studiengang_kz = ?
			   	   AND m.fixangestellt = TRUE
				   AND m.personalnummer > 0
			      GROUP BY lm.mitarbeiter_uid, b.person_id
			      ORDER BY lm.mitarbeiter_uid
			', array($studySemester, $customProject->studiengang_kz));

			// If error occurred while retrieving csutom project employee from database then return the error
			if (isError($customEmployeesResult)) return $customEmployeesResult;

			// If employees are present for this custom project
			if (hasData($customEmployeesResult))
			{
				// For each employee
				foreach (getData($customEmployeesResult) as $customEmployee)
				{
					// Add the employee to this project
					$addEmployeeResult = $this->_addEmployeeToProject(
						$customEmployee,
						$projectObjectId,
						$studySemesterStartDateTS,
						$studySemesterEndDateTS
					);

					// If an error occurred then return it
					if (isError($addEmployeeResult)) return $addEmployeeResult;
				}
			}
		}

		return success('Custom projects synchronization ended successfully');
	}

	/**
	 * Add the given employee to the given project
	 */
	private function _addEmployeeToProject($employee, $projectObjectId, $studySemesterStartDateTS, $studySemesterEndDateTS)
	{
		// Get the service id for this employee
		$serviceResult = $this->_ci->SAPServicesModel->loadWhere(array('person_id' => $employee->person_id));

		// If an error occurred then return it
		if (isError($serviceResult)) return $serviceResult;

		// If the service is present for this employee
		if (hasData($serviceResult))
		{
			// SAP service id for the employee
			$sapServiceId = getData($serviceResult)[0]->sap_service_id;

			// Load the SAP eeid from the sync table
			$sapEeidResult = $this->_ci->SAPMitarbeiterModel->loadWhere(
				array('mitarbeiter_uid' => $employee->mitarbeiter_uid
			));

			// If an error occurred return it
			if (isError($sapEeidResult)) return $sapEeidResult;

			// If an employee was found in the sync table
			if (hasData($sapEeidResult))
			{
				// SAP employee id
				$sapEeid = getData($sapEeidResult)[0]->sap_eeid;

				// Add employee to project team
				$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
					$projectObjectId,
					$sapEeid,
					$studySemesterStartDateTS,
					$studySemesterEndDateTS,
					isset($employee->commited_work) ? $employee->commited_work : null
				);

				// If an error occurred and:
				if (isError($addEmployeeResult))
				{
					// - Not because of an already existing employee in this project
					// - Not because of not existing employee
					if (getCode($addEmployeeResult) != self::PARTECIPANT_PROJ_EXISTS_ERROR
						&& getCode($addEmployeeResult) != self::PROJECT_EMPLOYEE_NOT_EXISTS)
					{
						return $addEmployeeResult; // return the error
					}
					else // if non blocking error then log it
					{
						$this->_ci->loglib->logWarningDB(getError($addEmployeeResult));
					}
				}

				// Add employee to project work
				$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
					$projectObjectId,
					$sapEeid,
					$sapServiceId,
					isset($employee->planned_work) ? $employee->planned_work : null,
					isset($employee->lehre_grobplanung) ? $employee->lehre_grobplanung : null,
					isset($employee->ma_soll_stunden) ? $employee->ma_soll_stunden : null
				);

				// If an error occurred and:
				if (isError($addEmployeeToTaskResult))
				{
					// - Not because of an already existing employee in this project
					// - Not because of not existing employee
					if (getCode($addEmployeeToTaskResult) != self::PARTECIPANT_PROJ_EXISTS_ERROR
						&& getCode($addEmployeeToTaskResult) != self::PROJECT_EMPLOYEE_NOT_EXISTS
						&& getCode($addEmployeeToTaskResult) != self::PARTECIPANT_TASK_EXISTS_ERROR)
					{
						return $addEmployeeToTaskResult; // return the error
					}
					else // if non blocking error then log it
					{
						$this->_ci->loglib->logWarningDB(getError($addEmployeeToTaskResult));
					}
				}
			}
		}

		// If here then everything is fine
		return success('Employee successfully added to this project');
	}
}

