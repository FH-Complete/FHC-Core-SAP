<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update Mitarbeiterzeiten in SAP Business by Design
 */
class ManageMitarbeiterzeiten extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
        $this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncMitarbeiterzeitenLib
        $this->load->library('extensions/FHC-Core-SAP/SyncMitarbeiterzeitenLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all projects
	 */
	public function getMitarbeiterzeiten()
	{
		$results = array();
	    $entries = $this->syncmitarbeiterzeitenlib->getMitarbeiterzeiten();

		foreach ($entries->retval as $entry)
        {
            if ($entry->C_StartDate === null)
                continue;

            $temp = array();
            $timestamp = substr(filter_var($entry->C_StartDate, FILTER_SANITIZE_NUMBER_INT), 0, 10);

            $temp['uid'] = $entry->C_EmployeeUuid;
            $temp['aktivitaet_kurzbz'] = 'Arbeit';
            $temp['start'] = date('Y-m-d', $timestamp) . ' ' . $this->_sanitizeTime($entry->C_StartTime);
            $temp['ende'] = date('Y-m-d', $timestamp) . ' ' . $this->_sanitizeTime($entry->C_EndTime);
            //$temp['debug'] = $entry->C_WorkDescription;

            $results[] = $temp;
        }

		var_dump($results);
	}

	private function _sanitizeTime($value)
    {
        return substr(str_replace(['H', 'M'], ':', $value), 2, 8);
    }
}

