/**
 *
 */
function changePasswordClick()
{

 	FHC_AjaxClient.ajaxCallPost(
 		"extensions/FHC-Core-SAP/cis/PasswordChange/save",
 		{
 			oldPassword: $("#oldPassword").val(),
 			newPassword: $("#newPassword").val(),
 			repeatPassword: $("#repeatPassword").val()
 		},
 		{
 			successCallback: function(response, textStatus, jqXHR) {

				if (FHC_AjaxClient.isError(response))
				{
					FHC_DialogLib.alertError(FHC_AjaxClient.getError(response));
				}
				else
				{
					FHC_DialogLib.alertSuccess(FHC_AjaxClient.getData(response));
				}
 			},
			errorCallback: function(jqXHR, textStatus, errorThrown) {
				FHC_DialogLib.alertError("Something terribly wrong just happened! Call a super hero!!");
			}
 		}
 	);
}

/**
 *
 */
function setEvents()
{
	$("#changePassword").click(changePasswordClick);
}

/**
 * When JQuery is up
 */
$(document).ready(function() {

	setEvents();

});
