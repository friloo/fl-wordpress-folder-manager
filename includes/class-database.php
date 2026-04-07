<?php
namespace EFM;

class Database {
    
    private static $table_prefix = 'efm_';
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . self::$table_prefix;
        
        $sql = array();
        
        // Tabelle: Ordner
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}folders (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id BIGINT(20) UNSIGNED DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_id (parent_id),
            KEY slug (slug(191)),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Tabelle: Dateien
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}files (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            folder_id BIGINT(20) UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            mime_type VARCHAR(100),
            uploaded_by BIGINT(20) UNSIGNED NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            download_count BIGINT(20) UNSIGNED DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY folder_id (folder_id),
            KEY uploaded_by (uploaded_by),
            KEY file_type (file_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Tabelle: Upload-Berechtigungen (nur für Upload!)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}permissions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            folder_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(100) NOT NULL,
            can_upload TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY folder_role (folder_id, role),
            KEY folder_id (folder_id),
            KEY role (role),
            KEY can_upload (can_upload)
        ) $charset_collate;";
        
        // Tabelle: Benutzerdefinierte Rollen (optional)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}custom_roles (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            role_key VARCHAR(100) NOT NULL,
            role_name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY role_key (role_key),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Tabelle: Benutzer-Rollen-Zuordnung
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}user_roles (
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
        
        foreach ($sql as $query) {
            dbDelta($query);
        }
        
        // Standard-Ordner erstellen (Root)
        self::create_default_folder();
        
        // Standard-Rollen erstellen
        self::create_default_custom_roles();
    }
    
    private static function create_default_folder() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_prefix . 'folders';
        
        // Prüfen ob Root-Ordner existiert
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE slug = %s",
            'root'
        ));
        
        if (!$exists) {
            $current_user_id = get_current_user_id() ?: 1;
            
            $wpdb->insert($table, array(
                'parent_id' => 0,
                'name' => 'Root',
                'slug' => 'root',
                'description' => 'Root folder for all files',
                'created_by' => $current_user_id
            ));
        }
    }
    
    private static function create_default_custom_roles() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_prefix . 'custom_roles';
        
        $default_roles = array(
            array('mav', 'MAV', 'MAV Department'),
            array('vertrieb', 'Vertrieb', 'Sales Department'),
            array('entwicklung', 'Entwicklung', 'Development Department'),
            array('marketing', 'Marketing', 'Marketing Department'),
            array('hr', 'HR', 'Human Resources'),
        );
        
        foreach ($default_roles as $role) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE role_key = %s",
                $role[0]
            ));
            
            if (!$exists) {
                $wpdb->insert($table, array(
                    'role_key' => $role[0],
                    'role_name' => $role[1],
                    'description' => $role[2]
                ));
            }
        }
    }
    
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . self::$table_prefix . $table;
    }
    
    public static function get_folders_tree($parent_id = 0) {
        global $wpdb;
        $table = self::get_table_name('folders');
        
        $folders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE parent_id = %d ORDER BY name ASC",
            $parent_id
        ), ARRAY_A);
        
        foreach ($folders as &$folder) {
            $folder['children'] = self::get_folders_tree($folder['id']);
            $folder['file_count'] = self::get_folder_file_count($folder['id']);
            $folder['permissions'] = self::get_folder_permissions($folder['id']);
        }
        
        return $folders;
    }
    
    public static function get_folder($folder_id) {
        global $wpdb;
        $table = self::get_table_name('folders');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $folder_id
        ), ARRAY_A);
    }
    
    public static function create_folder($data) {
        global $wpdb;
        $table = self::get_table_name('folders');
        
        $slug = sanitize_title($data['name']);
        $counter = 1;
        $original_slug = $slug;
        
        // Eindeutigen Slug sicherstellen
        while (self::slug_exists($slug, $data['parent_id'])) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        $folder_data = array(
            'parent_id' => intval($data['parent_id']),
            'name' => sanitize_text_field($data['name']),
            'slug' => $slug,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($table, $folder_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public static function update_folder($folder_id, $data) {
        global $wpdb;
        $table = self::get_table_name('folders');
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            
            // Slug aktualisieren wenn Name geändert
            $slug = sanitize_title($data['name']);
            $current_folder = self::get_folder($folder_id);
            
            if ($current_folder['name'] !== $data['name']) {
                $counter = 1;
                $original_slug = $slug;
                
                while (self::slug_exists($slug, $current_folder['parent_id'], $folder_id)) {
                    $slug = $original_slug . '-' . $counter;
                    $counter++;
                }
                
                $update_data['slug'] = $slug;
            }
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['parent_id'])) {
            $update_data['parent_id'] = intval($data['parent_id']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $folder_id)
        );
    }
    
    public static function delete_folder($folder_id) {
        global $wpdb;
        $folders_table = self::get_table_name('folders');
        $files_table = self::get_table_name('files');
        $permissions_table = self::get_table_name('permissions');
        
        // Unterordner löschen (rekursiv)
        $children = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $folders_table WHERE parent_id = %d",
            $folder_id
        ));
        
        foreach ($children as $child_id) {
            self::delete_folder($child_id);
        }
        
        // Dateien löschen (physische Dateien werden vom File-Handler gelöscht)
        $wpdb->delete($files_table, array('folder_id' => $folder_id));
        
        // Berechtigungen löschen
        $wpdb->delete($permissions_table, array('folder_id' => $folder_id));
        
        // Ordner löschen
        return $wpdb->delete($folders_table, array('id' => $folder_id));
    }
    
    public static function slug_exists($slug, $parent_id, $exclude_id = 0) {
        global $wpdb;
        $table = self::get_table_name('folders');
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE slug = %s AND parent_id = %d",
            $slug,
            $parent_id
        );
        
        if ($exclude_id > 0) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        return $wpdb->get_var($query) > 0;
    }
    
    public static function get_folder_file_count($folder_id) {
        global $wpdb;
        $table = self::get_table_name('files');
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE folder_id = %d AND is_active = 1",
            $folder_id
        ));
    }
    
    public static function get_folder_permissions($folder_id) {
        global $wpdb;
        $table = self::get_table_name('permissions');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE folder_id = %d",
            $folder_id
        ), ARRAY_A);
    }
    
    public static function update_folder_permission($folder_id, $role, $can_upload) {
        global $wpdb;
        $table = self::get_table_name('permissions');
        
        $data = array(
            'folder_id' => $folder_id,
            'role' => sanitize_text_field($role),
            'can_upload' => $can_upload ? 1 : 0,
            'created_by' => get_current_user_id()
        );
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE folder_id = %d AND role = %s",
            $folder_id,
            $role
        ));
        
        if ($existing) {
            return $wpdb->update(
                $table,
                array('can_upload' => $can_upload ? 1 : 0),
                array('id' => $existing)
            );
        } else {
            return $wpdb->insert($table, $data);
        }
    }
    
    public static function user_can_upload_to_folder($user_id, $folder_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Admin kann immer hochladen
        if (in_array('administrator', $user->roles)) {
            return true;
        }
        
        global $wpdb;
        $table = self::get_table_name('permissions');
        
        // WordPress Rollen prüfen
        foreach ($user->roles as $role) {
            $can_upload = $wpdb->get_var($wpdb->prepare(
                "SELECT can_upload FROM $table WHERE folder_id = %d AND role = %s",
                $folder_id,
                $role
            ));
            
            if ($can_upload) {
                return true;
            }
        }
        
        // Benutzerdefinierte Rollen prüfen
        $user_roles_table = self::get_table_name('user_roles');
        $user_custom_roles = $wpdb->get_col($wpdb->prepare(
            "SELECT role_key FROM $user_roles_table WHERE user_id = %d",
            $user_id
        ));
        
        foreach ($user_custom_roles as $role) {
            $can_upload = $wpdb->get_var($wpdb->prepare(
                "SELECT can_upload FROM $table WHERE folder_id = %d AND role = %s",
                $folder_id,
                $role
            ));
            
            if ($can_upload) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function get_all_roles() {
        global $wpdb;
        
        $roles = array();
        
        // WordPress Standard-Rollen
        $wp_roles = wp_roles()->get_names();
        foreach ($wp_roles as $key => $name) {
            $roles[] = array(
                'type' => 'wordpress',
                'key' => $key,
                'name' => $name,
                'description' => __('WordPress Standard Role', 'fl-folder-manager')
            );
        }
        
        // Benutzerdefinierte Rollen
        $table = self::get_table_name('custom_roles');
        $custom_roles = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1", ARRAY_A);
        
        foreach ($custom_roles as $role) {
            $roles[] = array(
                'type' => 'custom',
                'key' => $role['role_key'],
                'name' => $role['role_name'],
                'description' => $role['description']
            );
        }
        
        return $roles;
    }
}