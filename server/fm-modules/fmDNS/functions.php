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

/**
 * Checks the app functionality
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function moduleFunctionalCheck() {
	global $fmdb, $__FM_CONFIG;
	$html_checks = '';
	
	/** Count active name servers */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'active')) ? null : sprintf('<p>' . _('You currently have no active servers defined. <a href="%s">Click here</a> to define one or more to manage.') . '</p>', getMenuURL(_('Servers')));
	
	/** Count global options */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_id', 'cfg_')) ? null : sprintf('<p>' . __('You currently have no global options defined for named.conf. <a href="%s">Click here</a> to define one or more.') . '</p>', getMenuURL(__('Options')));
	
	/** Count zones */
	$checks[] = (basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_')) ? null : sprintf('<p>' . __('You currently have no zones defined. <a href="%s">Click here</a> to define one or more.') . '</p>', getMenuURL(__('Zones')));
	
	foreach ($checks as $val) {
		$html_checks .= $val;
	}
	
	return $html_checks;
}

/**
 * Builds the dashboard for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleDashboard() {
	global $fmdb, $__FM_CONFIG;

	$errors = '';
	$display_server_count = false;

	/** Name server stats */
	if (currentUserCan('manage_servers', $_SESSION['module'])) {
		$display_server_count = true;
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', 'AND server_type!="remote"');
		$server_count = $fmdb->num_rows;
		$server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			if ($server_results[$i]->server_installed != 'yes') {
				$errors .= sprintf('<b>%s</b> - %s' . "\n", $server_results[$i]->server_name, __('Client is not installed.'));
			} elseif (isset($server_results[$i]->server_client_version) && $server_results[$i]->server_client_version != getOption('client_version', 0, $_SESSION['module'])) {
				$errors .= sprintf('<a href="%s"><b>%s</b></a> - %s' . "\n", getMenuURL(_('Servers')), $server_results[$i]->server_name, __('Client needs to be upgraded.'));
			} elseif ($server_results[$i]->server_build_config != 'no' && $server_results[$i]->server_status == 'active') {
				$errors .= sprintf('<a href="%s"><b>%s</b></a> - %s' . "\n", getMenuURL(_('Servers')), $server_results[$i]->server_name, __('Server needs a new configuration built.'));
			}
		}
	}
	
	/** Zone stats */
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
	$domain_count = $fmdb->num_rows; 
	$domain_results = $fmdb->last_result;
	$privileged_domain_count = $record_count = 0;
	for ($i=0; $i<$domain_count; $i++) {
		$zone_access_allowed = currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_results[$i]->domain_id));
		if ($zone_access_allowed) {
			$privileged_domain_count++;

			/** Get record count */
			basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'records', $domain_results[$i]->domain_id, 'record_', 'domain_id');
			if (!$fmdb->sql_errors) $record_count += $fmdb->num_rows;
		}

		/** Get zones that need attention */
		if (!getSOACount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
				$domain_results[$i]->domain_type == 'primary' && $zone_access_allowed) {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if (currentUserCan('manage_zones', $_SESSION['module'])) $errors .= '&record_type=SOA';
			$errors .= '">' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</a> - ' . __('Zone does not have a SOA defined.') . "\n";
		} elseif (!getNSCount($domain_results[$i]->domain_id) && !$domain_results[$i]->domain_clone_domain_id && 
				$domain_results[$i]->domain_type == 'primary' && $zone_access_allowed) {
			$errors .= '<a href="zone-records.php?map=' . $domain_results[$i]->domain_mapping . '&domain_id=' . $domain_results[$i]->domain_id;
			if (currentUserCan('manage_zones', $_SESSION['module'])) $errors .= '&record_type=NS';
			$errors .= '">' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</a> - ' . __('Zone does not have any NS records defined.') . "\n";
		} elseif ($domain_results[$i]->domain_reload != 'no' && $zone_access_allowed &&
				currentUserCan('reload_zones', $_SESSION['module'])) {
			$errors .= '<a href="' . getMenuURL(ucfirst($domain_results[$i]->domain_mapping)) . '"><b>' . displayFriendlyDomainName($domain_results[$i]->domain_name) . '</b></a> - ' . __('Zone needs to be reloaded.') . "\n";
		}
	}
	if ($errors) {
		$error_display = '<li>' . str_replace("\n", "</li>\n<li>", $errors);
		$error_display = rtrim($error_display, '<li>');
	} else $error_display = null;

	$dashboard = sprintf('<div>
	<div id="shadow_box">
		<div id="shadow_container">
		<h3>%s</h3>
		%s
		<li>%s</li>
		<li>%s</li>
		</div>
	</div>
	</div>', __('Summary'),
			($display_server_count == true) ? '<li>' . sprintf(ngettext('You have <b>%s</b> name server configured.', 'You have <b>%s</b> name servers configured.', $server_count), formatNumber($server_count)) . '</li>' : '',
			sprintf(ngettext('You have <b>%s</b> zone defined.', 'You have <b>%s</b> zones defined.', $privileged_domain_count), formatNumber($privileged_domain_count)),
			sprintf(ngettext('You have <b>%s</b> record.', 'You have <b>%s</b> records.', $record_count), formatNumber($record_count))
			);

	if ($error_display) {
		$dashboard .= sprintf('<div>
	<div id="shadow_box">
		<div id="shadow_container">
		<h3>%s</h3>
		%s
		</div>
	</div>
	</div>', __('Needs Attention'), $error_display);
	}

	return $dashboard;
}

