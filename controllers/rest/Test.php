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
		parent::__construct(array('ActivationCode' => 'basis/benutzer:rw'));

		// Loads the SAPLib library
		$this->load->library('extensions/FHC-Core-SAP/SAPLib');
	}

	/**
	 * Returns the activation code for the provided uid
	 */
	public function ()
	{
		$ = $this->input->get('');

		$this->response($this->saplib->($), REST_Controller::HTTP_OK);
	}
}
