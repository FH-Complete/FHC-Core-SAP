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
	const PROJECT_MAX_NUMBER_COST_CENTERS = 'project_max_number_cost_centers';

	// Project types
	const ADMIN_PROJECT = 'admin';
	const LEHRE_PROJECT = 'lehre';
	const LEHRGAENGE_PROJECT = 'lehrgaenge';

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads model ProjectsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Projects_model', 'ProjectsModel');
		// Loads model EmployeeModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Employee_model', 'EmployeeModel');

		// Loads the StudiensemesterModel
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		// Loads model SAPProjectsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjects_model', 'SAPProjectsModel');
		// Loads model SAPProjectsCostcentersModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjectsCostcenters_model', 'SAPProjectsCostcentersModel');
		// Loads model SAPProjectsCoursesModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPProjectsCourses_model', 'SAPProjectsCoursesModel');

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
		// Get the last or current studysemester
		$lastOrCurrentStudySemesterResult = $this->_ci->StudiensemesterModel->getLastOrAktSemester();

		// If an error occurred while getting the study semester return it
		if (isError($lastOrCurrentStudySemesterResult)) return $lastOrCurrentStudySemesterResult;

		// If a study semester was found
		if (hasData($lastOrCurrentStudySemesterResult))
		{
			// Last or current study semester
			$lastOrCurrentStudySemester = getData($lastOrCurrentStudySemesterResult)[0]->studiensemester_kurzbz;

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

			// Get study semester start date
			$studySemesterStartDate = getData($lastOrCurrentStudySemesterResult)[0]->start;
			// Get study semester start date in timestamp format
			$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', getData($lastOrCurrentStudySemesterResult)[0]->start.' 00:00:00');
			$studySemesterStartDateTS = $dateTime->getTimestamp(); // project start date

			// Get study semester end date
			$studySemesterEndDate = getData($lastOrCurrentStudySemesterResult)[0]->ende;
			// Get study semester end date in timestamp format
			$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', getData($lastOrCurrentStudySemesterResult)[0]->ende.' 00:00:00');
			$studySemesterEndDateTS = $dateTime->getTimestamp(); // project end date

			// Create admin project
			$createResult = $this->_createAdminProject(
				$lastOrCurrentStudySemester,
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

			// Create lehre project
			$createResult = $this->_createLehreProject();
			if (isError($createResult)) return $createResult;

			// Create lehrgaenge projects
			$createResult = $this->_createLehrgaengeProject();
			if (isError($createResult)) return $createResult;
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

	// --------------------------------------------------------------------------------------------
	// Private methods
	
	/**
	 *
	 */
	private function _createLehreProject() {}
		
	/**
	 *
	 */
	private function _createLehrgaengeProject() {}

	/**
	 *
	 */
	private function _createAdminProject(
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
		// Loads all the active cost centers
		$dbModel = new DB_Model();

		$costCentersResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT so.oe_kurzbz_sap
			  FROM public.tbl_mitarbeiter m
			  JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
			  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
			  JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz) 
			 WHERE bf.funktion_kurzbz = \'oezuordnung\'
			   AND b.aktiv
			   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
			   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
		', array($studySemesterEndDate, $studySemesterStartDate));

		// If error occurred while retrieving const centers from database the return the error
		if (isError($costCentersResult)) return $costCentersResult;

		// Const centers
		$costCenters = getData($costCentersResult);

		$projectId = sprintf($projectIdFormats[self::ADMIN_PROJECT], $studySemester); // project id
		$type = $projectTypes[self::ADMIN_PROJECT]; // Project type
		$unitResponsible = $projectUnitResponsibles[self::ADMIN_PROJECT]; // project unit responsible
		$personResponsible = $projectPersonResponsibles[self::ADMIN_PROJECT]; // project person responsible

		// Create the project on ByD
		$createProjectResult = $this->_ci->ProjectsModel->create(
			$projectId,
			$type,
			$unitResponsible,
			$personResponsible,
			$studySemesterStartDateTS.'000',
			$studySemesterEndDateTS.'000'
		);

		// If an error occurred while creating the project on ByD return the error
		if (isError($createProjectResult)) return $createProjectResult;

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

		// Update project ProjectTaskCollection name
		$projectName = sprintf($projectNameFormats[self::ADMIN_PROJECT], $studySemester);
		$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
			getData($createProjectResult)->ObjectID,
			$projectName
		);

		// If an error occurred while creating the project on ByD return the error
		if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

		// If structure is present
		if (isset($projectStructures[self::ADMIN_PROJECT]))
		{
			$countCostCenters = 1;

			// For each cost center
			foreach ($costCenters as $costCenter)
			{
				// For each project task in the structure
				foreach ($projectStructures[self::ADMIN_PROJECT] as $taskFormatName)
				{
					// Create a task for this project
					$createTaskResult = $this->_ci->ProjectsModel->createTask(
						getData($createProjectResult)->ObjectID,
						sprintf($taskFormatName, $costCenter->oe_kurzbz_sap)
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
							'studiensemester_kurzbz' => $studySemester,
							'oe_kurzbz_sap' => $costCenter->oe_kurzbz_sap
						)
					);

					// If error occurred during insert return database error
					if (isError($insertResult)) return $insertResult;

					// Loads employees for this cost center
					$costCenterEmployeesResult = $dbModel->execReadOnlyQuery('
						SELECT m.mitarbeiter_uid
						  FROM public.tbl_mitarbeiter m
						  JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
						  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
						  JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz) 
						 WHERE bf.funktion_kurzbz = \'oezuordnung\'
						   AND b.aktiv
						   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
						   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
						   AND bf.oe_kurzbz = ?
					', array($studySemesterEndDate, $studySemesterStartDate, $costCenter->oe_kurzbz_sap));

					// If error occurred while retrieving const center employee from database the return the error
					if (isError($costCenterEmployeesResult)) return $costCenterEmployeesResult;

					// Const centers
					$costCenterEmployees = getData($costCenterEmployeesResult);



					// Add employees to this task
					$createTaskResult = $this->_ci->EmployeeModel->createTask();
				}

				// If the number of the created cost centers is the same of the config entry PROJECT_MAX_NUMBER_COST_CENTERS
				// break this loop. Useful for debugging
				if ($countCostCenters == $this->_ci->config->item(self::PROJECT_MAX_NUMBER_COST_CENTERS))
				{
					break;
				}

				$countCostCenters++; // count the number of cost centers added to this project
			}
		}
	}
}

