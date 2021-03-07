<?php

require_once APPPATH.'/models/extensions/FHC-Core-SAP/ODATAClientModel.php';

/**
 * Implements the SAP ODATA webservice calls for Banks web service
 */
class Banks_model extends ODATAClientModel
{
	const URI_PREFIX = 'odata/cust/v1/bank/';

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
	 * Get all the banks data
	 */
	public function getAllBanks($bankIds = null)
	{
		// ODATA call parameters
		$odataParameters = array(
			'$orderby' => 'BankInternalID',
			'$top' => 999999
		);

		// If the given parameter is a valid not empty array
		if (!isEmptyArray($bankIds))
		{
			$odataParameters['$filter'] = filter($bankIds, 'BankInternalID', 'eq', 'or');
		}

		return $this->_call(
			self::URI_PREFIX.'BankDirectoryEntryRootCollection',
			ODATAClientLib::HTTP_GET_METHOD,
			$odataParameters
		);
	}

	/**
	 * Get all active banks data
	 */
	public function getActiveBanks($bankIds = null)
	{
		// Formatted current date to be used in the filter
		$currentDate = date('Y-m-d').'T00:00:00';

		// ODATA call parameters
		$odataParameters = array(
			'$orderby' => 'BankInternalID',
			'$top' => 999999,
			'$filter' => sprintf(
				'(LifeCycleStatusCode eq \'2\' and StartDate lt datetime\'%s\' and EndDate gt datetime\'%s\')',
				$currentDate,
				$currentDate
			)
		);

		// If the given parameter is a valid not empty array
		if (!isEmptyArray($bankIds))
		{
			// Concatenates the previous filter string with this one
			$odataParameters['$filter'] .= ' and '.filter($bankIds, 'BankInternalID', 'eq', 'or');
		}

		return $this->_call(
			self::URI_PREFIX.'BankDirectoryEntryRootCollection',
			ODATAClientLib::HTTP_GET_METHOD,
			$odataParameters
		);
	}
}

