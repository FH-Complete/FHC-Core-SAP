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

import {CoreFetchCmpt} from '../../../../../js/components/Fetch.js';
import {CoreRESTClient} from '../../../../../js/RESTClient.js';
import Phrasen from '../../../../../js/plugin/Phrasen.js';


import {SAPInvoicesAPIs} from './API.js';

const SAPInvoicesApp = Vue.createApp({
	data: function() {
		return {
			syncedInvoices: null,
			notSyncedInvoices: null,
			notRelevantInvoices: null,
			displayInvoiceLink: true
		};
	},
	components: {
		CoreFetchCmpt
	},
	methods: {
	 	setData: function(payload) {

			//
			let payloadData = CoreRESTClient.getData(payload);

			//
			if (payloadData == null) return;

			//
			if (payloadData.hasOwnProperty("INVOICES_EXISTS_SAP"))
			{
				this.syncedInvoices = payloadData.INVOICES_EXISTS_SAP;
			}

			//
			if (payloadData.hasOwnProperty("INVOICES_TO_BE_SYNCED"))
			{
				this.notSyncedInvoices = payloadData.INVOICES_TO_BE_SYNCED;

				// Sort by study semester
				this.notSyncedInvoices.sort(function(a, b) {

					let r = 0; // by default they are equal

					// Sort by year, last 4 chars
					if (a.studiensemester.substring(2, a.studiensemester.length) < b.studiensemester.substring(2, a.studiensemester.length))
					{
						r =  1;
					}
					else if (a.studiensemester.substring(2, a.studiensemester.length) > b.studiensemester.substring(2, a.studiensemester.length))
					{
						r = -1;
					}

					// If the same year and different semester
					if (r == 0 && a.studiensemester.substring(0, 2) != b.studiensemester.substring(0, 2))
					{
						// Winter Semester after the Summer Semester
						r = -1;
						if (a.studiensemester.substring(0, 2) == 'WS') r = 1;
					}

					return r;
				});
			}

			//
			if (payloadData.hasOwnProperty("INVOICES_NOT_RELEVANT"))
			{
				this.notRelevantInvoices = payloadData.INVOICES_NOT_RELEVANT;
			}

			//
			if (payloadData.FHTW_INVOICES_EXISTS)
			{
				document.getElementById("ibans").innerHTML += "<br/><strong>Fachhochschule Technikum Wien<br/>IBAN: AT71 1100 0085 7328 7300</strong>";
			}

			//
			if (payloadData.FHTW_INVOICES_EXISTS && payloadData.GMBH_INVOICES_EXISTS) document.getElementById("ibans").innerHTML += "<br/>";

			//
			if (payloadData.GMBH_INVOICES_EXISTS)
			{
				document.getElementById("ibans").innerHTML += "<br/><strong>Technikum Wien GmbH<br/>IBAN: AT59 1200 0518 3820 2701</strong>";
			}
		},
		getInvoices: function() {
			let urlParams = new URLSearchParams(window.location.search);
			return SAPInvoicesAPIs.getInvoices(urlParams.has('person_id') ? urlParams.get('person_id') : null);
		},
		getSapPDFURL: function() {
			return FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + "/" + FHC_JS_DATA_STORAGE_OBJECT.called_path;
		},
		getPDFURL: function(invoiceEntries) {

			let buchungsnummern = ''; //

			//
			for (let i = 0; i < invoiceEntries.length; i++) buchungsnummern += invoiceEntries[i].buchungsnr + ";";

			return FHC_JS_DATA_STORAGE_OBJECT.app_root +
				"cis/private/pdfExport.php?xml=konto.rdf.php" +
				"&xsl=Zahlung&buchungsnummern=" + buchungsnummern +
				"&uid=" + invoiceEntries[0].uid +
				"&stg_kz=" + invoiceEntries[0].studiengang_kz;
		},
		formatValueIfNull: function(value) {
			return value == null ? '-' : value;
		},
		getTotal: function(invoice) {

			let total = 0.0;

			//
			for (let i = 0; i < invoice.length; i++) total += parseFloat(invoice[i].betrag);

			return total.toFixed(2);
		},
		getTotalPartial: function(invoice) {

			let total = 0.0;

			//
			for (let i = 0; i < invoice.length; i++) total += parseFloat(invoice[i].partial);

			return total.toFixed(2);
		},
		isPaid: function(invoice) {

			// For each invoice entry
			for (let i = 0; i < invoice.length; i++)
			{
				// If at least one is not paid then return everything as not paid
				if (invoice[i].paid == false) return false;
			}

			// Otherwise return everything as paid
			return true;
		},
		getStatus: function(statusObj, paid) {

			let status = "-";

			// Better safe than sorry!
			if (statusObj != null)
			{
				//
				if (statusObj.ClearingStatusCode == 4)
				{
					// If the invoice is consistent
					if (statusObj.ConsistencyStatusCode == 3)
					{
						// If the release status is approved
						if (statusObj.ReleaseStatusCode == 3
							|| statusObj.ReleaseStatusCode == 4
							|| statusObj.ReleaseStatusCode == 5)
						{
							// If paid
							if (paid === true)
							{
								status = "Bezahlt";
							}
							else
							{
								status = "Offen";
							}
						}
					}
				}
			}

			return status;
		}
	},
	template: `
                <!-- Loads invoices -->
                <core-fetch-cmpt
                        v-bind:api-function="getInvoices"
                        @data-fetched="setData">
                </core-fetch-cmpt>

		<div id="invoicesTable">
			<table class="table table-bordered">
				<thead>
					<tr>
						<th scope="col">{{ $p.t('infocenter', 'rechnungsnummer') }}</th>
						<th scope="col">{{ $p.t('infocenter', 'bezeichnung') }}</th>
						<th scope="col">{{ $p.t('infocenter', 'studiensemester') }}</th>
						<th scope="col">{{ $p.t('infocenter', 'datum') }}</th>
						<th scope="col">{{ $p.t('infocenter', 'faelligam') }}</th>
						<th scope="col">{{ $p.t('infocenter', 'gesamtbetrag') }}</th>
						<!-- <th scope="col">Eingezahlt</th> -->
						<th scope="col">{{ $p.t('infocenter', 'rechnungsempfaenger') }}</th>
						<th scope="col">{{ $p.t('global', 'status') }}</th>
						<th scope="col"  v-if="displayInvoiceLink">{{ $p.t('infocenter', 'rechnung') }}</th>
						<th scope="col">{{ $p.t('infocenter', 'zahlungsbestaetigung') }}</th>

					</tr>
				</thead>
				<tbody>

					<!-- Not synced invoices -->
					<template v-for="(invoice, invoiceIndex) in notSyncedInvoices">
						<tr>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">Wird erstellt</td>
							<td class="align-middle">{{ formatValueIfNull(invoice.bezeichnung) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoice.studiensemester) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">{{ formatValueIfNull(invoice.datum) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">{{ formatValueIfNull(invoice.faellingAm) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoice.betrag) }}</td>
							<!-- <td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">-</td> -->
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">-</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">Wird erstellt</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">-</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">-</td>
						</tr>
					</template>

					<!-- Synced invoices -->
					<template v-for="(invoice, sapInvoiceId) in syncedInvoices">
						<tr v-for="(invoiceEntry, invoiceEntryIndex) in invoice" v-if="getTotal(invoice) > 0">
							<td class="align-middle" v-if="invoiceEntryIndex == 0" v-bind:rowspan="invoice.length">
								{{ sapInvoiceId }}
							</td>

							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.bezeichnung) }}</td>

							<template v-if="invoiceEntryIndex == 0">
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(invoiceEntry.studiensemester) }}</td>
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(invoiceEntry.datum) }}</td>
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(invoiceEntry.faellingAm) }}</td>
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(getTotal(invoice)) }}</td>
								<!-- <td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(getTotalPartial(invoice)) }}</td> -->
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(invoiceEntry.email) }}</td>
								<td class="align-middle" v-bind:rowspan="invoice.length" v-bind:class="{'bg-success': isPaid(invoice), 'bg-warning': !isPaid(invoice)}">
									<strong>{{ getStatus(invoiceEntry.status, isPaid(invoice)) }}</strong>
								</td>
								<td class="align-middle text-center" v-if="displayInvoiceLink" v-bind:rowspan="invoice.length">
									<a
										class="fa-solid fa-file-pdf fa-2xl link-dark"
										style="text-decoration: none;"
										target="_blank"
										v-if="invoiceEntry.invoiceUUID != null"
										v-bind:href="getSapPDFURL() + '/getSapInvoicePDF?invoiceUuid=' + invoiceEntry.invoiceUUID"
									></a>
									<template v-if="invoiceEntry.invoiceUUID == null">-</template>
								</td>
								<td class="align-middle text-center" v-bind:rowspan="invoice.length">
									<a
										style="text-decoration: none;"
										class="fa-solid fa-file-pdf fa-2xl link-dark"
										target="_blank"
										v-if="invoiceEntry.paid"
										v-bind:href="getPDFURL(invoice)"
									></a>
								</td>
							</template>
						</tr>
					</template>

					<!-- Not relevant invoices -->
					<!--
					<template v-for="(invoice, invoiceIndex) in notRelevantInvoices">
						<tr>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">Nicht relevant</td>
							<td class="align-middle">{{ formatValueIfNull(invoice.bezeichnung) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">-</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">{{ formatValueIfNull(invoice.datum) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">{{ formatValueIfNull(invoice.faellingAm) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">{{ formatValueIfNull(getTotal(notRelevantInvoices)) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">{{ formatValueIfNull(getTotalPartial(notRelevantInvoices)) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">-</td>
							<td class="align-middle bg-success" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length"><strong>Bezahlt</strong></td>
							<td class="align-middle" v-if="invoiceIndex == 0 && displayInvoiceLink" v-bind:rowspan="notRelevantInvoices.length">-</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notRelevantInvoices.length">-</td>
						</tr>
					</template>
					-->

				</tbody>
			</table>
		</div>
	`
});

SAPInvoicesApp.use(Phrasen).mount('#divInvoicesTable');
