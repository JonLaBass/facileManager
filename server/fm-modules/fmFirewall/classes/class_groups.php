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
 | fmFirewall: Easily manage one or more software firewalls                |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmfirewall/                        |
 +-------------------------------------------------------------------------+
*/

class fm_module_groups {
	
	/**
	 * Displays the group list
	 */
	function rows($result, $type, $page, $total_pages) {
		global $fmdb;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		if (currentUserCan('manage_' . $type . 's', $_SESSION['module'])) {
			$bulk_actions_list = array(_('Delete'));
		}

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages, @buildBulkActionMenu($bulk_actions_list));

		$table_info = array(
						'class' => 'display_results sortable',
						'id' => 'table_edits',
						'name' => 'groups'
					);

		if (is_array($bulk_actions_list)) {
			$title_array[] = array(
								'title' => '<input type="checkbox" class="tickall" onClick="toggle(this, \'bulk_list[]\')" />',
								'class' => 'header-tiny header-nosort'
							);
		}
		$title_array = array_merge((array) $title_array, array(
			array('title' => _('Group Name'), 'rel' => 'group_name'),
			array('title' => $type . 's', 'class' => 'header-nosort'),
			array('title' => _('Comment'), 'style' => 'width: 40%;', 'class' => 'header-nosort'),
			array('title' => _('Actions'), 'class' => 'header-actions header-nosort')
		));

