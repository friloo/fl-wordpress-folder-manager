<?php
namespace EFM;

class ShortcodeHandler {
    
    private $folder_manager;
    private $permission_handler;
    private $file_uploader;
    
    public function __construct() {
        $this->folder_manager = FolderManager::get_instance();
        $this->permission_handler = new PermissionHandler();
        $this->file_uploader = new FileUploader();
    }
    
    public function render_folder_structure($atts) {
        // Nur für eingeloggte User
        if (!is_user_logged_in()) {
            return $this->render_login_message();
        }
        
        $atts = shortcode_atts(array(
            'root' => '0',
            'depth' => '0',
            'show_files' => 'true',
            'show_breadcrumb' => 'true',
            'show_search' => 'true',
            'show_stats' => 'false',
        ), $atts, 'folder_structure');
        
        // Daten für Template sammeln
        $current_folder_id = intval($atts['root']);
        $show_files = $atts['show_files'] === 'true';
        
        // Ordnerstruktur holen
        $folders = $this->folder_manager->get_folder_tree($current_folder_id, $show_files);
        
        // Dateien holen (nur wenn angezeigt werden sollen)
        $files = array();
        if ($show_files) {
            $files = $this->folder_manager->get_folder_files($current_folder_id, 20, 0);
        }
        
        // Breadcrumb
        $breadcrumb = $this->folder_manager->get_breadcrumb($current_folder_id);
        
        // Statistiken
        $stats = array(
            'folder_count' => count($folders),
            'file_count' => count($files),
            'total_size' => $this->folder_manager->get_folder_total_size($current_folder_id)
        );
        
        // Template laden
        return $this->load_template('folder-structure', array(
            'folders' => $folders,
            'files' => $files,
            'current_folder_id' => $current_folder_id,
            'breadcrumb' => $breadcrumb,
            'show_files' => $show_files,
            'stats' => $stats,
            'folder_manager' => $this->folder_manager,
            'permission_handler' => $this->permission_handler,
            'file_uploader' => $this->file_uploader
        ));
    }
    
    private function load_template($template_name, $args = array()) {
        if (!empty($args)) {
            extract($args);
        }
        
        $template_path = EFM_PLUGIN_DIR . 'public/templates/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback: Einfache Meldung
        return '<div class="efm-error">Template not found: ' . esc_html($template_name) . '</div>';
    }
    
    private function render_login_message() {
        ob_start();
        ?>
        <div class="efm-login-required">
            <div class="efm-login-message">
                <i class="fas fa-lock"></i>
                <h3><?php esc_html_e('Login Required', 'fl-folder-manager'); ?></h3>
                <p><?php esc_html_e('Please log in to view the folder structure.', 'fl-folder-manager'); ?></p>
                <?php if (function_exists('wp_login_url')): ?>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="efm-login-button">
                    <?php esc_html_e('Log In', 'fl-folder-manager'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
