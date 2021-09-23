// -----------------------------------------------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------------------------------------------
const SAP_PROJECT_TABLE = '[tableuniqueid = SAPProjects] #tableWidgetTabulator';
const FH_PROJECT_TABLE = '[tableuniqueid = FUEProjects] #tableWidgetTabulator';

const SAP_PHASES_TABLE = '[tableuniqueid = SAPPhases] #tableWidgetTabulator';
const FH_PHASES_TABLE = '[tableuniqueid = FUEPhases] #tableWidgetTabulator';

const SAP_PROJECT_STATUSBEZEICHNUNG = {
    "": "Alle",
    "1": "Planning",
    "2": "Start",
    "3": "Released",
    "4": "Stopped",
    "5": "Closed",
    "6": "Completed"
};

var organisationseinheit_selected = ''; // organisational unit, is needed to create new FH project

// -----------------------------------------------------------------------------------------------------------------
// Mutators - setter methods to manipulate table data when entering the tabulator
// -----------------------------------------------------------------------------------------------------------------

// Converts string date postgre style to string DD.MM.YYYY.
// This will allow correct filtering.
var mut_formatStringDate = function(value, data, type, params, component) {
    if (value != null)
    {
        var d = new Date(value);
        return ("0" + (d.getDate())).slice(-2)  + "." + ("0" + (d.getMonth() + 1)).slice(-2) + "." + d.getFullYear();
    }
}

// -----------------------------------------------------------------------------------------------------------------
// Tabulator table format functions
// -----------------------------------------------------------------------------------------------------------------

/**
 * Return nice readable sap projekt/phasenstatus instead of numeric value
 * @returns {{"": string, "1": string, "2": string, "3": string, "4": string, "5": string, "6": string}}
 */
function getSAPProjectStatusbezeichnung() {
    return SAP_PROJECT_STATUSBEZEICHNUNG;
}

// Resort table on row update and add row
function resortTable(row)
{
    var table = row.getTable();
    table.setSort([
        {column: 'isSynced', dir: 'desc'}
    ]);
}

// Get SAP phases and also, if the project is synchronized, the corresponding FH project and phases.
function rowSelected_onSAPProject(row)
{
    var is_synced = row.getData().isSynced;
    var project_id = row.getData().project_id;
    var projects_timesheet_id = row.getData().projects_timesheet_id;
    var name = row.getData().name;

    // Display SAP project name
	$("#span-sap-project").text(name);

    // Load SAP phases
    loadSAPPhases(project_id);

    // If SAP project is synced, get the synced FUE project name and project phases
    if (is_synced == 'true') {
        var data = {
            'projects_timesheet_id': projects_timesheet_id
        };

        FHC_AjaxClient.ajaxCallPost(
            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/getSyncedFHProject",
            data,
            {
                successCallback: function (data, textStatus, jqXHR) {
                    if (FHC_AjaxClient.hasData(data)) {

                        data = FHC_AjaxClient.getData(data);

                        // Display synced FH project name
                        $("#span-fh-project").text(data.titel != null ? data.titel : data.projekt_kurzbz);

                        // Load FH Phases table
                        loadFUEPhases(data.projekt_kurzbz);

                    }
                },
                errorCallback: function (jqXHR, textStatus, errorThrown) {
                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                }
            }
        );
    }
    else
    {
        // Reset synced FH project name
        $("#span-fh-project").text('-');

        // Empty FH phases table
        $(FH_PHASES_TABLE).tabulator('replaceData');
    }
}

/**
 * Empty Phasen tables and Project titles on Project Deselection.
 */
function rowDeselected_onSAPProject(row){
    $("#span-sap-project").text('-');
    $("#span-fh-project").text('-');
    $(SAP_PHASES_TABLE).tabulator('replaceData');
    $(FH_PHASES_TABLE).tabulator('replaceData');
}

