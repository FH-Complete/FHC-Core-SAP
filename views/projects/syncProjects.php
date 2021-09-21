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
				<span>SAP Projekt Überblick</span>
				<div class="btn-group pull-right">
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Neu erstellen" id="btn-create-project"><i class="fa fa-plus" aria-hidden="true"></i></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Verknüpfen" id="btn-sync-project"><i class="fa fa-link" aria-hidden="true"></i></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Entknüpfen"><i class="fa fa-chain-broken" aria-hidden="true"></i></button>
				</div>
			</div>
			<div class="col-xs-3">
				<span>FH Projekte</span>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-9">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPProjectsData.php'); ?>
			</div>
			<div class="col-xs-3">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/FHProjectsData.php'); ?>
			</div>
		</div>
		<br>
		<div class="row">
			<div class="col-xs-9">
				<span>SAP Projektphasen Überblick<small> | Gewähltes SAP Projekt: <span id="span-sap-project">-</span></small></span>
				<div class="btn-group pull-right">
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Neu erstellen" id="btn-create-phase"><i class="fa fa-plus" aria-hidden="true"></i></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Verknüpfen" id="btn-sync-phases"><i class="fa fa-link" aria-hidden="true"></i></button>
					<button type="button" class="btn btn-default" data-toggle="tooltip" data-placement="right" title="Entknüpfen" id="btn-desync-phases"><i class="fa fa-chain-broken" aria-hidden="true"></i></button>
				</div>
			</div>
			<div class="col-xs-3">
				<span>FH Projektphasen<small> | Verknüpftes FH Projekt: <span id="span-fh-project">-</span></small></span>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-9">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPPhasesData.php'); ?>
			</div>
			<div class="col-xs-3">
				<?php $this->load->view('extensions/FHC-Core-SAP/projects/FHPhasesData.php'); ?>
			</div>
		</div>
	</div><!--/.container-fluid -->
</div><!--/.wrapper -->
</body>
<?php $this->load->view('templates/FHC-Footer'); ?>