		echo '<div class="overflow-container">';
		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				$this->displayRow($results[$x]);
				$y++;
			}
		}
		
		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="groups">%s</p>', _('There are no groups defined.'));
		}
	}

	/**
	 * Adds the new group
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}groups`";
		$sql_fields = '(';
		$sql_values = '';
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		
		$exclude = array_merge($global_form_field_excludes, array('group_id'));

		$log_message = __("Added group with the following") . ":\n";
		$logging_excluded_fields = array('account_id');

		foreach ($post as $key => $data) {
			if (($key == 'group_name') && empty($data)) return _('No group name defined.');
			if (!in_array($key, $exclude)) {
				$sql_fields .= $key . ', ';
				$sql_values .= "'$data', ";
				if ($data && !in_array($key, $logging_excluded_fields)) {
					if ($key == 'group_items') {
						$log_message .= formatLogKeyData('', 'objects', $this->getGroupItemNames($data));
					} else {
						$log_message .= formatLogKeyData('group_', $key, $data);
					}
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not add the group because a database error occurred.'), 'sql');
		}

		addLogEntry($log_message);
		return true;
	}

	/**
	 * Updates the selected group
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG, $global_form_field_excludes;
		
		/** Validate entries */
		$post = $this->validatePost($post);
		if (!is_array($post)) return $post;
		
		$exclude = array_merge($global_form_field_excludes, array('group_id'));

		$sql_edit = '';
		$old_name = getNameFromID($post['group_id'], 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
		
		$log_message = sprintf(__("Updated group '%s' to the following"), $old_name) . ":\n";

		foreach ($post as $key => $data) {
			if (!in_array($key, $exclude)) {
				$sql_edit .= $key . "='" . $data . "', ";
				if ($data) {
					if ($key == 'group_items') {
						$log_message .= formatLogKeyData('', 'objects', $this->getGroupItemNames($data));
					} else {
						$log_message .= formatLogKeyData('group_', $key, $data);
					}
				}
			}
		}
		$sql = rtrim($sql_edit, ', ');
		$group_id = (int)$post['group_id'];

		// Update the group
		$query = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}groups` SET $sql WHERE `group_id`=$group_id AND `account_id`='{$_SESSION['user']['account_id']}'";
		$fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(_('Could not update the group because a database error occurred.'), 'sql');
		}
		
		/** Return if there are no changes */
		if (!$fmdb->rows_affected) return true;

//		setBuildUpdateConfigFlag(getServerSerial($post['group_id'], $_SESSION['module']), 'yes', 'build');
		
		addLogEntry($log_message);
		return true;
	}
	
	/**
	 * Deletes the selected group
	 */
	function delete($group_id) {
		global $fmdb, $__FM_CONFIG;
		
		/** Does the group_id exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', $group_id, 'group_', 'group_id');
		if ($fmdb->num_rows) {
			/** Is the group_id present in a policy? */
			if (isItemInPolicy($group_id, 'group')) return _('This group could not be deleted because it is associated with one or more policies.');
			
			/** Delete group */
			$tmp_name = getNameFromID($group_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
			if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', $group_id, 'group_', 'deleted', 'group_id')) {
				addLogEntry("Deleted group '$tmp_name'.");
				return true;
			}
		}
		
		return formatError(_('This group could not be deleted.'), 'sql');
	}


	function displayRow($row) {
		global $fmdb, $__FM_CONFIG;
		
		$disabled_class = ($row->group_status == 'disabled') ? ' class="disabled"' : null;
		
		$group_items = array();
		$checkbox = null;
		$edit_status = sprintf('<span rel="g%s">%s</span>', $row->group_id, $__FM_CONFIG['module']['icons']['search']);
		
		$permission = ($row->group_type == 'service') ? 'manage_services' : 'manage_objects';
		
		if (currentUserCan($permission, $_SESSION['module'])) {
			$edit_status .= '<a class="edit_form_link" name="' . $row->group_type . '" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
			if (!isItemInPolicy($row->group_id, 'group')) {
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
				$checkbox = '<td><input type="checkbox" name="bulk_list[]" value="' . $row->group_id .'" /></td>';
			} else {
				$checkbox = '<td></td>';
			}
		}
		$edit_status = '<td class="column-actions">' . $edit_status . '</td>';
		
		/** Process group items */
		$group_items = nl2br($this->getGroupItemNames($row->group_items));
		$comments = nl2br($row->group_comment);
		
		echo <<<HTML
			<tr id="$row->group_id" name="$row->group_name"$disabled_class>
				$checkbox
				<td>$row->group_name</td>
				<td>$group_items</td>
				<td>$comments</td>
				$edit_status
			</tr>

HTML;
	}

	/**
	 * Displays the form to add new group
	 */
	function printForm($data = '', $action = 'add', $group_type = 'service') {
		global $__FM_CONFIG;
		
		$group_id = 0;
		$group_name = $group_items = $group_comment = null;
		$uc_group_type = ucfirst($group_type) . 's';
		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}
		
		$group_items_assigned = getGroupItems($group_items);

		$group_name_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_name');
		$group_items = buildSelect('group_items', 'group_items', availableGroupItems($group_type, 'available', null, $group_id), $group_items_assigned, 1, null, true, null, 'required', 'Select one or more ' . $group_type . 's');
		
		$popup_title = $action == 'add' ? __('Add Group') : __('Edit Group');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('
		%s
		<form name="manage" id="manage">
			<input type="hidden" name="page" value="groups" />
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="group_id" value="%d" />
			<input type="hidden" name="group_type" value="%s" />
			<table class="form-table">
				<tr>
					<th width="33&#37;" scope="row"><label for="group_name">%s</label></th>
					<td width="67&#37;"><input name="group_name" id="group_name" type="text" value="%s" size="40" placeholder="http" maxlength="%d" class="required" /></td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_items">%s</label></th>
					<td width="67&#37;">
						%s
					</td>
				</tr>
				<tr>
					<th width="33&#37;" scope="row"><label for="group_comment">%s</label></th>
					<td width="67&#37;"><textarea id="group_comment" name="group_comment" rows="4" cols="30">%s</textarea></td>
				</tr>
			</table>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "200px",
					minimumResultsForSearch: 10
				});
			});
		</script>', $popup_header, $action, $group_id, $group_type, __('Group Name'),
				$group_name, $group_name_length, $uc_group_type, $group_items,
				_('Comment'), $group_comment, $popup_footer);

		return $return_form;
	}
	

	function validatePost($post) {
		global $fmdb, $__FM_CONFIG;
		
		if (empty($post['group_name'])) return _('No group name defined.');
		
		/** Check name field length */
		$field_length = getColumnLength('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_name');
		if ($field_length !== false && strlen($post['group_name']) > $field_length) return sprintf(dngettext($_SESSION['module'], 'Group name is too long (maximum %d character).', 'Group name is too long (maximum %d characters).', $field_length), $field_length);
		
		/** Does the record already exist for this account? */
		basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', $post['group_name'], 'group_', 'group_name', "AND group_type='{$post['group_type']}' AND group_id!={$post['group_id']}");
		if ($fmdb->num_rows) return _('This group name already exists.');
		
		/** Process assigned items */
		$post['group_items'] = (isset($post['group_items'])) ? implode(';', $post['group_items']) : null;
		if (empty($post['group_items'])) return 'You must assign at least one ' . $post['group_type'] . '.';
		
		return $post;
	}
	

	/**
	 * Gets friendly group member names
	 *
	 * @since 6.1.0
	 * @package facileManager
	 * @subpackage fmFirewall
	 *
	 * @param string $group_ids Semi-colon delimited string of group item ids
	 * @return string
	 */
	function getGroupItemNames($group_ids) {
		global $__FM_CONFIG, $fmdb;

		foreach (explode(';', $group_ids) as $item) {
			$item_id = substr($item, 1);
			switch ($item[0]) {
				case 's':
					basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'services', $item_id, 'service_', 'service_id');
					if ($fmdb->num_rows) {
						$result = $fmdb->last_result[0];
						if ($result->service_type == 'icmp') {
							$group_items[] = $result->service_name . ' (type: ' . $result->service_icmp_type . ' code: ' . $result->service_icmp_code . ')';
						} else {
							$service_src_ports = $result->service_src_ports ? $result->service_src_ports : '0:0';
							$service_dest_ports = $result->service_dest_ports ? $result->service_dest_ports : '0:0';
							$group_items[] = $result->service_name . ' (' . $service_src_ports . ' / ' . $service_dest_ports . ')';
						}
					}
					break;
				case 'o':
					basicGet('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'objects', $item_id, 'object_', 'object_id');
					if ($fmdb->num_rows) {
						$result = $fmdb->last_result[0];
						$group_items[] = sprintf('%s (%s%s)', $result->object_name, $result->object_address, ($result->object_mask) ? ' / ' . $result->object_mask : null);
					}
					break;
				case 'g':
					$group_items[] = getNameFromID($item_id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'groups', 'group_', 'group_id', 'group_name');
					break;
			}
		}
		$group_items = implode("\n", $group_items);

		return $group_items;
	}

}

if (!isset($fm_module_groups))
	$fm_module_groups = new fm_module_groups();
