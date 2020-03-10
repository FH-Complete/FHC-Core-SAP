<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example API
 */
class Example extends API_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(array('Example' => 'basis/person:rw'));

		// Loads QueryAccountsModel
		$this->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/QueryAccounts_model', 'QueryAccountsModel');
	}

	/**
	 * Example method
	 */
	public function getExample()
	{
		$this->response(
			$this->QueryAccountsModel->findByElementsByFamilyName('Mustermann'),
			REST_Controller::HTTP_OK
		);
	}
}
