<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example API
 */
class Example extends API_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'Example' => 'basis/person:rw'
			)
		);

		// Loads QueryCustomerInModel
		$this->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/QueryCustomerIn_model', 'QueryCustomerInModel');
	}

	/**
	 * Example method
	 */
	public function getExample()
	{
		$this->response(
			$this->QueryCustomerInModel->findByCommunicationData(
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
			),
                        REST_Controller::HTTP_OK
		);
	}
}

