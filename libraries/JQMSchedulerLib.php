<?php

/**
 * Copyright (C) 2023 fhcomplete.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('BASEPATH')) exit('No direct script access allowed');

use \DB_Model as DB_Model;

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance

	const JOB_TYPE_SAP_NEW_USERS = 'SAPUsersCreate';
	const JOB_TYPE_SAP_UPDATE_USERS = 'SAPUsersUpdate';
	const JOB_TYPE_SAP_NEW_SERVICES = 'SAPServicesCreate';
	const JOB_TYPE_SAP_UPDATE_SERVICES = 'SAPServicesUpdate';
	const JOB_TYPE_SAP_NEW_PAYMENTS = 'SAPPaymentCreate';
	const JOB_TYPE_SAP_NEW_EMPLOYEES = 'SAPEmployeesCreate';
	const JOB_TYPE_SAP_UPDATE_EMPLOYEES = 'SAPEmployeesUpdate';
	const JOB_TYPE_SAP_UPDATE_EMPLOYEES_WORKAGREEMENT = 'SAPEmployeesWorkAgreementUpdate';
	const JOB_TYPE_SAP_CREDIT_MEMO = 'SAPPaymentGutschrift';
	const JOB_TYPE_SAP_OTHER_CREDIT_MEMO = 'SAPSonstigeGutschrift';

	const JOB_TYPE_SAP_UPDATE_EMPLOYEE_SERVICE = 'SAPEmployeeIDServiceUpdate';
	const JOB_TYPE_SAP_CHECK_EMPLOYEE_DV = 'SAPEmployeeCheckDV';

	const USERS_BLOCK_LIST_COURSES = 'users_block_list_courses';
	const PAYMENTS_BOOKING_TYPE_ORGANIZATIONS = 'payments_booking_type_organizations';
	const PAYMENTS_BOOKING_TYPE_OTHER_CREDITS = 'payments_other_credits';

	const FHC_CONTRACT_TYPES = 'fhc_contract_types';
	const BEFORE_START = 'sap_sync_employees_x_days_before_start';
	const AFTER_END = 'sap_sync_employees_x_days_after_end';

	const EMPLOYEE_BLACKLIST = 'sap_employees_blacklist';


	// Maximum amount of users to be placed in a single job
	const UPDATE_LENGTH = 200;

	// Maximum amount of elements to be placed in a single job
	const MAX_JOB_ELEMENTS = 200;

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

		// Load payments configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Payments');

		// Load employees configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Employees');
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
			$newUsersResult = $dbModel->execReadOnlyQuery(
				'SELECT ps.person_id
				  FROM public.tbl_prestudent ps
				  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
				 WHERE pss.studiensemester_kurzbz = ?
				   AND NOT EXISTS(SELECT 1 FROM sync.tbl_sap_students WHERE person_id = ps.person_id)
				   AND ps.studiengang_kz NOT IN ?
				   AND
					(
						EXISTS (
							-- With benutzer
							SELECT
								1
							FROM
								public.tbl_prestudent
								JOIN public.tbl_student USING (prestudent_id)
								JOIN public.tbl_benutzer ON (uid = student_uid)
							WHERE
								tbl_prestudent.person_id = ps.person_id
								AND tbl_prestudent.studiengang_kz = ps.studiengang_kz
								AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Student\', \'Incoming\', \'Diplomand\', \'Unterbrecher\')
								AND tbl_benutzer.aktiv
						) OR EXISTS (
							-- No benutzer
							SELECT
								1
							FROM
								public.tbl_prestudent
							WHERE
								tbl_prestudent.person_id = ps.person_id
								AND studiengang_kz = ps.studiengang_kz
								AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Aufgenommener\')
						) OR EXISTS (
							-- Interessent with at least one payment and same degree program
							SELECT
								1
							FROM
								public.tbl_prestudent
							JOIN	public.tbl_konto k USING(person_id)
							WHERE
								tbl_prestudent.person_id = ps.person_id
								AND public.tbl_prestudent.studiengang_kz = ps.studiengang_kz
								AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Interessent\')
						)
					)
			      GROUP BY ps.person_id
				',
				array(
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
		$bankData = array();

		$dbModel = new DB_Model();

		// Persons

		// Get users that have been updated in tbl_person table
		$personResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT p.person_id
			  FROM public.tbl_person p
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE p.updateamum > s.last_update
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($personResult)) return $personResult;

		// If there are updated users
		if (hasData($personResult)) $persons = getData($personResult);

		// Prestudents

		// Get users that have been updated in tbl_prestudent = tbl_prestudentstatus table
		$prestudentsResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT ps.person_id
			  FROM public.tbl_prestudent ps
			  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE pss.insertamum > s.last_update
			    OR pss.updateamum > s.last_update
			    OR pss.datum > s.last_update
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($prestudentsResult)) return $prestudentsResult;

		// If there are updated users
		if (hasData($prestudentsResult)) $prestudents = getData($prestudentsResult);

		// Contacts

		// Get users that have been updated in tbl_kontakt table
		$contactsResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT k.person_id
			  FROM public.tbl_kontakt k
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE k.insertamum > s.last_update
			    OR k.updateamum > s.last_update
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($contactsResult)) return $contactsResult;

		// If there are updated users
		if (hasData($contactsResult)) $contacts = getData($contactsResult);

		// Addresses

		// Get users that have been updated in tbl_adresse table
		$addressesResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT a.person_id
			  FROM public.tbl_adresse a
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE a.insertamum > s.last_update
			    OR a.updateamum > s.last_update
		');

		// If error occurred while retrieving updated users from database then return the error
		if (isError($addressesResult)) return $addressesResult;

		// If there are updated users
		if (hasData($addressesResult)) $addresses = getData($addressesResult);

		// Bank data

		// Get users that have bank data updated
		$bankDataResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT bv.person_id
			  FROM public.tbl_bankverbindung bv
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE bv.insertamum > s.last_update
			    OR bv.updateamum > s.last_update
		');

		// If error occurred while retrieving updated bank data from database then return the error
		if (isError($bankDataResult)) return $bankDataResult;

		// If there are updated bank data
		if (hasData($bankDataResult)) $bankData = getData($bankDataResult);

		// Return a success that contains all the arrays merged together
		return success(uniquePersonIdArray(array_merge($persons, $contacts, $addresses, $prestudents, $bankData)));
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
			   AND m.mitarbeiter_uid IN (
			       SELECT sm.mitarbeiter_uid FROM sync.tbl_sap_mitarbeiter sm
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
	 * Gets all the active employees
	 */
	public function updateServices()
	{
		$dbModel = new DB_Model();

		// Gets all the employees
		$updateUsersResult = $dbModel->execReadOnlyQuery('
			SELECT vwm.person_id
			  FROM campus.vw_mitarbeiter vwm
			  JOIN public.tbl_benutzerfunktion bf USING (uid)
			 WHERE vwm.aktiv = TRUE
			   AND bf.funktion_kurzbz = \'oezuordnung\'
			   AND (bf.datum_von IS NULL OR bf.datum_von <= NOW())
			   AND (bf.datum_bis IS NULL OR bf.datum_bis >= NOW())
			   AND vwm.uid IN (
			       SELECT sm.mitarbeiter_uid FROM sync.tbl_sap_mitarbeiter sm
			   )
		      ORDER BY vwm.person_id DESC
		');

		// If error occurred while retrieving new users from database then return the error
		if (isError($updateUsersResult)) return $updateUsersResult;

		// Return a success that contains all the arrays merged together
		return success(getData($updateUsersResult));
	}

	/**
	 * Looks for new payments that have been created in FHC and stores their person id into a payment job input
	 */
	public function newPayments()
	{
		$this->_ci->load->library('extensions/FHC-Core-SAP/SyncPaymentsLib');

		$dbModel = new DB_Model();

		// Gets new permanent employees created in the last 42 hours
		$newPaymentsResult = $dbModel->execReadOnlyQuery('
			SELECT
				distinct person_id
			FROM
				public.tbl_konto bk
			WHERE
				bk.betrag < 0
				AND NOT EXISTS(SELECT 1 FROM sync.tbl_sap_salesorder WHERE buchungsnr = bk.buchungsnr)
				AND NOT EXISTS(SELECT 1 FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr)
				AND bk.buchungsnr_verweis IS NULL
				AND bk.buchungsdatum <= now()
				AND bk.buchungsdatum >= ?
				AND
				(
					EXISTS(
						-- Standard student
						SELECT
							1
						FROM
							public.tbl_prestudent
							JOIN public.tbl_student USING(prestudent_id)
							JOIN public.tbl_benutzer ON(uid = student_uid)
						WHERE
							tbl_prestudent.person_id = bk.person_id
							AND tbl_prestudent.studiengang_kz = bk.studiengang_kz
							AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Student\', \'Incoming\', \'Diplomand\', \'Unterbrecher\')
							AND tbl_benutzer.aktiv
					)
					OR
					EXISTS(
						-- Students form study program 10002 -> qualification course
						SELECT
							1
						FROM
							public.tbl_prestudent
						WHERE
							tbl_prestudent.person_id = bk.person_id
							AND tbl_prestudent.studiengang_kz = 10002
							AND get_rolle_prestudent(prestudent_id,null) IN (\'Student\', \'Incoming\', \'Diplomand\')
							AND EXISTS(SELECT
									1
								FROM
									public.tbl_prestudent
								WHERE
									tbl_prestudent.person_id = bk.person_id
									AND tbl_prestudent.studiengang_kz = bk.studiengang_kz
									AND get_rolle_prestudent(prestudent_id, NULL) IN (
										\'Student\', \'Incoming\', \'Diplomand\', \'Interessent\', \'Bewerber\', \'Aufgenommener\', \'Wartender\'
									)
							)
					)
					OR
					EXISTS(
						-- No benutzer
						SELECT
							1
						FROM
							public.tbl_prestudent
						WHERE
							tbl_prestudent.person_id = bk.person_id
							AND studiengang_kz = bk.studiengang_kz
							AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Aufgenommener\')
					)
					OR
					EXISTS(
						-- No benutzer and at least a payment of type StudiengebuehrAnzahlung (drittstaaten) and same degree program
						SELECT
							1
						FROM
							public.tbl_prestudent
						WHERE
							tbl_prestudent.person_id = bk.person_id
							AND studiengang_kz = bk.studiengang_kz
							AND bk.buchungstyp_kurzbz = \'StudiengebuehrAnzahlung\'
							AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Interessent\')
					)
					OR
					EXISTS (
						-- All booking types from the configuration that are associated with "ETW."
						SELECT
							1
						FROM
							public.tbl_prestudent
						WHERE
							tbl_prestudent.person_id = bk.person_id
							AND buchungstyp_kurzbz IN ?
							AND bk.studiengang_kz = 0
							AND get_rolle_prestudent(prestudent_id, NULL) IN (\'Interessent\', \'Student\', \'Aufgenommener\', \'Wartender\')
					)
				)
		', array(SyncPaymentsLib::BUCHUNGSDATUM_SYNC_START, array_keys($this->_ci->config->item(SyncPaymentsLib::PAYMENTS_FH_COST_CENTERS_BUCHUNG))));

		return $newPaymentsResult;
	}

	/**
	 * Looks for new credit memo
	 */
	public function creditMemo()
	{
		$dbModel = new DB_Model();

		// Get users that have updated credit memo
		$creditMemoResult = $dbModel->execReadOnlyQuery(
			'SELECT ko.person_id
			  FROM public.tbl_konto ko
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE ko.betrag > 0
			   AND ko.buchungstyp_kurzbz IN ?
			   AND ko.buchungsnr NOT IN (
				SELECT kos.buchungsnr_verweis
				  FROM public.tbl_konto kos
				 WHERE kos.buchungsnr_verweis = ko.buchungsnr
			)
		      GROUP BY ko.person_id
			',
			array(
				$this->_ci->config->item(self::PAYMENTS_BOOKING_TYPE_ORGANIZATIONS)
			)
		);

		return $creditMemoResult;
	}
	
	public function creditSonstigeGutschrift()
	{
		$this->_ci->load->library('extensions/FHC-Core-SAP/SyncPaymentsLib');
		
		$dbModel = new DB_Model();

		// Get users that have updated credit memo
		$creditMemoResult = $dbModel->execReadOnlyQuery(
			'SELECT ko.person_id
			  FROM public.tbl_konto ko
			  JOIN sync.tbl_sap_students s USING(person_id)
			 WHERE ko.betrag > 0
			   AND ko.buchungstyp_kurzbz IN ?
			   AND ko.buchungsdatum >= ?
			   AND ko.buchungsnr NOT IN (
				SELECT kos.buchungsnr_verweis
				  FROM public.tbl_konto kos
				 WHERE kos.buchungsnr_verweis = ko.buchungsnr
			)
		      GROUP BY ko.person_id
			',
			array(
				array_keys($this->_ci->config->item(self::PAYMENTS_BOOKING_TYPE_OTHER_CREDITS)),
				SyncPaymentsLib::BUCHUNGSDATUM_SYNC_START
			)
		);

		return $creditMemoResult;
	}

	/**
	 *
	 */
	public function newEmployees()
	{
		$jobInput = null;

		$dbModel = new DB_Model();

		$newUsersResult = $dbModel->execReadOnlyQuery('
			SELECT m.mitarbeiter_uid AS uid
			FROM public.tbl_person p
			JOIN public.tbl_benutzer b USING(person_id)
			JOIN public.tbl_mitarbeiter m ON (m.mitarbeiter_uid = b.uid)
			LEFT JOIN sync.tbl_sap_mitarbeiter sm ON (m.mitarbeiter_uid = sm.mitarbeiter_uid)
			JOIN (
				SELECT DISTINCT ON (mitarbeiter_uid) *
				FROM hr.tbl_dienstverhaeltnis dv
				WHERE (
					(dv.bis >= NOW() OR dv.bis IS NULL)
					AND
					(dv.von::DATE <= (NOW() + INTERVAL ?\' Days\')::DATE)
				)
				AND dv.vertragsart_kurzbz IN ?
			    ORDER BY mitarbeiter_uid, von
			) dv ON dv.mitarbeiter_uid = m.mitarbeiter_uid
			WHERE m.fixangestellt = TRUE
			AND sm.mitarbeiter_uid IS NULL
			AND b.aktiv
			AND personalnummer > 0
		', array(
			$this->_ci->config->item(self::BEFORE_START),
			$this->_ci->config->item(self::FHC_CONTRACT_TYPES)
		));

		// If error occurred while retrieving new users from database then return the error
		if (isError($newUsersResult)) return $newUsersResult;

		// If new users are present
		if (hasData($newUsersResult))
		{
			$jobInput = json_encode(getData($newUsersResult));
		}

		return success($jobInput);
	}

	public function updateEmployees()
	{
		$persons = array();
		$addresses = array();
		$banks = array();

		$dbModel = new DB_Model();

		$personResult = $dbModel->execReadOnlyQuery('
			SELECT m.mitarbeiter_uid AS uid
			FROM public.tbl_person p
			JOIN public.tbl_benutzer b USING(person_id)
			JOIN public.tbl_mitarbeiter m ON (m.mitarbeiter_uid = b.uid)
			JOIN sync.tbl_sap_mitarbeiter sm ON(sm.mitarbeiter_uid = m.mitarbeiter_uid)
			WHERE (p.updateamum > sm.last_update
				OR sm.last_update IS NULL)
				AND m.mitarbeiter_uid NOT IN ?
			GROUP BY m.mitarbeiter_uid
		', array($this->_ci->config->item(self::EMPLOYEE_BLACKLIST)));

		if (isError($personResult)) return $personResult;

		if (hasData($personResult)) $persons = getData($personResult);

		$addressesResult = $dbModel->execReadOnlyQuery('
			SELECT m.mitarbeiter_uid AS uid
			FROM public.tbl_person p
			JOIN public.tbl_adresse a USING(person_id)
			JOIN public.tbl_benutzer b USING(person_id)
			JOIN public.tbl_mitarbeiter m ON (m.mitarbeiter_uid = b.uid)
			JOIN sync.tbl_sap_mitarbeiter sm ON(sm.mitarbeiter_uid = m.mitarbeiter_uid)
			WHERE (a.updateamum > sm.last_update
				OR sm.last_update IS NULL)
				AND m.mitarbeiter_uid NOT IN ?
			GROUP BY m.mitarbeiter_uid
		', array($this->_ci->config->item(self::EMPLOYEE_BLACKLIST)));

		if (isError($addressesResult)) return $addressesResult;

		if (hasData($addressesResult)) $addresses = getData($personResult);

		$banksResult = $dbModel->execReadOnlyQuery('
			SELECT m.mitarbeiter_uid AS uid
			FROM public.tbl_person p
			JOIN public.tbl_bankverbindung ba USING(person_id)
			JOIN public.tbl_benutzer b USING(person_id)
			JOIN public.tbl_mitarbeiter m ON (m.mitarbeiter_uid = b.uid)
			JOIN sync.tbl_sap_mitarbeiter sm ON(sm.mitarbeiter_uid = m.mitarbeiter_uid)
			WHERE (ba.updateamum > sm.last_update
				OR sm.last_update IS NULL)
				AND m.mitarbeiter_uid NOT IN ?
			GROUP BY m.mitarbeiter_uid
		', array($this->_ci->config->item(self::EMPLOYEE_BLACKLIST)));

		if (isError($banksResult)) return $banksResult;

		if (hasData($banksResult)) $banks = getData($banksResult);

		return success(uniqudMitarbeiterUidArray(array_merge($persons, $addresses, $banks)));
	}

	public function updateEmployeesWorkAgreement()
	{
		$functions = array();

		$dbModel = new DB_Model();

		$personResult = $dbModel->execReadOnlyQuery('
			SELECT dv.mitarbeiter_uid AS uid
			FROM hr.tbl_dienstverhaeltnis dv
				JOIN hr.tbl_vertragsbestandteil vbst USING (dienstverhaeltnis_id)
				JOIN sync.tbl_sap_mitarbeiter sm ON(sm.mitarbeiter_uid = dv.mitarbeiter_uid)
			WHERE (dv.updateamum > sm.last_update_workagreement
				OR sm.last_update_workagreement IS NULL
				OR vbst.updateamum > sm.last_update_workagreement
				OR (
					(
						current_date > (SELECT (sdv.bis::date + INTERVAL ?\' Days\')
										FROM hr.tbl_dienstverhaeltnis sdv
										WHERE sdv.mitarbeiter_uid = dv.mitarbeiter_uid
										ORDER by sdv.bis DESC
										LIMIT 1)
					)
					AND
					(
						 sm.last_update_workagreement < (SELECT (sdv.bis::date + INTERVAL ?\' Days\')
														FROM hr.tbl_dienstverhaeltnis sdv
														WHERE sdv.mitarbeiter_uid = dv.mitarbeiter_uid
														ORDER by sdv.bis DESC
														LIMIT 1)
					)
				)
			)
			AND dv.mitarbeiter_uid NOT IN ?
			GROUP BY dv.mitarbeiter_uid
		', array($this->_ci->config->item(self::AFTER_END),
				$this->_ci->config->item(self::AFTER_END),
				$this->_ci->config->item(self::EMPLOYEE_BLACKLIST)
			)
		);

		if (isError($personResult)) return $personResult;

		if (hasData($personResult)) $functions = getData($personResult);

		return success(uniqudMitarbeiterUidArray(array_merge($functions)));
	}
	
	public function setEmployeeOnService()
	{
		$dbModel = new DB_Model();

		$personResult = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT tbl_person.person_id
			FROM sync.tbl_sap_services
				JOIN public.tbl_person ON tbl_sap_services.person_id = tbl_person.person_id
				JOIN public.tbl_benutzer ON tbl_person.person_id = tbl_benutzer.person_id
				JOIN public.tbl_mitarbeiter ON tbl_benutzer.uid = tbl_mitarbeiter.mitarbeiter_uid
				JOIN sync.tbl_sap_mitarbeiter ON tbl_mitarbeiter.mitarbeiter_uid = tbl_sap_mitarbeiter.mitarbeiter_uid');
		// If error occurred while retrieving new users from database then return the error
		if (isError($personResult)) return $personResult;
		
		// Return a success that contains all the arrays merged together
		return success(getData($personResult));
	}
	
	public function checkEmployeesDVs()
	{
		$dbModel = new DB_Model();

		$personResult = $dbModel->execReadOnlyQuery('
				SELECT DISTINCT tbl_dienstverhaeltnis.mitarbeiter_uid AS uid
				FROM sync.tbl_sap_mitarbeiter
					JOIN hr.tbl_dienstverhaeltnis ON tbl_sap_mitarbeiter.mitarbeiter_uid = tbl_dienstverhaeltnis.mitarbeiter_uid
					JOIN public.tbl_mitarbeiter ON tbl_dienstverhaeltnis.mitarbeiter_uid = tbl_mitarbeiter.mitarbeiter_uid
					JOIN public.tbl_benutzer ON tbl_mitarbeiter.mitarbeiter_uid = tbl_benutzer.uid
					JOIN public.tbl_person ON tbl_benutzer.person_id = tbl_person.person_id
				WHERE EXISTS (
					SELECT 1
					FROM hr.tbl_dienstverhaeltnis dv
					WHERE dv.mitarbeiter_uid = tbl_dienstverhaeltnis.mitarbeiter_uid
						AND dv.vertragsart_kurzbz IN ?
						AND (dv.von <= NOW())
						AND (dv.bis >= NOW() OR dv.bis IS NULL)
				)
				ORDER BY tbl_dienstverhaeltnis.mitarbeiter_uid;
				', array($this->_ci->config->item(self::FHC_CONTRACT_TYPES)));
		
		// If error occurred while retrieving new users from database then return the error
		if (isError($personResult)) return $personResult;
		
		// Return a success that contains all the arrays merged together
		return success(getData($personResult));
	}
}

