<?php
namespace EFM;

class FileUploader {
    
    private $allowed_mime_types = array(
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        
        // Documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        
        // Text
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        
        // Archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        
        // Other
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
    );
    
    private $max_file_size = 10485760; // 10MB
    
    public function __construct() {
        // Max File Size aus WordPress Settings lesen
        $this->max_file_size = wp_max_upload_size();
    }
    
    public function upload_file($folder_id, $file, $user_id = null) {
        // Sicherheitschecks
        if (!$this->validate_upload_permission($folder_id, $user_id)) {
            return new \WP_Error('permission_denied', __('You do not have permission to upload to this folder.', 'fl-folder-manager'));
        }
        
        if (!$this->validate_file($file)) {
            return new \WP_Error('invalid_file', __('Invalid file or file type not allowed.', 'fl-folder-manager'));
        }
        
        // Upload-Verzeichnis vorbereiten
        $upload_dir = $this->get_upload_directory($folder_id);
        if (is_wp_error($upload_dir)) {
            return $upload_dir;
        }
        
        // Dateiname sicher machen
        $filename = $this->sanitize_filename($file['name']);
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // Prüfen ob Datei bereits existiert
        $counter = 1;
        $original_filename = $filename;
        $pathinfo = pathinfo($filename);
        
        while (file_exists($filepath)) {
            $filename = $pathinfo['filename'] . '-' . $counter . '.' . $pathinfo['extension'];
            $filepath = $upload_dir['path'] . '/' . $filename;
            $counter++;
        }
        
        // Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new \WP_Error('move_failed', __('Failed to move uploaded file.', 'fl-folder-manager'));
        }
        
        // Datei-Informationen sammeln
        $file_info = array(
            'folder_id' => $folder_id,
            'file_name' => $filename,
            'file_path' => $upload_dir['url_path'] . '/' . $filename,
            'file_size' => filesize($filepath),
            'file_type' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            'mime_type' => $file['type'],
            'uploaded_by' => $user_id ?: get_current_user_id(),
        );
        
        // In Datenbank speichern
        $file_id = $this->save_file_to_database($file_info);
        
        if (!$file_id) {
            // Datei löschen wenn DB-Eintrag fehlschlägt
            unlink($filepath);
            return new \WP_Error('db_error', __('Failed to save file information to database.', 'fl-folder-manager'));
        }
        
        // Thumbnail für Bilder erstellen
        if ($this->is_image($file_info['file_type'])) {
            $this->create_thumbnail($filepath, $filename, $upload_dir['path']);
        }
        