/**
 * Builds the additional module menu for display
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleToolbar() {
	global $__FM_CONFIG, $fmdb, $fm_dns_zones;
	
	if (isset($_GET['domain_id']) && !is_array($_GET['domain_id'])) {
		basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $_REQUEST['domain_id'], 'domain_', 'domain_id');
		extract(get_object_vars($fmdb->last_result[0]));
		
		$domain = displayFriendlyDomainName($domain_name);
		$icon = (getNameFromID($_REQUEST['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_dnssec') == 'yes') ? sprintf('<span><i class="mini-icon fa fa-lock" title="%s" aria-hidden="true"></i></span>', __('Zone is secured with DNSSEC')) : null;
		$pending_changes = sprintf('<a href="#" class="tooltip-bottom" data-tooltip="%s"><i class="fa fa-circle" aria-hidden="true"></i></a>', __('Pending unsaved changes exist'));

		if (!class_exists('fm_dns_zones')) {
			include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
		}
		$domain_menu = sprintf('
			%s:<span>%s</span>%s<span class="pending-changes">%s</span><br />%s:<span>%s</span>
		', __('Domain'), $domain, $icon, $pending_changes, __('View'), $fm_dns_zones->IDs2Name($domain_view, 'view'));
		if ($parent_domain_id = getNameFromID($_GET['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id')) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $parent_domain_id, 'domain_', 'domain_id');
			extract(get_object_vars($fmdb->last_result[0]));
			$domain_name = displayFriendlyDomainName($domain_name);
			$record_type_uri = array_key_exists('record_type', $_GET) ? '&record_type=' . $_GET['record_type'] : null;
			$domain_menu .= sprintf('</div><div>
			%s:<span><a href="zone-records.php?map=%s&domain_id=%s%s" title="%s">%s</a></span>
		', __('Clone of'), $domain_mapping, $parent_domain_id, $record_type_uri, __('Edit parent zone records'), $domain_name);
		}
		if ($parent_domain_id = getNameFromID($_GET['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) {
			basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $parent_domain_id, 'domain_', 'domain_id');
			extract(get_object_vars($fmdb->last_result[0]));
			$domain_name = displayFriendlyDomainName($domain_name);
			$record_type_uri = array_key_exists('record_type', $_GET) ? '&record_type=' . $_GET['record_type'] : null;
			$domain_menu .= sprintf('</div><div>
			%s:<span><a href="zone-records.php?map=%s&domain_id=%s%s" title="%s">%s</a></span>
		', __('Based on template'), $domain_mapping, $parent_domain_id, $record_type_uri, __('Edit template zone records'), $domain_name);
		}
	} else $domain_menu = null;
	
	return array($domain_menu, null);
}

function moduleAddServer($action) {
	include(ABSPATH . 'fm-modules/' . $_POST['module_name'] . '/classes/class_servers.php');
	
	$action .= 'Server';
	return $fm_module_servers->$action($_POST);
}


function moduleCompleteClientInstallation() {
	/** Install default config option overrides based on OS distro */
	setDefaultOverrideOptions();
	
	setBuildUpdateConfigFlag($_POST['SERIALNO'], 'yes', 'build');
}


function reloadZoneSQL($domain_ids, $reload_zone, $associated) {
	global $fmdb, $__FM_CONFIG;
	
	if ($associated == 'all') {
		if (!is_array($domain_ids)) $domain_ids = array($domain_ids);

		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_reload`='$reload_zone', `domain_check_config`='no' WHERE `domain_template` = 'no' AND
				(`domain_id` IN (" . join(',', $domain_ids) . ") OR `domain_clone_domain_id` IN (" . join(',', $domain_ids) . ") OR `domain_template_id` IN (" . join(',', $domain_ids) . '))';
	} else {
		$query = "UPDATE `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` SET `domain_reload`='$reload_zone', `domain_check_config`='no' WHERE `domain_template` = 'no' AND `domain_id`=$domain_ids";
	}
	$fmdb->query($query);
}

function reloadZone($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		if ($result[0]->domain_reload == 'yes') return true;
	}
	return false;
}

function getSOACount($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	if (version_compare(getOption('version', 0, 'fmDNS'), '1.3-beta1', '<')) {
		$query = "SELECT soa_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE (`domain_id`='$domain_id' OR
			`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id')
		) AND `soa_status`!='deleted'";
	} else {
		$query = "SELECT soa_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `soa_id`= (SELECT DISTINCT(`soa_id`) FROM 
			`fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `soa_id`!=0 AND (`domain_id`='$domain_id' OR
				`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id') OR
				`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id') OR
				`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`=(SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
					`domain_id`='$domain_id'))
			)) AND `soa_status`!='deleted'";
	}
	$fmdb->get_results($query);
	return $fmdb->num_rows;
}

function getNSCount($domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	$query = "SELECT record_id FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}records` WHERE (`domain_id`='$domain_id' OR
			`domain_id` = (SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id') OR
			`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id') OR
			`domain_id` = (SELECT `domain_template_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`=(SELECT `domain_clone_domain_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE
				`domain_id`='$domain_id'))
		) AND `record_type`='NS' AND `record_status`='active'";
	$fmdb->get_results($query);
	return $fmdb->num_rows;
}


/**
 * Cleans addresses for future parsing
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function verifyAndCleanAddresses($data, $subnets_allowed = 'subnets-allowed') {
	/** Remove extra spaces */
	$data = preg_replace('/\s\s+/', ' ', $data);
	
	/** Swap delimiters for ; */
	$data = str_replace(array("\n", ';', ' ', ','), ',', $data);
	$data = str_replace(',,', ',', $data);
	$data = trim($data, ',');
	
	$addresses = explode(',', $data);
	foreach ($addresses as $ip_address) {
		$cidr = null;

		$ip_address = rtrim(trim($ip_address), '.');
		if (!strlen($ip_address)) continue;
		
		/** Handle negated addresses */
		if (strpos($ip_address, '!') === 0) {
			$ip_address = substr($ip_address, 1);
		}
		
		if (strpos($ip_address, '/') !== false && $subnets_allowed == 'subnets-allowed') {
			$cidr_array = explode('/', $ip_address);
			list($ip_address, $cidr) = $cidr_array;
		}
		
		/** IPv4 checks */
		if (strpos($ip_address, ':') === false) {
			/** Create full IP */
			$ip_octets = explode('.', $ip_address);
			if (count($ip_octets) < 4) {
				$ip_octets = array_merge($ip_octets, array_fill(count($ip_octets), 4 - count($ip_octets), 0));
			}
			$ip_address = implode('.', $ip_octets);

			/** Valid CIDR? */
			if ($cidr && !checkCIDR($ip_address, $cidr, 32)) return sprintf(__('%s is not valid.'), "$ip_address/$cidr");
		} else {
			/** IPv6 checks */
			if ($cidr && !checkCIDR($ip_address, $cidr, 128)) return sprintf(__('%s is not valid.'), "$ip_address/$cidr");
		}
		
		if (verifyIPAddress($ip_address) === false) return sprintf(__('%s is not valid.'), $ip_address);
	}
	
	return $data;
}


