<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Projects web service
 */
class Projects_model extends ODATAClientModel
{
	// --------------------------------------------------------------------------------------------
	// Public methods GET API calls

	/**
	 * 
	 */
	public function getProjects()
	{
		return $this->_call(
			'projekt/ProjectCollection',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}
	
	/**
	 * 
	 */
	public function getProjectById($id)
	{
		return $this->_call(
			'projekt/ProjectCollection(\''.$id.'\')',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}

	/**
	 * 
	 */
	public function getProjectTasks($id)
	{
		return $this->_call(
			'projekt/ProjectCollection(\''.$id.'\')/ProjectTask',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}

	// --------------------------------------------------------------------------------------------
	// Public methods POST API calls

	/**
	 * 
	 */
	public function create($projectId)
	{
		return $this->_call(
			'projekt/ProjectCollection',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ProjectID' => $projectId,
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
				'LanguageCode' => 'DE',
				'MedicalDevicesHealthEngineering_KUT' => false,
				'Meldedatum_KUT' => null,
				'PlannedEndDateTime' => '/Date(1593644400000)/',
				'PlannedStartDateTime' => '/Date(1583017200000)/',
				'PlanningMeasureUnitCode' => 'HUR',
				'ProgrammeID' => '',
				'Projektnamelang_KUT' => '',
				'RenewableEnergySystems_KUT' => false,
				'RequestingCostCentreID' => '',
				'ResponsibleCostCentreID' => 'GF20',
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
				'TypeCode' => 'Z3',
				'VirtualTechnologiesSystemsEng_KUT' => false,
				'ResponsibleEmployeeID'  => '7000004'
			)
		);
	}

	// --------------------------------------------------------------------------------------------
	// Public methods MERGE API calls

	/**
	 * 
	 */
	public function update($projectId, $name)
	{
		return $this->_call(
			'projekt/ProjectTaskCollection(\''.$projectId.'\')',
			ODATAClientLib::HTTP_MERGE_METHOD,
			array(
				'ObjectID' => $projectId,
				'Name' => $name
			)
		);
	}
}

