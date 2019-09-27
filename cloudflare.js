
		var zoneLists = [];
		jQuery(document).ready(function($) {


			jQuery("#settings_form").live("submit",function(){
				$('#loading').show();
			
				var data = {
				'action': 'save_settings',
				'cloudflare_key': jQuery("#cloudflare_key").val(),
				'cloudflare_email': jQuery("#cloudflare_email").val(),
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(ajaxurl, data, function(response) {
					
					res= jQuery.parseJSON(jQuery.trim(response));
					$('#loading').html("").hide();	
					alert(res.Msg);
					
				});
				return false;
		
			});
			jQuery("#purgeAll").live("click",function(){
				
				 var purgeZoneID = jQuery('#zones').val();
				 purgeAll(purgeZoneID);
				 return false;
			});
			jQuery("#purgeFile").live("click",function(){
				 var files = jQuery('#purge_file').val();
				 var purgeZoneID = jQuery('#zones').val();
				 purgeFiles(files, purgeZoneID);
				 return false;
			});
		});
		
		function getZoneList() {
		 
			var data = {
				'action': 'getZoneList',
					}

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			
				var success = function(data){
						 var data = jQuery.parseJSON(data);
					if (data.success) {
						//console.log(data);
						if (data.result) {
						
						var data = data.result;
								for (var item in data) {
									item = data[item];
									if (item) {
										zoneLists.push(item);
									}
								}
								showPurgeZoneDropDown(zoneLists);
						}

					}
					else {

						var errors = data.errors;
						var msg = "";
						for (var item in errors) {
							item = errors[item];
							msg += "<br/> (" + item.code + ") " + item.message;
						}

					   
					}

					   
					
					
				};
				var error =  function(){
					
				};
				SendAjaxRequest(data, 'POST', '', ajaxurl, success, error);
			   
	}
	 function showPurgeZoneDropDown(arr) {

		   
				window.domainList = [];

				for (var item in arr) {
					item = arr[item];
					if (item) {

						if (item.id) {

							var exist = true;
							if (exist) {
								jQuery('#zones').append('<option value=' + item.id + '> ' + item.name + ' </option>');
							  
							}
						}
					}
				}

				
				jQuery("#zones").val($("#zones option:first").val());
			}
	function purgeFiles(file_list, purgeZoneID) {
				var success = function (data) {
					console.log(data);
					alert("Files purged successfully!!!");

				};
				var error = function (data) {
					console.log(data);
					alert("Error purging file!");
				};
				var url = ajaxurl;
				var data = {
					action: "purgeFiles",
					files: file_list,
					purgeZoneID: purgeZoneID
				};
				SendAjaxRequest(data, 'POST', '', url, success, error);
			}
	 function purgeAll(purgeZoneID) {
				var success = function (data) {
					console.log(data);
					alert("Domain purged successfully!");
				};
				var error = function (data) {
					console.log(data);
					alert("Error purging Domain!");
				};
				var url = ajaxurl;
				var data = {
					action: "purgeAll",
					purgeZoneID: purgeZoneID
				};
				SendAjaxRequest(data, 'POST', '', url, success, error);
			}
	 function SendAjaxRequest(data, type, datatype, url, success_callback, error_callback) {
				jQuery('#loading').show();
				jQuery.ajax({
					type: type,
					url: url,
					dataType: datatype,
					data: data,
					cache: false,
					success:
							function (data) {
								jQuery('#loading').hide();
								success_callback(data);
							},
					error:
							function (data) {
							   jQuery('#loading').hide();
								if (data.status === 200) {
									success_callback(data);
								}
								else {
									error_callback(data);
								}
							}

				});
			}