// Load SAP phases of a given SAP project.
function loadSAPPhases(project_id)
{
    var data = {
        'project_id': project_id
    };

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/loadSAPPhases",
        data,
        {
            successCallback: function (data, textStatus, jqXHR) {
                if(FHC_AjaxClient.hasData(data))
                {
                    $(SAP_PHASES_TABLE).tabulator('replaceData', data.retval);
                }
                else
                {
                    $(SAP_PHASES_TABLE).tabulator('replaceData');
                }
            },
            errorCallback: function (jqXHR, textStatus, errorThrown) {
                FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
            }
        }
    );
}

// Load FH phases of a given FH project.
function loadFUEPhases(projekt_kurzbz)
{
    var data = {
        'projekt_kurzbz': projekt_kurzbz
    };

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/loadFUEPhases",
        data,
        {
            successCallback: function (data, textStatus, jqXHR) {
                if(FHC_AjaxClient.hasData(data))
                {
                    $(FH_PHASES_TABLE).tabulator('replaceData', data.retval);
                }
                else
                {
                    // FHC_DialogLib.alertInfo("FH-Projekt hat keine Phasen");
                    $(FH_PHASES_TABLE).tabulator('replaceData');
                }
            },
            errorCallback: function (jqXHR, textStatus, errorThrown) {
                FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
            }
        }
    );

}

$(function() {
// Init tooltip
$('[data-toggle="tooltip"]').tooltip();

// Synchronize SAP and FH project.
$("#btn-sync-project").click(function () {

    // Get selected rows data
    var sap_project_data = $(SAP_PROJECT_TABLE).tabulator('getSelectedData');
    var fue_project_data = $(FH_PROJECT_TABLE).tabulator('getSelectedData');

    // Checks
	if (sap_project_data.length == 0) {
		FHC_DialogLib.alertInfo('Bitte wählen Sie ein Projekt aus.');
		return;
	}

    if (sap_project_data[0].isSynced == 'true') {
        FHC_DialogLib.alertInfo('Projekt kann nicht verknüpft werden, da es bereits synchronisiert ist.');
        return;
    }

    if (fue_project_data.length == 0) {
        FHC_DialogLib.alertInfo('Bitte wählen Sie zum Verknüpfen noch ein FH-Projekt aus.');
        return;
    }

    if (fue_project_data[0].isSynced == 'true') {
        FHC_DialogLib.alertInfo('FH-Projekt ist bereits mit anderem Projekt synchronisiert.');
        return;
    }

    var projects_timesheet_id = sap_project_data[0].projects_timesheet_id;
    var projekt_id = fue_project_data[0].projekt_id;
    var projekt_kurzbz = fue_project_data[0].projekt_kurzbz;
    var titel = fue_project_data[0].titel;

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/syncProjects",
        {
            projects_timesheet_id: projects_timesheet_id,
            projekt_id: projekt_id
        },
        {
            successCallback: function (data, textStatus, jqXHR) {
                if (FHC_AjaxClient.isError(data)) {
                    // Print error message
                    FHC_DialogLib.alertWarning(FHC_AjaxClient.getError(data));
                }

                if (data.retval) {
                    // Update sync status
                    $(SAP_PROJECT_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{
                            projects_timesheet_id: projects_timesheet_id,
                            projekt_kurzbz: projekt_kurzbz,
                            titel: titel,
                            isSynced: 'true'
                        }])
                    );

                    $(FH_PROJECT_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{projekt_id: projekt_id, isSynced: 'true'}])
                    );

                    // Update FH Projecttitle and -phasen
                    $('#span-fh-project').text(titel != null ? titel : projekt_kurzbz);
                    loadFUEPhases(projekt_kurzbz);
                }
            },
            errorCallback: function (jqXHR, textStatus, errorThrown) {
                FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
            }
        }
    );
});

