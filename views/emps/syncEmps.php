<?php
	$this->load->view(
		'templates/FHC-Header',
		array(
			'title' => 'EMPs',
			'jquery' => true,
			'jqueryui' => true,
			'bootstrap' => true,
			'fontawesome' => true,
			'sbadmintemplate' => true,
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
				<div class="col-lg-12">
					<h3 class="page-header">Mitarbeiter synchen</h3>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-4">
					<input class="form-control" type="text" id="empId" placeholder="Mitarbeiter" />
				</div>
				<div class="col-xs-4">
					<button id="sync" class="btn btn-default">Syncen</button>
				</div>
			</div>
			<div class="row">
				<div id="syncOutput" class="col-xs-12">
				</div>
			</div>
		</div>
	</div>
</body>
<?php $this->load->view('templates/FHC-Footer'); ?>