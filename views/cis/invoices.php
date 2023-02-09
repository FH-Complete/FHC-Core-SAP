<?php
	$includesArray = array(
		'title' => 'Invoices',
		'axios027' => true,
		'bootstrap5' => true,
		'fontawesome6' => true,
		'vue3' => true,
		'customJSModules' => array('public/extensions/FHC-Core-SAP/js/apps/Invoices/SAPInvoices.js')
	);

	$this->load->view('templates/FHC-Header', $includesArray);
?>

	<div id="main">
		<div id="content">

			<div>
				<h2>Meine Zahlungen</h2>
			</div>

			<br/>
			<br/>

			<div>
				<div class="row">
					<div class="col-8">
						<h5 class="card-title">Ablauf und Zahlungsbedingungen</h5>

						<br/>

						Wir möchten Sie darauf aufmerksam machen, dass bei der Überweisung *immer* die Rechnungsnummer als Zahlungsreferenz anzuführen ist.
						Andernfalls erfolgt keine automatische Zahlungszuordnung und es kann zu einer Verzögerung der Darstellung des aktuellen Zahlungsstatus
						der Rechnung im CIS kommen.
						<br/>
						<br/>
						Im Falle dass der Betrag an ein falsches Konto überwiesen wurde, bitten wir Sie höflichst sich an Ihre Bank zu wenden.
						<br/>
						<br/>
						Jede Rechnung gilt als "Bezahlt", wenn der Gesamtbetrag vollständig auf unser Konto eingelangt ist.
						<br/>
						<br/>
						Zahlungsbedingungen: 14 Tage netto nach Rechnungserhalt
					</div>
					<div class="col-4">
						<div class="card">
							<div class="card-header" style="background-color: #cfe2ff;">
								<h5>Kontoinformationen der FHTW</h5>
							</div>
							<div class="card-body border-light">
								Sämtliche Zahlungen sind an die nachstehende Kontonummer zu leisten und die Rechnungsnummer muss als Zahlungsreferenz eingegeben werden.

								<br/>

								<h6>
									<div id="ibans"></div>
								</h6>

								<br/>

								<div class="content">
									<div class="row g-0">
										<div class="col-1" style="width: 3px; background-color: #cfe2ff;"></div>
										<div class="col-1" style="width: 7px;"></div>
										<div class="col-10">
											Auslandsüberweisungen:
											<br/>
											Bei Auslandsüberweisungen sind die Spesenkosten von den
											<br/>
											Zahlenden zusätzlich zu den Rechnungsbeträgen zu zahlen.
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<h3>Rechnungen & Zahlungsbestätigungen</h3>

			<br/>

			<!-- Invoices table -->
			<div class="w-100" id="divInvoicesTable"></div>

			<br/>
			<br/>

			<div>
				<h3>FAQs</h3>
			</div>

			<br/>

			<div>
				<!-- FAQ 0 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-0" aria-expanded="false" aria-controls="faq-0"
						></a>
					</div>
					<div class="col-11">
						Warum ist die Einzahlung trotz Einzahlung noch offen?
					</div>
				</div>
				<div class="row collapse" id="faq-0">
					<div class="col card card-body border-light">
						<pre>Der häufigste Grund für diesen Fall ist, dass bei der Überweisung nicht die Rechnungsnummer als Zahlungsreferenz eingegeben wird. Wir bitten Sie höflichst in diesem
Fall eine Mail an <a href="mailto:billing@technikum-wien.at">billing@technikum-wien.at</a> mit Zahlungsbestätigung zu senden.
Die Transaktion und die Bearbeitung der Zahlung, kann bis zu sechs Werktage dauern.</pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 1 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-1" aria-expanded="false" aria-controls="faq-1"
						></a>
					</div>
					<div class="col-11">
						Ich habe keine Rechnung erhalten, was tun?
					</div>
				</div>
				<div class="row collapse" id="faq-1">
					<div class="col card card-body border-light">
						<pre>In diesem Fall ist der Spam-Ordner zu kontrollieren. Falls die Rechnung nicht übermittelt wurde ersuchen wir um Information an <a href="mailto:billing@technikum-wien.at">billing@technikum-wien.at</a>.