function isDNSNameAcceptable($string) {
	if (preg_match('/[^a-z_\-0-9]/i', $string)) return false;
	
	return true;
}


/**
 * Posts the data to the DNS server
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 */
function postReloadZones($server, $data, $proto = 'http') {
	$url = $proto . '://' . $server . '/facileManager/index.php';
	
	return getPostData($url, $data);
}



function buildFullIPAddress($partial_ip, $domain) {
	/** Remove range */
	$domain = preg_replace('/\d{1,3}\-\d{1,3}\./', '', $domain);
	
	$domain_pieces = array_reverse(explode('.', $domain));
	$domain_parts = count($domain_pieces);
	
	$subnet_ips = '';
	for ($i=2; $i<$domain_parts; $i++) {
		$subnet_ips .= $domain_pieces[$i] . '.';
	}
	$record_octets = array_reverse(explode('.', str_replace($subnet_ips, '', $partial_ip)));
	$temp_record_value = '';
	for ($j=0; $j<count($record_octets); $j++) {
		$temp_record_value .= $record_octets[$j] . '.';
	}
	$subnet_ips .= rtrim($temp_record_value, '.');
	
	/** IPv6? */
	if (substr_count($subnet_ips, '.') > 3) {
		$pieces = explode('.', $subnet_ips);
		$pack = pack('H*', implode('', $pieces));
		$subnet_ips = inet_ntop($pack);
	}
	
	return $subnet_ips;
}


/**
 * Returns if a zone reload is allowed or not
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param int $domain_id Domain ID to check
 * @return boolean
 */