        return array(
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $filename,
            'file_url' => $upload_dir['url'] . '/' . $filename,
            'file_size' => $file_info['file_size'],
            'file_type' => $file_info['file_type'],
            'message' => __('File uploaded successfully.', 'fl-folder-manager')
        );
    }
    
    private function validate_upload_permission($folder_id, $user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        
        // Admin kann immer hochladen
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        return Database::user_can_upload_to_folder($user_id, $folder_id);
    }
    
    private function validate_file($file) {
        // Grundlegende Validierung
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Dateigröße prüfen
        if ($file['size'] > $this->max_file_size) {
            return false;
        }
        
        // Dateityp prüfen
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!array_key_exists($extension, $this->allowed_mime_types)) {
            return false;
        }
        
        // MIME-Type prüfen
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type !== $this->allowed_mime_types[$extension]) {
            return false;
        }
        
        // Sicherheitscheck für Bilder
        if ($this->is_image($extension)) {
            $image_info = getimagesize($file['tmp_name']);
            if (!$image_info) {
                return false;
            }
        }
        
        return true;
    }
    
    private function get_upload_directory($folder_id) {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/fl-wordpress-folder-manager';
        $base_url = $upload_dir['baseurl'] . '/fl-wordpress-folder-manager';
        
        // Ordner-Struktur erstellen: /fl-wordpress-folder-manager/{folder_id}/
        $folder_dir = $base_dir . '/' . $folder_id;
        $folder_url = $base_url . '/' . $folder_id;
        
        // Verzeichnis erstellen wenn nicht existiert
        if (!file_exists($folder_dir)) {
            if (!wp_mkdir_p($folder_dir)) {
                return new \WP_Error('directory_error', __('Failed to create upload directory.', 'fl-folder-manager'));
            }
            
            // .htaccess für Sicherheit
            $this->create_htaccess($folder_dir);
        }
        
        return array(
            'path' => $folder_dir,
            'url' => $folder_url,
            'url_path' => '/fl-wordpress-folder-manager/' . $folder_id
        );
    }
    
    private function create_htaccess($directory) {
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "Deny from all\n";
        $htaccess_content .= "<FilesMatch '\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|csv)$'>\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        
        file_put_contents($directory . '/.htaccess', $htaccess_content);
    }
    
    private function sanitize_filename($filename) {
        $filename = sanitize_file_name($filename);
        
        // Umlaute ersetzen
        $replacements = array(
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue'
        );
        
        $filename = str_replace(array_keys($replacements), array_values($replacements), $filename);
        
        // Sonderzeichen entfernen
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        return $filename;
    }
    
    private function save_file_to_database($file_info) {
        global $wpdb;
        $table = Database::get_table_name('files');
        
        $result = $wpdb->insert($table, $file_info);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    private function is_image($extension) {
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        return in_array($extension, $image_extensions);
    }
    
    private function create_thumbnail($filepath, $filename, $directory) {
        $image_editor = wp_get_image_editor($filepath);
        
        if (!is_wp_error($image_editor)) {
            // Thumbnail Größe
            $image_editor->resize(150, 150, true);
            $thumbnail_filename = 'thumb_' . $filename;
            $thumbnail_path = $directory . '/' . $thumbnail_filename;
            $image_editor->save($thumbnail_path);
        }
    }
    
    public function delete_file($file_id, $user_id = null) {
        global $wpdb;
        $table = Database::get_table_name('files');
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $file_id
        ), ARRAY_A);
        
        if (!$file) {
            return new \WP_Error('not_found', __('File not found.', 'fl-folder-manager'));
        }
        
        $user_id = $user_id ?: get_current_user_id();
        
        // Berechtigung prüfen: Nur Uploader oder Admin kann löschen
        if ($file['uploaded_by'] != $user_id && !user_can($user_id, 'manage_options')) {
            return new \WP_Error('permission_denied', __('You do not have permission to delete this file.', 'fl-folder-manager'));
        }
        
        // Datei löschen
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . $file['file_path'];
        
        if (file_exists($file_path)) {
            unlink($file_path);
            
            // Thumbnail löschen falls vorhanden
            $thumbnail_path = dirname($file_path) . '/thumb_' . basename($file_path);
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        }
        
        // Datenbank-Eintrag löschen (soft delete)
        $result = $wpdb->update(
            $table,
            array('is_active' => 0),
            array('id' => $file_id)
        );
        
        if (!$result) {
            return new \WP_Error('delete_failed', __('Failed to delete file from database.', 'fl-folder-manager'));
        }
        
        return array(
            'success' => true,
            'message' => __('File deleted successfully.', 'fl-folder-manager')
        );
    }
    
    public function get_file($file_id) {
        global $wpdb;
        $table = Database::get_table_name('files');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND is_active = 1",
            $file_id
        ), ARRAY_A);
    }
    
    public function increment_download_count($file_id) {
        global $wpdb;
        $table = Database::get_table_name('files');
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET download_count = download_count + 1 WHERE id = %d",
            $file_id
        ));
    }
    
    public function get_allowed_file_types() {
        return array_keys($this->allowed_mime_types);
    }
    
    public function get_max_file_size() {
        return $this->max_file_size;
    }
    
    public function get_max_file_size_formatted() {
        $size = $this->max_file_size;
        
        if ($size >= 1073741824) {
            return number_format($size / 1073741824, 1) . ' GB';
        } elseif ($size >= 1048576) {
            return number_format($size / 1048576, 1) . ' MB';
        } elseif ($size >= 1024) {
            return number_format($size / 1024, 1) . ' KB';
        } else {
            return $size . ' bytes';
        }
    }
    
    public function get_file_url($file_id) {
        $file = $this->get_file($file_id);
        
        if (!$file) {
            return '';
        }
        
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . $file['file_path'];
    }
    
    public function get_file_download_url($file_id) {
        return add_query_arg(array(
            'efm_download' => $file_id,
            'nonce' => wp_create_nonce('efm_download_' . $file_id)
        ), home_url());
    }
}
    /**
     * Get file download URL
     */
    public function get_file_download_url($file_id) {
        return add_query_arg(array(
            'efm_download' => $file_id,
            'nonce' => wp_create_nonce('efm_download_' . $file_id)
        ), home_url('/'));
    }
    
    /**
     * Get file icon class based on file type
     */
    public function get_file_icon($file_type) {
        $icons = array(
            'image' => 'fas fa-file-image',
            'pdf' => 'fas fa-file-pdf',
            'word' => 'fas fa-file-word',
            'excel' => 'fas fa-file-excel',
            'powerpoint' => 'fas fa-file-powerpoint',
            'archive' => 'fas fa-file-archive',
            'audio' => 'fas fa-file-audio',
            'video' => 'fas fa-file-video',
            'text' => 'fas fa-file-alt',
            'default' => 'fas fa-file'
        );
        
        if (strpos($file_type, 'image/') === 0) {
            return $icons['image'];
        } elseif ($file_type === 'application/pdf') {
            return $icons['pdf'];
        } elseif (strpos($file_type, 'word') !== false) {
            return $icons['word'];
        } elseif (strpos($file_type, 'excel') !== false || strpos($file_type, 'spreadsheet') !== false) {
            return $icons['excel'];
        } elseif (strpos($file_type, 'powerpoint') !== false || strpos($file_type, 'presentation') !== false) {
            return $icons['powerpoint'];
        } elseif (strpos($file_type, 'zip') !== false || strpos($file_type, 'rar') !== false || strpos($file_type, 'compressed') !== false) {
            return $icons['archive'];
        } elseif (strpos($file_type, 'audio/') === 0) {
            return $icons['audio'];
        } elseif (strpos($file_type, 'video/') === 0) {
            return $icons['video'];
        } elseif (strpos($file_type, 'text/') === 0) {
            return $icons['text'];
        }
        
        return $icons['default'];
    }
    
    /**
     * Handle file download
     */
    public function handle_download() {
        if (!isset($_GET['efm_download']) || !isset($_GET['nonce'])) {
            return;
        }
        
        $file_id = intval($_GET['efm_download']);
        $nonce = sanitize_text_field($_GET['nonce']);
        
        // Nonce verifizieren
        if (!wp_verify_nonce($nonce, 'efm_download_' . $file_id)) {
            wp_die(__('Invalid download link.', 'fl-folder-manager'));
        }
        
        // File info aus Datenbank holen
        global $wpdb;
        $table_files = $wpdb->prefix . 'efm_files';
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_files} WHERE id = %d",
            $file_id
        ), ARRAY_A);
        
        if (!$file) {
            wp_die(__('File not found.', 'fl-folder-manager'));
        }
        
        // File path
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/efm-uploads/' . $file['file_path'];
        
        if (!file_exists($file_path)) {
            wp_die(__('File not found on server.', 'fl-folder-manager'));
        }
        
        // Download counter erhöhen
        $wpdb->update(
            $table_files,
            array('download_count' => $file['download_count'] + 1),
            array('id' => $file_id)
        );
        
        // File downloaden
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $file['file_type']);
        header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
    
    /**
     * Get file preview URL
     */
    public function get_file_preview_url($file_id) {
        $file = $this->get_file_info($file_id);
        if (!$file) {
            return '';
        }
        
        $upload_dir = wp_upload_dir();
        $file_url = $upload_dir['baseurl'] . '/efm-uploads/' . $file['file_path'];
        
        // Für Bilder: direkte URL
        if (strpos($file['file_type'], 'image/') === 0) {
            return $file_url;
        }
        
        // Für PDF: Google Docs Viewer
        if ($file['file_type'] === 'application/pdf') {
            return 'https://docs.google.com/viewer?url=' . urlencode($file_url) . '&embedded=true';
        }
        
        // Für andere Dateien: Download URL
        return $this->get_file_download_url($file_id);
    }
    
    /**
     * Get file info
     */
    private function get_file_info($file_id) {
        global $wpdb;
        $table_files = $wpdb->prefix . 'efm_files';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_files} WHERE id = %d",
            $file_id
        ), ARRAY_A);
    }
