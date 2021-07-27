<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncPaymentsLib
{
	// Jobs types used by this lib
	const SAP_PAYMENTS_CREATE = 'SAPPaymentCreate';
	const SAP_PAYMENT_GUTSCHRIFT = 'SAPPaymentGutschrift';

	// Prefix for SAP SOAP id calls
	const CREATE_PAYMENT_PREFIX = 'CP';
	const BUCHUNGSDATUM_SYNC_START = '2019-09-01';

	// Credit memo sales order
	const CREDIT_MEMO_SOI = 'CREDIT MEMO';

	// Incoming/outgoing grant config entry name
	const INCOMING_OUTGOING_GRANT = 'payments_incoming_outgoing_grant';

	// International office sales unit party id config entry name
	const INTERNATIONAL_OFFICE_SALES_UNIT_PARTY_ID = 'payments_international_office_sales_unit_party_id';

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
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageSalesOrderIn_model', 'ManageSalesOrderInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageCustomerInvoiceRequestIn_model', 'ManageCustomerInvoiceRequestInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/SORelease_model', 'SOReleaseModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/RPFINGLAU08_Q0002_model', 'RPFINGLAU08_Q0002Model');
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');

		// Loads SAPSalesOrderModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPSalesOrder_model', 'SAPSalesOrderModel');

		// Loads Payment configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Payments');
		$this->_ci->config->load('extensions/FHC-Core-SAP/Users');
		$this->_ci->config->load('extensions/FHC-Core-SAP/Projects');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Check if a SalesOrder is already fully paid
	 * @param $salesOrderId ID of the SalesOrder
	 * @param $studentId SAP ID of the Student / Customer
	 * @return success true if SalesOrder is cleard, success false if not
	 */
	public function isSalesOrderPaid($salesOrderId, $studentId)
	{
		$id_arr = '';
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
					$this->_getInvoiceIDFromSalesOrderCache[$salesOrderId] = '';
			}
			else
			{
				return error("Failed to get Invoices for SalesOrder".print_r($invoiceResult,true));
			}
		}

		if(is_array($id_arr))
		{
			// If there are Invoices, check if there are open amounts for this invoices
			foreach ($id_arr as $invoiceId)
			{
				// if there are open Amounts, its not cleared
				$isInvoiceClearedResult = $this->_isInvoiceCleared($studentId, $invoiceId);
				if (isSuccess($isInvoiceClearedResult))
				{
					if (!$isInvoiceClearedResult->retval)
					{
						echo "Offene Posten für Rechnung $invoiceId gefunden -> Nicht bezahlt";
						return success(false);
					}
				}
				else
				{
					return error('Invoice Clearance check failed');
				}
			}

			// PAID
			// If all invoices are cleared the SalesOrder is paid
			return success(true);
		}
		else
		{
			// If no Invoice is available its not paid;
			echo "Keine Rechnung gefunden -> nicht bezahlt";
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
			$resultCreditMemoResult = $this->_getUnsyncedCreditMemo($person_id);

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
							'QuantityTypeCode' => 'EA',
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
							)
						)
					)
				);

				// Create the Entry
				$manageCustomerInvoiceRequestInResult = $this->_ci->ManageCustomerInvoiceRequestInModel->MaintainBundle($data);

				// If an error occurred then return it
				if (!isError($manageCustomerInvoiceRequestInResult)) return $manageCustomerInvoiceRequestInResult;

				// SAP data
				$creditMemoResult = getData($manageCustomerInvoiceRequestInResult);

				// If data structure is ok...
				if (isset($creditMemoResult->CustomerInvoiceRequest)
				 && isset($creditMemoResult->CustomerInvoiceRequest->BaseBusinessTransactionDocumentID))
				{
					$salesOrderResult = $this->_ci->SAPSalesOrderModel->insert(
						array(
							'buchungsnr' => $singlePayment->buchungsnr,
							'sap_sales_order_id' => self::CREDIT_MEMO_SOI.' '.$singlePayment->person_id
						)
					);

					// If an error occurred then return it
					if (isError($salesOrderResult)) return $salesOrderResult;
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
			$openPayments = getData($openPaymentsResult);

			if(is_array($openPayments))
			{
				foreach ($openPayments as $row)
				{
					echo "\nCheck SO: $row->sap_sales_order_id ";
					$isPaidResult = $this->isSalesOrderPaid($row->sap_sales_order_id, $row->sap_user_id);
					if (isSuccess($isPaidResult) && getData($isPaidResult) === true)
					{
						echo " -> Paid ";
						// paid
						$this->_ci->KontoModel->setPaid($row->buchungsnr);
					}
					else
					{
						if(isError($isPaidResult))
						{
							echo "Error: ".print_r($isPaidResult, true);
						}
						else
						{
							echo " -> not Paid";
							// not paid yet
						}
					}

					// set last check timestamp
					$this->_ci->SAPSalesOrderModel->update(
						array($row->buchungsnr),
						array(
							'lastcheck' => 'NOW()',
						)
					);
				}
			}
			// else nothing to check
		}
		else
		{
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
			$last_stg = '';
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
				$paymentData = getData($result_openpayments);
				foreach ($paymentData as $singlePayment)
				{
					if ($last_stg != $singlePayment->studiengang_kz)
					{
						if ($last_stg != '')
						{
							if ($last_stg < 0)
								$release = false;
							else
								$release = true;

							// Create Sales Order for previous Degree Programm
							$this->_CreateSalesOrder($data, $buchungsnr_arr, $release);
						}
						$last_stg = $singlePayment->studiengang_kz;
						$buchungsnr_arr = array();


						$task_id = '';
						$name = $singlePayment->studiengang_kurzbz;
						$externeReferenz = $singlePayment->studiengang_kurzbz;

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

						if ($singlePayment->studiengang_kz < 0 || $singlePayment->studiengang_kz > 10000)
						{
							// GMBH or Special Courses

							// Get ProjectID if it is a Lehrgang or Special Course
							$TaskResult = $this->_getTaskId($singlePayment->studiengang_kz, $singlePayment->studiensemester_kurzbz);
							if (!isError($TaskResult) && hasData($TaskResult))
								$task_id = getData($TaskResult)[0]->project_id;
							else
							{
								$nonBlockingErrorsArray[] = 'Could not get Project for DegreeProgramm: '.$singlePayment->studiengang_kz.' and studysemester '.$singlePayment->studiensemester_kurzbz;
								continue 2;
							}

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
						else
						{
							// FH
							$salesUnitPartyIDResult = $this->_getsalesUnitPartyID($singlePayment->studiengang_kz);
							if (!isError($salesUnitPartyIDResult) && hasData($salesUnitPartyIDResult))
								$salesUnitPartyID = getData($salesUnitPartyIDResult)[0]->oe_kurzbz_sap;
							else
							{
								$nonBlockingErrorsArray[] = 'Could not get SalesUnit for DegreeProgramm: '.$singlePayment->studiengang_kz;
								continue;
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

					if($singlePayment->buchungstyp_kurzbz == 'StudiengebuehrAnzahlung')
					{
						// Zahlung zur Studienplatzsicherung wird nicht gemahnt und hat
						// andere Zahlungsbedingungen
						$data['SalesOrder']['mahnsperre'] = '1';
						$data['SalesOrder']['CashDiscountTerms'] = array(
							'actionCode' => '01',
							'Code' => 'z006' // 5 Werktage
						);
					}

					// Prepare Sales Positions
					$service_id = $this->_getServiceID($singlePayment->buchungstyp_kurzbz, $singlePayment->studiengang_kz, $singlePayment->studiensemester_kurzbz);

					if ($service_id === false)
					{
						$nonBlockingErrorsArray[] = 'Could not get Payment Service for '.$singlePayment->buchungstyp_kurzbz.', '.$singlePayment->studiengang_kz.', '.$singlePayment->studiensemester_kurzbz;
						$data = array();
						continue;
					}
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
					if ($task_id != '')
					{
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

				$result = $this->_CreateSalesOrder($data, $buchungsnr_arr, $release);
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
	private function _CreateSalesOrder($data, $buchungsnr_arr, $release)
	{

		$nonBlockingErrorsArray = array();

		// Create the Entry
		$manageSalesOrderResult = $this->_ci->ManageSalesOrderInModel->MaintainBundle($data);

		// If no error occurred...
		if (!isError($manageSalesOrderResult))
		{
			$manageSalesOrder = getData($manageSalesOrderResult);

			// If data structure is ok...
			if (isset($manageSalesOrder->SalesOrder) && isset($manageSalesOrder->SalesOrder->ID) && isset($manageSalesOrder->SalesOrder->ID->_))
			{

				if ($release)
				{
					// If FH then Release SO that Invoice is created
					$releaseResult = $this->_releaseSO($manageSalesOrder->SalesOrder->ID);
					if (isError($releaseResult))
					{
						$nonBlockingErrorsArray = array_merge($nonBlockingErrorsArray, getData($releaseResult));
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
				AND buchungsnr_verweis IS NULL
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
		$studiensemesterStartMaxDate = $this->_ci->config->item('payments_studiensemester_start_max_date');

		$dbModel = new DB_Model();
		$dbPaymentData = $dbModel->execReadOnlyQuery('
			SELECT
				bk.buchungsnr, bk.studiengang_kz, bk.studiensemester_kurzbz, bk.betrag, bk.buchungsdatum,
				bk.buchungstext, bk.buchungstyp_kurzbz,
				UPPER(tbl_studiengang.typ || tbl_studiengang.kurzbz) as studiengang_kurzbz,
				tbl_studiensemester.start as studiensemester_start
			FROM
				public.tbl_konto bk
				JOIN public.tbl_studiengang USING(studiengang_kz)
				JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
			WHERE
				NOT EXISTS(SELECT 1 FROM sync.tbl_sap_salesorder WHERE buchungsnr=bk.buchungsnr)
				AND betrag < 0
				AND 0 != betrag + COALESCE((SELECT sum(betrag) FROM public.tbl_konto WHERE buchungsnr_verweis = bk.buchungsnr),0)
				AND buchungsnr_verweis IS NULL
				AND person_id = ?
				AND buchungsdatum >= ?
				AND buchungsdatum <= now()
				AND tbl_studiensemester.start <= ?
			ORDER BY
				studiengang_kz
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

