<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class ExampleJob extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads QueryCustomerInModel
                $this->load->model('extensions/FHC-Core-SAP/SOAP/QueryCustomerIn_model', 'QueryCustomerInModel');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function example()
	{
		$this->logInfo('Example job start');

		$callResult = $this->QueryCustomerInModel->findByCommunicationData(
			array(
				'CustomerSelectionByCommunicationData' => array(
					'SelectionByEmailURI' => array(
						'LowerBoundaryEmailURI' => '*@technikum-wien.at',
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)                                     
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);

		// If groups are present
		if (hasData($callResult))
		{
			$countCustomers = count(getData($callResult)->Customer);

			$this->logInfo('Total customers: '.$countCustomers);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Example job stop');
	}
}

