<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncBanksLib
{
	// Project types
	const ADMIN_FHTW_PROJECT = 'admin_fhtw';
	const ADMIN_GMBH_PROJECT = 'admin_gmbh';
	const LEHRE_PROJECT = 'lehre';
	const LEHRGAENGE_PROJECT = 'lehrgaenge';

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads the LogLib with the needed parameters to log correctly from this library
		$this->_ci->load->library(
			'LogLib',
			array(
				'classIndex' => 3,
				'functionIndex' => 3,
				'lineIndex' => 2,
				'dbLogType' => 'job', // required
				'dbExecuteUser' => 'Cronjob system',
				'requestId' => 'JOB',
				'requestDataFormatter' => function($data) {
					return json_encode($data);
				}
			),
			'LogLibSAP'
		);

		// Loads model BanksModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Banks_model', 'BanksModel');

		// Loads model SAPBanksModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPBanks_model', 'SAPBanksModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get all the banks data
	 */
	public function getAllBanks()
	{
		return $this->_ci->BanksModel->getAllBanks();
	}

	/**
	 * Get all active banks data
	 */
	public function getActiveBanks()
	{
		return $this->_ci->BanksModel->getActiveBanks();
	}

	/**
	 * Save active SAP banks data into the sync table
	 */
	public function syncActiveBanks()
	{
		// Get all the active banks data from SAP
		$activeBanks = $this->_ci->BanksModel->getActiveBanks();

		// If an error occurred then return it
		if (isError($activeBanks)) return $activeBanks;

		// If data are available in SAP
		if (hasData($activeBanks))
		{
			// Clean the sync table
			$delete = $this->_ci->SAPBanksModel->deleteAll();

			// If an error occurred then return it
	                if (isError($delete)) return $delete;

			// For each bank data
			foreach (getData($activeBanks) as $bank)
			{
				// Insert SAP bank data into database
				$insert = $this->_ci->SAPBanksModel->insert(
					array(
						'sap_bank_id' => $bank->BankInternalID,
						'sap_bank_swift' => $bank->BankStandardID,
						'sap_bank_name' => $bank->OrganisationFormattedName
					)
				);

				// If an error occurred then return it
	                	if (isError($insert)) return $insert;
			}

			// If here then everything went fine
			return success('Banks data were successfully synchronized');
		}
		else
		{
			return success('No active banks are present in SAP');
		}
	}
}