// Synchronize SAP and FH projectphases.
$("#btn-sync-phases").click(function () {

    // Get selected rows data
    var sap_phases_data = $(SAP_PHASES_TABLE).tabulator('getSelectedData');
    var fue_phases_data = $(FH_PHASES_TABLE).tabulator('getSelectedData');

    // Checks
    if (sap_phases_data.length == 0 || fue_phases_data.length == 0) {
        FHC_DialogLib.alertInfo('Bitte wählen Sie die zweite Phase aus.');
        return;
    }

    if (sap_phases_data.length > 1 || fue_phases_data.length > 1)
    {
        FHC_DialogLib.alertInfo('Bitte verknüpfen Sie nur einzelne Phasen direkt miteinander.');
        return;
    }

    var projects_timesheet_id =  sap_phases_data[0].projects_timesheet_id;
    var project_id = sap_phases_data[0].project_id;
    var projekt_id = fue_phases_data[0].projekt_id;
    var projektphase_id = fue_phases_data[0].projektphase_id;
    var bezeichnung = fue_phases_data[0].bezeichnung;

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/syncProjectphases",
        {
            projects_timesheet_id: projects_timesheet_id,
            project_id: project_id,
            projekt_id: projekt_id,
            projektphase_id: projektphase_id,
            bezeichnung: bezeichnung
        },
        {
            successCallback: function (data, textStatus, jqXHR) {
                if (FHC_AjaxClient.isError(data)) {
                    // Print error message
                    FHC_DialogLib.alertWarning(FHC_AjaxClient.getError(data));
                }

                if (data.retval) {

                    // Update sync status
                    $(SAP_PHASES_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{
                            projects_timesheet_id: projects_timesheet_id,
                            projektphase_id: projektphase_id,
                            bezeichnung: bezeichnung,
                            isSynced: 'true'
                        }])
                    );
                    $(FH_PHASES_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{
                            projektphase_id: projektphase_id,
                            bezeichnung: bezeichnung,
                            isSynced: 'true'
                        }])
                    );
                }
            },
            errorCallback: function (jqXHR, textStatus, errorThrown) {
                FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
            }
        }
    );
});

// Create new FH project like the given SAP project. Synchronize them too.
$("#btn-create-project").click(function () {

    // Get selected rows data
    var sap_project_data = $(SAP_PROJECT_TABLE).tabulator('getSelectedData');

    // Checks
    if (sap_project_data.length == 0) {
        FHC_DialogLib.alertInfo('Bitte wählen Sie ein Projekt aus.');
        return;
    }

    if (sap_project_data[0].isSynced == 'true'){
        FHC_DialogLib.alertInfo('Projekt kann nicht neu erstellt werden, da es bereits synchronisiert ist.');
        return;
    }

    var projects_timesheet_id = sap_project_data[0].projects_timesheet_id;
    var oe_kurzbz = sap_project_data[0].oe_kurzbz;

    // Set SAP project
    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/createFUEProject",
        {
            projects_timesheet_id: projects_timesheet_id,
            oe_kurzbz : oe_kurzbz
        },
        {
            successCallback: function (data, textStatus, jqXHR) {
                if (FHC_AjaxClient.isError(data)) {
                    // Print error message
                    FHC_DialogLib.alertWarning(FHC_AjaxClient.getError(data));
                }

                if (FHC_AjaxClient.hasData(data)) {

                    data = FHC_AjaxClient.getData(data);

                    // Add new FUE project row
                    $(FH_PROJECT_TABLE).tabulator(
                        'addRow',
                        JSON.stringify({
                            projekt_id: data.projekt_id,
                            projekt_kurzbz: data.projekt_kurzbz,
                            titel: data.titel,
                            isSynced: 'true'
                        })
                    );

                    // Update SAP project sync status
                    $(SAP_PROJECT_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{
                            projects_timesheet_id: projects_timesheet_id,
                            projekt_kurzbz: data.projekt_kurzbz,
                            titel: data.titel,
                            isSynced: 'true'
                        }])
                    );

                    // Update FH Projecttitle
                    $('#span-fh-project').text(data.titel != null ? data.titel : data.projekt_kurzbz);

                }
            },
            errorCallback: function (jqXHR, textStatus, errorThrown) {
                FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
            }
        }
    );
});

