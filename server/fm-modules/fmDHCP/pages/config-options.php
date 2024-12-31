<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmDHCP: Easily manage one or more ISC DHCP servers                      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdhcp/                            |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');

$option_type = (isset($_GET['type'])) ? sanitize(ucfirst($_GET['type'])) : 'Global';
//$display_option_type = $__FM_CONFIG['options']['avail_types'][strtolower($option_type)];
$display_option_type = $option_type;
$display_option_type_sql = strtolower($option_type);
$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

/* Configure options for an item */
if (array_key_exists('item_id', $_GET)) {
	$item_id = (isset($_GET['item_id'])) ? sanitize($_GET['item_id']) : 0;
	if (!$item_id) {
		header('Location: ' . $GLOBALS['basename']);
		exit;
	}
	
	basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $item_id, 'config_', 'config_id');
	$item_info = $fmdb->last_result;
	
	$display_option_type = ucfirst($item_info[0]->config_data);
	$display_option_type_sql = "{$item_info[0]->config_type}' AND config_parent_id='$item_id' AND config_name!='config_children' AND config_data!='";
	
	$name = $item_info[0]->config_type;
	$rel = $item_id;
} else {
	$view_id = $domain_id = $name = $rel = null;
//	$display_option_type_sql .= "' AND view_id='0";
//	if ($option_type == 'Global') $display_option_type_sql .= "' AND domain_id='0";
}

printHeader();
@printMenu();

//$avail_servers = buildServerSubMenu($server_serial_no);
$addl_title_blocks[] = buildObjectsSubMenu($item_id);

$sort_direction = null;
$sort_field = 'config_name';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

echo printPageHeader((string) $response, $display_option_type . ' ' . getPageTitle(), currentUserCan('manage_servers', $_SESSION['module']), $name, $rel, 'noscroll', $addl_title_blocks);

$result = basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array($sort_field, 'config_name'), 'config_', "AND config_type='$display_option_type_sql' AND server_serial_no='$server_serial_no'", null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_options->rows($result, $page, $total_pages);

printFooter();

/**
 * Builds the server listing in a dropdown menu
 *
 * @since 0.1
 * @package facileManager
 * @subpackage fmDHCP
 *
 * @param integer $item_id
 * @return string
 */
function buildObjectsSubMenu($item_id = 0) {
	$object_list = buildSelect('item_id', 'item_id', availableObjects(), $item_id, 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = '';
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'item_id') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$return = <<<HTML
	<div id="configtypesmenu">
		<form action="{$GLOBALS['basename']}" method="GET">
		$hidden_inputs
		$object_list
		</form>
	</div>
HTML;

	return $return;
}

