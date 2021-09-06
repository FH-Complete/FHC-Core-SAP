// -----------------------------------------------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------------------------------------------
const SAP_PROJECT_TABLE = '[tableuniqueid = SAPProjects] #tableWidgetTabulator';
const FH_PROJECT_TABLE = '[tableuniqueid = FUEProjects] #tableWidgetTabulator';

const SAP_PHASES_TABLE = '[tableuniqueid = SAPPhases] #tableWidgetTabulator';
const FH_PHASES_TABLE = '[tableuniqueid = FUEPhases] #tableWidgetTabulator';

const PROJECT_MSG = '#projects-msg';
const PHASES_MSG = '#projectphases-msg';

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

// Allow selection of projects / phases, that are NOT synchronized
function func_selectableCheck(row)
{
    return row.getData().isSynced == 'false';
}

/**
 * Empty project title input field and phases when deselecting SAP project.
 * NOTE: used rowClick callback instead of rowDeselect callback, because rowDeselect would be triggered also when
 * using 'deselectRow' programmatically and this would cause unwanted behaviour.
  */
function func_rowClick_onSAPProject(e, row)
{
	var is_synced = row.getData().isSynced;

	if (!row.isSelected())
	{
		$("#input-sap-project").val('');
		$(SAP_PHASES_TABLE).tabulator('replaceData');

		if (is_synced == 'true' ||  $("#input-fue-project").attr('data-fue-project-syncStatus') == 'true')
		{
			$("#input-fue-project").val('');
			$(FH_PHASES_TABLE).tabulator('replaceData');

			_resetGUI();
		}
	}
}

/**
 * Empty project title input field and phases when deselecting FH project.
 * NOTE: used rowClick callback instead of rowDeselect callback, because rowDeselect would be triggered also when
 * using 'deselectRow' programmatically and this would cause unwanted behaviour.
 */
function func_rowClick_onFHProject(e, row)
{
	var is_synced = row.getData().isSynced;

	if (!row.isSelected())
	{
		$("#input-fue-project").val('');
		$(FH_PHASES_TABLE).tabulator('replaceData');

		if (is_synced == 'true' ||  $("#input-sap-project").attr('data-sap-project-syncStatus') == 'true')
		{
			$("#input-sap-project").val('');
			$(SAP_PHASES_TABLE).tabulator('replaceData');

			_resetGUI();
		}
	}
}

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

// Display FUE projekt_kurzbz if project title is null
function renderStarted_onFUEProject(table)
{
    table.getRows().forEach(function(row){
        if (row.getData().titel == null || row.getData().titel == '')
        {
            row.getData().titel = row.getData().projekt_kurzbz;
        }
    });
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

// Get FH phases and also, if the project is synchronized, the corresponding SAP project and phases.
function rowSelected_onFUEProject(row)
{
    var is_synced = row.getData().isSynced;
    var titel = row.getData().titel;
    var projekt_id = row.getData().projekt_id;

    // Reset GUI
    _resetGUI();

	// Set FH project title into input field
	$("#input-fue-project").val(titel);

	// Load FH projectphases
    loadFUEPhases(row.getData().projekt_kurzbz);

    if (is_synced == 'true') {
        var data = {
            'projekt_id': projekt_id
        };

        FHC_AjaxClient.ajaxCallPost(
            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/getSyncedSAPProject",
            data,
            {
                successCallback: function (data, textStatus, jqXHR)
                {
                    if (!data.error && data.retval)
                    {
	                    // Set SAP project title into input field
                        $("#input-sap-project").val(data.retval.name + " (" + data.retval.project_id + ')');

                        $("#input-fue-project").attr('data-fue-project-syncStatus', 'true');
                        $("#input-sap-project").attr('data-sap-project-syncStatus', 'true');

                        // Deselect former selected SAP project row
                        $(SAP_PROJECT_TABLE).tabulator('deselectRow');

                        _setGUI_SyncedProjects();

                        // Load SAP phases
                        loadSAPPhases(data.retval.project_id);
                    }
                },
                errorCallback: function (jqXHR, textStatus, errorThrown)
                {
                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                }
            }
        );
    }
    else
    {
        $("#input-fue-project").attr('data-fue-project-syncStatus', 'false');

        // if projects of last selection were synced
        if($("#input-sap-project").attr('data-sap-project-syncStatus') == 'true')
        {
            $("#input-sap-project").val('');
            $(SAP_PROJECT_TABLE).tabulator('deselectRow');
            $(SAP_PHASES_TABLE).tabulator('replaceData');
        }
    }

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
                if (!data.error) {
                    if(data.retval != null)
                    {
                        $(SAP_PHASES_TABLE).tabulator('replaceData', data.retval);
                    }
                    else
                    {
                        // FHC_DialogLib.alertInfo("SAP-Projekt hat keine Phasen");
                        // $(PHASES_MSG).text(project_id + ' hat keine Phasen');
                        $(SAP_PHASES_TABLE).tabulator('replaceData');
                    }
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
                if (!data.error) {
                    if(data.retval != null)
                    {
                        $(FH_PHASES_TABLE).tabulator('replaceData', data.retval);
                    }
                    else
                    {
                        // FHC_DialogLib.alertInfo("FH-Projekt hat keine Phasen");
                        $(FH_PHASES_TABLE).tabulator('replaceData');
                    }
                }
            },
            errorCallback: function (jqXHR, textStatus, errorThrown) {
                FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
            }
        }
    );

}

