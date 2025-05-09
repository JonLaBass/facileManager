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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_servers.php');

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['servers']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'servers';
$display_type = ($type == 'servers') ? __('Name Servers') : __('Name Server Groups');

printHeader();
@printMenu();

$addl_title_blocks[] = buildSubMenu($type, $__FM_CONFIG['servers']['avail_types']);
echo printPageHeader((string) $response, $display_type, currentUserCan('manage_servers', $_SESSION['module']), $type, null, 'noscroll', $addl_title_blocks);

$sort_direction = null;
$sort_field = null;
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

if ($type == 'groups') {
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_name', 'group_', null, null, false, $sort_direction);
} elseif ($type == 'servers') {
	if (!$sort_field) $sort_field = 'server_name';
	$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', array($sort_field, 'server_name'), 'server_', null, null, false, $sort_direction);
}
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_module_servers->rows($result, $type, $page, $total_pages);

printFooter();