function reloadAllowed($domain_id = null, $server_serial_no = null) {
	global $fmdb, $__FM_CONFIG;
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'servers', 'active', 'server_', 'server_status');
	if ($fmdb->num_rows) {
		if ($domain_id) {
			$query = 'SELECT domain_id FROM `fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'track_builds` WHERE domain_id=' . $domain_id;
			if ($server_serial_no) {
				$query .= ' AND server_serial_no=' . $server_serial_no;
			}
			$query .= ' LIMIT 1';
			$result = $fmdb->get_results($query);
			$reload_allowed = ($fmdb->num_rows) ? true : false;
		} else $reload_allowed = true;
	} else $reload_allowed = false;
	
	return $reload_allowed;
}
	

/**
 * Removed trailing periods
 *
 * @since 1.0
 * @package facileManager
 */
function trimFullStop($value){
	return rtrim($value, '.');
}


/**
 * Gets the menu badge counts
 *
 * @since 1.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @return integer|array
 */
function getModuleBadgeCounts($type) {
	global $fmdb, $__FM_CONFIG;
	
	$badge_counts = null;
	if ($type == 'zones') {
		$badge_counts = array('forward' => 0, 'reverse' => 0);
		
		/** Zones */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_');
		$domain_count = $fmdb->num_rows;
		if ($domain_count) $domain_results = $fmdb->last_result;
		for ($i=0; $i<$domain_count; $i++) {
			if ($domain_results[$i]->domain_template == 'no' && !$domain_results[$i]->domain_clone_domain_id && 
					$domain_results[$i]->domain_type == 'primary') {
				if (currentUserCan(array('access_specific_zones'), $_SESSION['module'], array(0, $domain_results[$i]->domain_id))) {
					if (!getSOACount($domain_results[$i]->domain_id)) {
						$badge_counts[$domain_results[$i]->domain_mapping]++;
					} elseif (!getNSCount($domain_results[$i]->domain_id)) {
						$badge_counts[$domain_results[$i]->domain_mapping]++;
					} elseif ($domain_results[$i]->domain_reload != 'no') {
						$badge_counts[$domain_results[$i]->domain_mapping]++;
					} elseif ($domain_results[$i]->domain_dnssec != 'no') {
						basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'keys', 'key_id', 'key_', 'AND domain_id=' . $domain_results[$i]->domain_id);
						if (!$fmdb->num_rows || ($domain_results[$i]->domain_dnssec_signed && getDNSSECExpiration($domain_results[$i]) <= strtotime('now + 7 days'))) {
							$badge_counts[$domain_results[$i]->domain_mapping]++;
						}
					}
				}
			}
		}
		unset($domain_results, $domain_count);
	} elseif ($type == 'servers' && currentUserCan('manage_servers', $_SESSION['module'])) {
		$server_builds = array();
		
		/** Servers */
		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', " AND `server_type`!='remote' AND (`server_installed`!='yes' OR (`server_status`='active' AND `server_build_config`='yes'))");
		$server_count = $fmdb->num_rows;
		if ($server_count) $server_results = $fmdb->last_result;
		for ($i=0; $i<$server_count; $i++) {
			$server_builds[] = $server_results[$i]->server_name;
		}
		if (version_compare(getOption('version', 0, $_SESSION['module']), '1.1', '>=')) {
			basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'servers', 'server_id', 'server_', "AND `server_type`!='remote' AND `server_client_version`!='" . getOption('client_version', 0, $_SESSION['module']) . "'");
			$server_count = $fmdb->num_rows;
			if ($server_count) $server_results = $fmdb->last_result;
			for ($i=0; $i<$server_count; $i++) {
				$server_builds[] = $server_results[$i]->server_name;
			}
		}
		
		$servers = array_unique($server_builds);
		$badge_counts = count($servers);
		
		unset($server_builds, $servers, $server_count, $server_results);
	}
	
	return $badge_counts;
}


/**
 * Gets the name servers hosting a zone
 *
 * @since 1.1.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param integer $domain_id Domain ID to check
 * @param array $server_types Type of servers to pull
 * @return string
 */
function getZoneServers($domain_id, $server_types = array('masters')) {
	global $__FM_CONFIG, $fmdb, $fm_dns_zones;
	
	$serial_no = array();
	
	if ($domain_id) {
		if ($domain_template_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id')) {
			$domain_id = $domain_template_id;
		}
		$domain_name_servers = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name_servers');
		if ($domain_name_servers !== false) {
			if (!isset($fm_dns_zones)) {
				include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
			}
			$name_servers = $fm_dns_zones->getNameServers($domain_name_servers, $server_types);
			
			/** Loop through name servers */
			if ($name_servers) {
				$name_server_count = $fmdb->num_rows;
				for ($i=0; $i<$name_server_count; $i++) {
					$serial_no[] = $name_servers[$i]->server_serial_no;
				}
				$serial_no = implode(',', $serial_no);
			}
		}
	}
	
	return (is_array($serial_no)) ? '' : $serial_no;
}


/**
 * Sets default override configuration options based on OS distro
 *
 * @since 1.2
 * @package facileManager
 * @subpackage fmDNS
 */
function setDefaultOverrideOptions() {
	global $fm_module_options;
	
	$config = '';
	$server_os_distro = isDebianSystem($_POST['server_os_distro']) ? 'debian' : strtolower($_POST['server_os_distro']);
	
	switch ($server_os_distro) {
		case 'debian':
			$config = array(
							array('cfg_type' => 'global', 'server_serial_no' => $_POST['SERIALNO'], 'cfg_name' => 'pid-file', 'cfg_data' => '/var/run/named/named.pid')
						);
	}
	
	if (is_array($config)) {
		if (!isset($fm_module_options)) include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		
		foreach ($config as $config_data) {
			$fm_module_options->add($config_data);
		}
	}
}


/**
 * Returns if a RR should be allowed or not
 *
 * @since 1.2
 * @package facileManager
 * @subpackage fmDNS
 */
function removeRestrictedRR($rr) {
	global $__FM_CONFIG;
	
	return (!in_array($rr, $__FM_CONFIG['records']['require_zone_rights']) || currentUserCan('manage_zones', $_SESSION['module'])) ? true : false;
}


/**
 * Adds the module menu items
 *
 * @since 1.2
 * @package facileManager
 * @subpackage fmDNS
 */
function buildModuleMenu() {
	$badge_counts = getModuleBadgeCounts('zones');
	$GLOBALS['zone_badge_counts'] = $badge_counts;
	addObjectPage(array(__('Zones'), 'file-text'), __('Zones'), array('manage_zones', 'manage_records', 'reload_zones', 'view_all'), $_SESSION['module'], 'zones.php');
		addSubmenuPage('zones.php', __('Forward'), __('Forward Zones'), null, $_SESSION['module'], 'zones-forward.php', null, null, $badge_counts['forward']);
		addSubmenuPage('zones.php', __('Reverse'), __('Reverse Zones'), null, $_SESSION['module'], 'zones-reverse.php', null, null, $badge_counts['reverse']);
		addSubmenuPage('zones.php', __('Groups'), __('Zones Groups'), array('view_all'), $_SESSION['module'], 'zones-groups.php');
		addSubmenuPage('zones.php', null, __('Records'), null, $_SESSION['module'], 'zone-records.php');
	
	addObjectPage(array(__('Config'), 'sliders'), __('Name Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php');
		addSubmenuPage('config-servers.php', _('Servers'), __('Name Servers'), array('manage_servers', 'build_server_configs', 'view_all'), $_SESSION['module'], 'config-servers.php', null, null, getModuleBadgeCounts('servers'));
		addSubmenuPage('config-servers.php', __('Views'), __('Views'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-views.php');
		addSubmenuPage('config-servers.php', __('ACLs'), __('Access Control Lists'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-acls.php');
		addSubmenuPage('config-servers.php', __('Keys'), __('Keys'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-keys.php');
		addSubmenuPage('config-servers.php', __('Primaries'), __('Primaries'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-masters.php');
		addSubmenuPage('config-servers.php', __('HTTP'), __('HTTP Endpoints'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-http.php');
		addSubmenuPage('config-servers.php', __('TLS'), __('TLS Connections'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-tls.php');
		addSubmenuPage('config-servers.php', __('Options'), __('Options'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-options.php');
		addSubmenuPage('config-servers.php', __('Logging'), __('Logging'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-logging.php');
		addSubmenuPage('config-servers.php', __('DNSSEC'), __('DNSSEC'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-dnssec.php');
		addSubmenuPage('config-servers.php', __('Operations'), __('Operations'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-controls.php');
		addSubmenuPage('config-servers.php', __('Files'), __('Files'), array('manage_servers', 'view_all'), $_SESSION['module'], 'config-files.php');
	
	addObjectPage(array(__('Templates'), 'picture-o'), __('Zones'), array('manage_zones', 'view_all'), $_SESSION['module'], 'templates-soa.php');
		addSubmenuPage('templates-soa.php', __('SOA'), __('SOA Templates'), array('manage_zones', 'view_all'), $_SESSION['module'], 'templates-soa.php');
		addSubmenuPage('templates-soa.php', __('Zones'), __('Zone Templates'), array('manage_zones', 'manage_records', 'view_all'), $_SESSION['module'], 'templates-zones.php');

	addSettingsPage($_SESSION['module'], sprintf(__('%s Settings'), $_SESSION['module']), array('manage_settings', 'view_all'), $_SESSION['module'], 'module-settings.php');
}


/**
 * Gets the name servers hosting a zone
 *
 * @since 1.3
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $domain_name Domain name to convert to utf8
 * @return string
 */
function displayFriendlyDomainName($domain_name) {
	if ($domain_name) {
		$new_domain_name = function_exists('idn_to_utf8') ? idn_to_utf8($domain_name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $domain_name;
		if ($new_domain_name != $domain_name) $new_domain_name = $domain_name . ' (' . $new_domain_name . ')';
	}

	return isset($new_domain_name) ? $new_domain_name : $domain_name;
}


/**
 * Gets the parent domain ID
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param int $domain_id Domain ID to check
 * @param string $level How deep to traverse (clone, template)
 * @return integer|void
 */
function getParentDomainID($domain_id, $level = 'clone') {
	global $__FM_CONFIG;
	
	/** Clone */
	$parent_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
	$domain_id = ($parent_id) ? $parent_id : $domain_id;
	if ($level == 'clone') {
		return $domain_id;
	}
	
	/** Template */
	if ($level == 'template') {
		$parent_id = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');

		return ($parent_id) ? $parent_id : $domain_id;
	}
}


/**
 * Gets the count for zones requiring a reload
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $return_what What to return (count|ids)
 * @return integer|array
 */
function getZoneReloads($return_what) {
	global $fmdb, $__FM_CONFIG;
	
	$zone_count = 0;
	$zone_ids = array();
	
	basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_id', 'domain_', 'AND domain_reload!="no"');
	if ($fmdb->num_rows) {
		$num_rows = $fmdb->num_rows;
		$domain_list = $fmdb->last_result;
		for ($i=0; $i<$num_rows; $i++) {
			$zone_access_allowed = zoneAccessIsAllowed(array($domain_list[$i]->domain_id, $domain_list[$i]->domain_clone_domain_id));
			if (currentUserCan('reload_zones', $_SESSION['module']) && $zone_access_allowed) {
				$zone_count++;
				$zone_ids[] = $domain_list[$i]->domain_clone_domain_id ? $domain_list[$i]->domain_clone_domain_id : $domain_list[$i]->domain_id;
			}
		}
	}
	
	return $return_what == 'count' ? $zone_count : $zone_ids;
}


/**
 * Returns if access to a zone is allowed
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $domain_ids Domain IDs to check
 * @param string $included_action Included action to check against
 * @return boolean
 */
function zoneAccessIsAllowed($domain_ids, $included_action = null) {
	if ($included_action) {
		return currentUserCan('access_specific_zones', $_SESSION['module'], array_merge(array(0), $domain_ids)) & 
			currentUserCan($included_action, $_SESSION['module']);
	} else {
		return currentUserCan('access_specific_zones', $_SESSION['module'], array_merge(array(0), $domain_ids));
	}
}


/**
 * Returns the parent ID of the domain
 *
 * @since 2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param int $domain_id Domain ID to check
 * @return array
 */
function getZoneParentID($domain_id) {
	global $__FM_CONFIG;
	
	$parent_domain_id[] = $domain_id;
	$parent_domain_id[] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_clone_domain_id');
	if ($parent_domain_id[1]) {
		$parent_domain_id[] = getNameFromID($parent_domain_id[1], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');
	} else {
		$parent_domain_id[1] = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_template_id');
	}
	if (!$parent_domain_id[count($parent_domain_id) - 1]) array_pop($parent_domain_id);
	
	return $parent_domain_id;
}


/**
 * Returns whether the config is in use or not
 *
 * @since 2.0
 * @package fmDNS
 *
 * @param integer $id ID to get associations
 * @param string $type Type of config to search
 * @return boolean
 */
function getConfigAssoc($id, $type) {
	global $fmdb, $__FM_CONFIG;

	/** Config options */
	$queries[] = "SELECT cfg_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND (
			cfg_data='{$type}_{$id}' OR cfg_data LIKE '{$type}_{$id};%' OR cfg_data LIKE '%;{$type}_{$id};%' OR cfg_data LIKE '%;{$type}_{$id}' OR
			cfg_data='!{$type}_{$id}' OR cfg_data LIKE '!{$type}_{$id};%' OR cfg_data LIKE '%;!{$type}_{$id};%' OR cfg_data LIKE '%;!{$type}_{$id}' OR
			cfg_data LIKE '{$type}_{$id},%' OR cfg_data LIKE '%,{$type}_{$id},%' OR cfg_data LIKE '%,{$type}_{$id}' OR
			cfg_data LIKE '!{$type}_{$id},%' OR cfg_data LIKE '%,!{$type}_{$id},%' OR cfg_data LIKE '%,!{$type}_{$id}' OR
			cfg_data='\"{$type}_{$id}\"' OR cfg_data LIKE '%{$type} {$type}_{$id}' OR cfg_data LIKE '%{$type} {$type}_{$id} %'
		)";

	/** Controls */
	$queries[] = "SELECT control_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}controls WHERE account_id='{$_SESSION['user']['account_id']}' AND control_status!='deleted' AND (
			control_addresses='{$type}_{$id}' OR control_addresses LIKE '{$type}_{$id};%' OR control_addresses LIKE '%;{$type}_{$id};%' OR control_addresses LIKE '%;{$type}_{$id}'
		)";

	/** Masters */
	$queries[] = "SELECT master_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}masters WHERE account_id='{$_SESSION['user']['account_id']}' AND master_status!='deleted' AND (
			master_addresses='{$type}_{$id}' OR master_addresses LIKE '{$type}_{$id};%' OR master_addresses LIKE '%;{$type}_{$id};%' OR master_addresses LIKE '%;{$type}_{$id}'
		)";

	/** ACLs */
	$queries[] = "SELECT acl_id FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}acls WHERE account_id='{$_SESSION['user']['account_id']}' AND acl_status!='deleted' AND (
			acl_addresses='{$type}_{$id}' OR acl_addresses LIKE '{$type}_{$id};%' OR acl_addresses LIKE '%;{$type}_{$id};%' OR acl_addresses LIKE '%;{$type}_{$id}' OR
			acl_addresses='!{$type}_{$id}' OR acl_addresses LIKE '!{$type}_{$id};%' OR acl_addresses LIKE '%;!{$type}_{$id};%' OR acl_addresses LIKE '%;!{$type}_{$id}'
		)";

	foreach ($queries as $query) {
		if ($fmdb->get_results($query)) {
			return true;
		}
	}

	return false;
}


/**
 * Returns whether the CIDR is valid or not
 *
 * @since 2.1
 * @package fmDNS
 *
 * @param string $ip IP address
 * @param string $prefix CIDR to check
 * @param integer $max_bits Maximum valid bits
 * @return boolean
 */
function checkCIDR($ip, $prefix, $max_bits) {
	$netmask = verifyNumber($prefix, 0, $max_bits);

	if (!$netmask) return $netmask;

	/* Check if the ip is the network id 
	 * Used from https://stackoverflow.com/questions/4931721/getting-list-ips-from-cidr-notation-in-php
	*/
	if (strpos($ip, ':') === false) {
		if ($ip != long2ip((ip2long($ip)) & ((-1 << ($max_bits - (int)$prefix))))) return false;
	} else {
		/** IPv6
		 * Used from https://gist.github.com/pavinjosdev/cb1d636ea9dc2bd201d54107d10650c5#file-validate_cidr-php-L33
		 */
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
	}

	return true;
}


/**
 * Builds the view listing in a dropdown menu
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param integer $view_id View ID to select
 * $param string $class Class name to apply to the div
 * @return string
 */
function buildViewSubMenu($view_id = 0, $server_serial_no = 0, $class = null) {
	$server_list = buildSelect('view_id', 'view_id', availableViews(), $view_id, 1, null, false, 'this.form.submit()');
	
	$hidden_inputs = '';
	foreach ($GLOBALS['URI'] as $param => $value) {
		if ($param == 'view_id') continue;
		$hidden_inputs .= '<input type="hidden" name="' . $param . '" value="' . $value . '" />' . "\n";
	}
	
	$class = $class ? 'class="' . $class . '"' : null;

	$return = <<<HTML
	<div id="configtypesmenu" $class>
		<form action="{$GLOBALS['basename']}" method="GET">
		$hidden_inputs
		$server_list
		</form>
	</div>
HTML;

	return $return;
}


/**
 * Returns an array of views
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $status What view status to pull results with
 * @return array
 */
function availableViews($status = 'any') {
	global $fmdb, $__FM_CONFIG;
	
	$array[0][] = null;
	$array[0][0][] = __('All Views');
	$array[0][0][] = '0';
	
	$j = $k = $l = 0;
	/** Views */
	$status = ($status == 'any') ? null : "AND v.view_status='$status'";
	$fmdb->query("SELECT v.server_serial_no,v.view_id,v.view_name,s.server_name FROM `fm_dns_views` v LEFT JOIN `fm_dns_servers` s USING(server_serial_no) WHERE v.`view_status`!='deleted' $status AND v.account_id='1' ORDER BY v.`server_serial_no`  ASC,v.view_name ASC");
	if ($fmdb->num_rows && !$fmdb->sql_errors) {
		$last_result = $fmdb->last_result;
		foreach ($last_result as $results) {
			if (!$results->server_serial_no) {
				if (!array_key_exists(_('All Servers'), $array)) {
					$array[_('All Servers')][] = null;
				}
				$array[_('All Servers')][$j][] = $results->view_name;
				$array[_('All Servers')][$j][] = $results->view_id;
				$j++;
			} elseif (strpos($results->server_serial_no, 'g_') !== false) {
				$group_name = getNameFromID(str_replace('g_', '', $results->server_serial_no), 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'server_groups', 'group_', 'group_id', 'group_name');
				if (!array_key_exists($group_name, $array)) {
					$array[$group_name][] = null;
					$k = 0;
				}
				$array[$group_name][$k][] = $results->view_name;
				$array[$group_name][$k][] = $results->view_id;
				$k++;
			} elseif ($results->server_serial_no) {
				if (!array_key_exists($results->server_name, $array)) {
					$array[$results->server_name][] = null;
					$l = 0;
				}
				$array[$results->server_name][$l][] = $results->view_name;
				$array[$results->server_name][$l][] = $results->view_id;
				$l++;
			}
		}
	}
	
	return $array;
}


/**
 * Returns an array of resource records from SQL
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $record_type
 * @param integer $domain_id
 * @return array
 */
function buildSQLRecords($record_type, $domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	if ($record_type == 'SOA') {
		$soa_query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `account_id`='{$_SESSION['user']['account_id']}' AND
			`soa_id`=(SELECT `soa_id` FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}domains` WHERE `domain_id`='$domain_id') AND 
			`soa_template`='no' AND `soa_status`='active'";
		$result = $fmdb->get_results($soa_query);
		if (!$fmdb->num_rows) return array();
		
		foreach (get_object_vars($result[0]) as $key => $val) {
			$sql_results[$result[0]->soa_id][$key] = $val;
		}
		array_shift($sql_results[$result[0]->soa_id]);
		array_shift($sql_results[$result[0]->soa_id]);
		return (array) $sql_results;
	} else {
		$valid_domain_ids = 'IN (' . join(',', getZoneParentID($domain_id)) . ')';
		
		$record_sql = "AND domain_id $valid_domain_ids ";
		if ($record_type != 'all') {
			if (in_array($record_type, array('A', 'AAAA'))) {
				$record_sql .= "AND record_type IN ('A', 'AAAA')";
			} else {
				$record_sql .= "AND record_type='$record_type'";
			}
		}
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_name', 'record_', $record_sql);
		if ($result) {
			$results = $fmdb->last_result;

			for ($i=0; $i<$result; $i++) {
				$static_array = array('record_name', 'record_ttl', 'record_class',
					'record_type', 'record_value', 'record_comment', 'record_status');
				$optional_array = array('record_priority', 'record_weight', 'record_port',
					'record_os', 'record_cert_type', 'record_key_tag', 'record_algorithm',
					'record_flags', 'record_text', 'record_params', 'record_regex',
					'record_append');
				
				foreach ($static_array as $field) {
					$sql_results[$results[$i]->record_id][$field] = $results[$i]->$field;
				}
				foreach ($optional_array as $field) {
					if ($results[$i]->$field != null) {
						$sql_results[$results[$i]->record_id][$field] = $results[$i]->$field;
					}
				}
				
				/** Skipped record? */
				basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records_skipped', $results[$i]->record_id, 'record_', 'record_id', "AND domain_id=$domain_id");
				$sql_results[$results[$i]->record_id]['record_skipped'] = ($fmdb->num_rows) ? 'on' : 'off';
			}
		}
		return (array) $sql_results;
	}
}


