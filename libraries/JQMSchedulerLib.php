<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance

	const JOB_TYPE_SAP_NEW_USERS = 'SAPUsersCreate';
	const JOB_TYPE_SAP_UPDATE_USERS = 'SAPUsersUpdate';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
	}

	// --------------------------------------------------------------------------------------------
	// Public methods
	
	/**
	 * Looks for new users that have been created in FHC and stores their person id into a job input
	 */
	public function newUsers()
	{
		$jobInput = null;

		// Loads the StudiensemesterModel
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		// Get the last or current studysemester
		$lastOrCurrentStudySemesterResult = $this->_ci->StudiensemesterModel->getLastOrAktSemester();

		// If an error occurred while getting the study semester return it
		if (isError($lastOrCurrentStudySemesterResult)) return $lastOrCurrentStudySemesterResult;

		// If a study semester is configured in database
		if (hasData($lastOrCurrentStudySemesterResult))
		{
			// Last or current study semester
			$lastOrCurrentStudySemester = getData($lastOrCurrentStudySemesterResult)[0]->studiensemester_kurzbz;

			$dbModel = new DB_Model();

			// 
			$newUsersResult = $dbModel->execReadOnlyQuery('
				SELECT ps.person_id
				  FROM public.tbl_prestudent ps
				  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
				 WHERE pss.studiensemester_kurzbz = ?
				   AND pss.status_kurzbz IN (\'Aufgenommener\', \'Student\', \'Incoming\', \'Diplomand\')
			      GROUP BY ps.person_id
			', array($lastOrCurrentStudySemester));

			// If error occurred while retrieving new users from database then return the error
			if (isError($newUsersResult)) return $newUsersResult;

			// If new users are present
			if (hasData($newUsersResult))
			{
				$jobInput = json_encode(getData($newUsersResult));
			}
		}
		else
		{
			return error('No study semester present in database');
		}

		return success($jobInput);
	}

	/**
	 * Looks for users that have been update in FHC and stores their person id into a job input
	 */
	public function updateUsers()
	{
		$jobInput = null;

		$persons = array();
		$contacts = array();
		$addresses = array();

		$dbModel = new DB_Model();

		// Persons

		// Get users that have been updated in tbl_person table
		$personResult = $dbModel->execReadOnlyQuery('
			SELECT p.person_id
			  FROM public.tbl_person p
			 WHERE NOW() - p.updateamum::timestamptz <= INTERVAL \'24 hours\'
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($personResult)) return $personResult;

		// If there are updated users
		if (hasData($personResult)) $persons = getData($personResult);

		// Contacts

		// Get users that have been updated in tbl_kontakt table
		$contactsResult = $dbModel->execReadOnlyQuery('
			SELECT k.person_id
			  FROM public.tbl_kontakt k
			 WHERE NOW() - k.updateamum::timestamptz <= INTERVAL \'24 hours\'
		      GROUP BY k.person_id
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($contactsResult)) return $contactsResult;

		// If there are updated users
		if (hasData($contactsResult)) $contacts = getData($contactsResult);

		// Addresses

		// Get users that have been updated in tbl_adresse table
		$addressesResult = $dbModel->execReadOnlyQuery('
			SELECT a.person_id
			  FROM public.tbl_adresse a
			 WHERE NOW() - a.updateamum::timestamptz <= INTERVAL \'24 hours\'
		      GROUP BY a.person_id
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($addressesResult)) return $addressesResult;

		// If there are updated users
		if (hasData($addressesResult)) $addresses = getData($addressesResult);

		$jobInput = json_encode(array_merge($persons, $contacts, $addresses));

		return success($jobInput);
	}
}

