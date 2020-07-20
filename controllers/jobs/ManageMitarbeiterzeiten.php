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
		$allowedTypcodes = array('AT0001', 'AT0031');
        $results['workinghours'] = array();
        $results['absences'] = array();
        $person_arr = array();

	    // check Job queue
        $jobType = 'SyncTimesheetFromSAP';
        $this->logInfo('Start data synchronization with SAP ByD: Mitarbeiterzeiten');

        // get the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
        {
            $this->logError('An error occurred while creating working hour entries in SAP', getError($lastJobs));
        }
        else
        {
            $person_arr = $this->_getPersonIdArray(getData($lastJobs));
        }

        // get working hours from ByD
        // TODO: use $person_arr as input
        $entries = $this->syncmitarbeiterzeitenlib->getMitarbeiterzeiten();

		foreach ($entries->retval as $entry)
        {
            if (!in_array($entry->C_TmitTypcode, $allowedTypcodes))
                continue;

            $temp = array();
            $employee = $this->syncmitarbeiterzeitenlib->getMitarbeiter($entry->C_EmployeeUuid);
            $timestampStart = substr(filter_var($entry->C_StartDate, FILTER_SANITIZE_NUMBER_INT), 0, 10);

            if ($entry->C_TmitTypcode == 'AT0001')
            {
                $temp['uid'] = strtolower($employee->retval[0]->C_BusinessUserId);
                $temp['aktivitaet_kurzbz'] = 'Arbeit';
                $temp['start'] = date('Y-m-d', $timestampStart) . ' ' . $this->_sanitizeTime($entry->C_StartTime);
                $temp['ende'] = date('Y-m-d', $timestampStart) . ' ' . $this->_sanitizeTime($entry->C_EndTime);

                $results['workinghours'][] = $temp;
            }
            elseif ($entry->C_TmitTypcode == 'AT0031' && $entry->C_ApprovalStatus == '4')
            {
                $approver = $this->syncmitarbeiterzeitenlib->getMitarbeiter($entry->C_ApproverUuid);
                $timestampApproval = substr(filter_var($entry->C_ApprovalDate, FILTER_SANITIZE_NUMBER_INT), 0, 10);

                $temp['zeitsperretyp_kurzbz'] = 'Urlaub';
                $temp['mitarbeiter_uid'] = strtolower($employee->retval[0]->C_BusinessUserId);
                $temp['vondatum'] = date('Y-m-d', $timestampStart);
                $temp['bisdatum'] = date('Y-m-d', $timestampStart);
                $temp['freigabeamum'] = date('Y-m-d', $timestampApproval);
                $temp['freigabevon'] = strtolower($approver->retval[0]->C_BusinessUserId);

                $results['absences'][] = $temp;
            }
        }

		// delete entries for today
        $this->syncmitarbeiterzeitenlib->deleteWorkingHoursForToday();
		$this->syncmitarbeiterzeitenlib->deleteAbsencesForToday();

		// sync entries
		foreach ($results['workinghours'] as $entry)
        {
            $this->syncmitarbeiterzeitenlib->setWorkingHoursEntry($entry);
        }

		foreach ($results['absences'] as $entry)
        {
            $this->syncmitarbeiterzeitenlib->setAbsenceEntry($entry);
        }
	}

	private function _sanitizeTime($value)
    {
        return substr(str_replace(['H', 'M'], ':', $value), 2, 8);
    }

    private function _getPersonIdArray($jobs)
    {
        $mergedUsersArray = array();

        if (count($jobs) == 0) return $mergedUsersArray;

        foreach ($jobs as $job)
        {
            $decodedInput = json_decode($job->input);
            if ($decodedInput != null)
            {
                foreach ($decodedInput as $el)
                {
                    $mergedUsersArray[] = $el->person_id;
                }
            }
        }
        return $mergedUsersArray;
    }
}

