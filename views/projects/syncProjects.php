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
		<!-- title & helper link -->
		<div class="row">
			<div class="col-lg-12 page-header">
				<h3>Projekt Synchronisation</h3>
			</div>
		</div>
        <div class="row">
            <div class="col-xs-3">
                <h4 class="text-center">SAP Projekte</h4>
            </div>
            <div class="col-xs-6">
                <h4 class="text-center">Verknüpfung</h4>
            </div>
            <div class="col-xs-3">
                <h4 class="text-center">FH Projekte</h4>
            </div>
        </div>
		<div class="row">
            <!--  LEFT COLUMN  -->
			<div class="col-xs-3">
				<div class="row">
					<?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPProjectsData.php'); ?>
				</div>
			</div>
            <!--  MIDDLE COLUMN  -->
			<div class="col-xs-6">
                <br>
                <div class="row">
                    <div class="col-xs-offset-1 col-xs-10">
                        <div id="panel-projects" class="panel panel-default">

                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-xs-3">
                                        <strong>PROJEKTE</strong>
                                    </div>
                                    <div class="col-xs-9 text-right">
                                        <strong><span id="projects-msg"></span></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-heading">
                                <br>
                                <div class="row">
                                    <div class="col-xs-6">
                                        <input type="text" id="input-sap-project"
                                               data-sap-project-syncStatus="false" placeholder="SAP Projekt..." readonly>
                                    </div>
                                    <div class="col-xs-6">
                                        <input type="text" id="input-fue-project"
                                               data-fue-project-syncStatus="false" placeholder="FH Projekt..." readonly>
                                    </div>
                                </div>
                                <br>
                            </div>

                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-xs-4">
                                        <small><span class="text-muted">SAP Projekt mit FH Projekt verknüpfen</span></small>
                                    </div>
                                    <div class="col-xs-8">
                                        <button id="btn-sync-projects" class="btn btn-default pull-right">Verknüpfen</button>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-xs-4">
                                        <small><span class="text-muted">SAP Projekt als FH Projekt erstellen & verknüpfen</span></small>
                                    </div>
                                    <div class="col-xs-8">
                                        <button id="btn-create-project" class="btn btn-default pull-right">Neu & Verknüpfen</button>
                                        <div class="form-group">
		                                    <?php
		                                    echo $this->widgetlib->widget(
			                                    'Organisationseinheit_widget',
			                                    array(
				                                    'id' => 'select-organisationseinheit'
			                                    )
		                                    );
		                                    ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br>
                <div class="row">
                    <div class="col-xs-offset-1 col-xs-10">
                        <div id="panel-projectphases" class="panel panel-default">

                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-xs-3">
                                        <strong>Projektphasen</strong>
                                    </div>
                                    <div class="col-xs-9 text-right">
                                        <strong><span id="projectphases-msg"></span></strong>
                                    </div>
                                </div>

                            </div>

                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-xs-5">
                                        <small><span class="text-muted">SAP Projektphase mit FH Projektphase verknüpfen</span></small>
                                    </div>
                                    <div class="col-xs-7">
                                        <button id="btn-sync-phases" class="btn btn-default pull-right">Verknüpfen</button>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-xs-5">
                                        <small><span class="text-muted">SAP Projektphase als FH Projektphase erstellen & verknüpfen</span></small>
                                    </div>
                                    <div class="col-xs-7">
                                        <button id="btn-create-phase" class="btn btn-default pull-right">Neu & Verknüpfen</button>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-body">
                                <div class="col-xs-6">
                                    <?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPPhasesData.php'); ?>
                                </div>
                                <div class="col-xs-6">
                                    <?php $this->load->view('extensions/FHC-Core-SAP/projects/FHPhasesData.php'); ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
			</div>
            <!--  RIGHT COLUMN  -->
			<div class="col-xs-3">
                <div class="row">
					<?php $this->load->view('extensions/FHC-Core-SAP/projects/FHProjectsData.php'); ?>
                </div>
			</div>
		</div>
	</div><!--/.container-fluid -->
</div><!--/.wrapper -->
</body>
<?php $this->load->view('templates/FHC-Footer'); ?>
