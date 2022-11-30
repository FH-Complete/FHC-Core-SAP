/**
 * Copyright (C) 2022 fhcomplete.org
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
			invoices: null,
			notExistsSAP: "NOT_EXISTS_SAP"
		};
	},
	components: {
		CoreFetchCmpt
	},
	methods: {
		setInvoices: function(payload) {
			this.invoices = CoreRESTClient.getData(payload);
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
		formatValueIfNull(value) {
			return value == null ? '-' : value;
		}
	},
	template: `
                <!-- Loads invoices -->
                <core-fetch-cmpt
                        v-bind:api-function="getInvoices"
                        @data-fetched="setInvoices">
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
						<th scope="col">Betrag</th>
						<th scope="col">Rechnungsempfänger</th>
						<th scope="col">Status</th>
						<th scope="col">Rechnung</th>
						<th scope="col">Zahlungsbestätigung</th>
					</tr>
				</thead>
				<tbody>
					<template v-for="(invoice, sapInvoiceId) in invoices">
						<tr v-for="(invoiceEntry, invoiceEntryIndex) in invoice">
							<td class="align-middle" v-if="invoiceEntryIndex == 0 && sapInvoiceId != notExistsSAP" v-bind:rowspan="invoice.length">
								{{ sapInvoiceId }}
							</td>
							<td class="align-middle" v-else-if="invoiceEntryIndex == 0 && sapInvoiceId == notExistsSAP" v-bind:rowspan="invoice.length">
								Wird erstellt
							</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.bezeichnung) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.studiensemester) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.datum) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.faellingAm) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.betrag) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.email) }}</td>
							<td class="align-middle">{{ formatValueIfNull(invoiceEntry.status) }}</td>
							<td class="align-middle text-center" v-if="invoiceEntryIndex == 0" v-bind:rowspan="invoice.length">
								<a
									class="fa-solid fa-file-pdf fa-2xl link-dark"
									style="text-decoration: none;"
									target="_blank"
									v-if="invoiceEntry.invoiceUUID != null"
									v-bind:href="getSapPDFURL() + '/getSapInvoicePDF?invoiceUuid=' + invoiceEntry.invoiceUUID"
								></a>
								<template v-if="invoiceEntry.invoiceUUID == null">-</template>
							</td>
							<td class="align-middle text-center">
								<a
									style="text-decoration: none;"
									class="fa-solid fa-file-pdf fa-2xl link-dark"
									target="_blank"
									v-if="invoiceEntry.paid"
									v-bind:href="getPDFURL(invoiceEntry.uid, invoiceEntry.buchungsnr)"
								></a>
							</td>
						</tr>
					</template>
				</tbody>
			</table>
		</div>
	`
});

SAPInvoicesApp.mount('#divInvoicesTable');

