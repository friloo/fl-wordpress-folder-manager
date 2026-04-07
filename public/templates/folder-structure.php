<?php
/**
 * Template for folder structure display
 * 
 * @var array $folders
 * @var array $files
 * @var int $current_folder_id
 * @var array $breadcrumb
 * @var bool $show_files
 * @var array $stats
 * @var EFM\FolderManager $folder_manager
 * @var EFM\PermissionHandler $permission_handler
 * @var EFM\FileUploader $file_uploader
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user_id = get_current_user_id();
?>

<div class="efm-frontend-container" 
     data-root="<?php echo esc_attr($current_folder_id); ?>"
     data-show-files="<?php echo $show_files ? 'true' : 'false'; ?>"
     data-user-id="<?php echo esc_attr($current_user_id); ?>">
    
    <!-- Breadcrumb -->
    <?php if (!empty($breadcrumb)): ?>
    <div class="efm-breadcrumb-container">
        <nav class="efm-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <?php foreach ($breadcrumb as $index => $item): ?>
                    <li>
                        <?php if ($index < count($breadcrumb) - 1): ?>
                            <a href="#" class="efm-breadcrumb-link" data-folder-id="<?php echo esc_attr($item['id']); ?>">
                                <?php echo esc_html($item['name']); ?>
                            </a>
                            <span class="efm-breadcrumb-separator">/</span>
                        <?php else: ?>
                            <span class="efm-breadcrumb-current">
                                <?php echo esc_html($item['name']); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
    <?php endif; ?>
    
    <!-- Search -->
    <div class="efm-search-container">
        <div class="efm-search-box">
            <i class="fas fa-search"></i>
            <input type="text" 
                   class="efm-search-input" 
                   placeholder="<?php esc_attr_e('Search folders and files...', 'fl-folder-manager'); ?>"
                   aria-label="<?php esc_attr_e('Search', 'fl-folder-manager'); ?>">
            <button class="efm-search-clear" style="display: none;" aria-label="<?php esc_attr_e('Clear search', 'fl-folder-manager'); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- Stats -->
    <?php if (!empty($stats)): ?>
    <div class="efm-stats-container">
        <div class="efm-stats-box">
            <div class="efm-stat-item">
                <div class="efm-stat-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="efm-stat-info">
                    <div class="efm-stat-value"><?php echo esc_html($stats['folder_count']); ?></div>
                    <div class="efm-stat-label"><?php esc_html_e('Folders', 'fl-folder-manager'); ?></div>
                </div>
            </div>
            
            <div class="efm-stat-item">
                <div class="efm-stat-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="efm-stat-info">
                    <div class="efm-stat-value"><?php echo esc_html($stats['file_count']); ?></div>
                    <div class="efm-stat-label"><?php esc_html_e('Files', 'fl-folder-manager'); ?></div>
                </div>
            </div>
            
            <div class="efm-stat-item">
                <div class="efm-stat-icon">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="efm-stat-info">
                    <div class="efm-stat-value"><?php echo esc_html($stats['total_size']); ?></div>
                    <div class="efm-stat-label"><?php esc_html_e('Total Size', 'fl-folder-manager'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="efm-content-container">
        
        <!-- Folders Section -->
        <div class="efm-folders-section">
            <div class="efm-section-title">
                <i class="fas fa-folder"></i>
                <span><?php esc_html_e('Folders', 'fl-folder-manager'); ?></span>
                <?php if (!empty($folders)): ?>
                <span class="efm-folder-count"><?php echo count($folders); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($folders)): ?>
                <div class="efm-empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p><?php esc_html_e('No folders found.', 'fl-folder-manager'); ?></p>
                </div>
            <?php else: ?>
                <div class="efm-folders-grid">
                    <?php foreach ($folders as $folder): ?>
                        <?php 
                        $can_upload = $permission_handler->user_can_upload_to_folder($current_user_id, $folder['id']);
                        ?>
                        <div class="efm-folder-item" data-folder-id="<?php echo esc_attr($folder['id']); ?>">
                            <div class="efm-folder-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            
                            <div class="efm-folder-info">
                                <h3 class="efm-folder-name">
                                    <?php echo esc_html($folder['name']); ?>
                                    <?php if ($folder['file_count'] > 0): ?>
                                        <span class="efm-file-count-badge"><?php echo esc_html($folder['file_count']); ?></span>
                                    <?php endif; ?>
                                </h3>
                                
                                <?php if (!empty($folder['description'])): ?>
                                    <p class="efm-folder-description">
                                        <?php echo esc_html($folder['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="efm-folder-meta">
                                    <div class="efm-folder-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date_i18n(get_option('date_format'), strtotime($folder['created_at'])); ?></span>
                                    </div>
                                    <?php if ($folder['file_count'] > 0): ?>
                                    <div class="efm-folder-meta-item">
                                        <i class="fas fa-file"></i>
                                        <span><?php echo esc_html($folder['file_count']); ?> <?php esc_html_e('files', 'fl-folder-manager'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="efm-folder-actions">
                                <button class="efm-folder-actions-button efm-view-button" 
                                        data-folder-id="<?php echo esc_attr($folder['id']); ?>"
                                        title="<?php esc_attr_e('View folder', 'fl-folder-manager'); ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($can_upload): ?>
                                <button class="efm-folder-actions-button efm-upload-button" 
                                        data-folder-id="<?php echo esc_attr($folder['id']); ?>"
                                        title="<?php esc_attr_e('Upload files', 'fl-folder-manager'); ?>">
                                    <i class="fas fa-upload"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Files Section -->
        <?php if ($show_files): ?>
        <div class="efm-files-section">
            <div class="efm-section-title">
                <i class="fas fa-file"></i>
                <span><?php esc_html_e('Files', 'fl-folder-manager'); ?></span>
                <?php if (!empty($files)): ?>
                <span class="efm-folder-count"><?php echo count($files); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($files)): ?>
                <div class="efm-empty-state">
                    <i class="fas fa-file"></i>
                    <p><?php esc_html_e('No files in this folder.', 'fl-folder-manager'); ?></p>
                </div>
            <?php else: ?>
                <div class="efm-files-list-container">
                    <div class="efm-files-list">
                        <div class="efm-files-header">
                            <div><?php esc_html_e('Name', 'fl-folder-manager'); ?></div>
                            <div><?php esc_html_e('Size', 'fl-folder-manager'); ?></div>
                            <div><?php esc_html_e('Date', 'fl-folder-manager'); ?></div>
                            <div><?php esc_html_e('Actions', 'fl-folder-manager'); ?></div>
                        </div>
                        
                        <div class="efm-files-body">
                            <?php foreach ($files as $file): ?>
                                <div class="efm-file-item" data-file-id="<?php echo esc_attr($file['id']); ?>">
                                    <div class="efm-file-icon">
                                        <i class="<?php echo esc_attr($file_uploader->get_file_icon($file['file_type'])); ?>"></i>
                                        <div class="efm-file-name" title="<?php echo esc_attr($file['file_name']); ?>">
                                            <?php echo esc_html($file['file_name']); ?>
                                        </div>
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
                                                data-preview-url="<?php echo esc_url($file_uploader->get_file_preview_url($file['id'])); ?>"
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
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (count($files) >= 20): ?>
                <div class="efm-load-more-container">
                    <button class="efm-load-more-button" 
                            data-folder-id="<?php echo esc_attr($current_folder_id); ?>"
                            data-offset="20">
                        <i class="fas fa-plus"></i>
                        <?php esc_html_e('Load More Files', 'fl-folder-manager'); ?>
                    </button>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="efm-upload-modal" style="display: none;">
    <div class="efm-upload-modal-content">
        <div class="efm-upload-modal-header">
            <h3><?php esc_html_e('Upload Files', 'fl-folder-manager'); ?></h3>
            <button class="efm-upload-modal-close" aria-label="<?php esc_attr_e('Close', 'fl-folder-manager'); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="efm-upload-modal-body">
            <div class="efm-upload-dropzone">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><?php esc_html_e('Drag & drop files here or click to browse', 'fl-folder-manager'); ?></p>
                <button class="efm-browse-button">
                    <i class="fas fa-folder-open"></i>
                    <?php esc_html_e('Browse Files', 'fl-folder-manager'); ?>
                </button>
                <input type="file" class="efm-file-input" multiple style="display: none;">
            </div>
            
            <div class="efm-upload-queue" style="display: none;">
                <!-- Upload queue will be populated by JavaScript -->
            </div>
            
            <div class="efm-upload-progress" style="display: none;">
                <div class="efm-progress-bar">
                    <div class="efm-progress-fill" style="width: 0%"></div>
                </div>
                <div class="efm-progress-text">0%</div>
            </div>
            
            <div class="efm-upload-info">
                <i class="fas fa-info-circle"></i>
                <?php esc_html_e('Maximum file size: 10MB. Allowed file types: Images, PDF, Documents.', 'fl-folder-manager'); ?>
            </div>
        </div>
        
        <div class="efm-upload-modal-footer">
            <button class="efm-upload-cancel-button">
                <?php esc_html_e('Cancel', 'fl-folder-manager'); ?>
            </button>
            <button class="efm-upload-start-button" disabled>
                <i class="fas fa-upload"></i>
                <?php esc_html_e('Start Upload', 'fl-folder-manager'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="efm-preview-modal" style="display: none;">
    <div class="efm-preview-modal-content">
        <div class="efm-preview-modal-header">
            <h3 class="efm-preview-title"><?php esc_html_e('File Preview', 'fl-folder-manager'); ?></h3>
            <button class="efm-preview-modal-close" aria-label="<?php esc_attr_e('Close', 'fl-folder-manager'); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="efm-preview-modal-body">
            <div class="efm-preview-content">
                <!-- Preview content will be loaded by JavaScript -->
            </div>
            
            <div class="efm-preview-info">
                <div class="efm-preview-info-item">
                    <span class="efm-info-label"><?php esc_html_e('File Size:', 'fl-folder-manager'); ?></span>
                    <span class="efm-info-value efm-file-size">-</span>
                </div>
                <div class="efm-preview-info-item">
                    <span class="efm-info-label"><?php esc_html_e('File Type:', 'fl-folder-manager'); ?></span>
                    <span class="efm-info-value efm-file-type">-</span>
                </div>
                <div class="efm-preview-info-item">
                    <span class="efm-info-label"><?php esc_html_e('Upload Date:', 'fl-folder-manager'); ?></span>
                    <span class="efm-info-value efm-upload-date">-</span>
                </div>
                <div class="efm-preview-info-item">
                    <span class="efm-info-label"><?php esc_html_e('Downloads:', 'fl-folder-manager'); ?></span>
                    <span class="efm-info-value efm-download-count">0</span>
                </div>
            </div>
        </div>
        
        <div class="efm-preview-modal-footer">
            <button class="efm-preview-close-button">
                <?php esc_html_e('Close', 'fl-folder-manager'); ?>
            </button>
            <a href="#" class="efm-download-button" target="_blank">
                <i class="fas fa-download"></i>
                <?php esc_html_e('Download', 'fl-folder-manager'); ?>
            </a>
        </div>
    </div>
</div>