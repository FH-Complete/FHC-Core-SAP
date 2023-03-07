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
	public function getProjects($projectObjectIds = null)
	{
		// ODATA call parameters
		$odataParameters = array(
			'$orderby' => 'ProjectID',
			'$top' => 999999
		);

		// If the given parameter is a valid not empty array
		if (!isEmptyArray($projectObjectIds))
		{
			$odataParameters['$filter'] = filter($projectObjectIds, 'ObjectID', 'eq', 'or');
		}

		return $this->_call(
			self::URI_PREFIX.'ProjectCollection',
			ODATAClientLib::HTTP_GET_METHOD,
			$odataParameters
		);
	}

	/**
	 *
	 */
	public function getTask($projectTaskObjectId)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$projectTaskObjectId.'\')',
			ODATAClientLib::HTTP_GET_METHOD
		);
	}

	/**
	 *
	 */
	public function getProjectsAndTasks($projectObjectIds = null)
	{
		// ODATA call parameters
		$odataParameters = array(
			'$select' => 'ProjectID,ObjectID,PlannedStartDateTime,PlannedEndDateTime,ProjectTask,ProjectLifeCycleStatusCode,ResponsibleCostCentreID',
			'$orderby' => 'ProjectID',
			'$expand' => 'ProjectTask',
			'$top' => 999999
		);

		// If the given parameter is a valid not empty array
		if (!isEmptyArray($projectObjectIds))
		{
			$odataParameters['$filter'] = filter($projectObjectIds, 'ObjectID', 'eq', 'or');
		}

		return $this->_call(
			self::URI_PREFIX.'ProjectCollection',
			ODATAClientLib::HTTP_GET_METHOD,
			$odataParameters
		);
	}

	/**
	 *
	 */
	public function getProjectsAndPartecipants($projectObjectIds)
	{
		// ODATA call parameters
		$odataParameters = array(
			'$select' => 'ProjectID,ObjectID,PlannedStartDateTime,PlannedEndDateTime,ProjectParticipant,ProjectLifeCycleStatusCode',
			'$filter' => filter($projectObjectIds, 'ObjectID', 'eq', 'or'),
			'$orderby' => 'ProjectID',
			'$expand' => 'ProjectParticipant',
			'$top' => 999999
		);

		// If the given parameter is a valid not empty array
		if (!isEmptyArray($projectObjectIds))
		{
			$odataParameters['$filter'] = filter($projectObjectIds, 'ObjectID', 'eq', 'or');
		}

		return $this->_call(
			self::URI_PREFIX.'ProjectCollection',
			ODATAClientLib::HTTP_GET_METHOD,
			$odataParameters
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

	/**
	 *
	 */
	public function getProjectTaskService($id)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$id.'\')/ProjectTaskService',
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
			//	'ResponsibleCostCentreID' => $unitResponsible,
				'ResponsibleEmployeeID'  => $personResponsible,
				'PlannedStartDateTime' => toDate($startDate),
				'PlannedEndDateTime' => toDate($endDate),

				// Constants
				'LanguageCode' => 'DE',

				// Maybe  usefull
				'DecimalValue' => '0.00000000000000',
				'EstimatedCompletionPercent' => 0,
				'PlanningMeasureUnitCode' => 'HUR',
				'ProgrammeID' => '',
				'RequestingCostCentreID' => '',
			)
		);
	}

	/**
	 *
	 */
	public function createTask($parentObjectID, $name, $responsibleCostCentreID, $duration, $timeRecording)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$parentObjectID.'\')/ProjectTask',
			ODATAClientLib::HTTP_POST_METHOD,
			array(
				'ParentObjectID' => $parentObjectID,
				'Name' => $name,
				'ResponsibleCostCentreID' => $responsibleCostCentreID,
				'PlannedDuration' => 'P'.$duration.'D',
				'MASollStunden1content_KUT' => '1.00000000000000',
				'MASollStunden1unitCode_KUT' => 'HUR',
				'LehreGrobplanung1content_KUT' => '20.00000000000000',
				'LehreGrobplanung1unitCode_KUT' => 'HUR',
				'TimeConfirmationProfileCode' => $timeRecording
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
	public function setTimeRecording($projectObjectId, $timeRecording)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$projectObjectId.'\')',
			ODATAClientLib::HTTP_MERGE_METHOD,
			array(
				'ObjectID' => $projectObjectId,
				'TimeConfirmationProfileCode' => $timeRecording
			)
		);
	}

	/**
	 *
	 */
	public function setTaskDates($taskObjectID, $duration)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectTaskCollection(\''.$taskObjectID.'\')',
			ODATAClientLib::HTTP_MERGE_METHOD,
			array(
				'ObjectID' => $taskObjectID,
				'PlannedDuration' => 'P'.$duration.'D'
			)
		);
	}

	// --------------------------------------------------------------------------------------------
	// Public methods PATCH API calls

	/**
	 *
	 */
	public function setDates($projectObjectId, $startDate, $endDate)
	{
		return $this->_call(
			self::URI_PREFIX.'ProjectCollection(\''.$projectObjectId.'\')',
			ODATAClientLib::HTTP_PATCH_METHOD,
			array(
				'ObjectID' => $projectObjectId,
				'PlannedStartDateTime' => $startDate,
				'PlannedEndDateTime' => $endDate
			)
		);
	}
}
