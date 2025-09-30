<?php
if (!defined('FM_NO_CHECKS')) define('FM_NO_CHECKS', true);
require_once('../../../fm-init.php');

$module_name = basename(dirname(__FILE__, 2));

header("Content-Type: text/javascript");

echo '
$(document).ready(function() {
	
	$("#manage_item_contents").delegate("#service_type", "change", function(e) {
		if ($(this).val() == "icmp") {
			$("#tcpudp_option").slideUp();
			$("#tcp_option").slideUp();
			$("#icmp_option").show("slow");
		} else if ($(this).val() == "tcp") {
			$("#icmp_option").slideUp();
			$("#tcpudp_option").show("slow");
			$("#tcp_option").show("slow");
		} else {
			$("#icmp_option").slideUp();
			$("#tcp_option").slideUp();
			$("#tcpudp_option").show("slow");
		}
	});
	
	$("#manage_item_contents").delegate("#object_type", "change", function(e) {
		if ($(this).val() == "host") {
			$("#netmask_option").slideUp();
		} else {
			$("#netmask_option").show("slow");
		}
	});
	
	$("#manage_item_contents").delegate("#submit_items", "click", function(e) {
		var arr = [ "group", "source", "destination", "services" ];
		$.each(arr, function(index, box_id) {
			var options = $("#" + box_id + "_items_assigned option");
			var items = "";
			
			for (i=0; i<options.length; i++) {
				items += options[i].value + ";";
			}
			$("#" + box_id + "_items").val(items);
		});
	});
	
	/* Global search */
	$("#table_edits,#manage_item_contents").delegate("a.global-search", "click tap", function(e) {
		var $this 		= $(this);
		item_type		= $("#table_edits").attr("name");
		item_sub_type	= $this.attr("name");
		item_id			= $this.parent().attr("rel");
		var server_serial_no	= getUrlVars()["server_serial_no"];
		var uri_params = getUrlVars();

		$("body").addClass("fm-noscroll");
		$("#manage_item").fadeIn(200);
		$(".popup-wait").show();

		var form_data = {
			global_find: true,
			item_type: item_type,
			item_sub_type: item_sub_type,
			item_id: item_id,
			server_serial_no: server_serial_no,
			request_uri: uri_params,
			is_ajax: 1
		};

		$.ajax({
			type: "POST",
			url: "fm-modules/facileManager/ajax/getData.php",
			data: form_data,
			success: function(response)
			{
				if (response.indexOf("force_logout") >= 0 || response.indexOf("login_form") >= 0) {
					doLogout();
					return false;
				}
				$("#manage_item_contents").html(response);
				$(".popup-wait").hide();
			}
		});

		return false;
	});

});
';
