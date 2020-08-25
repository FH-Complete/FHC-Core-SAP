<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncEmployeeIDsLib
{
	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads model EmployeeModel
        $this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Employee_model', 'EmployeeModel');

        // Loads model SAPMitarbeiterModel
        $this->_ci->load->model('extensions/FHC-Core-SAP/SAPMitarbeiter_model', 'SAPMitarbeiterModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	public function getSubsetOfEmployeeUIDs($limit, $offset)
    {
        $dbModel = new DB_Model();

        return $dbModel->execReadOnlyQuery('
			SELECT m.mitarbeiter_uid
			FROM public.tbl_mitarbeiter m
			WHERE m.mitarbeiter_uid NOT IN (
			    SELECT mitarbeiter_uid
			    FROM sync.tbl_sap_mitarbeiter
			)
			ORDER BY mitarbeiter_uid
			LIMIT ? OFFSET ?
		', array($limit, $offset));
    }

    public function getEmployeeUUIDs($arrayUids)
    {
        return $this->_ci->EmployeeModel->getEmployeesByUIDs($arrayUids);
    }

    public function saveID($data)
    {
        return $this->_ci->SAPMitarbeiterModel->insert($data);
    }
}
