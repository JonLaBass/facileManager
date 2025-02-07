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

if (!currentUserCan(array('manage_servers', 'view_all'), $_SESSION['module'])) unAuth();

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_keys.php');

$server_serial_no = (isset($_REQUEST['server_serial_no'])) ? sanitize($_REQUEST['server_serial_no']) : 0;

$type = (isset($_GET['type']) && array_key_exists(sanitize(strtolower($_GET['type'])), $__FM_CONFIG['keys']['avail_types'])) ? sanitize(strtolower($_GET['type'])) : 'tsig';
$display_type = $__FM_CONFIG['keys']['avail_types'][$type];

printHeader();
@printMenu();

$addl_title_blocks[] = buildSubMenu($type, $__FM_CONFIG['keys']['avail_types']);
echo printPageHeader((string) $response, __('Keys') . " ($display_type)", currentUserCan('manage_servers', $_SESSION['module']), $type, null, 'noscroll', $addl_title_blocks);
	
$sort_direction = null;
$sort_field = 'key_name';
if (isset($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']])) {
	extract($_SESSION[$_SESSION['module']][$GLOBALS['path_parts']['filename']], EXTR_OVERWRITE);
}

/** Process domain_id filtering */
if (isset($_GET['domain_id']) && !in_array(0, $_GET['domain_id'])) {
	$filter_sql = ' AND domain_id IN (' . sanitize(implode(',', $_GET['domain_id'])) . ')';
}

$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', array($sort_field, 'key_name'), 'key_', "AND key_type='$type'" . (string) $filter_sql, null, false, $sort_direction);
$total_pages = ceil($fmdb->num_rows / $_SESSION['user']['record_count']);
if ($page > $total_pages) $page = $total_pages;
$fm_dns_keys->rows($result, $type, $page, $total_pages);

printFooter();
