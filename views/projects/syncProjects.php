<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'Projekt-Synchronisation',
		'jquery' => true,
		'jqueryui' => true,
		'jquerycheckboxes' => true,
		'bootstrap' => true,
		'fontawesome' => true,
		'sbadmintemplate' => true,
		'tabulator' => true,
		'momentjs' => true,
		'ajaxlib' => true,
		'dialoglib' => true,
		'tablewidget' => true,
		'navigationwidget' => true,
		'phrases' => array(
			'ui' => array(
				'keineDatenVorhanden'
			),
            'lehre' => array(
                'organisationseinheit'
            )
        ),
		'customJSs' => array(
			'public/js/bootstrapper.js',
			'public/extensions/FHC-Core-SAP/js/syncProjects.js'
		),
		'customCSSs' => array(
            'public/extensions/FHC-Core-SAP/css/syncProjects.css'
        )
	)
);
?>
<body>
<?php echo $this->widgetlib->widget('NavigationWidget'); ?>
<div id="page-wrapper">
	<div class="container-fluid">
		<!-- title -->
		<div class="row">
			<div class="col-lg-12 page-header">
				<h3>Projekt Synchronisation</h3>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-9">
				<h4>SAP Projekt Überblick</small></h4>
			</div>
			<div class="col-xs-3">
				<h4>FH Projekte</h4>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-8">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPProjectsData.php'); ?>
			</div>
			<div class="col-xs-1 text-center">
				<div class="btn-group btn-group-vertical btn-group-lg" style="margin-top: 15%;">
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Neu erstellen"><span style="font-size: xx-large; font-weight: bold">+</span></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Verknüpfen"><i class="fa fa-link fa-2x" aria-hidden="true"></i></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Entknüpfen"><i class="fa fa-chain-broken fa-2x" aria-hidden="true"></i></button>
				</div>
			</div>
			<div class="col-xs-3">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/FHProjectsData.php'); ?>
			</div>
		</div>
		<br>
		<div class="row">
			<div class="col-xs-12">
				<div class="col-xs-9">
					<h4>SAP Projektphasen Überblick<small> | Gewähltes SAP Projekt: -</small></h4>
				</div>
				<div class="col-xs-3">
					<h4>FH Projektphasen<small> | Verknüpftes FH Projekt: -</small></h4>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-8">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPPhasesData.php'); ?>
			</div>
			<div class="col-xs-1 text-center">
				<div class="btn-group btn-group-vertical btn-group-lg" style="margin-top: 15%;">
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Neu erstellen"><span style="font-size: xx-large; font-weight: bold">+</span></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Verknüpfen"><i class="fa fa-link fa-2x" aria-hidden="true"></i></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Entknüpfen"><i class="fa fa-chain-broken fa-2x" aria-hidden="true"></i></button>
				</div>
			</div>
			<div class="col-xs-3">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/FHPhasesData.php'); ?>
			</div>
		</div>
	</div><!--/.container-fluid -->
</div><!--/.wrapper -->
</body>
<?php $this->load->view('templates/FHC-Footer'); ?>
