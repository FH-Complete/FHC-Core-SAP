<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncProjectsLib
{
	// Jobs types used by this lib
	const SAP_PROJECTS_CREATE = 'SAPProjectsCreate';
	const SAP_PROJECTS_UPDATE = 'SAPProjectsUpdate';

	// Indexes used to access to the configuration array
	const PROJECT_ID_FORMATS = 'project_id_formats';
	const PROJECT_NAME_FORMATS = 'project_name_formats';
	const PROJECT_STRUCTURES = 'project_structures';
	const PORJECT_UNIT_RESPONSIBLES = 'project_unit_responsibles';
	const PROJECT_PERSON_RESPONSIBLES = 'project_person_responsibles';
	const PROJECT_TYPES = 'project_types';

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads model ProjectsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Projects_model', 'ProjectsModel');

		// Loads Projects configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Projects');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Create new projects for the current study semester
	 */
	public function create()
	{
		// Loads the StudiensemesterModel
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		$lastOrCurrentStudySemesterResult = $this->_ci->StudiensemesterModel->getLastOrAktSemester();
		// If an error occurred while getting the study semester return it
		if (isError($lastOrCurrentStudySemesterResult)) return $lastOrCurrentStudySemesterResult;

		// If a study semester was found
		if (hasData($lastOrCurrentStudySemesterResult))
		{
			// Project structures are optionals and are used to create tasks for a project
			$projectStructures = $this->_ci->config->item(self::PROJECT_STRUCTURES);

			// Project ID format
			$projectIdFormats = $this->_ci->config->item(self::PROJECT_ID_FORMATS);
			// Project names format
			$projectNameFormats = $this->_ci->config->item(self::PROJECT_NAME_FORMATS);
			// Project unit responsibles
			$projectUnitResponsibles = $this->_ci->config->item(self::PORJECT_UNIT_RESPONSIBLES);
			// Project person responsibles
			$projectPersonResponsibles = $this->_ci->config->item(self::PROJECT_PERSON_RESPONSIBLES);
			// Project types
			$projectTypes = $this->_ci->config->item(self::PROJECT_TYPES);

			// Last or current study semester
			$lastOrCurrentStudySemester = getData($lastOrCurrentStudySemesterResult)[0]->studiensemester_kurzbz;

			// Loads model SAPProjectsModel
			$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjects_model', 'SAPProjectsModel');
			// Loads model SAPProjectsCostcentersModel
			$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjectsCostcenters_model', 'SAPProjectsCostcentersModel');
			// Loads KostenstelleModel
			$this->_ci->load->model('accounting/Kostenstelle_model', 'KostenstelleModel');

			// Loads all the active cost centers
			$costCentersResult = $this->_ci->KostenstelleModel->loadWhere(array('aktiv' => true));

			// If error occurred while retrieving const centers from database the return the error
			if (isError($costCentersResult)) return $costCentersResult;

			// Const centers
			$costCenters = getData($costCentersResult);

			// For each configured element
			foreach ($projectIdFormats as $key => $projectIdFormat)
			{
				$projectId = sprintf($projectIdFormat, $lastOrCurrentStudySemester); // project id
				$type = $projectTypes[$key]; // Project type
				$unitResponsible = $projectUnitResponsibles[$key]; // project unit responsible
				$personResponsible = $projectPersonResponsibles[$key]; // project person responsible

				$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', getData($lastOrCurrentStudySemesterResult)[0]->start.' 00:00:00');
				$studySemesterStartDate = $dateTime->getTimestamp(); // project start date

				$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', getData($lastOrCurrentStudySemesterResult)[0]->ende.' 00:00:00');
				$studySemesterEndDate = $dateTime->getTimestamp(); // project end date

				// Create the project on ByD
				$createProjectResult = $this->_ci->ProjectsModel->create(
					$projectId,
					$type,
					$unitResponsible,
					$personResponsible,
					$studySemesterStartDate.'000',
					$studySemesterEndDate.'000'
				);

				// If an error occurred while creating the project on ByD return the error
				if (isError($createProjectResult)) return $createProjectResult;

				// Update project ProjectTaskCollection name
				$projectName = sprintf($projectNameFormats[$key], $lastOrCurrentStudySemester);
				$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
					getData($createProjectResult)->ObjectID,
					$projectName
				);

				// If an error occurred while creating the project on ByD return the error
				if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

				// Add employee to the project
				$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
					getData($createProjectResult)->ObjectID,
					'16',
					'20000100',
					$studySemesterStartDate.'000',
					$studySemesterEndDate.'000'
				);

				// If an error occurred while adding the employee to a project on ByD return the error
				if (isError($addEmployeeResult)) return $addEmployeeResult;

				// If structure is present for this category of project then write tasks
				if (isset($projectStructures[$key]))
				{
					// For each cost center
					foreach ($costCenters as $costCenter)
					{
						// For each project task in the structure
						foreach ($projectStructures[$key] as $taskFormatName)
						{
							// Create a task for this project
							$createTaskResult = $this->_ci->ProjectsModel->createTask(
								getData($createProjectResult)->ObjectID,
								sprintf($taskFormatName, $costCenter->kurzbz)
							);

							// If an error occurred while creating the project task on ByD return the error
							if (isError($createTaskResult)) return $createTaskResult;

							// Add entry database into sync table for projects
							$insertResult = $this->_ci->SAPProjectsCostcentersModel->insert(
								array(
									'project_id' => $projectId,
									'project_object_id' => getData($createProjectResult)->ObjectID,
									'project_task_id' => getData($createTaskResult)->ID,
									'project_task_object_id' => getData($createTaskResult)->ObjectID,
									'studiensemester_kurzbz' => $lastOrCurrentStudySemester,
									'kostenstelle_id' => $costCenter->kostenstelle_id
								)
							);

							// If error occurred during insert return database error
							if (isError($insertResult)) return $insertResult;
						}
					}
				}

				// Add entry database into sync table for projects
				$insertResult = $this->_ci->SAPProjectsModel->insert(
					array(
						'project_id' => $projectId,
						'project_object_id' => getData($createProjectResult)->ObjectID,
						'studiensemester_kurzbz' => $lastOrCurrentStudySemester
					)
				);

				// If error occurred during insert return database error
				if (isError($insertResult)) return $insertResult;
			}
		}
		else
		{
			return success('No study semesters configured in data base');
		}
	}

	/**
	 * Updates projects fot the current study semester
	 */
	public function update()
	{
		return $this->_ci->ProjectsModel->update();
	}

	/**
	 * Return the raw result of projekt/ProjectCollection
	 */
	public function getProjects()
	{
		return $this->_ci->ProjectsModel->getProjects();
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
}

