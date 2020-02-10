<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class Test extends API_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(array('Test' => 'basis/person:rw'));

		// Loads SAPClientModel
		$this->load->model('extensions/FHC-Core-SAP/SAPClient_model', 'SAPClientModel');
	}

	/**
	 *
	 */
	public function getTest()
	{
		$result = $this->SAPClientModel->findByElementsByFamilyName('Mustermann');

		var_dump($result);
	}
}
