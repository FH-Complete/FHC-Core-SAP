$(document).ready(function() {

	$("#sync").click(function()
	{
		var emp_id = $("#empId").val();

		if (emp_id === '')
			return FHC_DialogLib.alertWarning('Bitte alle Felder ausfüllen');

		Emps.sync(emp_id);
	});
});

var Emps = {

	sync: function(emp_id)
	{
		FHC_AjaxClient.ajaxCallPost(
			"extensions/FHC-Core-SAP/emps/SyncEmps/syncEmp",
			{
				'emp_id': emp_id
			},
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

