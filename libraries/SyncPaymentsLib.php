<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncPaymentsLib
{
	// Jobs types used by this lib
	const SAP_PAYMENTS_CREATE = 'SAPPayemtnsCreate';

	// Prefix for SAP SOAP id calls
	const CREATE_PAYMENT_PREFIX = 'CP';
	const BUCHUNGSDATUM_SYNC_START = '2019-09-01';

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QuerySalesOrderIn_model', 'QuerySalesOrderInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageSalesOrderIn_model', 'ManageSalesOrderInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageCustomerInvoiceRequestIn_model', 'ManageCustomerInvoiceRequestInModel');

		$this->_ci->load->model('crm/Konto_model', 'KontoModel');

		// Loads SAPSalesOrderModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPSalesOrder_model', 'SAPSalesOrderModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

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

	/**
	 * Creates new SalesOrders in SAP using the array of person ids given as parameter
	 */
	public function createGutschrift($person_arr)
	{
		$nonBlockingErrorsArray = array();

		// If the given array of person ids is empty stop here
		if (isEmptyArray($person_arr)) return success('No gutschrift to be synced');

		foreach ($person_arr as $person_id)
		{
			$UserPartyID_Result = $this->_getUserPartyID($person_id);
			if(isError($UserPartyID_Result) || !hasData($UserPartyID_Result))
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
			//$UserPartyID = '1000148';

			// Get all Open Payments of Person that are not yet transfered to SAP
			$result_creditmemo = $this->_getUnsyncedCreditMemo($person_id);

			if (!isError($result_creditmemo) && hasData($result_creditmemo))
			{
				$paymentData = getData($result_creditmemo);
				foreach ($paymentData as $row_payment)
				{
					// Prepare Sales Positions
					$service_id = $this->_getServiceID($row_payment->buchungstyp_kurzbz, $row_payment->studiengang_kz, $row_payment->studiensemester_kurzbz);

					if ($service_id === false)
					{
						$nonBlockingErrorsArray[] = 'Could not get Payment Service for '.$row_payment->buchungstyp_kurzbz.', '.$row_payment->studiengang_kz.', '.$row_payment->studiensemester_kurzbz;
						$data = array();
						continue;
					}

					$SalesUnitPartyID_result = $this->_getSalesUnitPartyID($row_payment->studiengang_kz);
					if (!isError($SalesUnitPartyID_result) && hasData($SalesUnitPartyID_result))
						$SalesUnitPartyID = getData($SalesUnitPartyID_result)[0]->oe_kurzbz_sap;
					else
					{
						$nonBlockingErrorsArray[] = 'Could not get SalesUnit for DegreeProgramm: '.$row_payment->studiengang_kz;
						continue;
					}

					//$service_id = '20000015';
					//$SalesUnitPartyID = 'BBE';

					$gutschriftID = 'FHC-OUT-'.$row_payment->buchungsnr;

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
							'BaseBusinessTransactionDocumentID' => $gutschriftID,
							'SalesAndServiceBusinessArea' => array(
								'DistributionChannelCode' => '01'
							),
							'SalesUnitParty' => array(
								'InternalID' => $SalesUnitPartyID
							),
							'BuyerParty' => array(
								'InternalID' => $UserPartyID
							),
							'PricingTerms' => array(
								'PricingProcedureCode' => 'PPSTD1',
								'CurrencyCode' => 'EUR'
							)
						)
					);

					$position = array(
						'Description' => $row_payment->buchungstext,
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
									'DecimalValue' => $row_payment->betrag * (-1),
									'CurrencyCode' => 'EUR',
									'BaseMeasureUnitCode' => 'EA'
								)
							)
						),
						'Quantity' => '1',
						'QuantityTypeCode' => 'EA'
					);

					$data['CustomerInvoiceRequest']['Item'][] = $position;

					// Create the Entry
					$manageCustomerInvoiceRequestInResult = $this->_ci->ManageCustomerInvoiceRequestInModel->MaintainBundle($data);

					// TODO: REMOVE DEBUG OUTPUT
					echo print_r($manageCustomerInvoiceRequestInResult,true);

					// If no error occurred...
					if (!isError($manageCustomerInvoiceRequestInResult))
					{
						$creditMemoResult = getData($manageCustomerInvoiceRequestInResult);

						// If data structure is ok...
						if (isset($creditMemoResult->CustomerInvoiceRequest)
						 && isset($creditMemoResult->CustomerInvoiceRequest->BaseBusinessTransactionDocumentID))
						{
							// Mark Entry in FAS as payed
							$this->_ci->KontoModel->insert(
								array(
									'person_id' => $row_payment->person_id,
									'studiengang_kz' => $row_payment->studiengang_kz,
									'studiensemester_kurzbz' => $row_payment->studiensemester_kurzbz,
									'buchungsnr_verweis' => $row_payment->buchungsnr,
									'betrag' => $row_payment->betrag*(-1),
									'buchungsdatum' => date('Y-m-d'),
									'buchungstext' => $row_payment->buchungstext,
									'buchungstyp_kurzbz' => $row_payment->buchungstyp_kurzbz
								)
							);
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
										if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for Buchungsnr: '.$row_payment->buchungsnr;
									}
								}
								elseif ($creditMemoResult->Log->Item->Note)
								{
									$nonBlockingErrorsArray[] = $creditMemoResult->Log->Item->Note.' for Buchungsnr: '.$row_payment->buchungsnr;
								}
							}
							else
							{
								// Default non blocking error
								$nonBlockingErrorsArray[] = 'SAP did not return the BaseBusinessTransactionDocumentID for Buchungsnr: '.$row_payment->buchungsnr;
							}
						}
					}
				}
			}
		}
		return success($nonBlockingErrorsArray);
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
			$last_stg = '';
			$buchungsnr_arr = array();

			// Get SAP ID of the Student
			$UserPartyID_Result = $this->_getUserPartyID($person_id);
			if(isError($UserPartyID_Result) || !hasData($UserPartyID_Result))
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
				$paymentData = getData($result_openpayments);
				foreach ($paymentData as $row_payment)
				{
					if ($last_stg != $row_payment->studiengang_kz)
					{
						if ($last_stg != '')
						{
							// Create Sales Order for previous Degree Programm
							$this->_CreateSalesOrder($data, $buchungsnr_arr);
						}
						$last_stg = $row_payment->studiengang_kz;
						$buchungsnr_arr = array();

						// TODO: Sales Unit for Lehrgaenge? Project ?

						$SalesUnitPartyID_result = $this->_getSalesUnitPartyID($row_payment->studiengang_kz);
						if (!isError($SalesUnitPartyID_result) && hasData($SalesUnitPartyID_result))
							$SalesUnitPartyID = getData($SalesUnitPartyID_result)[0]->oe_kurzbz_sap;
						else
						{
							$nonBlockingErrorsArray[] = 'Could not get SalesUnit for DegreeProgramm: '.$row_payment->studiengang_kz;
							continue;
						}

						//$SalesUnitPartyID = 'BBE';
						$ResponsiblePartyID = '23'; // TODO MIA
						
						$data = array(
							'BasicMessageHeader' => array(
								'ID' => generateUID(self::CREATE_PAYMENT_PREFIX),
								'UUID' => generateUUID()
							),
							'SalesOrder' => array(
								'actionCode' => '01',
								'DeliveryTerms' => array(
									'CompleteDeliveryRequestedIndicator' => 1
								),
								'SalesAndServiceBusinessArea' => array(
									'DistributionChannelCode' => '01'
								),
								'SalesUnitParty' => array(
									'PartyID' => $SalesUnitPartyID
								),
								'AccountParty' => array(
									'PartyID' => $UserPartyID
								),
								'EmployeeResponsibleParty' = array(
									'PartyID' => $ResponsiblePartyID
								)
							)
						);
					}

					// Prepare Sales Positions
					$service_id = $this->_getServiceID($row_payment->buchungstyp_kurzbz, $row_payment->studiengang_kz, $row_payment->studiensemester_kurzbz);

					if ($service_id === false)
					{
						$nonBlockingErrorsArray[] = 'Could not get Payment Service for '.$row_payment->buchungstyp_kurzbz.', '.$row_payment->studiengang_kz.', '.$row_payment->studiensemester_kurzbz;
						$data = array();
						continue;
					}
					$buchungsnr_arr[] = $row_payment->buchungsnr;

					$position = array(
						'Description' => $row_payment->buchungstext,
						'ItemProduct' => array(
							'ProductID' => $service_id,
							'UnitOfMeasure' => 'EA'
						),
						'ItemServiceTerms' => array(
							'ServicePlannedDuration' => 'P6M'
						),
						'PriceAndTaxCalculationItem' => array(
							'ItemMainPrice'=> array(
								'Rate' => array(
									'DecimalValue' => $row_payment->betrag*(-1),
									'CurrencyCode' => 'EUR',
									'BaseMeasureUnitCode' => 'EA'
								)
							)
						)
					);

					$data['SalesOrder']['Item'][] = $position;
				}
			}

			if (count($data) > 0)
			{
				$result = $this->_CreateSalesOrder($data, $buchungsnr_arr);
				if(hasData($result))
					$nonBlockingErrorsArray = array_merge($nonBlockingErrorsArray, getData($result));
			}
		}

		return success($nonBlockingErrorsArray);
	}

	/**
	 * Create a SalesOrder and writes the Sync Table entry
	 */
	private function _CreateSalesOrder($data, $buchungsnr_arr)
	{

		$nonBlockingErrorsArray = array();

		// Create the Entry
		$manageSalesOrderResult = $this->_ci->ManageSalesOrderInModel->MaintainBundle($data);
echo print_r($manageSalesOrderResult,true);
		// If no error occurred...
		if (!isError($manageSalesOrderResult))
		{
			$manageSalesOrder = getData($manageSalesOrderResult);

			// If data structure is ok...
			if (isset($manageSalesOrder->SalesOrder) && isset($manageSalesOrder->SalesOrder->ID) && isset($manageSalesOrder->SalesOrder->ID->_))
			{
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
						$nonBlockingErrorsArray[] = 'Could not write SyncTable entry Buchungsnr: '.$buchungsnr.' SalesOrderID: '.$manageSalesOrder->SalesOrder->ID->_;
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
		return success($nonBlockingErrorsArray);
	}

	/**
	 * Get all open Payments that are not yet Transfered to SAP
	 * Also Check if Payments are not older than BUCHUNGSDATUM_SYNC_START
	 * @param $person_id ID of the Person
	 * @return payment results
	 */
	private function _getUnsyncedCreditMemo($person_id)
	{
		$dbModel = new DB_Model();
		$dbPaymentData = $dbModel->execReadOnlyQuery('
			SELECT
				buchungsnr, studiengang_kz, studiensemester_kurzbz, betrag, buchungsdatum,
				buchungstext, buchungstyp_kurzbz, person_id
			FROM
				public.tbl_konto bk
			WHERE
				betrag > 0
				AND NOT EXISTS(SELECT 1 FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr)
				AND buchungsnr_verweis is null
				AND person_id = ?
				AND buchungsdatum >= ?
			ORDER BY
				studiengang_kz
		', array($person_id, self::BUCHUNGSDATUM_SYNC_START));

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
		$dbModel = new DB_Model();
		$dbPaymentData = $dbModel->execReadOnlyQuery('
			SELECT
				buchungsnr, studiengang_kz, studiensemester_kurzbz, betrag, buchungsdatum,
				buchungstext, buchungstyp_kurzbz
			FROM
				public.tbl_konto bk
			WHERE
				NOT EXISTS(SELECT 1 FROM sync.tbl_sap_salesorder WHERE buchungsnr=bk.buchungsnr)
				AND betrag < 0
				AND 0 != betrag + COALESCE((SELECT sum(betrag) FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr),0)
				AND buchungsnr_verweis is null
				AND person_id = ?
				AND buchungsdatum >= ?
			ORDER BY
				studiengang_kz
		', array($person_id, self::BUCHUNGSDATUM_SYNC_START));

		return $dbPaymentData;
	}

	/**
	 * Get the SalesUnit of a DegreeProgramm
	 */
	private function _getSalesUnitPartyID($studiengang_kz)
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
		$dbUserData = $dbModel->execReadOnlyQuery('
			SELECT
				service_id
			FROM
				sync.tbl_sap_service_buchungstyp
			WHERE
				buchungstyp_kurzbz = ?
				AND (studiensemester_kurzbz = ? OR studiensemester_kurzbz is null)
				AND (studiengang_kz = ? OR studiengang_kz is null)
		', array($buchungstyp_kurzbz, $studiensemester_kurzbz, $studiengang_kz));

		if(hasData($dbUserData))
		{
			$service = getData($dbUserData);
			return $service[0]->service_id;
		}

		return false;
	}

	/**
	 * Loads the SAP ID of the Person from the Sync Table
	 * @param $person_id
	 * @return DB Result with sap_user_id
	 */
	private function _getUserPartyID($person_id)
	{
		$dbModel = new DB_Model();
		$dbUserData = $dbModel->execReadOnlyQuery('
			SELECT
				sap_user_id
			FROM
				sync.tbl_sap_students
			WHERE
				person_id = ?
		', array($person_id));

		return $dbUserData;
	}
}