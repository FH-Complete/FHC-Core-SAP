<?php
$includesArray = array(
    'title' => 'Projekt-Synchronisation',
    'jquery3' => true,
    'jqueryui1' => true,
    'jquerycheckboxes1' => true,
    'bootstrap3' => true,
    'fontawesome4' => true,
    'sbadmintemplate3' => true,
    'tabulator5' => true,
    'tabulator5JQuery' => true,
    'momentjs2' => true,
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
);

$this->load->view('templates/FHC-Header', $includesArray);
?>
<div id="wrapper">
    <?php echo $this->widgetlib->widget('NavigationWidget'); ?>
    <div id="page-wrapper">
        <div class="container-fluid">
            <!-- title -->
            <div class="row">
                <div class="col-lg-12 page-header">
                    <h3>Projekt Synchronisation<small> | SAP <> FH Projekte- und Phasensynchronisation</small></h3>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-9">
                    <span class="tabulator-title">Projekte Überblick</span>
                    <div class="btn-group pull-right">
                        <button type="button" class="btn btn-default btn-lg" data-toggle="tooltip" data-placement="right" title="Projekt neu erstellen" id="btn-create-project"><i class="fa fa-plus" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-default btn-lg" data-toggle="tooltip" data-placement="right" title="Projekte verknüpfen" id="btn-sync-project"><i class="fa fa-link" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-default btn-lg" data-toggle="tooltip" data-placement="right" title="Projekte entknüpfen [Entknüpft auch Phasen zum Projekt]" id="btn-desync-projects"><i class="fa fa-chain-broken" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="col-xs-3">
                    <span><b>FH Projekte</b></span>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-9 tabulator-initialfontsize">
                    <?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPProjectsData.php'); ?>
                </div>
                <div class="col-xs-3 tabulator-initialfontsize">
                    <?php $this->load->view('extensions/FHC-Core-SAP/projects/FHProjectsData.php'); ?>
                </div>
            </div>
            <br>
            <div class="row">
                <div class="col-xs-9">
                    <span class="tabulator-title">Projektphasen Überblick von Projekt: <span id="span-sap-project">[ Projekt auswählen ]</span></span>
                    <div class="btn-group pull-right">
                        <button type="button" class="btn btn-default btn-lg" data-toggle="tooltip" data-placement="right" title="Phase(n) neu erstellen [Mehrfachselektion möglich]" id="btn-create-phase"><i class="fa fa-plus" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-default btn-lg" data-toggle="tooltip" data-placement="right" title="Phasen verknüpfen" id="btn-sync-phases"><i class="fa fa-link" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-default btn-lg" data-toggle="tooltip" data-placement="right" title="Phasen entknüpfen" id="btn-desync-phases"><i class="fa fa-chain-broken" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="col-xs-3">
                    <span data-toggle="tooltip" data-placement="right" title="Wenn das gewählte Projekt aus der Projekt Übersichtstabelle mit einem FH-Projekt verknüpft ist, werden hier die zugehörigen FH-Projektphasen automatisch angezeigt.">
                        <i class="fa fa-lg fa-info-circle" aria-hidden="true"></i>
                    </span>
                    <span><b>FH Projektphasen von: <span id="span-fh-project">-</span></b></span>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-9 tabulator-initialfontsize">
                    <?php $this->load->view('extensions/FHC-Core-SAP/projects/SAPPhasesData.php'); ?>
                </div>
                <div class="col-xs-3 tabulator-initialfontsize">
                    <?php $this->load->view('extensions/FHC-Core-SAP/projects/FHPhasesData.php'); ?>
                </div>
            </div>
        </div><!--/.container-fluid -->
    </div>
</div><!--/.wrapper -->
<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
