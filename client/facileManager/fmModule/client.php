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
 | fmModule: Brief module description                                      |
 +-------------------------------------------------------------------------+
 | http://URL                                                              |
 +-------------------------------------------------------------------------+
*/

/**
 * fmModule Client Utility
 *
 * @package fmModule
 * @subpackage Client
 *
 */

/** Client version */
$data['server_client_version'] = '1.0';

error_reporting(0);

$module_name = basename(dirname(__FILE__));

/** Include shared client functions */
$fm_client_functions = dirname(__FILE__, 2) . '/functions.php';
if (file_exists($fm_client_functions)) {
	include_once($fm_client_functions);
} else {
	echo fM("The facileManager client scripts are not installed.\n");
	exit(1);
}

/** Build the configs provided by $url */
$retval = buildConf($url, $data);

if (!$retval) exit(1);
