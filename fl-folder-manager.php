<?php
/**
 * Plugin Name: FL-Wordpress Folder Manager
 * Plugin URI: https://loheide.eu/fl-wordpress-folder-manager
 * Description: Ein elegantes Ordner-Management-System mit rollenbasierten Upload-Berechtigungen und schöner Frontend-Darstellung für WordPress.
 * Version: 1.0.0
 * Author: Friederich Loheide
 * Author URI: https://loheide.eu
 * License: GPL v2 or later
 * Text Domain: fl-folder-manager
 * Domain Path: /languages
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Konstanten
define('EFM_VERSION', '1.0.0');
define('EFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EFM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader für Klassen
spl_autoload_register(function ($class) {
    $prefix = 'EFM\\';
    $base_dir = EFM_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Haupt-Plugin-Klasse
class ElegantFolderManager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Aktivierung/Deaktivierung
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialisierung
        add_action('plugins_loaded', array($this, 'init'));
        
        // Admin Hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // Frontend Hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('folder_structure', array($this, 'folder_structure_shortcode'));
    }
    
    public function activate() {
        // Datenbank-Tabellen werden erst bei der Initialisierung erstellt
        // um sicherzustellen, dass WordPress vollständig geladen ist
        // Die eigentliche Tabellenerstellung erfolgt in init() oder bei erster Verwendung
        // Tabellen erstellen, um Fehler bei der ersten Verwendung zu vermeiden
        $this->maybe_create_tables();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function setup_default_roles() {
        // Hier können benutzerdefinierte Rollen angelegt werden
        // z.B.: add_role('mav', 'MAV', array('read' => true));
    }
    
    public function init() {
        // Internationalisierung
        load_plugin_textdomain('fl-folder-manager', false, dirname(EFM_PLUGIN_BASENAME) . '/languages');
        
        // Datenbank-Tabellen erstellen (falls nicht vorhanden)
        $this->maybe_create_tables();
        
        // AJAX Handler
        $this->setup_ajax_handlers();
        
        // Download Handler
        add_action('init', array($this, 'handle_download'));
    }
    
    public function maybe_create_tables() {
        require_once EFM_PLUGIN_DIR . 'includes/class-database.php';
        EFM\Database::create_tables();
    }
    
    public function handle_download() {
        if (isset($_GET['efm_download'])) {
            // Sicherstellen, dass die Klasse geladen ist
            if (class_exists('EFM\\FileUploader')) {
                $file_uploader = new EFM\FileUploader();
                $file_uploader->handle_download();
            }
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Elegant Folder Manager', 'fl-folder-manager'),
            __('Folder Manager', 'fl-folder-manager'),
            'manage_options',
            'fl-folder-manager',
            array($this, 'render_admin_page'),
            'dashicons-category',
            30
        );
        
        add_submenu_page(
            'fl-folder-manager',
            __('Folder Structure', 'fl-folder-manager'),
            __('Folder Structure', 'fl-folder-manager'),
            'manage_options',
            'fl-folder-manager',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'fl-folder-manager',
            __('Permissions', 'fl-folder-manager'),
            __('Permissions', 'fl-folder-manager'),
            'manage_options',
            'fl-folder-manager-permissions',
            array($this, 'render_permissions_page')
        );
        
        add_submenu_page(
            'fl-folder-manager',
            __('Settings', 'fl-folder-manager'),
            __('Settings', 'fl-folder-manager'),
            'manage_options',
            'fl-folder-manager-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function render_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Elegant Folder Manager', 'fl-folder-manager') . '</h1>';
        echo '<div id="efm-admin-app"></div>';
        echo '</div>';
    }
    
    public function render_permissions_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Folder Permissions', 'fl-folder-manager') . '</h1>';
        echo '<div id="efm-permissions-app"></div>';
        echo '</div>';
    }
    
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Settings', 'fl-folder-manager') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('efm_settings');
        do_settings_sections('efm_settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'fl-folder-manager') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style('efm-admin-style', EFM_PLUGIN_URL . 'admin/css/admin-style.css', array(), EFM_VERSION);
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        
        // JS
        wp_enqueue_script('efm-admin-script', EFM_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery', 'wp-util'), EFM_VERSION, true);
        
        // Localize script
        wp_localize_script('efm-admin-script', 'efm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('efm_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this folder?', 'fl-folder-manager'),
                'folder_name' => __('Folder Name', 'fl-folder-manager'),
                'save' => __('Save', 'fl-folder-manager'),
                'cancel' => __('Cancel', 'fl-folder-manager'),
            )
        ));
    }
    
    public function enqueue_frontend_scripts() {
        if (!is_user_logged_in()) {
            return;
        }
        
        // CSS
        wp_enqueue_style('efm-frontend-style', EFM_PLUGIN_URL . 'public/css/public-style.css', array(), EFM_VERSION);
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        
        // JS
        wp_enqueue_script('efm-frontend-script', EFM_PLUGIN_URL . 'public/js/public-script.js', array('jquery'), EFM_VERSION, true);
        
        wp_localize_script('efm-frontend-script', 'efm_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('efm_frontend_nonce'),
            'user_roles' => wp_get_current_user()->roles,
            'strings' => array(
                'loading' => __('Loading...', 'fl-folder-manager'),
                'no_files' => __('No files in this folder', 'fl-folder-manager'),
            )
        ));
    }
    
    public function folder_structure_shortcode($atts) {
        // ShortcodeHandler Klasse verwenden
        $shortcode_handler = new EFM\ShortcodeHandler();
        return $shortcode_handler->render_folder_structure($atts);
    }
    
    private function setup_ajax_handlers() {
        // Admin AJAX
        add_action('wp_ajax_efm_admin_get_folders', array($this, 'ajax_get_folders'));
        add_action('wp_ajax_efm_admin_create_folder', array($this, 'ajax_create_folder'));
        add_action('wp_ajax_efm_admin_update_folder', array($this, 'ajax_update_folder'));
        add_action('wp_ajax_efm_admin_delete_folder', array($this, 'ajax_delete_folder'));
        add_action('wp_ajax_efm_admin_upload_file', array($this, 'ajax_upload_file'));
        
        // Frontend AJAX
        add_action('wp_ajax_efm_get_folder_structure', array($this, 'ajax_get_folder_structure'));
        add_action('wp_ajax_nopriv_efm_get_folder_structure', array($this, 'ajax_require_login'));
        add_action('wp_ajax_efm_get_folder_files', array($this, 'ajax_get_folder_files'));
        add_action('wp_ajax_nopriv_efm_get_folder_files', array($this, 'ajax_require_login'));
    }
    
    public function ajax_require_login() {
        wp_send_json_error(array(
            'message' => __('You must be logged in to access this content.', 'fl-folder-manager')
        ));
    }
    
    // AJAX Methoden
    public function ajax_get_folders() {
        check_ajax_referer('efm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'fl-folder-manager')));
        }
        
        $folder_manager = EFM\FolderManager::get_instance();
        $folders = $folder_manager->get_folder_tree();
        
        wp_send_json_success(array('folders' => $folders));
    }
    
    public function ajax_create_folder() {
        check_ajax_referer('efm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'fl-folder-manager')));
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Folder name is required.', 'fl-folder-manager')));
        }
        
        $folder_manager = EFM\FolderManager::get_instance();
        $result = $folder_manager->create_folder($name, $parent_id, $description);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_update_folder() {
        check_ajax_referer('efm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'fl-folder-manager')));
        }
        
        $folder_id = intval($_POST['folder_id'] ?? 0);
        $data = array();
        
        if (isset($_POST['name'])) {
            $data['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field($_POST['description']);
        }
        if (isset($_POST['parent_id'])) {
            $data['parent_id'] = intval($_POST['parent_id']);
        }
        
        $folder_manager = EFM\FolderManager::get_instance();
        $result = $folder_manager->update_folder($folder_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_delete_folder() {
        check_ajax_referer('efm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'fl-folder-manager')));
        }
        
        $folder_id = intval($_POST['folder_id'] ?? 0);
        
        $folder_manager = EFM\FolderManager::get_instance();
        $result = $folder_manager->delete_folder($folder_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_upload_file() {
        check_ajax_referer('efm_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to upload files.', 'fl-folder-manager')));
        }
        
        $folder_id = intval($_POST['folder_id'] ?? 0);
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'fl-folder-manager')));
        }
        
        $file_uploader = new EFM\FileUploader();
        $result = $file_uploader->upload_file($folder_id, $_FILES['file']);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_folder_structure() {
        check_ajax_referer('efm_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'fl-folder-manager')));
        }
        
        $folder_id = intval($_POST['folder_id'] ?? 0);
        $shortcode_handler = new EFM\ShortcodeHandler();
        
        // Hier müsste eigentlich der komplette HTML-Content zurückgegeben werden
        // Für jetzt geben wir einfach success zurück
        wp_send_json_success(array(
            'message' => __('Folder structure loaded.', 'fl-folder-manager'),
            'folder_id' => $folder_id
        ));
    }
    
    public function ajax_get_folder_files() {
        check_ajax_referer('efm_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'fl-folder-manager')));
        }
        
        $folder_id = intval($_POST['folder_id'] ?? 0);
        $offset = intval($_POST['offset'] ?? 0);
        
        $folder_manager = EFM\FolderManager::get_instance();
        $files = $folder_manager->get_folder_files($folder_id, 20, $offset);
        
        if (empty($files)) {
            wp_send_json_success(array(
                'html' => '',
                'has_more' => false
            ));
        }
        
        // HTML für Dateiliste generieren
        ob_start();
        foreach ($files as $file) {
            $file_uploader = new EFM\FileUploader();
            ?>
            <div class="efm-file-item" data-file-id="<?php echo esc_attr($file['id']); ?>">
                <div class="efm-file-icon">
                    <i class="<?php echo esc_attr($folder_manager->get_file_icon($file['file_type'])); ?>"></i>
                </div>
                
                <div class="efm-file-name" title="<?php echo esc_attr($file['file_name']); ?>">
                    <?php echo esc_html($file['file_name']); ?>
                </div>
                
                <div class="efm-file-size">
                    <?php echo $folder_manager->format_file_size($file['file_size']); ?>
                </div>
                
                <div class="efm-file-date">
                    <?php echo date_i18n(get_option('date_format'), strtotime($file['uploaded_at'])); ?>
                </div>
                
                <div class="efm-file-actions">
                    <button class="efm-file-preview-button" 
                            data-file-id="<?php echo esc_attr($file['id']); ?>"
                            title="<?php esc_attr_e('Preview file', 'fl-folder-manager'); ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                    
                    <a href="<?php echo esc_url($file_uploader->get_file_download_url($file['id'])); ?>" 
                       class="efm-file-download-button"
                       title="<?php esc_attr_e('Download file', 'fl-folder-manager'); ?>"
                       target="_blank">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
            <?php
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => count($files) >= 20
        ));
    }
}

// Plugin initialisieren
function efm_init() {
    return ElegantFolderManager::get_instance();
}
add_action('plugins_loaded', 'efm_init');
