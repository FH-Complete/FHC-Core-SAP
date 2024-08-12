$(document).ready(function() {

	$("#sync").click(function()
	{
		var emp_id = $("#empId").val();
		var stammdaten = $("#stammdaten").is(':checked');
		var testlauf = $("#testlauf").is(':checked');
		if (emp_id === '')
			return FHC_DialogLib.alertWarning('Bitte alle Felder ausfüllen');

		var data = {
			'emp_id': emp_id,
			'stammdaten': stammdaten,
			'testlauf': testlauf,
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
						var responseData = FHC_AjaxClient.getData(response);

						if (data.testlauf)
							Emps._writeTesttry(responseData);
						else
							Emps._writeSuccess(responseData);
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError(jqXHR);
				}
			}
		);
	},

	_writeTesttry: function(response)
	{
		$('#syncOutput').empty();
		$("#syncOutput").append("<table id='syncTable' class='tablesorter' width='100%' border='1px' '>");
		$("#syncTable").append("<tr>" +
			"<th>Type</th>" +
			"<th>Datum</th>" +
			"<th>Stunden</th>" +
			"<th>OE</th>" +
			"</tr>");

		$.each(response, function(key, value){
			var tr = $("<tr></tr>");

			$.each(value, function(index, column)
			{
				tr.append($("<td></td>").text(column));
			})
			$("#syncTable").append(tr);
		})

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
		$('#syncOutput').empty();
		$("#syncOutput").append("<p class='" + status + "'>" + output + "</p>");
	}

}

