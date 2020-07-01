<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncProjectsLib
{
	// Jobs types used by this lib
	const SAP_PROJECTS_SYNC = 'SAPProjectsSync';

	// Indexes used to access to the configuration array
	const PROJECT_ID_FORMATS = 'project_id_formats';
	const PROJECT_NAME_FORMATS = 'project_name_formats';
	const PROJECT_STRUCTURES = 'project_structures';
	const PROJECT_UNIT_RESPONSIBLES = 'project_unit_responsibles';
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
	public function sync()
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

			// If here everything went fine
			return success('Was really hard, but we did it!');
		}
		else
		{
			return success('No study semesters configured in data base');
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
		$projectId = sprintf($projectIdFormats[self::LEHRE_PROJECT], $studySemester); // project id
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
		$projectName = sprintf($projectNameFormats[self::LEHRE_PROJECT], $studySemester);
		$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
			getData($createProjectResult)->ObjectID,
			$projectName
		);

		// If an error occurred while creating the project on ByD return the error
		if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

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
			 WHERE l.studiensemester_kurzbz = ?
			   AND s.typ IN (\'b\', \'m\')
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

					// If an error occurred and this error is _not_ data not found then return the error
					if (isError($sapEmployeeResult) && getCode($sapEmployeeResult) != ODATAClientLib::INVALID_WS) return $sapEmployeeResult;

					// If an employee was found in SAP
					if (hasData($sapEmployeeResult) && getCode($sapEmployeeResult) != ODATAClientLib::INVALID_WS)
					{
						// Add employee to project
						$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
							getData($createProjectResult)->ObjectID,
							getData($sapEmployeeResult)[0]->C_EeId,
							$studySemesterStartDateTS,
							$studySemesterEndDateTS,
							'38.5'
						);

						// If an error occurred and it is not because of an already existing employee in this project
						if (isError($addEmployeeResult) && getCode($addEmployeeResult) != ODATAClientLib::RS_ERROR) return $addEmployeeResult;

						// Add employee to project task
						$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
							getData($createProjectResult)->ObjectID,
							getData($sapEmployeeResult)[0]->C_EeId,
							getData($serviceResult)[0]->sap_service_id,
							'37',
							$lehreEmployee->lehre_grobplanung,
							$lehreEmployee->ma_soll_stunden
						);

						if (isError($addEmployeeToTaskResult)) return $addEmployeeToTaskResult;
					}
				}
			}
		}

		return success('Project lehre synchronization ended succesfully');
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
			$projectId = sprintf($projectIdFormats[self::LEHRGAENGE_PROJECT], $course->name, $studySemester); // project id

			// Create the project on ByD
			$createProjectResult = $this->_ci->ProjectsModel->create(
				$projectId,
				$type,
				$unitResponsible,
				$personResponsible,
				$studySemesterStartDateTS,
				$studySemesterEndDateTS
			);

			// If an error occurred while creating the project on ByD return the error
			if (isError($createProjectResult)) return $createProjectResult;

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

			// Update project ProjectTaskCollection name
			$projectName = sprintf($projectNameFormats[self::LEHRGAENGE_PROJECT], $course->name, $studySemester);
			$updateTaskCollectionResult = $this->_ci->ProjectsModel->updateTaskCollection(
				getData($createProjectResult)->ObjectID,
				$projectName
			);

			// If an error occurred while creating the project on ByD return the error
			if (isError($updateTaskCollectionResult)) return $updateTaskCollectionResult;

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
				 WHERE l.studiensemester_kurzbz = ?
				   AND s.studiengang_kz = ?
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

						// If an error occurred and this error is _not_ data not found then return the error
						if (isError($sapEmployeeResult) && getCode($sapEmployeeResult) != ODATAClientLib::INVALID_WS) return $sapEmployeeResult;

						// If an employee was found in SAP
						if (hasData($sapEmployeeResult) && getCode($sapEmployeeResult) != ODATAClientLib::INVALID_WS)
						{
							// Add employee to project team
							$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
								getData($createProjectResult)->ObjectID,
								getData($sapEmployeeResult)[0]->C_EeId,
								$studySemesterStartDateTS,
								$studySemesterEndDateTS,
								'38.5'
							);

							// If an error occurred and it is not because of an already existing employee in this project
							if (isError($addEmployeeResult) && getCode($addEmployeeResult) != ODATAClientLib::RS_ERROR) return $addEmployeeResult;

							// Add employee to project work
							$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
								getData($createProjectResult)->ObjectID,
								getData($sapEmployeeResult)[0]->C_EeId,
								getData($serviceResult)[0]->sap_service_id,
								'37'
							);

							if (isError($addEmployeeToTaskResult)) return $addEmployeeToTaskResult;
						}
					}
				}
			}
		}

		return success('Project lehrgaenge synchronization ended succesfully');
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
			$studySemesterStartDateTS,
			$studySemesterEndDateTS
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
			$dbModel = new DB_Model();

			// Loads all the active cost centers
			$costCentersResult = $dbModel->execReadOnlyQuery('
				SELECT so.oe_kurzbz, so.oe_kurzbz_sap
				  FROM public.tbl_mitarbeiter m
				  JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
				  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
				  JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz)
				 WHERE bf.funktion_kurzbz = \'oezuordnung\'
				   AND b.aktiv
				   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
				   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
			      GROUP BY so.oe_kurzbz, so.oe_kurzbz_sap
			', array($studySemesterEndDate, $studySemesterStartDate));

			// If error occurred while retrieving const centers from database the return the error
			if (isError($costCentersResult)) return $costCentersResult;

			$countCostCenters = 1;

			// For each cost center
			foreach (getData($costCentersResult) as $costCenter)
			{
				// For each project task in the structure
				foreach ($projectStructures[self::ADMIN_PROJECT] as $taskFormatName)
				{
					// Create a task for this project
					$createTaskResult = $this->_ci->ProjectsModel->createTask(
						getData($createProjectResult)->ObjectID,
						sprintf($taskFormatName, $costCenter->oe_kurzbz),
						$costCenter->oe_kurzbz_sap
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

					// Loads employees for this cost center
					$costCenterEmployeesResult = $dbModel->execReadOnlyQuery('
						SELECT m.mitarbeiter_uid,
						       b.person_id
						  FROM public.tbl_mitarbeiter m
						  JOIN public.tbl_benutzer b ON(b.uid = m.mitarbeiter_uid)
						  JOIN public.tbl_benutzerfunktion bf ON(bf.uid = m.mitarbeiter_uid)
						  JOIN sync.tbl_sap_organisationsstruktur so ON(bf.oe_kurzbz = so.oe_kurzbz)
						 WHERE bf.funktion_kurzbz = \'oezuordnung\'
						   AND b.aktiv
						   AND (bf.datum_von IS NULL OR bf.datum_von <= ?)
						   AND (bf.datum_bis IS NULL OR bf.datum_bis >= ?)
						   AND so.oe_kurzbz_sap = ?
					', array($studySemesterEndDate, $studySemesterStartDate, $costCenter->oe_kurzbz_sap));

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
								if (isError($sapEmployeeResult) && getCode($sapEmployeeResult) != ODATAClientLib::INVALID_WS) return $sapEmployeeResult;

								// If an employee was found in SAP
								if (hasData($sapEmployeeResult) && getCode($sapEmployeeResult) != ODATAClientLib::INVALID_WS)
								{
									// Add employee to project
									$addEmployeeResult = $this->_ci->ProjectsModel->addEmployee(
										getData($createProjectResult)->ObjectID,
										getData($sapEmployeeResult)[0]->C_EeId,
										$studySemesterStartDateTS,
										$studySemesterEndDateTS,
										'38.5'
									);

									// If an error occurred and it is not because of an already existing employee in this project
									if (isError($addEmployeeResult) && getCode($addEmployeeResult) != ODATAClientLib::RS_ERROR) return $addEmployeeResult;

									// Add employee to project task
									$addEmployeeToTaskResult = $this->_ci->ProjectsModel->addEmployeeToTask(
										getData($createTaskResult)->ObjectID,
										getData($sapEmployeeResult)[0]->C_EeId,
										getData($serviceResult)[0]->sap_service_id,
										'37'
									);

									if (isError($addEmployeeToTaskResult)) return $addEmployeeToTaskResult;
								}
							}
						}
					}
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

		// Set the project as active
		// $setActiveResult = $this->_ci->ProjectsModel->setActive(getData($createProjectResult)->ObjectID);

		// If an error occurred while setting the project as active on ByD return the error
		// if (isError($setActiveResult)) return $setActiveResult;

		return success('Project admin synchronization ended succesfully');
	}
}

