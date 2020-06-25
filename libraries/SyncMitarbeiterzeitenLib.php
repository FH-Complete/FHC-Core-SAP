<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncMitarbeiterzeitenLib
{
	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads model MitarbeiterzeitenModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Mitarbeiterzeiten_model', 'MitarbeiterzeitenModel');

        // Loads model EmployeeModel
        $this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Employee_model', 'EmployeeModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Return the raw result of Mitarbeiterzeiten
	 */
	public function getMitarbeiterzeiten()
	{
		return $this->_ci->MitarbeiterzeitenModel->getMitarbeiterzeiten();
	}

	public function getMitarbeiter($uuid)
    {
        return $this->_ci->EmployeeModel->getEmployeesByUUIDs(array($uuid));
    }
}

