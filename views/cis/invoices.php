<?php
	$includesArray = array(
		'title' => 'Invoices',
		'axios027' => true,
		'bootstrap5' => true,
		'fontawesome6' => true,
		'vue3' => true,
		'customJSModules' => array('public/extensions/FHC-Core-SAP/js/apps/Invoices/SAPInvoices.js')
	);

	if(defined("CIS4") && CIS4){
		$this->load->view('templates/CISVUE-Header', $includesArray);
	}else{
		$this->load->view('templates/FHC-Header', $includesArray);
	}
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
						<h5 class="card-title"><?php echo $this->p->t('bewerbung', 'erklaerungInvoices')?></h5>
						<br />
						<?php echo $this->p->t('infocenter', 'rechnungserklaerung'); ?>
					</div>
					<div class="col-4">
						<div class="card">
							<div class="card-header" style="background-color: #cfe2ff;">
								<h5><?php echo $this->p->t('infocenter', 'kontoinfotitle'); ?></h5>
							</div>
							<div class="card-body border-light">
								<?php echo $this->p->t('infocenter', 'kontoinfobody'); ?>

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
											<?php echo $this->p->t('infocenter', 'kontoinfoausland'); ?>

										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<h3><?php echo $this->p->t('infocenter', 'rechnungtitle'); ?></h3>

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
						<?php echo $this->p->t('infocenter', 'faq0frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-0">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq0antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq1frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-1">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq1antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq2frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-2">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq2antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq3frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-3">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq3antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq4frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-4">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq4antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq5frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-5">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq5antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq6frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-6">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq6antwort'); ?></pre>
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
						<?php echo $this->p->t('infocenter', 'faq7frage'); ?>
					</div>
				</div>
				<div class="row collapse" id="faq-7">
					<div class="col card card-body border-light">
						<pre><?php echo $this->p->t('infocenter', 'faq7antwort'); ?></pre>
					</div>
				</div>

			</div>
		</div>
	</div>

<?php

if (defined("CIS4") && CIS4) {
	$this->load->view('templates/CISVUE-Footer', $includesArray);
} else {
	$this->load->view('templates/FHC-Footer', $includesArray);
}

?>

