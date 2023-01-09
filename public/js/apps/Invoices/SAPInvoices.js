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

import {SAPInvoicesAPIs} from './API.js';

const SAPInvoicesApp = Vue.createApp({
	data: function() {
		return {
			syncedInvoices: null,
			notSyncedInvoices: null
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
			if (payloadData.hasOwnProperty("INVOICES_NOT_EXISTS_SAP"))
			{
				this.notSyncedInvoices = payloadData.INVOICES_NOT_EXISTS_SAP;
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
		getPDFURL: function(uid, buchungsnr) {
			return FHC_JS_DATA_STORAGE_OBJECT.app_root +
				"cis/private/pdfExport.php?xml=konto.rdf.php" +
				"&xsl=Zahlung&uid='" + uid + "'&buchungsnummern='" + buchungsnr + "'";
		},
		formatValueIfNull: function(value) {
			return value == null ? '-' : value;
		},
		getTotal: function(invoice) {
			let total = 0;

			for (let i = 0; i < invoice.length; i++)
			{
				total = invoice[i].betrag;
			}

			return total;
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
						<th scope="col">RechnungsNr.</th>
						<th scope="col">Bezeichnung</th>
						<th scope="col">Studiensemester</th>
						<th scope="col">Datum</th>
						<th scope="col">Fällig am</th>
						<th scope="col">Gesamtbetrag</th>
						<th scope="col">Eingezahlt</th>
						<th scope="col">Rechnungsempfänger</th>
						<th scope="col">Status</th>
						<th scope="col">Rechnung</th>
						<th scope="col">Zahlungsbestätigung</th>
					</tr>
				</thead>
				<tbody>

					<!-- Not synced invoices -->
					<template v-for="(invoice, invoiceIndex) in notSyncedInvoices">
						<tr>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">Wird erstellt</td>
							<td class="align-middle">{{ formatValueIfNull(invoice.bezeichnung) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">{{ formatValueIfNull(invoice.studiensemester) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">{{ formatValueIfNull(invoice.datum) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">{{ formatValueIfNull(invoice.faellingAm) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">{{ formatValueIfNull(getTotal(notSyncedInvoices)) }}</td>
							<td class="align-middle" v-if="invoiceIndex == 0" v-bind:rowspan="notSyncedInvoices.length">-</td>
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
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(invoiceEntry.partial) }}</td>
								<td class="align-middle" v-bind:rowspan="invoice.length">{{ formatValueIfNull(invoiceEntry.email) }}</td>
								<td class="align-middle" v-bind:rowspan="invoice.length" v-bind:class="{'bg-success': invoiceEntry.paid, 'bg-warning': !invoiceEntry.paid}">
									<template v-if="invoiceEntry.status != null && invoiceEntry.status.ReleaseStatusCode == 3 && invoiceEntry.status.ClearingStatusCode == 4">
										<template v-if="invoiceEntry.paid">
											<strong>Bezahlt</strong>
										</template>
										<template v-else>
											<strong>Offen</strong>
										</template>
									</template>
									<template v-else>
										Undefined
									</template>
								</td>
								<td class="align-middle text-center" v-bind:rowspan="invoice.length">
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
										v-bind:href="getPDFURL(invoiceEntry.uid, invoiceEntry.buchungsnr)"
									></a>
								</td>
							</template>
						</tr>
					</template>
				</tbody>
			</table>
		</div>
	`
});

SAPInvoicesApp.mount('#divInvoicesTable');

