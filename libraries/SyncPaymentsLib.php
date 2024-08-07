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

use \stdClass as stdClass;

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncPaymentsLib
{
	// Jobs types used by this lib
	const SAP_PAYMENT_CREATE = 'SAPPaymentCreate';
	const SAP_PAYMENT_GUTSCHRIFT = 'SAPPaymentGutschrift';
	const SAP_SONSTIGE_PAYMENT_GUTSCHRIFT = 'SAPSonstigeGutschrift';

	// Prefix for SAP SOAP id calls
	const CREATE_PAYMENT_PREFIX = 'CP';
	const BUCHUNGSDATUM_SYNC_START = '2019-09-01';

	// Credit memo sales order
	const CREDIT_MEMO_SOI = 'CREDIT MEMO';

	// Incoming/outgoing grant config entry name
	const INCOMING_OUTGOING_GRANT = 'payments_incoming_outgoing_grant';
	const PAYMENTS_BOOKING_TYPE_ORGANIZATIONS = 'payments_booking_type_organizations';

	const PAYMENTS_FH_COST_CENTERS_BUCHUNG = 'payments_fh_cost_centers_buchung';
	
	// International office sales unit party id config entry name
	const INTERNATIONAL_OFFICE_SALES_UNIT_PARTY_ID = 'payments_international_office_sales_unit_party_id';

	const PAYMENTS_BOOKING_TYPE_OTHER_CREDITS = 'payments_other_credits';
	const PAYMENTS_BOOKING_TYPE_OTHER_CREDITS_COMPANY = 'payments_other_credits_company';

	//
	const INVOICES_EXISTS_SAP = 'INVOICES_EXISTS_SAP';
	const INVOICES_TO_BE_SYNCED = 'INVOICES_TO_BE_SYNCED';
	const INVOICES_NOT_RELEVANT = 'INVOICES_NOT_RELEVANT';
	//
	const GMBH_INVOICES_EXISTS = 'GMBH_INVOICES_EXISTS';
	const FHTW_INVOICES_EXISTS = 'FHTW_INVOICES_EXISTS';
	//
	const SESSION_NAME_CIS_INVOICES = 'CIS_INVOICES';
	const SESSION_NAME_CIS_INVOICES_ELEMENT = 'INVOICE_LIST';
	//
	const GMBH_LEHRGAENGE_LIST = array(-18, -30);

	//
	const GUTSCHRIFT_CODE = "CCM";

	private $_ci; // Code igniter instance
	private $_isInvoiceClearedCache; // Cache Invoice Status Results
	private $_getInvoiceIDFromSalesOrderCache; // Cache Sales Order results

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads the LogLib with the needed parameters to log correctly from this library
		$this->_ci->load->library(
			'LogLib',
			array(
				'classIndex' => 3,
				'functionIndex' => 3,
				'lineIndex' => 2,
				'dbLogType' => 'job', // required
				'dbExecuteUser' => 'Jobs queue system',
				'requestId' => 'JQW',
				'requestDataFormatter' => function($data) {
					return json_encode($data);
				}
			),
			'LogLibSAP'
		);

		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QuerySalesOrderIn_model', 'QuerySalesOrderInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryCustomerInvoiceIn_model', 'QueryCustomerInvoiceInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryDocumentOutputRequestIn_model', 'QueryDocumentOutputRequestInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageSalesOrderIn_model', 'ManageSalesOrderInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageCustomerInvoiceRequestIn_model', 'ManageCustomerInvoiceRequestInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/SORelease_model', 'SOReleaseModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/Y95KEPJZY_WS_CRPE_ManageRecPayEntry_model', 'ManageRecPayEntryModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/RPFINGLAU08_Q0002_model', 'RPFINGLAU08_Q0002Model');
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');
		$this->_ci->load->model('organisation/Studiengang_model', 'StudiengangModel');

		// Loads SAPSalesOrderModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPSalesOrder_model', 'SAPSalesOrderModel');
		
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPStudentsGutschriften_model', 'SAPStudentsGutschriftenModel');

		// Loads Payment configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Payments');
		$this->_ci->config->load('extensions/FHC-Core-SAP/Users');
		$this->_ci->config->load('extensions/FHC-Core-SAP/Projects');
		$this->_ci->load->helper('hlp_authentication');
		
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 *
	 */
	public function listInvoicesByPersonId($person_id)
	{
		$dbModel = new DB_Model();

		// Get the sap_user_id using the given person_id
		$sapStudentResult = $dbModel->execReadOnlyQuery('
			SELECT ss.sap_user_id
			  FROM sync.tbl_sap_students ss
			 WHERE ss.person_id = ?
		', array(
			$person_id
		));

		// If data have been found
		if (!hasData($sapStudentResult)) return error('Person id not found');

		// Calls SAP to get all the invoices related to the given SAP user
		$customerInvoiceResult = $this->_ci->QueryCustomerInvoiceInModel->findByElements(
			array(
				'CustomerInvoiceSelectionByElements' => array(
					'SelectionByBillToPartyID' => array(
						'LowerBoundaryIdentifier' => getData($sapStudentResult)[0]->sap_user_id,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);

		return $customerInvoiceResult;
	}

	/**
	 *
	 */
	public function listInvoices($person_id)
	{
		$dbModel = new DB_Model();

		// Get the sap_user_id using the given person_id
		$sapStudentResult = $dbModel->execReadOnlyQuery('
			SELECT ss.sap_user_id
			  FROM sync.tbl_sap_students ss
			 WHERE ss.person_id = ?
		', array(
			$person_id
		));

		// If data have been found
		if (!hasData($sapStudentResult)) return error('Person id not found');

		// Calls SAP to get all the invoices related to the given SAP user
		$customerInvoiceResult = $this->_ci->QueryCustomerInvoiceInModel->findByElements(
			array(
				'CustomerInvoiceSelectionByElements' => array(
					'SelectionByBillToPartyID' => array(
						'LowerBoundaryIdentifier' => getData($sapStudentResult)[0]->sap_user_id,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);

		// List of SAP invoices
		$data = array();
		// List of SAP invoices related to a sale order
		$sapInvoicesWithSO = array();

		// If there are data in the SAP response and the property CustomerInvoice is set
		if (hasData($customerInvoiceResult) && isset(getData($customerInvoiceResult)->CustomerInvoice))
		{
			$data = getData($customerInvoiceResult)->CustomerInvoice;
		}
		
		// If there is only one Invoice it is just an object instead of an array of objects
		if (isEmptyArray($data)) $data = array($data);

		// For each SAP invoice
		foreach ($data as $customerInvoice)
		{
			// If the invoice is not a "Gutschrift" => self::GUTSCHRIFT_CODE
			if (isset($customerInvoice->ProcessingTypeCode) && $customerInvoice->ProcessingTypeCode != self::GUTSCHRIFT_CODE)
			{
				// If The Item property exists
				if (isset($customerInvoice->Item))
				{
					// If the item property is an array
					if (!isEmptyArray($customerInvoice->Item))
					{
						// For each sale order
						foreach ($customerInvoice->Item as $ciItem)
						{
							// Check if the sale order id exists
							if (isset($ciItem->SalesOrderReference)
								&& isset($ciItem->SalesOrderReference->ID)
								&& isset($ciItem->SalesOrderReference->ID->_))
							{
								// Add the SAP invoice to the list
								$sapInvoicesWithSO[$ciItem->SalesOrderReference->ID->_] = $customerInvoice;
							}
						}
					} // Otherwise check if the sale order id exists
					elseif (isset($customerInvoice->Item->SalesOrderReference)
						&& isset($customerInvoice->Item->SalesOrderReference->ID)
						&& isset($customerInvoice->Item->SalesOrderReference->ID->_))
					{
						// Add the SAP invoice to the list
						$sapInvoicesWithSO[$customerInvoice->Item->SalesOrderReference->ID->_] = $customerInvoice;
					}
				}
			}
		}

		// Get the info from the tbl_konto database table
		$sapSOsResult = $dbModel->execReadOnlyQuery('
			SELECT k.buchungsnr,
				k.buchungstext,
				k.studiensemester_kurzbz,
				k.betrag,
				sso.sap_sales_order_id,
				(SELECT SUM(kk.betrag) + k.betrag FROM public.tbl_konto kk WHERE kk.buchungsnr_verweis = k.buchungsnr) AS paid,
				k.studiengang_kz
			  FROM public.tbl_konto k
		     LEFT JOIN sync.tbl_sap_salesorder sso USING(buchungsnr)
			 WHERE k.person_id = ?
			   AND k.buchungsnr_verweis IS NULL
			   AND (k.studiengang_kz >= 0 OR k.studiengang_kz IN ?)
			   AND k.buchungsdatum >= ?
		      ORDER BY k.buchungsdatum DESC
		', array(
			$person_id, self::GMBH_LEHRGAENGE_LIST, self::BUCHUNGSDATUM_SYNC_START
		));

		// No sale orders in database
		if (!hasData($sapSOsResult)) return success('Currently there are no sales orders');

		// List of invoices
		$resultInvoices = new stdClass();
		// List of invoices that exists on SAP
		$resultInvoices->{self::INVOICES_EXISTS_SAP} = array();
		// List of invoices that do not exist on SAP
		$resultInvoices->{self::INVOICES_TO_BE_SYNCED} = array();
		// List of invoices that are not going to be synced
		$resultInvoices->{self::INVOICES_NOT_RELEVANT} = array();
		// By default no GMBH invoices
		$resultInvoices->{self::GMBH_INVOICES_EXISTS} = false;
		// By default no FHTW invoices
		$resultInvoices->{self::FHTW_INVOICES_EXISTS} = false;

		// For each database invoice
		foreach (getData($sapSOsResult) as $sapSO)
		{
			// Object that represents a record: SAP invoice data + DB invoice data
			$resultInvoiceObj = new stdClass();

			// DB invoice data
			$resultInvoiceObj->uid = getAuthUID();
			$resultInvoiceObj->buchungsnr = $sapSO->buchungsnr;
			$resultInvoiceObj->bezeichnung = $sapSO->buchungstext;
			$resultInvoiceObj->studiensemester = $sapSO->studiensemester_kurzbz;
			$resultInvoiceObj->betrag = $sapSO->betrag * -1;
			$resultInvoiceObj->paid = $sapSO->paid != null && $sapSO->paid == 0;
			$resultInvoiceObj->partial = $resultInvoiceObj->paid ? $resultInvoiceObj->betrag : 0;
			$resultInvoiceObj->studiengang_kz = $sapSO->studiengang_kz;

			// If there are invoices from the FHTW
			if ($sapSO->studiengang_kz >= 0) $resultInvoices->{self::FHTW_INVOICES_EXISTS} = true;
			// If there are invoices from the GMBH
			if ($sapSO->studiengang_kz < 0) $resultInvoices->{self::GMBH_INVOICES_EXISTS} = true;

			// SAP invoice data
			$resultInvoiceObj->datum = null;
			$resultInvoiceObj->faellingAm = null;
			$resultInvoiceObj->email = null;
			$resultInvoiceObj->status = null;
			$resultInvoiceObj->invoiceUUID = null;

			// If the related SAP invoice exists for this sale order
			if (array_key_exists($sapSO->sap_sales_order_id, $sapInvoicesWithSO))
			{
				// Get the related SAP invoice to the current sale order
				$sapInvoice = $sapInvoicesWithSO[$sapSO->sap_sales_order_id];

				// SAP invoice creation date formatted
				if (isset($sapInvoice->CashDiscountTerms) && isset($sapInvoice->CashDiscountTerms->PaymentBaselineDate))
				{
					$resultInvoiceObj->datum = $sapInvoice->CashDiscountTerms->PaymentBaselineDate;
					$resultInvoiceObj->datum = substr($resultInvoiceObj->datum, 0, 10);
					$resultInvoiceObj->datum = date('d.m.y', strtotime($resultInvoiceObj->datum));
				}

				// SAP invoice expiring date formatted
				if (isset($sapInvoice->CashDiscountTerms) && isset($sapInvoice->CashDiscountTerms->FullPaymentEndDate))
				{
					$resultInvoiceObj->faellingAm = $sapInvoice->CashDiscountTerms->FullPaymentEndDate;
					$resultInvoiceObj->faellingAm = substr($resultInvoiceObj->faellingAm, 0, 10);
					$resultInvoiceObj->faellingAm = date('d.m.y', strtotime($resultInvoiceObj->faellingAm));
				}

				// SAP invoice recipient data
				if (isset($sapInvoice->BillToParty) && isset($sapInvoice->BillToParty->Address)
					&& isset($sapInvoice->BillToParty->Address->EmailURI) && isset($sapInvoice->BillToParty->Address->EmailURI->_))
				{
					$resultInvoiceObj->email = $sapInvoice->BillToParty->Address->EmailURI->_;
				}

				// SAP invoice status
				if (isset($sapInvoice->Status)) $resultInvoiceObj->status = $sapInvoice->Status;

				// SAP invoice UUID
				if (isset($sapInvoice->UUID) && isset($sapInvoice->UUID->_)) $resultInvoiceObj->invoiceUUID = $sapInvoice->UUID->_;

				// If this invoice does not exists in the array
				if (!array_key_exists($sapInvoice->ID->_, $resultInvoices->{self::INVOICES_EXISTS_SAP}))
				{
					$resultInvoices->{self::INVOICES_EXISTS_SAP}[$sapInvoice->ID->_] = array();
				}

				// Save a record for this invoice
				$resultInvoices->{self::INVOICES_EXISTS_SAP}[$sapInvoice->ID->_][] = $resultInvoiceObj;
			}
			else // otherwise save the invoice in the _not_ existing on SAP list
			{
				// If already paid
				if ($resultInvoiceObj->paid)
				{
					// It will not be synced
					$resultInvoices->{self::INVOICES_NOT_RELEVANT}[] = $resultInvoiceObj;
				}
				else // otherwise it is going to be synced
				{
					// Save a record for this invoice
					$resultInvoices->{self::INVOICES_TO_BE_SYNCED}[] = $resultInvoiceObj;
				}
			}
		}

		return success($resultInvoices);
	}

	/**
	 * Get the PDF for the given invoice
	 */
	public function getSapInvoicePDF($invoiceUuid)
	{
                // Get the document from SAP using the invoice UUID
                $sapDocumentResult = $this->getDocumentUUID($invoiceUuid);

		// SAP returned data about this invoice
		if (hasData($sapDocumentResult))
		{
			$sapDocument = getData($sapDocumentResult);

			// If the structure of the returned data is fine
			if (isset($sapDocument->DocumentOutputRequestInformation)
				&& isset($sapDocument->DocumentOutputRequestInformation->DocumentUUID)
				&& isset($sapDocument->DocumentOutputRequestInformation->DocumentUUID->_))
			{
	                	// Get the PDF document from SAP using the document UUID
        	        	$sapPDFResult = $this->getPDF($sapDocument->DocumentOutputRequestInformation->DocumentUUID->_);
			}

			// If SAP returned valid PDF data about this document
			if (hasData($sapPDFResult))
			{
				$sapPDF = getData($sapPDFResult);

				// If the structure of the returned data is fine
				if (isset($sapPDF->DocumentOutputRequestPDF)
					&& isset($sapPDF->DocumentOutputRequestPDF->OutputPDF)
					&& isset($sapPDF->DocumentOutputRequestPDF->OutputPDF->_))
				{
					// Return the PDF
					return success($sapPDF->DocumentOutputRequestPDF->OutputPDF->_);
				}
			}
		}

		return error('Generic error');
	}

	/**
	 * Get the documents for the given invoice
	 */
	public function getDocumentUUID($invoiceUUID)
	{
		return $this->_ci->QueryDocumentOutputRequestInModel->findByElements(
			array(
				'DocumentOutputRequestSelectionByElements' => array(
					'SelectionByHostObjectUUID' => array(
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1,
						'LowerBoundaryUUID' => $invoiceUUID
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);
	}

	/**
	 * Gets the PDF version of the given document
	 */
	public function getPDF($documentUUID)
	{
		return $this->_ci->QueryDocumentOutputRequestInModel->readOutputPDF(
			array(
				'DocumentOutputRequestPDFInformation' => array(
					'ReadByDocumentUUID' => $documentUUID
				)
			)
		);
	}

	/**
	 * Check if a SalesOrder is already fully paid
	 * @param $salesOrderId ID of the SalesOrder
	 * @param $studentId SAP ID of the Student / Customer
	 * @return success true if SalesOrder is cleard, success false if not
	 */
	public function isSalesOrderPaid($salesOrderId, $studentId)
	{
		$id_arr = array();

		// Take results from cache if available
		if (isset($this->_getInvoiceIDFromSalesOrderCache[$salesOrderId]))
		{
			$id_arr = $this->_getInvoiceIDFromSalesOrderCache[$salesOrderId];
		}
		else
		{
			// Get Invoices for Sales Order
			$invoiceResult = $this->_getInvoiceIDFromSalesOrder($salesOrderId);

			if (isSuccess($invoiceResult))
			{
				if (hasData($invoiceResult))
				{
					$id_arr = getData($invoiceResult);
					// Add Data to cache for later usage
					$this->_getInvoiceIDFromSalesOrderCache[$salesOrderId] = $id_arr;
				}
				else
					$this->_getInvoiceIDFromSalesOrderCache[$salesOrderId] = array();
			}
			else
			{
				$this->_ci->LogLibSAP->logErrorDB("Failed to get Invoices for SalesOrder: ".getError($invoiceResult));
				return error("Failed to get Invoices for SalesOrder: ".getError($invoiceResult));
			}
		}

		if (!isEmptyArray($id_arr))
		{
			// If there are Invoices, check if there are open amounts for this invoices
			foreach ($id_arr as $invoiceId)
			{
				// If there are open Amounts, its not cleared
				$isInvoiceClearedResult = $this->_isInvoiceCleared($studentId, $invoiceId);

				if (isSuccess($isInvoiceClearedResult))
				{
					if (!hasData($isInvoiceClearedResult))
					{
						$this->_ci->LogLibSAP->logWarningDB('Offene Posten für Rechnung '.$invoiceId.' gefunden -> Nicht bezahlt');
						return success(false);
					}
				}
				else
				{
					$this->_ci->LogLibSAP->logErrorDB("Invoice Clearance check failed: ".getError($isInvoiceClearedResult));
					return error('Invoice Clearance check failed');
				}
			}

			// PAID
			// If all invoices are cleared the SalesOrder is paid
			return success(true);
		}
		else
		{
			// If no Invoice is available it's not paid
			$this->_ci->LogLibSAP->logWarningDB('Keine Rechnung gefunden -> nicht bezahlt: '.$salesOrderId.' - '.$studentId);
			return success(false);
		}

		return error("isSalesOrderPaid in SyncPaymentsLib exited unexpected");
	}

	/**
	 * Return the raw result of SAP->QuerySalesOrderIn->FindByElements->SalesOrderSelectionByElements
	 */
	public function getPaymentById($id)
	{
		// Calls SAP to find a user with the given email
		return $this->_ci->QuerySalesOrderInModel->findByElements(
			array(
				'SalesOrderSelectionByElements' => array(
					'SelectionByID' => array(
						'LowerBoundaryID' => $id,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);
	}
	
	public function createSonstigeGutschrift($personIdArray)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($personIdArray)) return success('No gutschrift to be created');

		// For each person id
		foreach ($personIdArray as $person_id)
		{
			// Get the SAP user id for this person
			$sapUserIdResult = $this->_getSAPUserId($person_id);

			// If an error occurred then return it
			if (isError($sapUserIdResult)) return $sapUserIdResult;

			// If no data have been found
			if (!hasData($sapUserIdResult))
			{
				// Then log it
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find the sap user id with this person id: '.$person_id);
				continue; // and continue to the next one
			}

			// Here the sap user id!
			$sapUserId = getData($sapUserIdResult)[0]->sap_user_id;

			// Get all Open Payments of Person that are not yet transfered to SAP
			$resultCreditMemoResult = $this->_getUnsyncedCreditMemo($person_id, array_keys($this->_ci->config->item(self::PAYMENTS_BOOKING_TYPE_OTHER_CREDITS)));
			
			// If an error occurred then return it
			if (isError($resultCreditMemoResult)) return $resultCreditMemoResult;
			
			// If no data have been found
			if (!hasData($resultCreditMemoResult))
			{
				// Then log it
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find a credit memos with this person id: '.$person_id);
				continue; // and continue to the next one
			}
			// For each payment found
			foreach (getData($resultCreditMemoResult) as $singlePayment)
			{
				$studiengang = $this->_ci->StudiengangModel->load(array('studiengang_kz' => $singlePayment->studiengang_kz));
				if (isError($studiengang)) return $studiengang;
				$studiengang_oe = getData($studiengang)[0]->oe_kurzbz;

				$oeRoot = $this->_ci->MessageTokenModel->getOeRoot($studiengang_oe);
				if (isError($oeRoot)) return $oeRoot;
				$oeRoot = getData($oeRoot)[0]->oe_kurzbz;

				$dbModel = new DB_Model();
				$sapOe = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz = ?
								', array($oeRoot));

				if (isError($sapOe)) return $sapOe;

				if (!hasData($sapOe))
				{
					$this->_ci->LogLibSAP->logWarningDB('Could not get SAP OE for '. $oeRoot);
					continue; // and continue to the next one
				}

				$company = getData($sapOe)[0]->oe_kurzbz_sap;

				$dbModel = new DB_Model();
				$qry = "SELECT nextval('sync.tbl_sap_students_gutschriften_id_seq')";
				$nextValResult = $dbModel->execReadOnlyQuery($qry);
				if (isError($nextValResult)) return $nextValResult;
				$nextValResult = getData($nextValResult)[0];
				$id = $nextValResult->nextval;

				$zahlungsCheck = $this->_ci->SAPStudentsGutschriftenModel->load(array('buchungsnr' => $singlePayment->buchungsnr));

				if (isError($zahlungsCheck)) return $zahlungsCheck;

				if (hasData($zahlungsCheck))
				{
					$zahlungsCheck = getData($zahlungsCheck)[0];
					
					if (!($zahlungsCheck->done))
					{
						$this->_ci->LogLibSAP->logWarningDB("counter booking should already exist " . $zahlungsCheck->buchungsnr);
					}

					$createPayReceivables = $this->createPayReceivablesEntry($zahlungsCheck->id, $singlePayment);
					if (isError($createPayReceivables)) return $createPayReceivables;
					continue;
				}

				$data = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
						'UUID' => generateUUID()
					),
					'BO_CRPE_OpenItemRequest' => array(
						'ID' => $id,
						'CompanyID' => $this->_ci->config->item(self::PAYMENTS_BOOKING_TYPE_OTHER_CREDITS_COMPANY),
						'BusinessPartnerInternalID' => $sapUserId,
						'Description' => mb_substr($singlePayment->buchungstext, 0, 40),
						'PostingDate' => $singlePayment->buchungsdatum,
						'DocumentDate' => $singlePayment->buchungsdatum,
						'DueDate' => $singlePayment->buchungsdatum,
						'NetAmount' => array(
							'currencyCode' => 'EUR',
							'_' => str_replace(',', '.', $singlePayment->betrag),
						),
						'TypeCode' => '2',
						'GLAccountOtherLiabilities' => $this->_ci->config->item(self::PAYMENTS_BOOKING_TYPE_OTHER_CREDITS)[$singlePayment->buchungstyp_kurzbz]['GLAccountOtherLiabilities']
					)
				);

				$createResult = $this->_ci->ManageRecPayEntryModel->create($data);
				if (isError($createResult)) return $createResult;

				$createResult = getData($createResult);
				
				if (isset($createResult->BO_CRPE_OpenItemRequest) &&
					isset($createResult->BO_CRPE_OpenItemRequest->SAP_UUID) &&
					isset($createResult->BO_CRPE_OpenItemRequest->ID))
				{
					$insertResult = $this->_ci->SAPStudentsGutschriftenModel->insert(
						array('id' => $id,
							'buchungsnr' => $singlePayment->buchungsnr)
					);
					
					if (isError($insertResult)) return $insertResult;
					$createPayReceivables = $this->createPayReceivablesEntry($id, $singlePayment);
					if (isError($createPayReceivables)) return $createPayReceivables;
				}
				else
				{
					if (isset($createResult->Log) && isset($createResult->Log->Item))
					{
						if (!isEmptyArray($createResult->Log->Item))
						{
							foreach ($createResult->Log->Item as $item)
							{
								if (isset($item->Note))
								{
									$this->_ci->LogLibSAP->logWarningDB($item->Note.' for create gutschrift/rechnung: '.$id);
								}
							}
						}
					}
					elseif ($createResult->Log->Item->Note)
					{
						$this->_ci->LogLibSAP->logWarningDB(
							$createResult->Log->Item->Note.' for create gutschrift/rechnung: '. $id
						);
					}
					else
					{
						$this->_ci->LogLibSAP->logWarningDB('SAP did not return ID sonstige gutschrift/rechnung: ' . $id);
					}
				}
			}
		}

		return success('SAP credit memo created successfully');
	}

	public function createPayReceivablesEntry($id, $singlePayment)
	{
		$data = array(
			'BasicMessageHeader' => array(
				'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
				'UUID' => generateUUID()
			),
			'BO_CRPE_OpenItemRequest' => array(
				'ID' => $id
			)
		);
		$createPayReceivablesEntry = $this->_ci->ManageRecPayEntryModel->createPayReceivablesEntry($data);

		if (isError($createPayReceivablesEntry)) return $createPayReceivablesEntry;
		$createPayReceivablesEntry = getData($createPayReceivablesEntry);

		if (isset($createPayReceivablesEntry->Log) &&
			isset($createPayReceivablesEntry->Log->Item))
		{
			if (!isEmptyArray($createPayReceivablesEntry->Log->Item))
			{
				foreach ($createPayReceivablesEntry->Log->Item as $item)
				{
					if (isset($item->Note))
					{
						$this->_ci->LogLibSAP->logWarningDB(
							$item->Note.' for createPayReceivablesEntry gutschrift/rechnung: '. $id
						);
					}
				}
			}
			elseif ($createPayReceivablesEntry->Log->Item->Note)
			{
				$note = $createPayReceivablesEntry->Log->Item->Note;

				if (strpos($note, 'Action') !== false && strpos($note, 'executed') !== false)
				{
					$kontoResult = $this->_ci->KontoModel->insert(
						array(
							'person_id' => $singlePayment->person_id,
							'studiengang_kz' => $singlePayment->studiengang_kz,
							'studiensemester_kurzbz' => $singlePayment->studiensemester_kurzbz,
							'buchungsnr_verweis' => $singlePayment->buchungsnr,
							'betrag' => str_replace(',', '.', $singlePayment->betrag*(-1)),
							'buchungsdatum' => date('Y-m-d'),
							'buchungstext' => $singlePayment->buchungstext,
							'buchungstyp_kurzbz' => $singlePayment->buchungstyp_kurzbz
						)
					);
					if (isError($kontoResult)) return $kontoResult;
					
					$updateResult = $this->_ci->SAPStudentsGutschriftenModel->update(
						array('buchungsnr' => $singlePayment->buchungsnr),
						array(
							'done' => true,
							'updateamum' => date('Y-m-d H:i:s')
						)
					);
					if (isError($updateResult)) return $updateResult;
				}
				else
				{
					$this->_ci->LogLibSAP->logWarningDB(
						$createPayReceivablesEntry->Log->Item->Note.' for createPayReceivablesEntry gutschrift/rechnung: '. $id
					);
				}
			}
		}
		return success('success');
	}
	
	/**
	 * Creates new SalesOrders in SAP using the array of person ids given as parameter
	 */
	public function createGutschrift($personIdArray)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($personIdArray)) return success('No gutschrift to be created');

		// For each person id
		foreach ($personIdArray as $person_id)
		{
			// Get the SAP user id for this person
			$sapUserIdResult = $this->_getSAPUserId($person_id);

			// If an error occurred then return it
			if (isError($sapUserIdResult)) return $sapUserIdResult;

			// If no data have been found
			if (!hasData($sapUserIdResult))
			{
				// Then log it
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find the sap user id with this person id: '.$person_id);
				continue; // and continue to the next one
			}

			// Here the sap user id!
			$sapUserId = getData($sapUserIdResult)[0]->sap_user_id;

			// Get all Open Payments of Person that are not yet transfered to SAP
			$resultCreditMemoResult = $this->_getUnsyncedCreditMemo($person_id, $this->_ci->config->item(self::PAYMENTS_BOOKING_TYPE_ORGANIZATIONS));

			// If an error occurred then return it
			if (isError($resultCreditMemoResult)) return $resultCreditMemoResult;

			// If no data have been found
			if (!hasData($resultCreditMemoResult))
			{
				// Then log it
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find a credit memos with this person id: '.$person_id);
				continue; // and continue to the next one
			}

			// For each payment found
			foreach (getData($resultCreditMemoResult) as $singlePayment)
			{
				// Get the service id
				$serviceIdResult = $this->_getServiceID(
					$singlePayment->buchungstyp_kurzbz,
					$singlePayment->studiengang_kz,
					$singlePayment->studiensemester_kurzbz
				);

				// If an error occurred then return it
				if (isError($serviceIdResult)) return $serviceIdResult;

				// If no data have been found
				if (!hasData($serviceIdResult))
				{
					// Then log it
					$this->_ci->LogLibSAP->logWarningDB(
						'Could not get Payment Service for '.$singlePayment->buchungstyp_kurzbz.', '.
						$singlePayment->studiengang_kz.', '.$singlePayment->studiensemester_kurzbz
					);
					continue; // and continue to the next one
				}

				// Here the service id!
				$service_id = getData($serviceIdResult)[0]->service_id;

				// By default get the sales unit party id from the configs
				$salesUnitPartyID = $this->_ci->config->item(self::INTERNATIONAL_OFFICE_SALES_UNIT_PARTY_ID);

				// If the buchungstyp_kurzbz is _not_ for an incoming/outgoing grant
				// then get the sales unit party id from database
				if ($singlePayment->buchungstyp_kurzbz != $this->_ci->config->item(self::INCOMING_OUTGOING_GRANT))
				{
					// Get the sales unit party
					$salesUnitPartyIDResult = $this->_getsalesUnitPartyID($singlePayment->studiengang_kz);

					// If an error occurred then return it
					if (isError($salesUnitPartyIDResult)) return $salesUnitPartyIDResult;

					// If no data have been found
					if (!hasData($salesUnitPartyIDResult))
					{
						// Then log it
						$this->_ci->LogLibSAP->logWarningDB('Could not get SalesUnit for DegreeProgramm: '.$singlePayment->studiengang_kz);
						continue; // and continue to the next one
					}

					// Here the salesUnitPartyID!
					$salesUnitPartyID = getData($salesUnitPartyIDResult)[0]->oe_kurzbz_sap;
				}

				// Builds the data structure for the SOAP call
				$data = array(
					'BusinessDocumentBasicMessageHeader' => array(
						'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
						'UUID' => generateUUID()
					),
					'CustomerInvoiceRequest' => array(
						'actionCode' => '01',
						'DeliveryTerms' => array(
							'CompleteDeliveryRequestedIndicator' => 1
						),
						'BaseBusinessTransactionDocumentID' => 'FHC-OUT-'.$singlePayment->buchungsnr,
						'SalesAndServiceBusinessArea' => array(
							'DistributionChannelCode' => '01'
						),
						'SalesUnitParty' => array(
							'InternalID' => $salesUnitPartyID
						),
						'BuyerParty' => array(
							'InternalID' => $sapUserId
						),
						'PricingTerms' => array(
							'PricingProcedureCode' => 'PPSTD1',
							'CurrencyCode' => 'EUR'
						),
						'Item' => array(
							'Description' => mb_substr($singlePayment->buchungstext, 0, 40),
							'Product' => array(
								'InternalID' => $service_id,
								'TypeCode' => '2' // = Service
							),
							'ReceivablesPropertyMovementDirectionCode' => '1', // = Credit Memo Item
							'CashDiscountDeductibleIndicator' => 'false', // = ??
							'BaseBusinessTransactionDocumentItemID' => '10', // = Positions ID
							'PriceAndTax' => array(
								'PriceComponent'=> array(
									'TypeCode' => '7PR1', // = List Price
									'Rate' => array(
										'DecimalValue' => str_replace(',', '.', $singlePayment->betrag * (-1)),
										'CurrencyCode' => 'EUR',
										'BaseMeasureUnitCode' => 'EA'
									)
								)
							),
							'Quantity' => '1',
							'QuantityTypeCode' => 'EA',/*
							'AccountingCodingBlockAssignment' => array(
								'AccountingCodingBlock' => array(
									'ProjectReference' => array(
										'ProjectID' => 'COURSES-ESW-WS2020'
									)
								),
								'PartnerAccountingCodingBlock' => array(
									'ProjectReference' => array(
										'ProjectID' => 'COURSES-ESW-WS2020'
									)
								)
							)*/
						)
					)
				);

				// Create the Entry
				$manageCustomerInvoiceRequestInResult = $this->_ci->ManageCustomerInvoiceRequestInModel->MaintainBundle($data);

				// If an error occurred then return it
				if (isError($manageCustomerInvoiceRequestInResult))
				{
					return $manageCustomerInvoiceRequestInResult;
				}

				// SAP data
				$creditMemoResult = getData($manageCustomerInvoiceRequestInResult);

				// If data structure is ok...
				if (isset($creditMemoResult->CustomerInvoiceRequest)
				 && isset($creditMemoResult->CustomerInvoiceRequest->BaseBusinessTransactionDocumentID))
				{
					// Mark Entry in FAS as payed
					$kontoResult = $this->_ci->KontoModel->insert(
						array(
							'person_id' => $singlePayment->person_id,
							'studiengang_kz' => $singlePayment->studiengang_kz,
							'studiensemester_kurzbz' => $singlePayment->studiensemester_kurzbz,
							'buchungsnr_verweis' => $singlePayment->buchungsnr,
							'betrag' => str_replace(',', '.', $singlePayment->betrag*(-1)),
							'buchungsdatum' => date('Y-m-d'),
							'buchungstext' => $singlePayment->buchungstext,
							'buchungstyp_kurzbz' => $singlePayment->buchungstyp_kurzbz
						)
					);

					if (isError($kontoResult)) return $kontoResult;

					/*
					$salesOrderResult = $this->_ci->SAPSalesOrderModel->insert(
						array(
							'buchungsnr' => $singlePayment->buchungsnr,
							'sap_sales_order_id' => self::CREDIT_MEMO_SOI.' '.$singlePayment->person_id
						)
					);
					// If an error occurred then return it
					if (isError($salesOrderResult)) return $salesOrderResult;
					*/
				}
				else // ...otherwise store a non blocking error
				{
					// If it is present a description from SAP then use it
					if (isset($creditMemoResult->Log) && isset($creditMemoResult->Log->Item))
					{
						if (!isEmptyArray($creditMemoResult->Log->Item))
						{
							foreach ($creditMemoResult->Log->Item as $item)
							{
								if (isset($item->Note))
									$this->_ci->LogLibSAP->logWarningDB($item->Note.' for Buchungsnr: '.$singlePayment->buchungsnr);
							}
						}
						elseif ($creditMemoResult->Log->Item->Note)
						{
							$this->_ci->LogLibSAP->logWarningDB(
								$creditMemoResult->Log->Item->Note.' for Buchungsnr: '.$singlePayment->buchungsnr
							);
						}
					}
					else
					{
						// Default non blocking error
						$this->_ci->LogLibSAP->logWarningDB(
							'SAP did not return the BaseBusinessTransactionDocumentID for Buchungsnr: '.$singlePayment->buchungsnr
						);
					}
				}
			}
		}

		return success('SAP credit memo created successfully');
	}

	/**
	 * Check all open Payments if they are already paid
	 * and set them as paid if so
	 */
	public function setPaid()
	{
		$openPaymentsResult = $this->_ci->SAPSalesOrderModel->getOpenPayments();

		if (isSuccess($openPaymentsResult))
		{
			if (hasData($openPaymentsResult))
			{
				$openPayments = getData($openPaymentsResult);

				foreach ($openPayments as $row)
				{
					$isPaidResult = $this->isSalesOrderPaid($row->sap_sales_order_id, $row->sap_user_id);
					if (isSuccess($isPaidResult))
					{
						if (hasData($isPaidResult))
						{
							if (getData($isPaidResult) === true)
							{
								// paid
								$kontoResult = $this->_ci->KontoModel->setPaid($row->buchungsnr);
								if (isError($kontoResult)) return $kontoResult;
							}
							// otherwise it is not paid
						}
						// otherwise no invoices, not paid or no sales order

						// In any case set last check timestamp
						$lastCheckResult = $this->_ci->SAPSalesOrderModel->update(
							array($row->buchungsnr),
							array(
								'lastcheck' => 'NOW()',
							)
						);

						if (isError($lastCheckResult)) return $lastCheckResult;
					}
					else // returns the error
						return $isPaidResult;

				}
			}
			else
			{
				return success("No Open Payments");
			}
		}
		else
		{
			$this->_ci->LogLibSAP->logErrorDB("setPaid: cannot get Open Payments");
			return error("Cannot get Open Payments");
		}
	}

	/**
	 * Creates new SalesOrders in SAP using the array of person ids given as parameter
	 */
	public function create($person_arr)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($person_arr)) return success('No payments to be synced');

		// Array used to store non blocking error messages to be returned back and then logged
		$nonBlockingErrorsArray = array();

		foreach ($person_arr as $person_id)
		{
			$data = array();
			$UserPartyID = '';

			// can hold the CostCenterID or GMBHLehrgaenge if it is assigned to a gmbh project
			$lastCostCenter = '-1';
			$last_stg = '';
			$ProjectRequired = false;
			$buchungsnr_arr = array();

			// Get SAP ID of the Student
			$UserPartyID_Result = $this->_getSAPUserId($person_id);
			if (isError($UserPartyID_Result) || !hasData($UserPartyID_Result))
			{
				// Person not found in sync Table
				$nonBlockingErrorsArray[] = 'PersonID '.$person_id.' not found in SAP';
				continue;
			}
			else
			{
				$UserPartyID_ResultData = getData($UserPartyID_Result);
				$UserPartyID = $UserPartyID_ResultData[0]->sap_user_id;
			}

			// Get all Open Payments of Person that are not yet transfered to SAP
			$result_openpayments = $this->_getUnsyncedPayments($person_id);

			if (!isError($result_openpayments) && hasData($result_openpayments))
			{
				$wunschtermin = null;

				$paymentData = getData($result_openpayments);
				foreach ($paymentData as $singlePayment)
				{
					// If it is a Special Buchungstyp from the config, then it is automatically redirected to an other cost center
					// This needs to be overwritten here to make sure it creates a separate SalesOrder if neccassary
					if (isset($this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]))
					{
						$singlePayment->kostenstellenzuordnung = $this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]['kostenstelle'];
					}

					if ($lastCostCenter != $singlePayment->kostenstellenzuordnung)
					{
						$ProjectRequired = false;

						if ($last_stg != '')
						{
							if ($last_stg < 0)
								$release = false;
							else
								$release = true;

							// Create Sales Order for previous Degree Programm
							$this->_createSalesOrder($data, $buchungsnr_arr, $release);
						}

						$lastCostCenter = $singlePayment->kostenstellenzuordnung;
						$last_stg = $singlePayment->studiengang_kz;
						$buchungsnr_arr = array();
						$task_id = '';
						$name = $singlePayment->studiengang_kurzbz;
						$externeReferenz = $singlePayment->studiengang_kurzbz;

						if ($wunschtermin == null)
						{
							if ($singlePayment->studiensemester_start < date('Y-m-d'))
							{
								// If it is an entry for a old semester set the date to tommorow
								$date = new DateTime();
								$date->modify('+1 day');
								$wunschtermin = $date->format('Y-m-d').'T00:00:00Z';
							}
							else
							{
								$wunschtermin = $singlePayment->studiensemester_start.'T00:00:00Z';
							}
						}

						// GMBH or Special Courses
						if (($singlePayment->studiengang_kz < 0 || $singlePayment->studiengang_kz > 10000)
							&& !in_array($singlePayment->studiengangstyp, array('b','m'))
							)
						{
							// Get ProjectID if it is a Lehrgang or Special Course
							$ProjectRequired = true;

							// Speziallehrgänge die in der FH sind statt in der GMBH!
							if ($singlePayment->studiengang_kz < 0
								|| in_array($singlePayment->studiengang_kz, $this->_ci->config->item('project_gmbh_custom_id_list'))
							)
							{
								// Lehrgaenge
								$ResponsiblePartyID = $this->_ci->config->item('payments_responsible_party')['gmbh'];
								$personalressource = $this->_ci->config->item('payments_personal_ressource')['gmbh'];
							 	$salesUnitPartyID = $this->_ci->config->item('payments_sales_unit_gmbh');
							}
							else
							{
								// Custom Courses
								$ResponsiblePartyID = $this->_ci->config->item('payments_responsible_party')['fh'];
								$personalressource = $this->_ci->config->item('payments_personal_ressource')['fh'];
							 	$salesUnitPartyID = $this->_ci->config->item('payments_sales_unit_custom');
							}
						}
						else // FH payments
						{
							// Standard payment cost center
							if (!isset($this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]))
							{
								$salesUnitPartyIDResult = $this->_getsalesUnitPartyID($singlePayment->studiengang_kz);
								if (!isError($salesUnitPartyIDResult) && hasData($salesUnitPartyIDResult))
									$salesUnitPartyID = getData($salesUnitPartyIDResult)[0]->oe_kurzbz_sap;
								else
								{
									$nonBlockingErrorsArray[] = 'Could not get SalesUnit for DegreeProgramm: '.$singlePayment->studiengang_kz;
									continue;
								}
							}
							else // alternative payment cost center
							{
								$salesUnitPartyID = $this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]['kostenstelle'];
								$lastCostCenter = $salesUnitPartyID;
							}

							$ResponsiblePartyID = $this->_ci->config->item('payments_responsible_party')['fh'];
							$personalressource = $this->_ci->config->item('payments_personal_ressource')['fh'];
						}

						$data = array(
							'BasicMessageHeader' => array(
								'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
								'UUID' => generateUUID()
							),
							'SalesOrder' => array(
								'actionCode' => '01',
								'Name' => $name,
								'BuyerID' => $externeReferenz,
								'RequestedFulfillmentPeriodPeriodTerms' => array(
									'actionCode' => '01',
 									'StartDateTime' => $wunschtermin,
 									'EndDateTime' => $wunschtermin
								),
								'DeliveryTerms' => array(
									'CompleteDeliveryRequestedIndicator' => 1
								),
								'SalesAndServiceBusinessArea' => array(
									'DistributionChannelCode' => '01'
								),
								'SalesUnitParty' => array(
									'PartyID' => $salesUnitPartyID
								),
								'AccountParty' => array(
									'PartyID' => $UserPartyID
								),
								'EmployeeResponsibleParty' => array(
									'PartyID' => $ResponsiblePartyID
								),
								'DataOriginTypeCode' => '4' // E-Commerce
							)
						);
					}

					//
					if ($singlePayment->buchungstyp_kurzbz == 'StudiengebuehrAnzahlung')
					{
						// Zahlung zur Studienplatzsicherung wird nicht gemahnt und hat
						// andere Zahlungsbedingungen
						$data['SalesOrder']['mahnsperre'] = '1';
						$data['SalesOrder']['CashDiscountTerms'] = array(
							'actionCode' => '01',
							'Code' => 'z006' // 5 Werktage
						);
					}
					
					if (isset($this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]))
					{
						$data['SalesOrder']['mahnsperre'] = $this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]['mahnsperre'];
						$data['SalesOrder']['CashDiscountTerms'] = array(
							'actionCode' => '01',
							'Code' =>  $this->_ci->config->item('payments_fh_cost_centers_buchung')[$singlePayment->buchungstyp_kurzbz]['zahlungsbedingung']
						);
					}

					// Prepare Sales Positions
					$serviceIdResult = $this->_getServiceID($singlePayment->buchungstyp_kurzbz, $singlePayment->studiengang_kz, $singlePayment->studiensemester_kurzbz);

					// If no data have been found
					if (isError($serviceIdResult) || !hasData($serviceIdResult))
					{
						$nonBlockingErrorsArray[] = 'Could not get Payment Service for '.
							$singlePayment->buchungstyp_kurzbz.', '.$singlePayment->studiengang_kz.', '.$singlePayment->studiensemester_kurzbz;
						$data = array();
						continue;
					}

					$service_id = getData($serviceIdResult)[0]->service_id;

					$buchungsnr_arr[] = $singlePayment->buchungsnr;

					$position = array(
						'Description' => mb_substr($singlePayment->buchungstext,0,40),
						'ItemProduct' => array(
							'ProductID' => $service_id,
							'UnitOfMeasure' => 'EA'
						),
						'ItemServiceTerms' => array(
							'ServicePlannedDuration' => 'P6M',
							'ResourceID' => $personalressource
						),
						'PriceAndTaxCalculationItem' => array(
							'ItemMainPrice'=> array(
								'Rate' => array(
									'DecimalValue' => str_replace(',','.',$singlePayment->betrag * (-1)),
									'CurrencyCode' => 'EUR',
									'BaseMeasureUnitCode' => 'EA'
								)
							)
						)
					);

					// If it is Payment for a Lehrgang or Special Degree Programm
					// add the reference to a project
					if ($ProjectRequired)
					{
						$TaskResult = $this->_getTaskId($singlePayment->studiengang_kz, $singlePayment->studiensemester_kurzbz);
						if (!isError($TaskResult) && hasData($TaskResult))
							$task_id = getData($TaskResult)[0]->project_id;
						else
						{
							$nonBlockingErrorsArray[] = 'Could not get Project for DegreeProgramm: '.
								$singlePayment->studiengang_kz.' and studysemester '.
								$singlePayment->studiensemester_kurzbz;
							continue 2;
						}

						$position['ItemAccountingCodingBlockDistribution'] = array(
							'AccountingCodingBlockAssignment' => array(
								'ProjectTaskKey' => array(
									'TaskID' => $task_id
								)
							)
						);
					}

					$data['SalesOrder']['Item'][] = $position;
				}
			}

			if (count($data) > 0)
			{
				if ($last_stg < 0)
					$release = false;
				else
					$release = true;

				$result = $this->_createSalesOrder($data, $buchungsnr_arr, $release);
				if (hasData($result))
					$nonBlockingErrorsArray = array_merge($nonBlockingErrorsArray, getData($result));
			}
		}

		return success($nonBlockingErrorsArray);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Create a SalesOrder and writes the Sync Table entry
	 */
	private function _createSalesOrder($data, $buchungsnr_arr, $release)
	{
		$nonBlockingErrorsArray = array();

		// Create the Entry
		$manageSalesOrderResult = $this->_ci->ManageSalesOrderInModel->MaintainBundle($data);

		// If no error occurred...
		if (!isError($manageSalesOrderResult) && hasData($manageSalesOrderResult))
		{
			$manageSalesOrder = getData($manageSalesOrderResult);

			// If data structure is ok...
			if (isset($manageSalesOrder->SalesOrder) && isset($manageSalesOrder->SalesOrder->ID) && isset($manageSalesOrder->SalesOrder->ID->_))
			{

				if ($release)
				{
					// If FH then Release SO that Invoice is created
					$releaseResult = $this->_releaseSO($manageSalesOrder->SalesOrder->ID->_);
					if (isError($releaseResult))
					{
						$nonBlockingErrorsArray = array_merge($nonBlockingErrorsArray, array(getError($releaseResult)));
					}
				}

				foreach ($buchungsnr_arr as $buchungsnr)
				{
					// Store in database the couple buchungsnr sales_order_id
					$insert = $this->_ci->SAPSalesOrderModel->insert(
						array(
							'buchungsnr' => $buchungsnr,
							'sap_sales_order_id' => $manageSalesOrder->SalesOrder->ID->_
						)
					);
					// If database error occurred then return it
					if (isError($insert))
						$nonBlockingErrorsArray[] = 'Could not write SyncTable entry Buchungsnr: '.
							$buchungsnr.
							' SalesOrderID: '.
							$manageSalesOrder->SalesOrder->ID->_;
				}
			}
			else // ...otherwise store a non blocking error
			{
				// If it is present a description from SAP then use it
				if (isset($manageSalesOrder->Log) && isset($manageSalesOrder->Log->Item)
					&& isset($manageSalesOrder->Log->Item))
				{
					if (!isEmptyArray($manageSalesOrder->Log->Item))
					{
						foreach ($manageSalesOrder->Log->Item as $item)
						{
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for Buchungsnr: '.implode(',',$buchungsnr_arr);
						}
					}
					elseif ($manageSalesOrder->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageSalesOrder->Log->Item->Note.' for Buchungsnr: '.implode(',',$buchungsnr_arr);
					}
				}
				else
				{
					// Default non blocking error
					$nonBlockingErrorsArray[] = 'SAP did not return the SalesOrderID for Buchungsnr: '.implode(',',$buchungsnr_arr);
				}
			}
		}
		else
		{
			$nonBlockingErrorsArray[] = 'Failed to create SalesOrder:'.print_r($manageSalesOrderResult, true);
		}

		return success($nonBlockingErrorsArray);
	}

	/**
	 * Release SalesOrder and set Fullfillment
	 * After this Invoices are created automatically at 5am for this SalesOrders
	 */
	private function _releaseSO($salesorderid)
	{
		$data = array(
			'BasicMessageHeader' => array(
				'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
				'UUID' => generateUUID()
			),
			 'SalesOrder'  => array(
				 'ID' => $salesorderid
			 )
		 );

		// Release a sales Order
		$releaseResult = $this->_ci->SOReleaseModel->Release($data);
		// returns with Notice "Action Release not possible; action is disabled"
		// But thats ok

		if (isSuccess($releaseResult))
		{
			$data = array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
					'UUID' => generateUUID()
				),
				 'SalesOrder'  => array(
					 'ID' => $salesorderid
				 )
			 );
			$fullfillmentResult = $this->_ci->SOReleaseModel->FinishFulfilmentProcessingOfAllItems($data);

			if (!isSuccess($fullfillmentResult))
			{
				return error('Failed to FinishFulfilmentProcessingOfAllItems for SalesOrder:'.$salesorderid);
			}
		}
		else
		{
			return error('Failed to Release SalesOrder '.$salesorderid);
		}

		return success();
	}

	/**
	 * Get all open Payments that are not yet Transfered to SAP
	 * Also Check if Payments are not older than BUCHUNGSDATUM_SYNC_START
	 * @param $person_id ID of the Person
	 * @return payment results
	 */
	private function _getUnsyncedCreditMemo($person_id, $buchungstypen)
	{
		$dbModel = new DB_Model();

		// Only fetch Incoming/Outgoing Credit Memos until it is clear how to handle other payments
		$dbPaymentData = $dbModel->execReadOnlyQuery('
			SELECT
				buchungsnr, studiengang_kz, studiensemester_kurzbz, betrag, buchungsdatum,
				buchungstext, buchungstyp_kurzbz, person_id
			FROM
				public.tbl_konto bk
			WHERE
				betrag > 0
				AND NOT EXISTS(SELECT 1 FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr)
				AND buchungsnr_verweis IS NULL
				AND person_id = ?
				AND buchungsdatum >= ?
				AND buchungstyp_kurzbz IN ?
			ORDER BY
				studiengang_kz
		', array(
			$person_id,
			self::BUCHUNGSDATUM_SYNC_START,
			$buchungstypen
		));

		return $dbPaymentData;
	}

	/**
	 * Get all open Payments that are not yet Transfered to SAP
	 * Also Check if Payments are not older than BUCHUNGSDATUM_SYNC_START
	 * @param $person_id ID of the Person
	 * @return payment results
	 */
	private function _getUnsyncedPayments($person_id)
	{
		$studiensemesterStartMaxDate = $this->_ci->config->item('payments_studiensemester_start_max_date');

		$dbModel = new DB_Model();
		$dbPaymentData = $dbModel->execReadOnlyQuery('
			SELECT
				bk.buchungsnr,
				bk.studiengang_kz,
				bk.studiensemester_kurzbz,
				bk.betrag,
				bk.buchungsdatum,
				bk.buchungstext, bk.buchungstyp_kurzbz,
				UPPER(sg.typ || sg.kurzbz) as studiengang_kurzbz,
				ss.start as studiensemester_start,
				sg.typ as studiengangstyp,
				COALESCE(so.oe_kurzbz_sap, \'GMBHPROJEKT\') as kostenstellenzuordnung
			FROM
				public.tbl_konto bk
				JOIN public.tbl_studiengang sg USING(studiengang_kz)
				JOIN public.tbl_studiensemester ss USING(studiensemester_kurzbz)
				LEFT JOIN sync.tbl_sap_organisationsstruktur so ON(so.oe_kurzbz = sg.oe_kurzbz)
			WHERE
				NOT EXISTS(SELECT 1 FROM sync.tbl_sap_salesorder WHERE buchungsnr = bk.buchungsnr)
				AND bk.betrag < 0
				AND 0 != bk.betrag + COALESCE((SELECT SUM(betrag) FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr), 0)
				AND bk.buchungsnr_verweis IS NULL
				AND bk.person_id = ?
				AND bk.buchungsdatum >= ?
				AND bk.buchungsdatum <= NOW()
				AND ss.start <= ?
			ORDER BY
				kostenstellenzuordnung, studiengang_kz, studiensemester_start
		', array($person_id, self::BUCHUNGSDATUM_SYNC_START, $studiensemesterStartMaxDate));

		return $dbPaymentData;
	}

	/**
	 * Get the SalesUnit of a DegreeProgramm
	 */
	private function _getsalesUnitPartyID($studiengang_kz)
	{
		$dbModel = new DB_Model();

		$dbOEData = $dbModel->execReadOnlyQuery('
			SELECT
				oe_kurzbz_sap
			FROM
				public.tbl_studiengang
				JOIN public.tbl_organisationseinheit USING(oe_kurzbz)
				JOIN sync.tbl_sap_organisationsstruktur USING(oe_kurzbz)
			WHERE
				studiengang_kz = ?
		', array($studiengang_kz));

		return $dbOEData;
	}

	/**
	 * Loads the Service for the Payment Type
	 * @param $buchungstyp_kurzbz
	 * @param $studiengang_kz
	 * @param $studiensemester_kurzbz
	 * @return ServiceID
	 */
	private function _getServiceID($buchungstyp_kurzbz, $studiengang_kz, $studiensemester_kurzbz)
	{
		$dbModel = new DB_Model();

		$dbResult = $dbModel->execReadOnlyQuery('
			SELECT
				service_id
			FROM
				sync.tbl_sap_service_buchungstyp
			WHERE
				buchungstyp_kurzbz = ?
				AND (studiensemester_kurzbz = ? OR studiensemester_kurzbz IS NULL)
				AND (studiengang_kz = ? OR studiengang_kz IS NULL)
		', array($buchungstyp_kurzbz, $studiensemester_kurzbz, $studiengang_kz));

		return $dbResult;
	}

	/**
	 * Loads the SAP ID of the Person from the Sync Table
	 * @param $person_id
	 * @return DB Result with sap_user_id
	 */
	private function _getSAPUserId($person_id)
	{
		$dbModel = new DB_Model();

		$dbResult = $dbModel->execReadOnlyQuery('
			SELECT
				sap_user_id
			FROM
				sync.tbl_sap_students
			WHERE
				person_id = ?
		', array($person_id));

		return $dbResult;
	}

	/**
	 * Load the Project of a Course
	 */
	private function _getTaskId($studiengang_kz, $studiensemester_kurzbz)
	{
		$dbModel = new DB_Model();
		$dbResult = $dbModel->execReadOnlyQuery('
			SELECT
				project_id
			FROM
				sync.tbl_sap_projects_courses
			WHERE
				studiengang_kz = ?
				AND studiensemester_kurzbz = ?
		', array($studiengang_kz, $studiensemester_kurzbz));

		return $dbResult;
	}

	/**
	 * Check if there are open Amounts for that Invoice/Student that are not paid yet
	 * @param $studentId SAP ID of the Student/Customer
	 * @param $invocieId Id of the Invoice to be checked
	 * @return success true if cleared, success false if open or error if something failed
	 */
	private function _isInvoiceCleared($studentId, $invoiceId)
	{
		// If we already checked this combination - return the cached results
		if (isset($this->_isInvoiceClearedCache[$studentId])
			&& isset($this->_isInvoiceClearedCache[$studentId][$invoiceId]))
		{
			return success($this->_isInvoiceClearedCache[$studentId][$invoiceId]);
		}

		$companyIds = array();
		$companyIds[] = $this->_ci->config->item('users_payment_company_ids')['fhtw'];
		$companyIds[] = $this->_ci->config->item('users_payment_company_ids')['gmbh'];

		$result = $this->_ci->RPFINGLAU08_Q0002Model->getByCustomer($studentId, $companyIds);

		if (isSuccess($result))
		{
			if (hasData($result))
			{
				$data = getData($result);
				foreach ($data as $row)
				{
					if ($row->CCINHUUID == $invoiceId)
					{
						// Invoice found in the List, means there are open amounts, means not fully paid
						// Maybe this is partially paid, so we can set some items of the invoice
						// as paid if we find a solid solution for doing that
						//echo "Rechnung gefunden in offenen posten -> nicht bezahlt";
						$this->_isInvoiceClearedCache[$studentId][$invoiceId] = false;
						return success(false);
					}
				}

				// There are open Invoices for the Customer, but not for that specific Invoice
				// so this is set as paid
				//echo "Offene posten für student aber nicht für diese Rechnung -> bezahlt";
				$this->_isInvoiceClearedCache[$studentId][$invoiceId] = true;
				return success(true);
			}
			else
			{
				// No Data found, means no open amounts for this customer, means everything paid
				//echo "keine Offenen Posten für den Studenten -> bezahlt";
				$this->_isInvoiceClearedCache[$studentId][$invoiceId] = true;
				return success(true);
			}
		}
		else
		{
			return error('Invoice Request failed');
		}

		return error('_isInvoiceCleared in SyncPaymentsLib exited unexpected');
	}

	/**
	 * Get all Invoices that are connected to that salesOrder
	 * @param $salesOrderId ID of the SalesOrder
	 * @return Result Array with all invoice IDs
	 */
	private function _getInvoiceIDFromSalesOrder($salesOrderId)
	{
		$id_arr = array();
		$result = $this->getPaymentById($salesOrderId);

		if (isSuccess($result))
		{
		 	if (hasData($result))
			{
				if (isset($result->retval->SalesOrder)
					&& isset($result->retval->SalesOrder->BusinessTransactionDocumentReference))
				{
					if (is_array($result->retval->SalesOrder->BusinessTransactionDocumentReference))
					{
						foreach ($result->retval->SalesOrder->BusinessTransactionDocumentReference as $invoice)
						{
							// Check if it is an Invoice (can also be an reference to a project)
							if (isset($invoice->BusinessTransactionDocumentReference->TypeCode)
								&& $invoice->BusinessTransactionDocumentReference->TypeCode->_ == 28)
							{
								$id_arr[] = $invoice->BusinessTransactionDocumentReference->ID->_;
							}
						}
					}
					else
					{
						// Check if it is an Invoice (can also be an reference to a project)
						if (isset($result->retval->SalesOrder->BusinessTransactionDocumentReference->BusinessTransactionDocumentReference->TypeCode)
							&& $result->retval->SalesOrder->BusinessTransactionDocumentReference->BusinessTransactionDocumentReference->TypeCode->_ == 28)
						{
							$id_arr[] = $result->retval->SalesOrder->BusinessTransactionDocumentReference->BusinessTransactionDocumentReference->ID->_;
						}
					}
				}
			}
		}
		else
			return error("Failed to Load SalesOrder".print_r($result,true));

		return success($id_arr);
	}
}
