<?php
	$this->load->view(
		'templates/FHC-Header',
		array(
			'title' => 'EMPs',
			'jquery3' => true,
			'jqueryui1' => true,
			'bootstrap3' => true,
			'fontawesome4' => true,
			'sbadmintemplate3' => true,
			'ajaxlib' => true,
			'dialoglib' => true,
			'navigationwidget' => true,
			'customJSs' => array('public/extensions/FHC-Core-SAP/js/syncEmps.js')
		)
	);
?>


<body>
	<?php echo $this->widgetlib->widget('NavigationWidget'); ?>
	<div id="page-wrapper">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-6">
					<h3 class="page-header">Mitarbeiter synchen</h3>
				</div>
				<div class="col-lg-6">
					<h3 class="page-header">Mitarbeiter CSV-Export</h3>
				</div>
			</div>
			<div class="row">
				<div class="form-group col-lg-6">
					<div class="col-lg-6">
						<input class="form-control" type="text" id="empId" placeholder="Mitarbeiter" />
					</div>
					<div class="col-lg-6">
						<button id="sync" class="btn btn-default">Syncen</button>
					</div>
				</div>
				<div class="col-lg-6">
					<form method="GET" action="<?php echo site_url('extensions/FHC-Core-SAP/emps/SyncEmps/getCSVEmployees'); ?>" target="_blank">
						<div class="col-lg-6">
							<button type="submit" class="btn btn-default">Download</button>
						</div>
					</form>
				</div>
			</div>
			<div class="row">
				<div class="form-group col-lg-6">
					<div id="syncOutput" class="col-lg-12">
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
<?php $this->load->view('templates/FHC-Footer'); ?>