/**
 * Returns an array of compared array values
 *
 * @since 1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $data_array
 * @param array $sql_records
 * @return array
 */
function compareValues($data_array, $sql_records) {
	$changes = array();
	foreach ($data_array as $key => $val) {
		$diff = array_diff_assoc((array) $data_array[$key], (array) $sql_records[$key]);
		if ($diff) {
			$changes[$key] = $diff;
		}
	}

	return $changes;
}


/**
 * Returns the DNSSEC expiration date for a zone
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array|object $data Domain details array
 * @param string $type What expiration should be processed
 * @return integer
 */
function getDNSSECExpiration($data, $type = 'calculated') {
	$domain_dnssec_sig_expires = ($data->domain_dnssec_sig_expire) ? $data->domain_dnssec_sig_expire : getOption('dnssec_expiry', $_SESSION['user']['account_id'], $_SESSION['module']);
	$domain_dnssec_sig_expires = ($type == 'calculated') ? strtotime(date('YmdHis', $data->domain_dnssec_signed) . ' + ' . $domain_dnssec_sig_expires . ' days') : date('YmdHis', strtotime('now + ' . $domain_dnssec_sig_expires . ' days'));
	
	return $domain_dnssec_sig_expires;
}


/**
 * Manages the PTR record
 *
 * @since 3.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param int $domain_id domain_id
 * @param string $record_type Type of RR
 * @param array $data RR data to process
 * @param string $operation Add or Update
 * @param object $old_record Old RR information
 * @return boolean
 */
