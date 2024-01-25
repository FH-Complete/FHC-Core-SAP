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
				'syncEmp' => 'admin:rw',
				'getCSVEmployees' => 'admin:rw'
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
		$onlyStammdaten = $this->_ci->input->post('stammdaten') === "true";

		if (empty($empID))
			$this->terminateWithJsonError('Bitte einen Mitarbeiter angeben');

		$syncStatus = $this->_ci->syncemployeeslib->sync($empID, $onlyStammdaten);

		$this->outputJson($syncStatus);

	}

	public function getCSVEmployees()
	{
		$data = $this->_ci->syncemployeeslib->getCSVEmployees();
		$filename = "employees.csv";
		header('Content-type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename='.$filename);
		$file = fopen('php://output', 'w');

		fputcsv($file, array('Mitarbeiter', 'Startdatum', 'Stunden', 'Verwaltungskategorie'));

		foreach ($data as $additionalClause){
			foreach ($additionalClause['workingAgreement'] as $workAgreement)
			{
				$line = [$additionalClause['emp'], date_format(date_create($workAgreement['startDate']), "m/d/Y"), $workAgreement['timeRate'], $workAgreement['category']];
				fputcsv($file, $line);
			}
		}
		fclose($file);
		exit();
	}
}
