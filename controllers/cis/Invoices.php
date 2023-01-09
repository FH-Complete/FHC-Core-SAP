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

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 */
class Invoices extends FHC_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		//
		parent::__construct();

		// Loads the AuthLib and starts the authentication
		$this->load->library('AuthLib');
		// Loads the PermissionLib
		$this->load->library('PermissionLib');

		// Loads the SyncPaymentsLib library
		$this->load->library('extensions/FHC-Core-SAP/SyncPaymentsLib');
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Loads the main page using a view
	 */
	public function index()
	{
		$this->load->view('extensions/FHC-Core-SAP/cis/invoices.php');
	}

	/**
	 * Get the invoices for the given user (default is the logged user)
	 */
	public function getInvoices()
	{
		// Get the person id of the logged user or the provided one if the logged user
		// has the rights to do it
		$person_id = $this->_getPersonId();

		// Get the list of invoices for the given user
		$invoices = $this->syncpaymentslib->listInvoices($person_id);

		// If invoices have been found
		if (hasData($invoices))
		{
			// Stores the result into the user session
			setSessionElement(
				SyncPaymentsLib::SESSION_NAME_CIS_INVOICES,
				SyncPaymentsLib::SESSION_NAME_CIS_INVOICES_ELEMENT,
				getData($invoices)->{SyncPaymentsLib::INVOICES_EXISTS_SAP}
			);
		}

		// JSON output of the invoices list for the given user
		$this->outputJson($invoices);
	}

	/**
	 * 
	 */
	public function getSapInvoicePDF()
	{
		$sapPDF = null; // SAP pdf file

		// Get the invoice UUID via HTTP GET
		$invoiceUuid = $this->input->get('invoiceUuid');

		// Get the list of invoices stored previously in the session
		$invoices = getSessionElement(
			SyncPaymentsLib::SESSION_NAME_CIS_INVOICES,
			SyncPaymentsLib::SESSION_NAME_CIS_INVOICES_ELEMENT
		);

		// If invoices were stored previously in the session
		if ($invoices != null)
		{
			$found = false; //

			// Search if the user has the rights to get this PDF
			foreach ($invoices as $invoice)
			{
				// Search into the invoice entry
				foreach ($invoice as $invoiceEntry)
				{
					// If the invoice is found
					if ($invoiceEntry->invoiceUUID == $invoiceUuid)
					{
						$found = true;
						break; // exit the first loop
					}
				}

				if ($found) break; // exit the second loop
			}

			// If the logged user has the rights to get this PDF
			if ($found) $sapPDF = $this->syncpaymentslib->getSapInvoicePDF($invoiceUuid);
		}

		// If a PDF has been found on SAP
		if (hasData($sapPDF))
		{
			// Download the PDF
			header('Content-Type: application/pdf');
			echo getData($sapPDF);
			exit;
		}
		else // otherwise file not found
		{
			header('HTTP/1.0 404 Not Found');
			echo 'The requested file has not been found, please contact the support';
			exit;
		}
	}

	/**
	 *
	 */
	private function _getPersonId()
	{
		// Get the person_id of the authenticated user
		$returnPersonId = $authPersonId = getAuthPersonId();

		// Get the required person id of another user
		$askedPersonId = $this->input->get('person_id');

		// If the user is trying to view the invoices on another user
		if (!isEmptyString($askedPersonId) && is_numeric($askedPersonId) && $authPersonId != $askedPersonId)
		{
			// If the logged user is admin then grant it
			if ($this->permissionlib->isEntitled(array('getInvoices' => 'admin:r', 'getSapInvoicePDF' => 'admin:r'), $this->router->method)
				|| $this->permissionlib->isEntitled(array('getInvoices' => 'student/zahlungAdmin:r', 'getSapInvoicePDF' => 'student/zahlungAdmin:r'), $this->router->method))
			{
				$returnPersonId = $askedPersonId;
			}
		}

		return $returnPersonId;
	}
}