function autoManagePTR($domain_id, $record_type, $data, $operation = 'add', $old_record = null) {
	global $__FM_CONFIG, $fmdb;

	$forward_record_id = ($old_record) ? $old_record->record_id : $fmdb->insert_id;

	/* We must have the PTR checkbox checked */
	if (!isset($data['PTR'])) return false;

	/* Get the proper reverse domain_id for the PTR */
	if (!is_numeric($data['PTR'])) {
		$retval = checkPTRZone($data['record_value'], $domain_id);
		list($data['PTR'], $error_msg) = $retval;
	}
	
	if ($record_type == 'A' && zoneAccessIsAllowed(array($data['PTR']))) {
		$domain = '.' . trimFullStop(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) . '.';
		if ($data['record_name'][0] == '@') {
			$data['record_name'] = null;
			$domain = substr($domain, 1);
		}

		/** Get reverse zone */
		if (!strrpos($data['record_value'], ':')) {
			$rev_domain = trimFullStop(getNameFromID($data['PTR'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
			$domain_pieces = array_reverse(explode('.', $rev_domain));
			$domain_parts = count($domain_pieces);

			$subnet_ips = '';
			for ($i=2; $i<$domain_parts; $i++) {
				if (strpos($domain_pieces[$i], '-')) break;
				$subnet_ips .= $domain_pieces[$i] . '.';
			}
			$record_octets = array_reverse(explode('.', substr($data['record_value'], strlen($subnet_ips))));
			$temp_record_value = '';
			for ($j=0; $j<count($record_octets); $j++) {
				$temp_record_value .= $record_octets[$j] . '.';
			}
			$data['record_value'] = rtrim($temp_record_value, '.');
		} else {
			/** IPv6 not yet supported */
			return false;
		}

		if (isset($data['record_status'])) {
			$array['record_status'] = $data['record_status'];
		}
		if (!isset($data['record_status']) || $data['record_status'] != 'deleted') {
			$array = array(
					'record_name' => $data['record_value'],
					'record_ttl' => $data['record_ttl'],
					'record_value' => $data['record_name'] . $domain,
					'record_comment' => $data['record_comment']
					);
		}

		global $fm_dns_records;
		if ($operation == 'update') {
			$fm_dns_records->update($data['PTR'], $old_record->record_ptr_id, 'PTR', $array);
			
			if ($fmdb->rows_affected) return true;
			array_pop($array);
		}
		
		if (!isset($data['record_status']) || $data['record_status'] != 'deleted') {
			$fm_dns_records->add($data['PTR'], 'PTR', $array, 'replace');
			if ($fmdb->insert_id != $forward_record_id) {
				basicUpdate('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $forward_record_id, 'record_ptr_id', $fmdb->insert_id, 'record_id');
			}
			return true;
		}

		return false;
	}

	return false;
}


/**
 * Returns the members of the group
 *
 * @since 3.1
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $group_id Group ID
 * @param string $capability Capability to process
 * @return array
 */
function moduleExplodeGroup($group_id, $capability) {
	global $fmdb, $__FM_CONFIG;
	
	$return = array();
	
	if ($capability == 'access_specific_zones') {
		$group_id = substr($group_id, 2);
		$domain_sql = "AND (domain_groups='$group_id' OR domain_groups LIKE '$group_id;%' OR domain_groups LIKE '%;$group_id;%' OR domain_groups LIKE '%;$group_id')";
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_id', 'domain_', $domain_sql);
		for ($x=0; $x<$fmdb->num_rows; $x++) {
			$return[] = $fmdb->last_result[$x]->domain_id;
		}
	}
	
	return $return;
}


/**
 * Checks the PTR zone for auto-PTR creation
 *
 * @since 3.2
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $ip Full IP address to parse
 * @param integer $domain_id Forward domain ID to link to
 * @return array
 */
function checkPTRZone($ip, $domain_id) {
	global $fmdb, $__FM_CONFIG;

	$octet = explode('.', $ip);
	$zone = "'{$octet[2]}.{$octet[1]}.{$octet[0]}.in-addr.arpa', '{$octet[1]}.{$octet[0]}.in-addr.arpa', '{$octet[0]}.in-addr.arpa'";

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone, 'domain_', 'domain_name', "OR domain_name IN ($zone) AND domain_type='primary' AND domain_status!='deleted'");
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		return array($result[0]->domain_id, null);
	} else {
		basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_name', 'domain_', "AND domain_mapping='reverse' AND domain_name LIKE '%-%-%'");
		if ($fmdb->num_rows) {
			for ($i=0; $i<$fmdb->num_rows; $i++) {
				$domain_name = $fmdb->last_result[$i]->domain_name;
				$range = array();
				foreach (array_reverse(explode('.', $domain_name)) as $key => $tmp_octect) {
					if (in_array($key, array(0, 1))) continue;
					
					if (strpos($tmp_octect, '-') !== false) {
						list($start, $end) = explode('-', $tmp_octect);
						$range['start'][] = $start;
						$range['end'][] = $end;
					} else {
						$range['start'][] = $tmp_octect;
						$range['end'][] = $tmp_octect;
					}
				}
				$range['start'] = array_pad($range['start'], 4, 0);
				$range['end'] = array_pad($range['end'], 4, 255);

				if (ip2long(join('.', $range['start'])) <= ip2long($ip) && ip2long(join('.', $range['end'])) >= ip2long($ip)) {
					return array($fmdb->last_result[$i]->domain_id, null);
				}
			}
		}
	}
	
	/** No match so auto create if allowed */
	if (getOption('auto_create_ptr_zones', $_SESSION['user']['account_id'], $_SESSION['module']) == 'yes') {
		return autoCreatePTRZone($zone, $domain_id);
	}
	return array(null, __('An existing reverse zone does not exist.'));
}

/**
 * Create the reverse zone if it does not exist
 *
 * @since 3.2
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $new_zones Zone names to check
 * @param integer $fwd_domain_id Forward domain ID to link to
 * @return array
 */
function autoCreatePTRZone($new_zones, $fwd_domain_id) {
	global $__FM_CONFIG, $fmdb;

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $fwd_domain_id, 'domain_', 'domain_id');
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;

		$new_zone = explode(",", $new_zones);

		$ptr_array['domain_id'] = 0;
		$ptr_array['domain_name'] = trim($new_zone[0], "'");
		$ptr_array['domain_mapping'] = 'reverse';
		$ptr_array['domain_name_servers'] = explode(';', $result[0]->domain_name_servers);

		$copy_fields = array('domain_view', 'domain_type', 'domain_template_id', 'domain_name_servers');
		foreach ($copy_fields as $field) {
			$ptr_array[$field] = $result[0]->$field;
		}

		/** Copy the SOA only if it's a template */
		if (getNameFromID($result[0]->soa_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'soa', 'soa_', 'soa_id', 'soa_template') == 'yes') {
			$ptr_array['soa_id'] = $result[0]->soa_id;
		}

		global $fm_dns_zones;
		if (!class_exists('fm_dns_zones')) {
			include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
		}
		$retval = $fm_dns_zones->add($ptr_array);

		return !is_int($retval) ? array(null, $retval) : array($retval, __('Created reverse zone.'));
	}

	return array(null, __('Forward zone not found.'));
}

