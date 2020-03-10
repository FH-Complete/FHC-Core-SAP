<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Example extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads QueryAccountsModel
		$this->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/QueryAccounts_model', 'QueryAccountsModel');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function example()
	{
		$this->logInfo('Example job start');

		$queryResult = $this->QueryAccountsModel->findByElementsByFamilyName('Mustermann');

		// If groups are present
		if (hasData($queryResult))
		{
			$countCustomers = count(getData($queryResult)->Customer);

			$this->logInfo('Total customers: '.$countCustomers);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Example job stop');
	}
}
