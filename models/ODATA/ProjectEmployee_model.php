<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Projects Employees web service
 */
class ProjectEmployee_model extends ODATAClientModel
{
	const URI_PREFIX = 'odata/cust/v1/projectemployee/';

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
	public function getProjectsAndEmployees($typeCodes, $projectIds = null)
	{
		$filter = $this->getTypeCodeFilter($typeCodes);

		$odataParameters = array(
			'$orderby' => 'ProjectID',
			'$expand' => 'ProjectTask/ProjectTaskService',
			'$format=json',
			'$top' => 999999,
			'$filter' => $filter
		);

		if (!isEmptyArray($projectIds))
		{
			$odataParameters['$filter'] .= ' and ' . filter($projectIds, 'ProjectID', 'eq', 'or');
		}

		return $this->_call(
			self::URI_PREFIX.'ProjectCollection',
			ODATAClientLib::HTTP_GET_METHOD,
			$odataParameters
		);
	}

	private function getTypeCodeFilter($typeCodes)
	{
		return "(" . implode(' or ', array_map(function($typeCode) {
				return "TypeCode eq '$typeCode'";
			}, $typeCodes)) . ")";
	}
}