/**
 * Resets the URL RR servers config builds status
 *
 * @since 4.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $build_update Build or Update
 * @return null
 */
function resetURLServerConfigStatus($build_update = 'build') {
	global $__FM_CONFIG, $fmdb;

	$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}servers` SET `server_{$build_update}_config`='yes' WHERE `server_url_config_file`!='' AND `server_status`!='deleted' AND `server_installed`='yes' AND `account_id`='{$_SESSION['user']['account_id']}'";
	$fmdb->query($query);

	return;
}

/**
 * Gets config item data from key
 *
 * @since 4.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param integer $config_id Config parent ID to retrieve children for
 * @param string $config_type Type of configuration item
 * @param string $return Array keys to populate and return
 * @return array|null
 */
function getConfigChildren($config_id, $config_type = 'global', $return = null) {
	global $fmdb, $__FM_CONFIG;
	
	/** Get the data from $config_id */
	$query = "SELECT cfg_name,cfg_data FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config WHERE account_id='{$_SESSION['user']['account_id']}' AND cfg_status!='deleted' AND cfg_type='{$config_type}' AND cfg_parent='{$config_id}' AND cfg_name!='!config_name!' ORDER BY cfg_id ASC";
	$result = $fmdb->get_results($query);
	if (!$fmdb->sql_errors && $fmdb->num_rows) {
		foreach ($fmdb->last_result as $result) {
			$return[$result->cfg_name] = $result->cfg_data;
		}
	}
	
	return isset($return) ? $return : array();
}


/**
 * Gets available items from type
 *
 * @since 4.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $type Type of items to retrieve
 * @param string $default What the default array item will be
 * @param string $addl_sql Additional SQL to send to the query
 * @param string $status Item status to retrieve
 * @param string $prefix
 * @return array
 */
function availableItems($type, $default = 'blank', $addl_sql = null, $status = 'active', $prefix = null) {
	global $fmdb, $__FM_CONFIG;
	
	$return = array();
	
	$j = 0;
	if ($default == 'blank') {
		$return[$j][] = '';
		$return[$j][] = '';
		$j++;
	}
	
	if ($status) {
		$addl_sql .= "AND `{$type}_status`='{$status}'";
	}
	
	$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}{$type}s WHERE account_id='{$_SESSION['user']['account_id']}' AND {$type}_status!='deleted' $addl_sql ORDER BY {$type}_name ASC";
	$result = $fmdb->get_results($query);
	if ($fmdb->num_rows) {
		foreach ($fmdb->last_result as $results) {
			if (property_exists($results, 'server_menu_display') && $results->server_menu_display == 'exclude') continue;
			$type_name = $type . '_name';
			$type_id   = $type . '_id';
			$return[$j][] = $results->$type_name;
			$return[$j][] = $prefix . $results->$type_id;
			$j++;
		}
	}
	
	return $return;
}


/**
 * Returns a minimum version
 *
 * @since 6.1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param string $feature Feature to retrieve version info for
 * @param string $option Specific option to query
 * @param string $format What format to return (message, version)
 * @param string $addl_sql Additional SQL query
 * @return string
 */
function getMinimumFeatureVersion($feature, $option = '', $format = 'message', $addl_sql = '') {
	global $fmdb, $__FM_CONFIG;

	if ($option) {
		$addl_sql = "AND def_option='{$option}' $addl_sql";
	}

	$query = "SELECT def_minimum_version FROM fm_{$__FM_CONFIG['fmDNS']['prefix']}functions WHERE def_function='{$feature}' {$addl_sql} AND def_minimum_version IS NOT NULL ORDER BY def_minimum_version,def_option ASC LIMIT 1";
	$fmdb->query($query);

	if ($fmdb->num_rows) {
		$required_version = $fmdb->last_result[0]->def_minimum_version;
		return ($format == 'message') ? sprintf('BIND %s or greater is required for this feature.', $required_version) : $required_version;
	}

	return '';
}


/**
 * Throws a form validation error
 *
 * @since 7.1.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param integer $code Error code to throw
 * @param string|array $value Optional value to pass in error message
 * @return string
 */
function moduleThrowErrorCode($code, $value = '') {
	switch ($code) {
		case 500:
			$message = _('There are no items defined.');
			break;
		case 501:
			break;
		case 502:
			break;
		case 503:
			break;
		case 504:
			break;
		case 601:
			break;
		case 602:
			break;
		case 603:
			break;
		case 700:
			break;
		case 701:
			break;
		case 702:
			break;
		default:
			break;
	}

	return $message;
}
