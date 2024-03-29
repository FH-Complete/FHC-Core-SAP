$(document).ready(function() {

	$("#sync").click(function()
	{
		var emp_id = $("#empId").val();
		var stammdaten = $("#stammdaten").is(':checked');
		if (emp_id === '')
			return FHC_DialogLib.alertWarning('Bitte alle Felder ausfüllen');

		var data = {
			'emp_id': emp_id,
			'stammdaten': stammdaten
		}
		Emps.sync(data);
	});
});

var Emps = {

	sync: function(data)
	{
		FHC_AjaxClient.ajaxCallPost(
			"extensions/FHC-Core-SAP/emps/SyncEmps/syncEmp",
			data,
			{
				successCallback: function(response, textStatus, jqXHR) {
					if (FHC_AjaxClient.isError(response))
					{
						Emps._writeError(FHC_AjaxClient.getError(response));
					}
					else
					{
						Emps._writeSuccess(FHC_AjaxClient.getData(response))
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError(jqXHR);
				}
			}
		);
	},

	_writeSuccess: function(text)
	{
		Emps._writeOutput(text, 'text-success');
	},

	_writeError: function(text)
	{
		Emps._writeOutput(text, 'text-danger');
	},

	_writeOutput: function(output, status)
	{
		$('#syncOutput p').remove();
		$("#syncOutput").append("<p class='" + status + "'>" + output + "</p>");
	}

}

