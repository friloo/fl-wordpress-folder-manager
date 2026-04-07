<?php
/**
 * Admin Page: Folder Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sicherheitscheck
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'fl-folder-manager'));
}

// Nonce für AJAX
$nonce = wp_create_nonce('efm_admin_nonce');
?>

<div class="wrap">
    <h1><?php esc_html_e('Elegant Folder Manager', 'fl-folder-manager'); ?></h1>
    
    <!-- Tabs Navigation -->
    <div class="efm-admin-tabs">
        <button class="efm-admin-tab active" data-target="#efm-folders">
            <i class="fas fa-folder"></i>
            <?php esc_html_e('Folders', 'fl-folder-manager'); ?>
        </button>
        <button class="efm-admin-tab" data-target="#efm-permissions">
            <i class="fas fa-user-shield"></i>
            <?php esc_html_e('Permissions', 'fl-folder-manager'); ?>
        </button>
        <button class="efm-admin-tab" data-target="#efm-settings">
            <i class="fas fa-cog"></i>
            <?php esc_html_e('Settings', 'fl-folder-manager'); ?>
        </button>
    </div>
    
    <!-- Messages Container -->
    <div id="efm-admin-messages"></div>
    
    <!-- Folders Tab -->
    <div id="efm-folders" class="efm-admin-content">
        <div class="efm-admin-header">
            <h2><?php esc_html_e('Folder Management', 'fl-folder-manager'); ?></h2>
            <p><?php esc_html_e('Create and organize folders in a tree structure. Drag and drop to reorder.', 'fl-folder-manager'); ?></p>
        </div>
        
        <div class="efm-admin-actions">
            <button class="efm-admin-button success efm-create-folder-btn">
                <i class="fas fa-plus"></i>
                <?php esc_html_e('Create New Folder', 'fl-folder-manager'); ?>
            </button>
        </div>
        
        <!-- Folder Forms Container -->
        <div id="efm-folder-forms" style="display: none;"></div>
        
        <!-- Folder Tree -->
        <div class="efm-admin-section">
            <h3><?php esc_html_e('Folder Structure', 'fl-folder-manager'); ?></h3>
            <div id="efm-folder-tree" class="efm-folder-tree">
                <div class="efm-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <?php esc_html_e('Loading folders...', 'fl-folder-manager'); ?>
                </div>
            </div>
        </div>
        
        <!-- Drag & Drop Hint -->
        <div class="efm-drag-hint">
            <i class="fas fa-arrows-alt"></i>
            <h3><?php esc_html_e('Drag & Drop Reordering', 'fl-folder-manager'); ?></h3>
            <p><?php esc_html_e('Drag folders to reorder them. Drop a folder onto another folder to make it a subfolder.', 'fl-folder-manager'); ?></p>
        </div>
    </div>
    
    <!-- Permissions Tab -->
    <div id="efm-permissions" class="efm-admin-content" style="display: none;">
        <div class="efm-admin-header">
            <h2><?php esc_html_e('Upload Permissions', 'fl-folder-manager'); ?></h2>
            <p><?php esc_html_e('Configure which user roles can upload files to which folders.', 'fl-folder-manager'); ?></p>
        </div>
        
        <div id="efm-permissions-content">
            <div class="efm-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <?php esc_html_e('Loading permissions...', 'fl-folder-manager'); ?>
            </div>
        </div>
    </div>
    
    <!-- Settings Tab -->
    <div id="efm-settings" class="efm-admin-content" style="display: none;">
        <div class="efm-admin-header">
            <h2><?php esc_html_e('Plugin Settings', 'fl-folder-manager'); ?></h2>
            <p><?php esc_html_e('Configure general plugin settings and behavior.', 'fl-folder-manager'); ?></p>
        </div>
        
        <div class="efm-admin-form">
            <h3><?php esc_html_e('Upload Settings', 'fl-folder-manager'); ?></h3>
            
            <div class="efm-form-group">
                <label class="efm-form-label">
                    <?php esc_html_e('Maximum File Size', 'fl-folder-manager'); ?>
                </label>
                <select class="efm-form-select" id="efm-max-file-size">
                    <option value="1048576">1 MB</option>
                    <option value="5242880">5 MB</option>
                    <option value="10485760" selected>10 MB</option>
                    <option value="20971520">20 MB</option>
                    <option value="52428800">50 MB</option>
                </select>
                <p class="description">
                    <?php esc_html_e('Maximum size for uploaded files. Note: This may be limited by your server configuration.', 'fl-folder-manager'); ?>
                </p>
            </div>
            
            <div class="efm-form-group">
                <label class="efm-form-label">
                    <?php esc_html_e('Allowed File Types', 'fl-folder-manager'); ?>
                </label>
                <div class="efm-checkbox-group">
                    <label>
                        <input type="checkbox" name="allowed_types[]" value="image" checked>
                        <?php esc_html_e('Images (JPG, PNG, GIF, SVG, WebP)', 'fl-folder-manager'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="allowed_types[]" value="pdf" checked>
                        <?php esc_html_e('PDF Documents', 'fl-folder-manager'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="allowed_types[]" value="office" checked>
                        <?php esc_html_e('Office Documents (Word, Excel, PowerPoint)', 'fl-folder-manager'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="allowed_types[]" value="text" checked>
                        <?php esc_html_e('Text Files (TXT, CSV)', 'fl-folder-manager'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="allowed_types[]" value="archive">
                        <?php esc_html_e('Archives (ZIP, RAR, 7Z)', 'fl-folder-manager'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="allowed_types[]" value="media">
                        <?php esc_html_e('Media Files (MP3, MP4, MOV, AVI)', 'fl-folder-manager'); ?>
                    </label>
                </div>
            </div>
            
            <div class="efm-form-actions">
                <button type="button" class="efm-admin-button success" id="efm-save-settings">
                    <i class="fas fa-save"></i>
                    <?php esc_html_e('Save Settings', 'fl-folder-manager'); ?>
                </button>
            </div>
        </div>
        
        <div class="efm-admin-form">
            <h3><?php esc_html_e('Frontend Settings', 'fl-folder-manager'); ?></h3>
            
            <div class="efm-form-group">
                <label class="efm-form-label">
                    <?php esc_html_e('Default Shortcode Parameters', 'fl-folder-manager'); ?>
                </label>
                <div class="efm-shortcode-preview">
                    <code>[folder_structure root="0" depth="0" show_files="true"]</code>
                </div>
                <p class="description">
                    <?php esc_html_e('These are the default parameters used when the shortcode is called without attributes.', 'fl-folder-manager'); ?>
                </p>
            </div>
            
            <div class="efm-form-group">
                <label class="efm-form-label">
                    <?php esc_html_e('Files Per Page', 'fl-folder-manager'); ?>
                </label>
                <input type="number" class="efm-form-input" id="efm-files-per-page" value="20" min="5" max="100">
                <p class="description">
                    <?php esc_html_e('Number of files to show per page in the file list.', 'fl-folder-manager'); ?>
                </p>
            </div>
        </div>
        
        <div class="efm-admin-form">
            <h3><?php esc_html_e('System Information', 'fl-folder-manager'); ?></h3>
            
            <table class="widefat">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Plugin Version', 'fl-folder-manager'); ?></th>
                        <td><?php echo esc_html(EFM_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('WordPress Version', 'fl-folder-manager'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('PHP Version', 'fl-folder-manager'); ?></th>
                        <td><?php echo esc_html(phpversion()); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Upload Directory', 'fl-folder-manager'); ?></th>
                        <td>
                            <?php
                            $upload_dir = wp_upload_dir();
                            echo esc_html($upload_dir['basedir'] . '/efm-uploads/');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Database Tables', 'fl-folder-manager'); ?></th>
                        <td>
                            <?php
                            global $wpdb;
                            $tables = array(
                                $wpdb->prefix . 'efm_folders',
                                $wpdb->prefix . 'efm_files',
                                $wpdb->prefix . 'efm_permissions',
                                $wpdb->prefix . 'efm_custom_roles'
                            );
                            
                            $existing_tables = array();
                            foreach ($tables as $table) {
                                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                                    $existing_tables[] = $table;
                                }
                            }
                            
                            echo esc_html(count($existing_tables) . ' of ' . count($tables) . ' tables exist');
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="text/javascript">
// Admin JavaScript Daten
var efm_admin = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo esc_js($nonce); ?>',
    strings: {
        loading: '<?php esc_html_e('Loading...', 'fl-folder-manager'); ?>',
        saving: '<?php esc_html_e('Saving...', 'fl-folder-manager'); ?>',
        confirm_delete: '<?php esc_html_e('Are you sure you want to delete this folder?', 'fl-folder-manager'); ?>',
        success: '<?php esc_html_e('Success!', 'fl-folder-manager'); ?>',
        error: '<?php esc_html_e('Error!', 'fl-folder-manager'); ?>'
    }
};
</script>