// Set GUI for synchronized projects. (Unable sync button,...)
function _setGUI_SyncedProjects()
{
	_setGUI_disabledButtons();

	$(PROJECT_MSG).text('VERKNÜPFT');
	$(PROJECT_MSG).removeClass().addClass('text-success');
}

function _setGUI_disabledButtons(){
	$("#panel-projects button").attr("disabled", true);
	$("#select-organisationseinheit").attr("disabled", 'disabled');
}

// Reset GUI
function _resetGUI()
{
    $("#panel-projects button").attr("disabled", false);
    $("#select-organisationseinheit")
	    .removeAttr("disabled")
	    .val('null')
	    .change();
	$("#sap-project-status").text('-');
	$("#sap-project-deleted").text('-');

    $(PROJECT_MSG).text('');
	$(PHASES_MSG).text('');
}


$(function() {
// Init tooltip
$('[data-toggle="tooltip"]').tooltip();

// Synchronize SAP and FH project.
$("#btn-sync-projects").click(function () {

    // Get selected rows data
    var sap_project_data = $(SAP_PROJECT_TABLE).tabulator('getSelectedData');
    var fue_project_data = $(FH_PROJECT_TABLE).tabulator('getSelectedData');

    // Checks

	if (sap_project_data.length == 0 || fue_project_data.length == 0) {
		FHC_DialogLib.alertInfo('Bitte wählen Sie ein SAP- und ein FH-Projekt aus.');
		return;
	}

    if (sap_project_data[0].isSynced == 'true' || fue_project_data[0].isSynced == 'true') {
        FHC_DialogLib.alertInfo('Mindestens ein Projekt ist bereits synchronisiert. Bitte wählen Sie ein anderes aus.');
        return;
    }

    var projects_timesheet_id = sap_project_data[0].projects_timesheet_id;
    var projekt_id = fue_project_data[0].projekt_id;

    // Prepare data object for ajax call
    var data = {
        'projects_timesheet_id': projects_timesheet_id,
        'projekt_id': projekt_id
    };

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/syncProjects",
        data,
        {
            successCallback: function (data, textStatus, jqXHR) {
                if (data.error && data.retval != null) {
                    // Print error message
                    FHC_DialogLib.alertWarning(data.retval);
                }

                if (!data.error && data.retval) {

                    // Update sync status
                    $(SAP_PROJECT_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{projects_timesheet_id: projects_timesheet_id, isSynced: 'true'}])
                    );

                    $(FH_PROJECT_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{projekt_id: projekt_id, isSynced: 'true'}])
                    );

                    $("#input-sap-project").attr('data-sap-project-syncStatus', 'true');
                    $("#input-fue-project").attr('data-fue-project-syncStatus', 'true');

                    _setGUI_SyncedProjects();

                    // Print success message
                    // FHC_DialogLib.alertSuccess("Projekte wurden verknüpft.");
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

    // Prepare data object for ajax call
    var data = {
        'projects_timesheet_id': projects_timesheet_id,
        'project_id': project_id,
        'projekt_id': projekt_id,
        'projektphase_id': projektphase_id
    };

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/syncProjectphases",
        data,
        {
            successCallback: function (data, textStatus, jqXHR) {
                if (data.error && data.retval != null) {
                    // Print error message
                    FHC_DialogLib.alertWarning(data.retval);
                }

                if (!data.error && data.retval) {

                    // Update sync status
                    $(SAP_PHASES_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{projects_timesheet_id: projects_timesheet_id, isSynced: 'true'}])
                    );
                    $(FH_PHASES_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{projektphase_id: projektphase_id, isSynced: 'true'}])
                    );

                    // Print success message
                    // FHC_DialogLib.alertSuccess("Phasen wurden verknüpft.");
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
    var fue_project_data = $(FH_PROJECT_TABLE).tabulator('getSelectedData');

    // Checks
    if (sap_project_data.length == 0) {
        FHC_DialogLib.alertInfo('Bitte wählen Sie ein SAP Projekt aus.');
        return;
    }

    if (fue_project_data.length != 0) {
        FHC_DialogLib.alertWarning('Es darf kein FH-Projekt gewählt sein.');
        return;
    }

    // Set SAP project
    var projects_timesheet_id = sap_project_data[0].projects_timesheet_id;

    // Prepare data object for ajax call
    var data = {
        'projects_timesheet_id': projects_timesheet_id,
	    'oe_kurzbz' : organisationseinheit_selected
    };

    FHC_AjaxClient.ajaxCallPost(
        FHC_JS_DATA_STORAGE_OBJECT.called_path + "/createFUEProject",
        data,
        {
            successCallback: function (data, textStatus, jqXHR) {
                if (data.error && data.retval != null) {
                    // Print error message
                    FHC_DialogLib.alertWarning(data.retval);
                }

                if (!data.error && data.retval != null) {

                    // Add new FUE project row
                    $(FH_PROJECT_TABLE).tabulator(
                        'addRow',
                        JSON.stringify({
                            projekt_id: data.retval.projekt_id, titel: data.retval.titel + " (" + data.retval.projekt_kurzbz + ")", isSynced: 'true'})
                    );

                    // Update SAP project sync status
                    $(SAP_PROJECT_TABLE).tabulator(
                        'updateData',
                        JSON.stringify([{projects_timesheet_id: projects_timesheet_id, isSynced: 'true'}])
                    );

	                $("#input-fue-project").val(data.retval.titel + " (" + data.retval.projekt_kurzbz + ")");

                    $("#input-sap-project").attr('data-sap-project-syncStatus', 'true');
                    $("#input-fue-project").attr('data-fue-project-syncStatus', 'true');

                    _setGUI_SyncedProjects();

                    // Print success message
                    // FHC_DialogLib.alertSuccess("Projekt wurde erstellt und verknüpft.");
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
            FHC_DialogLib.alertInfo('Bitte wählen Sie mindestens eine SAP Phase aus.');
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

        // Prepare data object for ajax call
        var data = {
            'projects_timesheet_id': projects_timesheet_id_arr
        };

        FHC_AjaxClient.ajaxCallPost(
            FHC_JS_DATA_STORAGE_OBJECT.called_path + "/createFUEPhase",
            data,
            {
                successCallback: function (data, textStatus, jqXHR) {
                    if (data.error && data.retval != null) {
                        // Print error message
                        FHC_DialogLib.alertWarning(data.retval);
                    }

                    if (!data.error && data.retval != null) {

                        for (var j = 0; j < data.retval.length; j++)
                        {
                            // Add new FUE phase row
                            $(FH_PHASES_TABLE).tabulator(
                                'addRow',
                                JSON.stringify({
                                    projektphase_id: data.retval[j].projektphase_id,
                                    bezeichnung: data.retval[j].bezeichnung,
                                    isSynced: 'true'})
                            );

                            // Updated sap phase sync status
                            $(SAP_PHASES_TABLE).tabulator(
                                'updateData',
                                JSON.stringify([{
                                    projects_timesheet_id: data.retval[j].projects_timesheet_id,
                                    isSynced: 'true'}])
                            );
                        }

                        // Print success message
                        // FHC_DialogLib.alertSuccess("Phase wurde erstellt und verknüpft.");
                    }
                },
                errorCallback: function (jqXHR, textStatus, errorThrown) {
                    FHC_DialogLib.alertError("Systemfehler<br>Bitte kontaktieren Sie Ihren Administrator.");
                }
            }
        );
    });

$("#select-organisationseinheit").change(function(){
	organisationseinheit_selected = $(this).val();
});

});




