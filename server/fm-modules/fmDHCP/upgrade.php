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

function upgradefmDHCPSchema($module_name) {
	global $fmdb;
	
	/** Include module variables */
	@include(dirname(__FILE__) . '/variables.inc.php');
	
	/** Get current version */
	$running_version = getOption('version', 0, 'fmDHCP');
	
	/** Checks to support older versions (ie n-3 upgrade scenarios */
	$success = version_compare($running_version, '0.11.0-beta1', '<') ? upgradefmDHCP_0110b1($__FM_CONFIG, $running_version) : true;
	if (!$success) return $fmdb->last_error;
	
	setOption('client_version', $__FM_CONFIG['fmDHCP']['client_version'], 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.2 */
function upgradefmDHCP_020($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	if (!columnExists("fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions", 'def_prefix')) {
		$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` ADD `def_prefix` VARCHAR(20) NULL DEFAULT NULL AFTER `def_option_type`";
	}
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` CHANGE `def_option_type` `def_option_type` ENUM('global','shared','subnet','group','host','pool','peer') NOT NULL DEFAULT 'global'";
	
	/** Insert upgrade steps here **/
	$queries[] = "INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` (
		`def_function`, `def_option_type`, `def_prefix`, `def_option`, `def_type`, `def_multiple_values`, `def_dropdown`, `def_max_parameters`, `def_direction`, `def_minimum_version`
)
VALUES
('options', 'global', 'option', 'host-name', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'routers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-name-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'subnet-mask', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'broadcast-address', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-name', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'domain-search', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'time-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'log-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'swap-server', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'root-path', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'nis-domain', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'nis-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'font-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'x-display-manager', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'ntp-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-name-servers', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-scope', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'netbios-node-type', '( integer )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'time-offset', '( integer )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'dhcp-server-identifier', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'slp-directory-agent', '( address_match_element )', 'no', 'no', '1', 'forward', NULL),
('options', 'global', 'option', 'slp-service-scope', '( quoted_string )', 'no', 'no', '1', 'forward', NULL),
('options', 'shared', NULL, 'authoritative', '( on | off )', 'no', 'yes', 1, 'empty', NULL)
";
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` SET `def_option_type`='subnet' WHERE `def_option`='authoritative' AND `def_option_type`='global' LIMIT 1";
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Add empty options so updates work */
	$new_options = array(
		'host-name', 'routers', 'domain-name-servers', 'subnet-mask', 'broadcast-address',
		'domain-name', 'domain-search', 'time-servers', 'log-servers', 'swap-server',
		'root-path', 'nis-domain', 'nis-servers', 'font-servers', 'x-display-manager',
		'ntp-servers', 'netbios-name-servers', 'netbios-scope', 'netbios-node-type',
		'time-offset', 'dhcp-server-identifier', 'slp-directory-agent', 'slp-service-scope'
	);
	$fmdb->query("SELECT * FROM `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` WHERE `config_is_parent`='yes' AND `config_status`!='deleted'");
	$num_rows = $fmdb->num_rows;
	$result = $fmdb->last_result;
	$sql_start = "INSERT IGNORE INTO `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` 
		(`config_type`,`config_parent_id`,`config_name`,`config_data`,`config_assigned_to`) VALUES ";

	for ($i=0; $i<$num_rows; $i++) {
		foreach ($new_options as $option) {
			$values[] = "('{$result[$i]->config_type}','{$result[$i]->config_id}','$option','','{$result[$i]->config_assigned_to}')";
		}
		$fmdb->query($sql_start . join(',', $values));
		unset($values);
	}

	/** Handle updating table with module version **/
	setOption('version', '0.2', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.3.1 */
function upgradefmDHCP_031($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '0.2', '<') ? upgradefmDHCP_020($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Insert upgrade steps here **/
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` SET `config_name`='load balance max seconds' WHERE `config_name`='load balance max secs'";
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.3.1', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.3.2 */
function upgradefmDHCP_032($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '0.3.1', '<') ? upgradefmDHCP_031($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Insert upgrade steps here **/
	$queries[] = "UPDATE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` SET `config_name`='load balance max seconds' WHERE `config_name`='load_balance_max_seconds'";
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.3.2', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.4.5 */
function upgradefmDHCP_045($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '0.3.2', '<') ? upgradefmDHCP_032($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	/** Insert upgrade steps here **/
	// Get all pools
	// Foreach pool, get failover peer config
	// If it does not exist, insert it with a value of 0
	$query = "SELECT * FROM fm_{$__FM_CONFIG['fmDHCP']['prefix']}config WHERE config_status!='deleted' AND config_type='pool' AND config_is_parent='yes' AND config_name='pool' ORDER BY config_id ASC";
	$result = $fmdb->get_results($query);
	if (!$fmdb->sql_errors && $fmdb->num_rows) {
		foreach ($fmdb->last_result as $record) {
			$query = "SELECT config_id,config_data FROM fm_{$__FM_CONFIG['fmDHCP']['prefix']}config WHERE config_status!='deleted' AND config_parent_id='{$record->config_id}' AND config_name='failover peer' ORDER BY config_id ASC";
			$result = $fmdb->get_results($query);
			if (!$fmdb->sql_errors && !$fmdb->num_rows) {
				$queries[] = "INSERT INTO `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` (`config_type`,`config_parent_id`,`config_name`,`config_data`) VALUES ('pool', {$record->config_id}, 'failover peer', '0')";
			}	
		}
	}
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.4.5', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.4.7 */
function upgradefmDHCP_047($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '0.4.5', '<') ? upgradefmDHCP_045($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;
	
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}servers` CHANGE `server_update_config` `server_update_config` ENUM('yes','no','conf') NOT NULL DEFAULT 'no'";
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.4.7', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.9.0 */
function upgradefmDHCP_090($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '0.4.7', '<') ? upgradefmDHCP_047($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;

	/** Insert upgrade steps here **/
	$queries[] = "INSERT IGNORE INTO  `fm_{$__FM_CONFIG['fmDHCP']['prefix']}functions` (
`def_function`,
`def_option_type`,
`def_prefix`,
`def_option`,
`def_type`,
`def_multiple_values`,
`def_dropdown`,
`def_max_parameters`,
`def_direction`,
`def_minimum_version`
)
VALUES 
('options', 'global', NULL, 'authoritative', '( on | off )', 'no', 'yes', 1, 'empty', NULL)
";
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}
	
	/** Handle updating table with module version **/
	setOption('version', '0.9.0', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

/** 0.11.0-beta1 */
function upgradefmDHCP_0110b1($__FM_CONFIG, $running_version) {
	global $fmdb;
	
	$success = version_compare($running_version, '0.9.0', '<') ? upgradefmDHCP_090($__FM_CONFIG, $running_version) : true;
	if (!$success) return false;

	/** Insert upgrade steps here **/
	$queries[] = "ALTER TABLE `fm_{$__FM_CONFIG['fmDHCP']['prefix']}config` DROP `config_assigned_to`";
	
	/** Run queries */
	if (count($queries) && $queries[0]) {
		foreach ($queries as $schema) {
			$fmdb->query($schema);
			if (!$fmdb->result || $fmdb->sql_errors) return false;
		}
	}

	/** Delete unused files */
	deleteDeprecatedFiles(array(
		dirname(__FILE__) . '/pages/config-peers.php'
	));
	
	/** Handle updating table with module version **/
	setOption('version', '0.11.0-beta1', 'auto', false, 0, 'fmDHCP');
	
	return true;
}

