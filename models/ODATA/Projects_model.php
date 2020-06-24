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
				'PlannedStartDateTime' => '/Date('.$startDate.')/',
				'PlannedEndDateTime' => '/Date('.$endDate.')/',

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
	public function createTask($parentObjectID, $name)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$parentObjectID.'\')/ProjectTask',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ParentObjectID' => $parentObjectID,
				'Name' => $name,
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
	public function addEmployee($parentObjectID, $employeeID, $productID, $plannedStartDateTime, $plannedEndDateTime)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$parentObjectID.'\')/ProjectParticipant',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ParentObjectID' => $parentObjectID,
				'EmployeeID' => $employeeID,
				'ProductID' => $productID,
				'PlannedStartDateTime' => '/Date('.$plannedStartDateTime.')/',
				'PlannedEndDateTime' => '/Date('.$plannedEndDateTime.')/'
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
}