// Create new FH phase(s) like the given SAP phase(s). Synchronize them too.
$("#btn-create-phase").click(function () {

        // Get selected rows data
        var sap_phase_data = $(SAP_PHASES_TABLE).tabulator('getSelectedData');
        var fue_phase_data = $(FH_PHASES_TABLE).tabulator('getSelectedData');

        // Checks
        if (sap_phase_data.length == 0) {
            FHC_DialogLib.alertInfo('Bitte wählen Sie mindestens eine Phase aus.');
            return;
        }

        if (fue_phase_data.length != 0) {
            FHC_DialogLib.alertWarning('Es darf keine FH-Phase gewählt sein.');
            return;
        }

        // Set array of SAP phases
        projects_timesheet_id_arr = [];
        for (var i = 0; i < sap_phase_data.length; i++)
        {
            projects_timesheet_id_arr.push(sap_phase_data[i].projects_timesheet_id);
        }

        FHC_AjaxClient.ajaxCallPost(
            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/createFUEPhase",
            {
                projects_timesheet_id: projects_timesheet_id_arr
            },
            {
                successCallback: function (data, textStatus, jqXHR) {
                    if (FHC_AjaxClient.isError(data)) {
                        // Print error message
                        FHC_DialogLib.alertWarning(FHC_AjaxClient.getError(data));
                    }

                    if (FHC_AjaxClient.hasData(data)) {

                        data = FHC_AjaxClient.getData(data);

                        for (var j = 0; j < data.length; j++)
                        {
                            // Add new FUE phase row
                            $(FH_PHASES_TABLE).tabulator(
                                'addRow',
                                JSON.stringify({
                                    projektphase_id: data[j].projektphase_id,
                                    bezeichnung: data[j].bezeichnung,
                                    isSynced: 'true'})
                            );

                            // Updated sap phase sync status
                            $(SAP_PHASES_TABLE).tabulator(
                                'updateData',
                                JSON.stringify([{
                                    projects_timesheet_id: data[j].projects_timesheet_id,
                                    projektphase_id: data[j].projektphase_id,
                                    bezeichnung: data[j].bezeichnung,
                                    isSynced: 'true'}])
                            );
                        }
                    }
                },
                errorCallback: function (jqXHR, textStatus, errorThrown) {
                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                }
            }
        );
    });

// Desynchronize projects
$('#btn-desync-projects').click(function() {
        // Get selected rows data
        var sap_project_data = $(SAP_PROJECT_TABLE).tabulator('getSelectedData');

        // Checks
        if (sap_project_data.length == 0) {
            FHC_DialogLib.alertInfo('Bitte wählen Sie ein Projekt aus.');
            return;
        }

        if (sap_project_data[0].isSynced == 'false') {
            FHC_DialogLib.alertInfo('Das Projekt kann nicht entknüpft werden, da es nicht synchronisiert ist.');
            return;
        }

    var projects_timesheet_id = sap_project_data[0].projects_timesheet_id;
    var projekt_kurzbz = sap_project_data[0].projekt_kurzbz;
    var projekt_id = sap_project_data[0].projekt_id;

    /**
     * First check if project has timerecordings.
     * Ask for confirmation.
     * If confirmed, desynchronize projects.
     */
    var confirmed = false;

    FHC_AjaxClient.ajaxCallPost(
            //Check if project has timerecordings.
            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/checkProjectHasTimerecordings",
            {projekt_kurzbz: projekt_kurzbz},
            {
                successCallback: function (data, textStatus, jqXHR) {
                    if (FHC_AjaxClient.isError(data))
                    {
                        FHC_DialogLib.alertWarning(data.retval);
                    }

                    let projectHasTimerecordings = data.retval;

                    // If project has timerecordings...
                    if (projectHasTimerecordings == true)
                    {
                        // ...ask for confirmation.
                        confirmed = confirm('Es sind bereits Zeiten auf das Projekt verbucht. Trotzdem entknüpfen?');
                    }

                    // If user confirmed or project has no timerecordings
                    if (confirmed || projectHasTimerecordings == false)
                    {
                        //...start desynchronisation
                        FHC_AjaxClient.ajaxCallPost(
                            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/desyncProjects",
                            {projects_timesheet_id: projects_timesheet_id},
                            {
                                successCallback: function (data, textStatus, jqXHR) {

                                    if (FHC_AjaxClient.isError(data))
                                    {
                                        FHC_DialogLib.alertWarning(data.retval);
                                    }

                                    if (data.retval)
                                    {
                                        // Update tables
                                        $(SAP_PROJECT_TABLE).tabulator(
                                            'updateData',
                                            JSON.stringify([{
                                                projects_timesheet_id: projects_timesheet_id,
                                                projekt_kurzbz: '',
                                                titel: '',
                                                isSynced: 'false'
                                            }])
                                        );

                                        $(FH_PROJECT_TABLE).tabulator(
                                            'updateData',
                                            JSON.stringify([{
                                                projekt_id: projekt_id,
                                                isSynced: 'false'
                                            }])
                                        );
                                    }
                                },
                                errorCallback: function (jqXHR, textStatus, errorThrown) {
                                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                                }
                            }
                        );
                    }
                },
                errorCallback: function (jqXHR, textStatus, errorThrown) {
                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                }
            }
    );
});

