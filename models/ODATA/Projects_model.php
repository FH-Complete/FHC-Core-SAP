<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Projects web service
 */
class Projects_model extends ODATAClientModel
{
	const URI_PREFIX = 'odata/cust/v1/projekt/';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_apiSetName = 'business';
	}

	// --------------------------------------------------------------------------------------------
	// Public methods GET API calls

	/**
	 * 
	 */
	public function getProjects()
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}

	/**
	 * 
	 */
	public function getProjectsAndTasks()
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection?$expand=ProjectTask',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}
	
	/**
	 * 
	 */
	public function getProjectById($id)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$id.'\')',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}

	/**
	 * 
	 */
	public function getProjectTasks($id)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$id.'\')/ProjectTask',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}

	// --------------------------------------------------------------------------------------------
	// Public methods POST API calls

	/**
	 * 
	 */
	public function create($projectId, $type, $unitResponsible, $personResponsible, $startDate, $endDate)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				// Required
				// Parameters
				'ProjectID' => $projectId,
				'TypeCode' => $type,
				'ResponsibleCostCentreID' => $unitResponsible,
				'ResponsibleEmployeeID'  => $personResponsible,
				'PlannedStartDateTime' => toDate($startDate),
				'PlannedEndDateTime' => toDate($endDate),

				// Constants
				'LanguageCode' => 'DE',

				// Maybe not usefull
				'Annahmewahrscheinlichkeitcontent_KUT' => '0.00000000000000',
				'ArtificialIntelligenceDataAnalytics_KUT' => false,
				'Ausschreibung_KUT' => '',
				'AutomationSensorTechnology_KUT' => false,
				'CellTechnologiesBiomaterials_KUT' => false,
				'DeMinimis_KUT' => false,
				'DecimalValue' => '0.00000000000000',
				'DepartmentCS_KUT' => false,
				'DepartmentEE_KUT' => false,
				'DepartmentIE_KUT' => false,
				'DepartmentLSE_KUT' => false,
				'DigitalEnterpriseUsabilityExperience_KUT' => false,
				'DigitalManufactoringRobotics_KUT' => false,
				'ElectronicBasedSystems_KUT' => false,
				'ElektronikKommunikationstechnik_KUT' => false,
				'EstimatedCompletionPercent' => 0,
				'ForschungieS_KUT' => false,
				'FrdergeberID_KUT' => '',
				'InformationSecurity_KUT' => false,
				'IntegratedHealthcare_KUT' => false,
				'KaufmnnischeLeitung_KUT' => '',
				'MedicalDevicesHealthEngineering_KUT' => false,
				'Meldedatum_KUT' => null,
				'PlanningMeasureUnitCode' => 'HUR',
				'ProgrammeID' => '',
				'Projektnamelang_KUT' => '',
				'RenewableEnergySystems_KUT' => false,
				'RequestingCostCentreID' => '',
				'RolleFH_KUT' => '',
				'SchwerpunktAutomationRobotics_KUT' => false,
				'SchwerpunktEmbeddedSystemsCyberPhysicalSystems_KUT' => false,
				'SchwerpunktRenewableUrbanEnergySystems_KUT' => false,
				'SchwerpunktSecureServiceseHealthMobility_KUT' => false,
				'SchwerpunktSonstige_KUT' => false,
				'SchwerpunktTissueEngineeringMolecularLifeScienceTechnologies_KUT' => false,
				'Schwerpunktbergreifend_KUT' => false,
				'Schwerpunktkeine_KUT' => false,
				'SoftwareEngineeringDevOps_KUT' => false,
				'SportsEngBiomechanicsErgonomics_KUT' => false,
				'Status_KUT' => '',
				'VirtualTechnologiesSystemsEng_KUT' => false
			)
		);
	}

	/**
	 * 
	 */
	public function createTask($parentObjectID, $name, $responsibleCostCentreID)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$parentObjectID.'\')/ProjectTask',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ParentObjectID' => $parentObjectID,
				'Name' => $name,
				'ResponsibleCostCentreID' => $responsibleCostCentreID,
				'MASollStunden1content_KUT' => '1.00000000000000',
				'MASollStunden1unitCode_KUT' => 'HUR',
				'LehreGrobplanung1content_KUT' => '20.00000000000000',
				'LehreGrobplanung1unitCode_KUT' => 'HUR'
			)
		);
	}
	
	/**
	 * 
	 */
	public function addEmployee($parentObjectID, $employeeID, $plannedStartDateTime, $plannedEndDateTime, $committedWork)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$parentObjectID.'\')/ProjectParticipant',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ParentObjectID' => $parentObjectID,
				'EmployeeID' => $employeeID,
				'PlannedStartDateTime' => toDate($plannedStartDateTime),
				'PlannedEndDateTime' => toDate($plannedEndDateTime),
				'ManagementCommittedWorkQuantity' => $committedWork
			)
		);
	}

	/**
	 * 
	 */
	public function addEmployeeToTask($taskObjectID, $employeeID, $productID, $plannedWorkQuantity, $lehreGrobplanung = '0', $maSollStunden = '0')
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$taskObjectID.'\')/ProjectTaskService',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ObjectID' => $taskObjectID,
				'AssignedEmployeeID' => $employeeID,
				'ProductID' => $productID,
				'PlannedWorkQuantity' => $plannedWorkQuantity,
				'LehreGrobplanungcontent_KUT' => $lehreGrobplanung,
				'LehreGrobplanungunitCode_KUT' => 'HUR',
				'MASollStundencontent_KUT' => $maSollStunden,
				'MASollStundenunitCode_KUT' => 'HUR'
			)
		);
	}

	/**
	 * 
	 */
	public function setActive($projectObjectId)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectStartAndRelease?ObjectID=\''.$projectObjectId.'\'',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ObjectID' => $projectObjectId
			)
		);
	}

	// --------------------------------------------------------------------------------------------
	// Public methods MERGE API calls

	/**
	 * 
	 */
	public function updateTaskCollection($projectObjectId, $name)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$projectObjectId.'\')',
			ODATAClientLib::HTTP_MERGE_METHOD,
			array(
				'ObjectID' => $projectObjectId,
				'Name' => $name
			)
		);
	}

	/**
	 * 
	 */
	public function setTimeRecordingOff($projectObjectId)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$projectObjectId.'\')',
			ODATAClientLib::HTTP_MERGE_METHOD,
			array(
				'ObjectID' => $projectObjectId,
				'TimeConfirmationProfileCode' => '1'
			)
		);
	}
}

