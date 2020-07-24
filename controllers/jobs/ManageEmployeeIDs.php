<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to create Employee IDs in FHC
 */
class ManageEmployeeIDs extends JQW_Controller
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
        $this->load->library('extensions/FHC-Core-SAP/SyncEmployeeIDsLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all projects
	 */
	public function getEmployeeUIDs()
    {
        $results = array();
        $offset = 0;
        $moreData = true;

        do
        {
            $arrayUids = array();
            $employeeUids = $this->syncemployeeidslib->getSubsetOfEmployeeUIDs(50, $offset);

            if (!empty($employeeUids->retval))
            {
                foreach($employeeUids->retval as $uid)
                {
                    $arrayUids[] = $uid->mitarbeiter_uid;
                }

                $employeeUuids = $this->syncemployeeidslib->getEmployeeUUIDs($arrayUids);

                if (is_array($employeeUuids->retval))
                {
                    foreach($employeeUuids->retval as $uuid)
                    {
                        $results[] = array(
                            'mitarbeiter_uid' => strtolower($uuid->C_BusinessUserId),
                            'sap_eeid' => $uuid->C_EeId
                        );
                    }
                }

                $offset += 50;
            }
            else
                $moreData = false;
        }
        while ($moreData);

        // save results in DB
        foreach ($results as $id)
        {
            $this->syncemployeeidslib->saveID($id);
        }
    }
}