// Desynchronize phases
$('#btn-desync-phases').click(function() {

        var sap_phases_data = $(SAP_PHASES_TABLE).tabulator('getSelectedData');

        // Checks
        if (sap_phases_data.length === 0) {
            FHC_DialogLib.alertInfo('Bitte wählen Sie eine Phase aus.');
            return;
        }

        if (sap_phases_data.length > 1)
        {
            FHC_DialogLib.alertInfo('Bitte entknüpfen Sie nur einzelne Phasen.');
            return;
        }

        if (sap_phases_data[0].isSynced === 'false') {
            FHC_DialogLib.alertInfo('Die Phase kann nicht entknüpft werden, da sie nicht synchronisiert ist.');
            return;
        }

        /**
         * First check if phase has timerecordings.
         * Ask for confirmation.
         * If confirmed, desynchronize phases.
         */
        var confirmed = false;

        FHC_AjaxClient.ajaxCallPost(
            //Check if phase has timerecordings.
            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/checkPhaseHasTimerecordings",
            {projektphase_id: sap_phases_data[0].projektphase_id},
            {
                successCallback: function (data, textStatus, jqXHR) {
                    if (FHC_AjaxClient.isError(data))
                    {
                        FHC_DialogLib.alertWarning(data.retval);
                    }

                    let phaseHasTimerecordings = data.retval;

                    // If phase has timerecordings...
                    if (phaseHasTimerecordings == true)
                    {
                        // ...ask for confirmation.
                        confirmed = confirm('Es sind bereits Zeiten auf das Projekt verbucht. Trotzdem entknüpfen?');
                    }

                    // If user confirmed or phase has no timerecordings
                    if (confirmed || phaseHasTimerecordings == false)
                    {
                        //...start desynchronisation
                        FHC_AjaxClient.ajaxCallPost(
                            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/desyncProjectphases",
                            {projects_timesheet_id: sap_phases_data[0].projects_timesheet_id},
                            {
                                successCallback: function (data, textStatus, jqXHR) {

                                    if (FHC_AjaxClient.isError(data))
                                        FHC_DialogLib.alertWarning(data.retval);

                                    if (FHC_AjaxClient.hasData(data))
                                    {
                                        data = FHC_AjaxClient.getData(data);

                                        $(SAP_PHASES_TABLE).tabulator(
                                            'updateData',
                                            JSON.stringify([{
                                                projects_timesheet_id: data.projects_timesheet_id,
                                                projektphase_id: '',
                                                bezeichnung: '',
                                                isSynced: 'false'
                                            }])
                                        );

                                        $(FH_PHASES_TABLE).tabulator(
                                            'updateData',
                                            JSON.stringify([{
                                                projektphase_id: data.projektphase_id,
                                                isSynced: 'false'
                                            }])
                                        );
                                    }
                                },
                                errorCallback: function (jqXHR, textStatus, errorThrown) {
                                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                                }
                            }
                        );
                    }
                },
                errorCallback: function (jqXHR, textStatus, errorThrown) {
                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                }
            }
        );
    });

});




