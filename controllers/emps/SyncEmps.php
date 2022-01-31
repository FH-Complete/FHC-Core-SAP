<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

class SyncEmps extends Auth_Controller
{

	private $_ci; // Code igniter instance

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'admin:rw',
				'syncEmp' => 'admin:rw'
			)
		);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->library('extensions/FHC-Core-SAP/SyncEmployeesLib');
		$this->_ci->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		$this->_ci->load->library('WidgetLib');
	}

	public function index()
	{
		$this->load->view('extensions/FHC-Core-SAP/emps/syncEmps.php');
	}

	public function syncEmp()
	{
		$empID = $this->_ci->input->post('emp_id');

		if (empty($empID))
			$this->terminateWithJsonError('Bitte einen Mitarbeiter angeben');

		$syncStatus = $this->_ci->syncemployeeslib->sync($empID);

		$this->outputJson($syncStatus);

	}
}
