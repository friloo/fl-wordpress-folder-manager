<?php
namespace EFM;

class FolderManager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get_folder_tree($parent_id = 0, $include_files = false) {
        $folders = Database::get_folders_tree($parent_id);
        
        if ($include_files) {
            foreach ($folders as &$folder) {
                $folder['files'] = $this->get_folder_files($folder['id']);
            }
        }
        
        return $folders;
    }
    
    public function get_folder_path($folder_id) {
        $path = array();
        $current_id = $folder_id;
        
        while ($current_id > 0) {
            $folder = Database::get_folder($current_id);
            if (!$folder) {
                break;
            }
            
            array_unshift($path, array(
                'id' => $folder['id'],
                'name' => $folder['name'],
                'slug' => $folder['slug']
            ));
            
            $current_id = $folder['parent_id'];
        }
        
        // Root hinzufügen
        array_unshift($path, array(
            'id' => 0,
            'name' => __('Root', 'fl-folder-manager'),
            'slug' => 'root'
        ));
        
        return $path;
    }
    
    public function get_folder_files($folder_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = Database::get_table_name('files');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE folder_id = %d AND is_active = 1 
             ORDER BY uploaded_at DESC 
             LIMIT %d OFFSET %d",
            $folder_id,
            $limit,
            $offset
        ), ARRAY_A);
    }
    
    public function create_folder($name, $parent_id = 0, $description = '') {
        if (empty($name)) {
            return new \WP_Error('empty_name', __('Folder name cannot be empty.', 'fl-folder-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to create folders.', 'fl-folder-manager'));
        }
        
        $data = array(
            'name' => $name,
            'parent_id' => $parent_id,
            'description' => $description
        );
        
        $folder_id = Database::create_folder($data);
        
        if (!$folder_id) {
            return new \WP_Error('create_failed', __('Failed to create folder.', 'fl-folder-manager'));
        }
        
        return array(
            'id' => $folder_id,
            'name' => $name,
            'parent_id' => $parent_id,
            'message' => __('Folder created successfully.', 'fl-folder-manager')
        );
    }
    
    public function update_folder($folder_id, $data) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to update folders.', 'fl-folder-manager'));
        }
        
        $folder = Database::get_folder($folder_id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'fl-folder-manager'));
        }
        
        $result = Database::update_folder($folder_id, $data);
        
        if ($result === false) {
            return new \WP_Error('update_failed', __('Failed to update folder.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Folder updated successfully.', 'fl-folder-manager')
        );
    }
    
    public function delete_folder($folder_id) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to delete folders.', 'fl-folder-manager'));
        }
        
        $folder = Database::get_folder($folder_id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'fl-folder-manager'));
        }
        
        // Prüfen ob Ordner Dateien enthält
        $file_count = Database::get_folder_file_count($folder_id);
        if ($file_count > 0) {
            return new \WP_Error('has_files', __('Cannot delete folder that contains files.', 'fl-folder-manager'));
        }
        
        $result = Database::delete_folder($folder_id);
        
        if (!$result) {
            return new \WP_Error('delete_failed', __('Failed to delete folder.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Folder deleted successfully.', 'fl-folder-manager')
        );
    }
    
    public function move_folder($folder_id, $new_parent_id) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to move folders.', 'fl-folder-manager'));
        }
        
        $folder = Database::get_folder($folder_id);
        if (!$folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'fl-folder-manager'));
        }
        
        // Prüfen ob neuer Parent existiert (außer Root)
        if ($new_parent_id > 0) {
            $new_parent = Database::get_folder($new_parent_id);
            if (!$new_parent) {
                return new \WP_Error('parent_not_found', __('Parent folder not found.', 'fl-folder-manager'));
            }
        }
        
        // Prüfen auf zirkuläre Referenzen
        if ($this->would_create_circular_reference($folder_id, $new_parent_id)) {
            return new \WP_Error('circular_reference', __('Cannot move folder into its own subfolder.', 'fl-folder-manager'));
        }
        
        $result = Database::update_folder($folder_id, array('parent_id' => $new_parent_id));
        
        if (!$result) {
            return new \WP_Error('move_failed', __('Failed to move folder.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('Folder moved successfully.', 'fl-folder-manager')
        );
    }
    
    private function would_create_circular_reference($folder_id, $new_parent_id) {
        if ($new_parent_id == 0) {
            return false;
        }
        
        // Wenn der neue Parent ein Unterordner des zu verschiebenden Ordners ist
        $children = $this->get_all_child_ids($folder_id);
        return in_array($new_parent_id, $children);
    }
    
    private function get_all_child_ids($parent_id) {
        global $wpdb;
        $table = Database::get_table_name('folders');
        
        $children = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table WHERE parent_id = %d",
            $parent_id
        ));
        
        $all_children = $children;
        
        foreach ($children as $child_id) {
            $all_children = array_merge($all_children, $this->get_all_child_ids($child_id));
        }
        
        return $all_children;
    }
    
    public function search_folders($search_term, $limit = 20) {
        global $wpdb;
        $table = Database::get_table_name('folders');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE name LIKE %s OR description LIKE %s 
             ORDER BY name ASC 
             LIMIT %d",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            $limit
        ), ARRAY_A);
    }
    
    public function get_breadcrumb_html($folder_id) {
        $path = $this->get_folder_path($folder_id);
        $html = '<nav class="efm-breadcrumb" aria-label="' . esc_attr__('Breadcrumb', 'fl-folder-manager') . '">';
        $html .= '<ol>';
        
        foreach ($path as $index => $item) {
            $is_last = ($index === count($path) - 1);
            
            $html .= '<li>';
            
            if (!$is_last) {
                $html .= '<a href="#" data-folder-id="' . esc_attr($item['id']) . '" class="efm-breadcrumb-link">';
                $html .= esc_html($item['name']);
                $html .= '</a>';
                $html .= '<span class="efm-breadcrumb-separator">/</span>';
            } else {
                $html .= '<span class="efm-breadcrumb-current" aria-current="page">';
                $html .= esc_html($item['name']);
                $html .= '</span>';
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }
    
    public function get_folder_stats($folder_id) {
        global $wpdb;
        $files_table = Database::get_table_name('files');
        
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'file_types' => array(),
            'last_upload' => null
        );
        
        // Dateien zählen und Größe berechnen
        $files_data = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as count, SUM(file_size) as total_size, MAX(uploaded_at) as last_upload
             FROM $files_table 
             WHERE folder_id = %d AND is_active = 1",
            $folder_id
        ), ARRAY_A);
        
        if ($files_data) {
            $stats['total_files'] = intval($files_data['count']);
            $stats['total_size'] = intval($files_data['total_size'] ?: 0);
            $stats['last_upload'] = $files_data['last_upload'];
        }
        
        // Dateitypen analysieren
        $file_types = $wpdb->get_results($wpdb->prepare(
            "SELECT file_type, COUNT(*) as count 
             FROM $files_table 
             WHERE folder_id = %d AND is_active = 1 
             GROUP BY file_type 
             ORDER BY count DESC",
            $folder_id
        ), ARRAY_A);
        
        foreach ($file_types as $type) {
            $stats['file_types'][$type['file_type']] = intval($type['count']);
        }
        
        // Unterordner zählen
        $folders_table = Database::get_table_name('folders');
        $subfolders_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $folders_table WHERE parent_id = %d",
            $folder_id
        ));
        
        $stats['subfolders'] = intval($subfolders_count);
        
        return $stats;
    }
    
    public function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }
    
    public function get_file_icon($file_type) {
        $icons = array(
            'pdf' => 'file-pdf',
            'doc' => 'file-word',
            'docx' => 'file-word',
            'xls' => 'file-excel',
            'xlsx' => 'file-excel',
            'ppt' => 'file-powerpoint',
            'pptx' => 'file-powerpoint',
            'jpg' => 'file-image',
            'jpeg' => 'file-image',
            'png' => 'file-image',
            'gif' => 'file-image',
            'zip' => 'file-archive',
            'rar' => 'file-archive',
            'txt' => 'file-alt',
            'csv' => 'file-csv',
        );
        
        $extension = strtolower($file_type);
        
        if (isset($icons[$extension])) {
            return 'fas fa-' . $icons[$extension];
        }
        
        return 'fas fa-file';
    }
}