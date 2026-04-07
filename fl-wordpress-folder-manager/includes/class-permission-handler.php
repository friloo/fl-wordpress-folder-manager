<?php
namespace EFM;

class PermissionHandler {
    
    public function get_all_roles() {
        return Database::get_all_roles();
    }
    
    public function get_folder_permissions($folder_id) {
        $permissions = Database::get_folder_permissions($folder_id);
        $all_roles = $this->get_all_roles();
        
        // Matrix erstellen: Alle Rollen mit ihren Berechtigungen für diesen Ordner
        $permission_matrix = array();
        
        foreach ($all_roles as $role) {
            $has_permission = false;
            
            // Prüfen ob Berechtigung existiert
            foreach ($permissions as $perm) {
                if ($perm['role'] === $role['key']) {
                    $has_permission = $perm['can_upload'];
                    break;
                }
            }
            
            $permission_matrix[] = array(
                'role' => $role,
                'can_upload' => $has_permission
            );
        }
        
        return $permission_matrix;
    }
    
    public function update_folder_permissions($folder_id, $permissions) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to update permissions.', 'fl-folder-manager'));
        }
        
        $folder = Database::get_folder($folder_id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'fl-folder-manager'));
        }
        
        $all_roles = $this->get_all_roles();
        $role_keys = array_column($all_roles, 'key');
        
        foreach ($permissions as $role_key => $can_upload) {
            // Sicherstellen dass die Rolle existiert
            if (!in_array($role_key, $role_keys)) {
                continue;
            }
            
            $result = Database::update_folder_permission(
                $folder_id,
                $role_key,
                $can_upload
            );
            
            if ($result === false) {
                return new \WP_Error('update_failed', sprintf(__('Failed to update permission for role: %s', 'fl-folder-manager'), $role_key));
            }
        }
        
        return array(
            'success' => true,
            'message' => __('Permissions updated successfully.', 'fl-folder-manager')
        );
    }
    
    public function user_can_upload_to_folder($user_id, $folder_id) {
        return Database::user_can_upload_to_folder($user_id, $folder_id);
    }
    
    public function get_user_uploadable_folders($user_id) {
        $all_folders = Database::get_folders_tree();
        $uploadable_folders = array();
        
        $this->filter_uploadable_folders($all_folders, $user_id, $uploadable_folders);
        
        return $uploadable_folders;
    }
    
    private function filter_uploadable_folders($folders, $user_id, &$result) {
        foreach ($folders as $folder) {
            if ($this->user_can_upload_to_folder($user_id, $folder['id'])) {
                $result[] = array(
                    'id' => $folder['id'],
                    'name' => $folder['name'],
                    'path' => $this->get_folder_path_names($folder['id'])
                );
            }
            
            if (!empty($folder['children'])) {
                $this->filter_uploadable_folders($folder['children'], $user_id, $result);
            }
        }
    }
    
    private function get_folder_path_names($folder_id) {
        $folder_manager = FolderManager::get_instance();
        $path = $folder_manager->get_folder_path($folder_id);
        
        $names = array();
        foreach ($path as $item) {
            if ($item['id'] > 0) { // Root ausschließen
                $names[] = $item['name'];
            }
        }
        
        return implode(' / ', $names);
    }
    
    public function get_permission_summary($folder_id) {
        $permissions = $this->get_folder_permissions($folder_id);
        $summary = array(
            'can_upload' => array(),
            'cannot_upload' => array()
        );
        
        foreach ($permissions as $perm) {
            if ($perm['can_upload']) {
                $summary['can_upload'][] = $perm['role']['name'];
            } else {
                $summary['cannot_upload'][] = $perm['role']['name'];
            }
        }
        
        return $summary;
    }
    
    public function create_custom_role($role_key, $role_name, $description = '') {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to create roles.', 'fl-folder-manager'));
        }
        
        global $wpdb;
        $table = Database::get_table_name('custom_roles');
        
        // Prüfen ob Rolle bereits existiert
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE role_key = %s",
            $role_key
        ));
        
        if ($exists) {
            return new \WP_Error('role_exists', __('Role already exists.', 'fl-folder-manager'));
        }
        
        $result = $wpdb->insert($table, array(
            'role_key' => sanitize_key($role_key),
            'role_name' => sanitize_text_field($role_name),
            'description' => sanitize_textarea_field($description)
        ));
        
        if (!$result) {
            return new \WP_Error('create_failed', __('Failed to create role.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'role_id' => $wpdb->insert_id,
            'message' => __('Role created successfully.', 'fl-folder-manager')
        );
    }
    
    public function update_custom_role($role_id, $data) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to update roles.', 'fl-folder-manager'));
        }
        
        global $wpdb;
        $table = Database::get_table_name('custom_roles');
        
        $update_data = array();
        
        if (isset($data['role_name'])) {
            $update_data['role_name'] = sanitize_text_field($data['role_name']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
        }
        
        if (empty($update_data)) {
            return new \WP_Error('no_data', __('No data to update.', 'fl-folder-manager'));
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $role_id)
        );
        
        if ($result === false) {
            return new \WP_Error('update_failed', __('Failed to update role.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Role updated successfully.', 'fl-folder-manager')
        );
    }
    
    public function delete_custom_role($role_id) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to delete roles.', 'fl-folder-manager'));
        }
        
        global $wpdb;
        $table = Database::get_table_name('custom_roles');
        
        // Prüfen ob Rolle in Verwendung ist
        $permissions_table = Database::get_table_name('permissions');
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $permissions_table WHERE role = (SELECT role_key FROM $table WHERE id = %d)",
            $role_id
        ));
        
        if ($in_use > 0) {
            return new \WP_Error('in_use', __('Cannot delete role that is in use.', 'fl-folder-manager'));
        }
        
        $result = $wpdb->delete($table, array('id' => $role_id));
        
        if (!$result) {
            return new \WP_Error('delete_failed', __('Failed to delete role.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Role deleted successfully.', 'fl-folder-manager')
        );
    }
    
    public function assign_role_to_user($user_id, $role_key) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to assign roles.', 'fl-folder-manager'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'efm_user_roles';
        
        // Tabelle erstellen falls nicht existiert
        $this->create_user_roles_table();
        
        // Prüfen ob Rolle existiert
        $custom_roles_table = Database::get_table_name('custom_roles');
        $role_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $custom_roles_table WHERE role_key = %s AND is_active = 1",
            $role_key
        ));
        
        if (!$role_exists) {
            return new \WP_Error('role_not_found', __('Role not found.', 'fl-folder-manager'));
        }
        
        // Prüfen ob Benutzer bereits diese Rolle hat
        $already_assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND role_key = %s",
            $user_id,
            $role_key
        ));
        
        if ($already_assigned) {
            return new \WP_Error('already_assigned', __('User already has this role.', 'fl-folder-manager'));
        }
        
        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'role_key' => $role_key,
            'assigned_at' => current_time('mysql'),
            'assigned_by' => get_current_user_id()
        ));
        
        if (!$result) {
            return new \WP_Error('assign_failed', __('Failed to assign role to user.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Role assigned successfully.', 'fl-folder-manager')
        );
    }
    
    public function remove_role_from_user($user_id, $role_key) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to remove roles.', 'fl-folder-manager'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'efm_user_roles';
        
        $result = $wpdb->delete($table, array(
            'user_id' => $user_id,
            'role_key' => $role_key
        ));
        
        if (!$result) {
            return new \WP_Error('remove_failed', __('Failed to remove role from user.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Role removed successfully.', 'fl-folder-manager')
        );
    }
    
    public function get_user_custom_roles($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'efm_user_roles';
        $custom_roles_table = Database::get_table_name('custom_roles');
        
        // Tabelle erstellen falls nicht existiert
        $this->create_user_roles_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT cr.*, ur.assigned_at 
             FROM $table ur 
             JOIN $custom_roles_table cr ON ur.role_key = cr.role_key 
             WHERE ur.user_id = %d AND cr.is_active = 1",
            $user_id
        ), ARRAY_A);
    }
    
    private function create_user_roles_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'efm_user_roles';
        
        // Prüfen ob Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                role_key VARCHAR(100) NOT NULL,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                assigned_by BIGINT(20) UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY user_role (user_id, role_key),
                KEY user_id (user_id),
                KEY role_key (role_key)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    public function get_users_with_role($role_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'efm_user_roles';
        $users_table = $wpdb->users;
        
        $this->create_user_roles_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.user_login, u.display_name, u.user_email, ur.assigned_at 
             FROM $table ur 
             JOIN $users_table u ON ur.user_id = u.ID 
             WHERE ur.role_key = %s 
             ORDER BY u.display_name ASC",
            $role_key
        ), ARRAY_A);
    }
}