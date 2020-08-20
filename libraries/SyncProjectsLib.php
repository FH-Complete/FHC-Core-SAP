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

	// Project types
	const ADMIN_PROJECT = 'admin';
	const LEHRE_PROJECT = 'lehre';
	const LEHRGAENGE_PROJECT = 'lehrgaenge';

	// SAP ByD logic errors
	const PROJECT_EXISTS_ERROR = 'PRO_CMN_SHRD:003';
	const PARTECIPANT_PROJ_EXISTS_ERROR = 'PRO_CMN_PROJ:010';
	const PARTECIPANT_TASK_EXISTS_ERROR = 'PRO_CMN_ESRV:010';
	const RELEASE_PROJECT_ERROR = 'CM_DS_APPL_ERROR:000';

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

		// Loads model SAPServicesModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPServices_model', 'SAPServicesModel');
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
	public function sync($studySemester = null)
	{
		$lastOrCurrentStudySemesterResult = null;

		// If a study semester was given as parameter
		if (!isEmptyString($studySemester))
		{
			// Get info about the provided study semester
			$lastOrCurrentStudySemesterResult = $this->_ci->StudiensemesterModel->loadWhere(
				array(
					'studiensemester_kurzbz' => $studySemester
				)
			);
		}
		else // otherwise get the last or current one
		{
			// Get the last or current studysemester
			$lastOrCurrentStudySemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();
		}

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
			$projectUnitResponsibles = $this->_ci->config->item(self::PROJECT_UNIT_RESPONSIBLES);
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
			$createResult = $this->_syncAdminProject(
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
			$createResult = $this->_syncLehreProject(
				$lastOrCurrentStudySemester,
				$projectIdFormats,
				$projectNameFormats,
				$projectUnitResponsibles,
				$projectPersonResponsibles,
				$projectTypes,
				$studySemesterStartDateTS,
				$studySemesterEndDateTS
			);
			if (isError($createResult)) return $createResult;

			// Create lehrgaenge projects
			$createResult = $this->_syncLehrgaengeProject(
				$lastOrCurrentStudySemester,
				$projectIdFormats,
				$projectNameFormats,
				$projectUnitResponsibles,
				$projectPersonResponsibles,
				$projectTypes,
				$studySemesterStartDateTS,
				$studySemesterEndDateTS
			);
			if (isError($createResult)) return $createResult;

			// Create custom projects
			$createResult = $this->_syncCustomProject(
				$lastOrCurrentStudySemester,
				$studySemesterStartDateTS,
				$studySemesterEndDateTS
			);
			if (isError($createResult)) return $createResult;

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
	 * Get all projects and their tasks from SAP Business by Design and store their ids in FHC database
	 */
	public function import()
	{
		// Get all projects and related tasks
		$projectsResult = $this->_ci->ProjectsModel->getProjectsAndTasks();

		// If an error occurred then return the error
		if (isError($projectsResult)) return $projectsResult;

		if (hasData($projectsResult))
		{
			foreach (getData($projectsResult) as $project)
			{
				var_dump($project);exit;
			}
		}
		else
		{
			return success('No projects are present on SAP ByD');
		}

		return success('All project have been imported successfully');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

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
		      GROUP BY lm.mitarbeiter_uid, b.person_id
		', array($studySemester));

		// If error occurred while retrieving teachers from database the return the error
		if (isError($lehreEmployeesResult)) return $lehreEmployeesResult;

		// If teachers are present
		if (hasData($lehreEmployeesResult))
		{
			// For each employee
			foreach (getData($lehreEmployeesResult) as $lehreEmployee)
			{
				// Get the service id for this employee
				$serviceResult = $this->_ci->SAPServicesModel->loadWhere(array('person_id' => $lehreEmployee->person_id));

				// If error occurred return it
				if (isError($serviceResult)) return $serviceResult;

				// If the service is present for this employee
				if (hasData($serviceResult))
				{
					// Get the employee from SAP
					$sapEmployeeResult = $this->_ci->EmployeeModel->getEmployeesByUIDs(
						array(
							strtoupper($lehreEmployee->mitarbeiter_uid)
						)
					);

					// If an error occurred and this error then return the error
					if (isError($sapEmployeeResult)) return $sapEmployeeResult;

					// If an employee was found in SAP
					if (hasData($sapEmployeeResult))
					{
						// Add employee to project team and staffing
						$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
							$projectObjectId,
							getData($sapEmployeeResult)[0]->C_EeId,
							$studySemesterStartDateTS,
							$studySemesterEndDateTS,
							$lehreEmployee->commited_work
						);

						// If an error occurred and it is not because of an already existing employee in this project
						if (isError($addEmployeeResult)
							&& getCode($addEmployeeResult) != self::PARTECIPANT_PROJ_EXISTS_ERROR)
						{
							return $addEmployeeResult;
						}

						// Add employee to project work
						$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
							$projectObjectId,
							getData($sapEmployeeResult)[0]->C_EeId,
							getData($serviceResult)[0]->sap_service_id,
							$lehreEmployee->planned_work,
							$lehreEmployee->lehre_grobplanung,
							$lehreEmployee->ma_soll_stunden
						);

						// If an error occurred and it is not because of an already existing employee in this project
						if (isError($addEmployeeToTaskResult)
							&& getCode($addEmployeeToTaskResult) != self::PARTECIPANT_TASK_EXISTS_ERROR)
						{
							return $addEmployeeToTaskResult;
						}
					}
				}
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
		$studySemesterEndDateTS
	)
	{
		$type = $projectTypes[self::LEHRGAENGE_PROJECT]; // Project type
		$unitResponsible = $projectUnitResponsibles[self::LEHRGAENGE_PROJECT]; // project unit responsible
		$personResponsible = $projectPersonResponsibles[self::LEHRGAENGE_PROJECT]; // project person responsible

		$dbModel = new DB_Model();

		// Loads all the courses
		$coursesResult = $dbModel->execReadOnlyQuery('
			SELECT s.studiengang_kz,
				UPPER(s.typ || s.kurzbz) AS name
			  FROM public.tbl_studiengang s
			 WHERE s.studiengang_kz < 0
		      ORDER BY name
		');

		// If error occurred while retrieving courses from database the return the error
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

			// Loads employees for this course
			$courseEmployeesResult = $dbModel->execReadOnlyQuery('
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
			      GROUP BY lm.mitarbeiter_uid, b.person_id
			      ORDER BY lm.mitarbeiter_uid
			', array($studySemester, $course->studiengang_kz));

			// If error occurred while retrieving course employee from database the return the error
			if (isError($courseEmployeesResult)) return $courseEmployeesResult;

			// If employees are present for this course
			if (hasData($courseEmployeesResult))
			{
				// For each employee
				foreach (getData($courseEmployeesResult) as $courseEmployee)
				{
					// Get the service id for this employee
					$serviceResult = $this->_ci->SAPServicesModel->loadWhere(array('person_id' => $courseEmployee->person_id));

					if (isError($serviceResult)) return $serviceResult;

					// If the service is present for this employee
					if (hasData($serviceResult))
					{
						// Get the employee from SAP
						$sapEmployeeResult = $this->_ci->EmployeeModel->getEmployeesByUIDs(
							array(
								strtoupper($courseEmployee->mitarbeiter_uid)
							)
						);

						// If an error occurred return it
						if (isError($sapEmployeeResult)) return $sapEmployeeResult;

						// If an employee was found in SAP
						if (hasData($sapEmployeeResult))
						{
							// Add employee to project team
							$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
								$projectObjectId,
								getData($sapEmployeeResult)[0]->C_EeId,
								$studySemesterStartDateTS,
								$studySemesterEndDateTS,
								$courseEmployee->commited_work
							);

							// If an error occurred and it is not because of an already existing employee in this project
							if (isError($addEmployeeResult)
								&& getCode($addEmployeeResult) != self::PARTECIPANT_PROJ_EXISTS_ERROR)
							{
								return $addEmployeeResult;
							}

							// Add employee to project work
							$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
								$projectObjectId,
								getData($sapEmployeeResult)[0]->C_EeId,
								getData($serviceResult)[0]->sap_service_id,
								$courseEmployee->planned_work
							);

							// If an error occurred and it is not because of an already existing employee in this project
							if (isError($addEmployeeToTaskResult)
								&& getCode($addEmployeeToTaskResult) != self::PARTECIPANT_TASK_EXISTS_ERROR)
							{
								return $addEmployeeToTaskResult;
							}
						}
					}
				}
			}
		}

		return success('Project lehrgaenge synchronization ended successfully');
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

		// Update the time recording attribute of the project
		$setTimeRecordingOffResult = $this->_ci->ProjectsModel->setTimeRecordingOff($projectObjectId);

		// If an error occurred while setting the project time recording as not allowed
		if (isError($setTimeRecordingOffResult)) return $setTimeRecordingOffResult;

		// Set the project as active
		$setActiveResult = $this->_ci->ProjectsModel->setActive($projectObjectId);

		// If an error occurred while setting the project as active on ByD
		// and not because the project was alredy released then return the error
		if (isError($setActiveResult)
			&& getCode($setActiveResult) != self::RELEASE_PROJECT_ERROR)
		{
			return $setActiveResult;
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
				   AND b.aktiv
				   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
				   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
				   AND so.oe_kurzbz_sap not like \'2%\'
				   AND so.oe_kurzbz_sap not in(\'100000\',\'LPC\',\'LEHRGANG\')
			      GROUP BY so.oe_kurzbz, so.oe_kurzbz_sap
			', array($studySemesterEndDate, $studySemesterStartDate));

			// If error occurred while retrieving const centers from database the return the error
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
						SELECT
							m.mitarbeiter_uid,
							b.person_id,
							(
								SELECT sum(wochenstunden) * 15
								FROM public.tbl_benutzerfunktion bfws
								WHERE
								bfws.uid=m.mitarbeiter_uid
								AND (bfws.datum_von IS NULL OR bfws.datum_von <= ?)
								AND (bfws.datum_bis IS NULL OR bfws.datum_bis >= ?)
							) as planned_work
						FROM public.tbl_mitarbeiter m
							JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
							JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
							JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz)
						WHERE bf.funktion_kurzbz = \'oezuordnung\'
							AND b.aktiv
							AND m.fixangestellt = TRUE
							AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
							AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
							AND so.oe_kurzbz_sap = ?
					', array($studySemesterEndDate, $studySemesterStartDate,$studySemesterEndDate, $studySemesterStartDate, $costCenter->oe_kurzbz_sap));

					// If error occurred while retrieving const center employee from database the return the error
					if (isError($costCenterEmployeesResult)) return $costCenterEmployeesResult;

					// If employees are present for this cost center
					if (hasData($costCenterEmployeesResult))
					{
						// For each employee
						foreach (getData($costCenterEmployeesResult) as $costCenterEmployee)
						{
							// Get the service id for this employee
							$serviceResult = $this->_ci->SAPServicesModel->loadWhere(array('person_id' => $costCenterEmployee->person_id));

							if (isError($serviceResult)) return $serviceResult;

							// If the service is present for this employee
							if (hasData($serviceResult))
							{
								// Get the employee from SAP
								$sapEmployeeResult = $this->_ci->EmployeeModel->getEmployeesByUIDs(
									array(
										strtoupper($costCenterEmployee->mitarbeiter_uid)
									)
								);

								// If an error occurred and this error is _not_ data not found then return the error
								if (isError($sapEmployeeResult)) return $sapEmployeeResult;

								// If an employee was found in SAP
								if (hasData($sapEmployeeResult))
								{
									// Add employee to project
									$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
										$projectObjectId,
										getData($sapEmployeeResult)[0]->C_EeId,
										$studySemesterStartDateTS,
										$studySemesterEndDateTS,
										$costCenterEmployee->planned_work
									);

									// If an error occurred and it is not because of an already existing employee in this project
									if (isError($addEmployeeResult)
										&& getCode($addEmployeeResult) != self::PARTECIPANT_PROJ_EXISTS_ERROR)
									{
										return $addEmployeeResult;
									}

									// Add employee to project task
									$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
										$taskObjectId,
										getData($sapEmployeeResult)[0]->C_EeId,
										getData($serviceResult)[0]->sap_service_id,
										$costCenterEmployee->planned_work
									);

									// If an error occurred and it is not because of an already existing employee in this project
									if (isError($addEmployeeToTaskResult)
										&& getCode($addEmployeeToTaskResult) != self::PARTECIPANT_TASK_EXISTS_ERROR)
									{
										return $addEmployeeToTaskResult;
									}
								}
							}
						}
					}
				}
			}
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

		// If error occurred while retrieving custom projects from database the return the error
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
			      GROUP BY lm.mitarbeiter_uid, b.person_id
			      ORDER BY lm.mitarbeiter_uid
			', array($studySemester, $customProject->studiengang_kz));

			// If error occurred while retrieving csutom project employee from database the return the error
			if (isError($customEmployeesResult)) return $customEmployeesResult;

			// If employees are present for this custom project
			if (hasData($customEmployeesResult))
			{
				// For each employee
				foreach (getData($customEmployeesResult) as $customEmployee)
				{
					// Get the service id for this employee
					$serviceResult = $this->_ci->SAPServicesModel->loadWhere(array('person_id' => $customEmployee->person_id));

					if (isError($serviceResult)) return $serviceResult;

					// If the service is present for this employee
					if (hasData($serviceResult))
					{
						// Get the employee from SAP
						$sapEmployeeResult = $this->_ci->EmployeeModel->getEmployeesByUIDs(
							array(
								strtoupper($customEmployee->mitarbeiter_uid)
							)
						);

						// If an error occurred return it
						if (isError($sapEmployeeResult)) return $sapEmployeeResult;

						// If an employee was found in SAP
						if (hasData($sapEmployeeResult))
						{
							// Add employee to project team
							$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
								$projectObjectId,
								getData($sapEmployeeResult)[0]->C_EeId,
								$studySemesterStartDateTS,
								$studySemesterEndDateTS,
								$customEmployee->commited_work
							);

							// If an error occurred and it is not because of an already existing employee in this project
							if (isError($addEmployeeResult)
								&& getCode($addEmployeeResult) != self::PARTECIPANT_PROJ_EXISTS_ERROR)
							{
								return $addEmployeeResult;
							}

							// Add employee to project work
							$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
								$projectObjectId,
								getData($sapEmployeeResult)[0]->C_EeId,
								getData($serviceResult)[0]->sap_service_id,
								$customEmployee->planned_work
							);

							// If an error occurred and it is not because of an already existing employee in this project
							if (isError($addEmployeeToTaskResult)
								&& getCode($addEmployeeToTaskResult) != self::PARTECIPANT_TASK_EXISTS_ERROR)
							{
								return $addEmployeeToTaskResult;
							}
						}
					}
				}
			}
		}

		return success('Custom projects synchronization ended successfully');
	}
}