Die Rechnung wird Ihnen erneut zugesendet. <u><strong>Erst nach Erhalt der Rechnung ist der Betrag zu überweisen</strong></u></pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 2 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-2" aria-expanded="false" aria-controls="faq-2"
						></a>
					</div>
					<div class="col-11">
						Refundierung des Studienbeitrags
					</div>
				</div>
				<div class="row collapse" id="faq-2">
					<div class="col card card-body border-light">
						<pre>Der Studienbeitrag wird nicht rückerstattet, wenn…
-Anfänger*innen, die ihren Studienplatz nach Semesterbeginn (1. September / 16. Februar) nicht in Anspruch nehmen
-Studierende, die ihr Studium nach Semesterbeginn (1. September / 16. Februar) abbrechen.

-Unterbrechung vor dem 15.10. bzw. 15.3.: Studienbeitrag wird rückerstattet 
-Unterbrechung nach dem 15.10. bzw. 15.3.: Studienbeitrag wird nicht rückerstattet 
-in den Folgesemestern der Unterbrechung sind keine Studienbeiträge zu zahlen; der ÖHBeitrag ist jedoch in jedem Semester der Unterbrechung zu zahlen</pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 3 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-3" aria-expanded="false" aria-controls="faq-3"
						></a>
					</div>
					<div class="col-11">
						Sie sind vom Studienbeitrag befreit und haben eine Rechnung für den Studienbeitrag bekommen?
					</div>
				</div>
				<div class="row collapse" id="faq-3">
					<div class="col card card-body border-light">
						<pre>Treten Sie bitte in Kontakt mit Ihrer Studiengangsassistenz. Die offene Rechnung wird storniert.</pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 4 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-4" aria-expanded="false" aria-controls="faq-4"
						></a>
					</div>
					<div class="col-11">
						Mir ist ein Fehler bei der Überweisung unterlaufen, was tun?
					</div>
				</div>
				<div class="row collapse" id="faq-4">
					<div class="col card card-body border-light">
						<pre>Bitte den Fehler an <a href="mailto:billing@technikum-wien.at">billing@technikum-wien.at</a> melden.</pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 5 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-5" aria-expanded="false" aria-controls="faq-5"
						></a>
					</div>
					<div class="col-11">
						Eine Rechnung wurde zwei Mal überwiesen, was tun?
					</div>
				</div>
				<div class="row collapse" id="faq-5">
					<div class="col card card-body border-light">
						<pre>Falls eine Rechnung doppelt überwiesen wurde, bitten wir Sie dies an <a href="mailto:billing@technikum-wien.at">billing@technikum-wien.at</a> zu melden. Wir werden Ihnen eine Zahlung refundieren.</pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 6 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-6" aria-expanded="false" aria-controls="faq-6"
						></a>
					</div>
					<div class="col-11">
						Es stehen mehrere Positionen auf der Rechnung – soll für jede Position eine Überweisung durchgeführt werden?
					</div>
				</div>
				<div class="row collapse" id="faq-6">
					<div class="col card card-body border-light">
						<pre>Nein, es ist immer der auf der Rechnung ausgewiesene Gesamtbetrag zu überweisen.</pre>
					</div>
				</div>

				<br/>

				<!-- FAQ 7 -->
				<div class="row g-0">
					<div class="col-1" style="width: 30px;">
						<a role="button"
							class="fa-solid fa-plus fa-xl link-dark"
							style="text-decoration: none;"
							data-bs-toggle="collapse" href="#faq-7" aria-expanded="false" aria-controls="faq-7"
						></a>
					</div>
					<div class="col-11">
						Wann kann der Betrag überwiesen werden?
					</div>
				</div>
				<div class="row collapse" id="faq-7">
					<div class="col card card-body border-light">
						<pre>Wir möchten Sie darauf hinweisen, dass Überweisungen erst bei Erhalt der Rechnung durchzuführen sind. Bitte um Angabe der Rechnungsnummer als Zahlungsreferenz.</pre>
					</div>
				</div>

			</div>
		</div>
	</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>

