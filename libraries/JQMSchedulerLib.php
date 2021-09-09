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
	const JOB_TYPE_SAP_NEW_SERVICES = 'SAPServicesCreate';
	const JOB_TYPE_SAP_NEW_PAYMENTS = 'SAPPaymentCreate';
	const USERS_BLOCK_LIST_COURSES = 'users_block_list_courses';

	// Maximum amount of users to be placed in a single job
	const UPDATE_LENGTH = 200;

	// Update time interval
	const UPDATE_TIME_INTERVAL = '24 hours';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Load users configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Users');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Looks for new users that have been created in FHC and stores their person id into a job input
	 */
	public function newUsers($studySemester = null)
	{
		$jobInput = null;
		$currentOrNextStudySemesterResult = null;

		// Loads the StudiensemesterModel
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		// If a study semester was given as parameter
		if (!isEmptyString($studySemester))
		{
			// Get info about the provided study semester
			$currentOrNextStudySemesterResult = $this->_ci->StudiensemesterModel->loadWhere(
				array(
					'studiensemester_kurzbz' => $studySemester
				)
			);
		}
		else // otherwise get the last or current one
		{
			// Get the last or current studysemester
			$currentOrNextStudySemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();
		}

		// If an error occurred while getting the study semester return it
		if (isError($currentOrNextStudySemesterResult)) return $currentOrNextStudySemesterResult;

		// If a study semester is configured in database
		if (hasData($currentOrNextStudySemesterResult))
		{
			// Last or current study semester
			$currentOrNextStudySemester = getData($currentOrNextStudySemesterResult)[0]->studiensemester_kurzbz;

			$dbModel = new DB_Model();

			//
			$newUsersResult = $dbModel->execReadOnlyQuery('
				SELECT ps.person_id
				  FROM public.tbl_prestudent ps
				  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
				 WHERE pss.studiensemester_kurzbz = ?
				   AND NOT EXISTS(SELECT 1 FROM sync.tbl_sap_students WHERE person_id = ps.person_id)
				   AND ps.studiengang_kz NOT IN ?
				   AND
					(
						EXISTS (
							SELECT
								1
							FROM
								public.tbl_prestudent
								JOIN public.tbl_student USING (prestudent_id)
								JOIN public.tbl_benutzer ON (uid = student_uid)
							WHERE
								tbl_prestudent.person_id = ps.person_id
								AND tbl_prestudent.studiengang_kz = ps.studiengang_kz
								AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Student\', \'Incoming\', \'Diplomand\')
								AND tbl_benutzer.aktiv
						) OR EXISTS (
							SELECT
								1
							FROM
								public.tbl_prestudent
							WHERE
								tbl_prestudent.person_id = ps.person_id
								AND studiengang_kz = ps.studiengang_kz
								AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Aufgenommener\')
						)
					)
			      GROUP BY ps.person_id
			', array(
				$currentOrNextStudySemester,
				$this->_ci->config->item(self::USERS_BLOCK_LIST_COURSES)
			  )
			);

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
		$persons = array();
		$contacts = array();
		$addresses = array();
		$prestudents = array();

		$dbModel = new DB_Model();

		// Persons

		// Get users that have been updated in tbl_person table
		$personResult = $dbModel->execReadOnlyQuery('
			SELECT p.person_id
			  FROM public.tbl_person p
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE p.updateamum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
			   AND (
				s.last_update::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
				OR s.last_update IS NULL
			)
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($personResult)) return $personResult;

		// If there are updated users
		if (hasData($personResult)) $persons = getData($personResult);

		// Prestudents

		// Get users that have been updated in tbl_prestudent = tbl_prestudentstatus table
		$prestudentsResult = $dbModel->execReadOnlyQuery('
			SELECT ps.person_id
			  FROM public.tbl_prestudent ps
			  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE (
			 	pss.insertamum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
			 	OR pss.updateamum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
			 	OR pss.datum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
			)
			   AND (
				s.last_update::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
				OR s.last_update IS NULL
			)
		      GROUP BY ps.person_id
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($prestudentsResult)) return $prestudentsResult;

		// If there are updated users
		if (hasData($prestudentsResult)) $prestudents = getData($prestudentsResult);

		// Contacts

		// Get users that have been updated in tbl_kontakt table
		$contactsResult = $dbModel->execReadOnlyQuery('
			SELECT k.person_id
			  FROM public.tbl_kontakt k
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE (
				k.insertamum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
				OR k.updateamum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
			)
			   AND (
				s.last_update::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
				OR s.last_update IS NULL
			)
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
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE a.updateamum::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
			   AND (
				s.last_update::DATE = (CURRENT_DATE - INTERVAL \''.self::UPDATE_TIME_INTERVAL.'\')::DATE
				OR s.last_update IS NULL
			)
		      GROUP BY a.person_id
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($addressesResult)) return $addressesResult;

		// If there are updated users
		if (hasData($addressesResult)) $addresses = getData($addressesResult);

		// Return a success that contains all the arrays merged together
		return success(uniquePersonIdArray(array_merge($persons, $contacts, $addresses, $prestudents)));
	}

	/**
	 * Looks for new users that have been created in FHC and stores their person id into a services job input
	 */
	public function newServices()
	{
		$jobInput = null;

		$dbModel = new DB_Model();

		// Gets new permanent employees created in the last 42 hours
		$newUsersResult = $dbModel->execReadOnlyQuery('
			SELECT b.person_id
			  FROM public.tbl_benutzer b
			  JOIN public.tbl_mitarbeiter m ON(m.mitarbeiter_uid = b.uid)
			 WHERE m.fixangestellt = TRUE
			   AND b.aktiv = TRUE
			   AND b.person_id NOT IN (
				SELECT ss.person_id FROM sync.tbl_sap_services ss
			   )
			   AND m.personalnummer > 0
		');

		// If error occurred while retrieving new users from database then return the error
		if (isError($newUsersResult)) return $newUsersResult;

		// If new users are present
		if (hasData($newUsersResult))
		{
			$jobInput = json_encode(getData($newUsersResult));
		}

		return success($jobInput);
	}

	/**
	 * Looks for new payments that have been created in FHC and stores their person id into a payment job input
	 */
	public function newPayments()
	{
		$jobInput = null;

		$this->_ci->load->library('extensions/FHC-Core-SAP/SyncPaymentsLib');

		$dbModel = new DB_Model();

		// Gets new permanent employees created in the last 42 hours
		$newPaymentsResult = $dbModel->execReadOnlyQuery('
		SELECT
			distinct person_id
		FROM
			public.tbl_konto bk
		WHERE
			betrag < 0
			AND NOT EXISTS(SELECT 1 FROM sync.tbl_sap_salesorder WHERE buchungsnr=bk.buchungsnr)
			AND NOT EXISTS(SELECT 1 FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr)
			AND
			(
				EXISTS(SELECT
				1
				FROM
					public.tbl_prestudent
					JOIN public.tbl_student USING(prestudent_id)
					JOIN public.tbl_benutzer ON(uid=student_uid)
				WHERE
					tbl_prestudent.person_id = bk.person_id
					AND tbl_prestudent.studiengang_kz = bk.studiengang_kz
					AND get_rolle_prestudent(prestudent_id,null) IN(\'Student\',\'Incoming\',\'Diplomand\')
					AND tbl_benutzer.aktiv
				)
				OR
				EXISTS(SELECT
					1
				FROM
					public.tbl_prestudent
				WHERE
					tbl_prestudent.person_id = bk.person_id
					AND tbl_prestudent.studiengang_kz=10002
					AND get_rolle_prestudent(prestudent_id,null) IN(\'Student\',\'Incoming\',\'Diplomand\')
					AND EXISTS(SELECT
							1
						FROM
							public.tbl_prestudent
						WHERE
							tbl_prestudent.person_id = bk.person_id
							AND tbl_prestudent.studiengang_kz=bk.studiengang_kz
							AND get_rolle_prestudent(prestudent_id,null) IN(\'Student\',\'Incoming\',\'Diplomand\',\'Interessent\',\'Bewerber\',\'Aufgenommener\',\'Wartender\') 
					)
				)
				OR
				EXISTS(SELECT
				1
				FROM
					public.tbl_prestudent
				WHERE
					tbl_prestudent.person_id = bk.person_id
					AND studiengang_kz = bk.studiengang_kz
					AND get_rolle_prestudent(prestudent_id,null) IN(\'Aufgenommener\')
				)
			)

			AND buchungsnr_verweis is null
			AND buchungsdatum <= now()
			AND buchungsdatum >= ?
		', array(SyncPaymentsLib::BUCHUNGSDATUM_SYNC_START));

		// If error occurred while retrieving new users from database then return the error
		if (isError($newPaymentsResult)) return $newPaymentsResult;

		// If new users are present
		if (hasData($newPaymentsResult))
		{
			$jobInput = json_encode(getData($newPaymentsResult));
		}

		return success($jobInput);
	}
}
