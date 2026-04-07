/**
 * Elegant Folder Manager - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    const EFM = {
        
        // Initialisierung
        init: function() {
            this.bindEvents();
            this.setupDragAndDrop();
            this.checkInitialState();
        },
        
        // Event Handler binden
        bindEvents: function() {
            // Breadcrumb Navigation
            $(document).on('click', '.efm-breadcrumb-link', this.handleBreadcrumbClick.bind(this));
            
            // Folder Actions
            $(document).on('click', '.efm-view-button', this.handleViewFolder.bind(this));
            $(document).on('click', '.efm-expand-button', this.handleExpandFolder.bind(this));
            $(document).on('click', '.efm-upload-button', this.handleUploadClick.bind(this));
            
            // File Actions
            $(document).on('click', '.efm-file-preview-button', this.handleFilePreview.bind(this));
            
            // Search
            $(document).on('input', '.efm-search-input', this.handleSearchInput.bind(this));
            $(document).on('click', '.efm-search-clear', this.handleSearchClear.bind(this));
            
            // Load More
            $(document).on('click', '.efm-load-more-button', this.handleLoadMore.bind(this));
            
            // Modal Actions
            $(document).on('click', '.efm-upload-modal-close, .efm-upload-cancel-button', this.closeUploadModal.bind(this));
            $(document).on('click', '.efm-preview-modal-close, .efm-preview-close-button', this.closePreviewModal.bind(this));
            $(document).on('click', '.efm-browse-button', this.handleBrowseFiles.bind(this));
            $(document).on('click', '.efm-upload-start-button', this.handleUploadStart.bind(this));
            
            // File Input Change
            $(document).on('change', '.efm-file-input', this.handleFileSelect.bind(this));
            
            // Close modals on background click
            $(document).on('click', '.efm-upload-modal, .efm-preview-modal', function(e) {
                if (e.target === this) {
                    EFM.closeUploadModal();
                    EFM.closePreviewModal();
                }
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        },
        
        // Drag & Drop Setup
        setupDragAndDrop: function() {
            const dropzone = $('.efm-upload-dropzone');
            
            dropzone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('efm-drag-over');
            });
            
            dropzone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('efm-drag-over');
            });
            
            dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('efm-drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    EFM.handleDroppedFiles(files);
                }
            });
        },
        
        // Initial State Check
        checkInitialState: function() {
            // Prüfen ob wir in einem bestimmten Ordner starten sollen
            const container = $('.efm-frontend-container');
            const rootFolder = container.data('root') || 0;
            
            if (rootFolder > 0) {
                this.loadFolderContents(rootFolder);
            }
        },
        
        // Breadcrumb Navigation
        handleBreadcrumbClick: function(e) {
            e.preventDefault();
            const folderId = $(e.currentTarget).data('folder-id');
            this.loadFolderContents(folderId);
        },
        
        // View Folder Contents
        handleViewFolder: function(e) {
            e.preventDefault();
            const folderId = $(e.currentTarget).data('folder-id');
            this.loadFolderContents(folderId);
        },
        
        // Expand/Collapse Folder
        handleExpandFolder: function(e) {
            e.preventDefault();
            const button = $(e.currentTarget);
            const folderItem = button.closest('.efm-folder-item');
            const childrenContainer = folderItem.find('.efm-folder-children');
            const icon = button.find('i');
            
            if (childrenContainer.is(':visible')) {
                childrenContainer.slideUp(200);
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                button.attr('title', EFM.i18n.expand_folder);
            } else {
                childrenContainer.slideDown(200);
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                button.attr('title', EFM.i18n.collapse_folder);
                
                // Wenn Kinder noch nicht geladen wurden
                if (childrenContainer.children().length === 0) {
                    this.loadSubfolders(folderItem.data('folder-id'), childrenContainer);
                }
            }
        },
        
        // Upload Button Click
        handleUploadClick: function(e) {
            e.preventDefault();
            const folderId = $(e.currentTarget).data('folder-id');
            this.openUploadModal(folderId);
        },
        
        // File Preview
        handleFilePreview: function(e) {
            e.preventDefault();
            const fileId = $(e.currentTarget).data('file-id');
            this.showFilePreview(fileId);
        },
        
        // Search Functionality
        handleSearchInput: function(e) {
            const searchTerm = $(e.currentTarget).val().trim();
            const clearButton = $(e.currentTarget).siblings('.efm-search-clear');
            
            if (searchTerm.length > 0) {
                clearButton.show();
                this.performSearch(searchTerm);
            } else {
                clearButton.hide();
                this.clearSearchResults();
            }
        },
        
        handleSearchClear: function(e) {
            e.preventDefault();
            $('.efm-search-input').val('').trigger('input');
        },
        
        // Load More Files
        handleLoadMore: function(e) {
            e.preventDefault();
            const button = $(e.currentTarget);
            const folderId = button.data('folder-id');
            const offset = button.data('offset');
            
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + EFM.i18n.loading);
            
            this.loadMoreFiles(folderId, offset, function(newOffset) {
                button.data('offset', newOffset);
                button.prop('disabled', false).html('<i class="fas fa-plus"></i> ' + EFM.i18n.load_more);
                
                // Button ausblenden wenn keine weiteren Dateien
                if (newOffset === null) {
                    button.hide();
                }
            });
        },
        
        // File Browse
        handleBrowseFiles: function(e) {
            e.preventDefault();
            $('.efm-file-input').click();
        },
        
        // File Selection
        handleFileSelect: function(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.addFilesToQueue(files);
            }
        },
        
        // Dropped Files
        handleDroppedFiles: function(files) {
            this.addFilesToQueue(files);
        },
        
        // Upload Start
        handleUploadStart: function(e) {
            e.preventDefault();
            this.startUpload();
        },
        
        // Keyboard Shortcuts
        handleKeyboardShortcuts: function(e) {
            // ESC schließt Modals
            if (e.key === 'Escape') {
                if ($('.efm-upload-modal').is(':visible')) {
                    this.closeUploadModal();
                }
                if ($('.efm-preview-modal').is(':visible')) {
                    this.closePreviewModal();
                }
            }
            
            // / fokussiert Suchfeld
            if (e.key === '/' && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                $('.efm-search-input').focus();
            }
        },
        
        // AJAX Methods
        loadFolderContents: function(folderId) {
            const container = $('.efm-frontend-container');
            
            // Loading State
            container.addClass('efm-loading');
            
            $.ajax({
                url: efm_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'efm_get_folder_contents',
                    folder_id: folderId,
                    nonce: efm_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Container ersetzen
                        container.replaceWith(response.data.html);
                        
                        // Breadcrumb aktualisieren
                        EFM.updateBreadcrumb(folderId);
                        
                        // URL aktualisieren (ohne Page-Reload)
                        history.pushState({ folderId: folderId }, '', '?efm_folder=' + folderId);
                    } else {
                        EFM.showError(response.data.message);
                    }
                },
                error: function() {
                    EFM.showError(EFM.i18n.load_error);
                },
                complete: function() {
                    container.removeClass('efm-loading');
                }
            });
        },
        
        loadSubfolders: function(folderId, container) {
            $.ajax({
                url: efm_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'efm_get_subfolders',
                    folder_id: folderId,
                    nonce: efm_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        container.html(response.data.html);
                    }
                }
            });
        },
        
        performSearch: function(searchTerm) {
            const resultsContainer = $('.efm-search-results');
            
            // Debounce suchen
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(function() {
                $.ajax({
                    url: efm_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'efm_search',
                        search_term: searchTerm,
                        nonce: efm_frontend.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            resultsContainer.html(response.data.html).slideDown(200);
                        }
                    }
                });
            }, 300);
        },
        
        clearSearchResults: function() {
            $('.efm-search-results').slideUp(200, function() {
                $(this).empty();
            });
        },
        
        loadMoreFiles: function(folderId, offset, callback) {
            $.ajax({
                url: efm_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'efm_load_more_files',
                    folder_id: folderId,
                    offset: offset,
                    nonce: efm_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Neue Dateien hinzufügen
                        $('.efm-files-body').append(response.data.html);
                        
                        // Neuen Offset zurückgeben oder null wenn fertig
                        const newOffset = response.data.has_more ? offset + 20 : null;
                        callback(newOffset);
                    } else {
                        EFM.showError(response.data.message);
                        callback(offset); // Offset nicht ändern bei Fehler
                    }
                },
                error: function() {
                    EFM.showError(EFM.i18n.load_error);
                    callback(offset);
                }
            });
        },
        
        showFilePreview: function(fileId) {
            $.ajax({
                url: efm_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'efm_get_file_preview',
                    file_id: fileId,
                    nonce: efm_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EFM.displayPreviewModal(response.data);
                    } else {
                        EFM.showError(response.data.message);
                    }
                },
                error: function() {
                    EFM.showError(EFM.i18n.load_error);
                }
            });
        }
    };

        // Upload Methods
        openUploadModal: function(folderId) {
            this.currentUploadFolder = folderId;
            this.uploadQueue = [];
            
            // Modal vorbereiten
            $('.efm-upload-queue').empty().hide();
            $('.efm-upload-progress').hide();
            $('.efm-upload-start-button').prop('disabled', true);
            
            // Modal anzeigen
            $('.efm-upload-modal').fadeIn(200);
            $('body').addClass('efm-modal-open');
        },
        
        closeUploadModal: function() {
            $('.efm-upload-modal').fadeOut(200);
            $('body').removeClass('efm-modal-open');
            
            // Reset
            this.currentUploadFolder = null;
            this.uploadQueue = [];
            $('.efm-file-input').val('');
        },
        
        closePreviewModal: function() {
            $('.efm-preview-modal').fadeOut(200);
            $('body').removeClass('efm-modal-open');
        },
        
        addFilesToQueue: function(files) {
            const queueContainer = $('.efm-upload-queue');
            queueContainer.show();
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileId = 'file-' + Date.now() + '-' + i;
                
                // Datei zur Queue hinzufügen
                this.uploadQueue.push({
                    id: fileId,
                    file: file,
                    status: 'pending'
                });
                
                // Queue Item erstellen
                const item = $(`
                    <div class="efm-queue-item" data-file-id="${fileId}">
                        <div class="efm-queue-file-info">
                            <i class="fas fa-file"></i>
                            <div>
                                <div class="efm-queue-file-name">${file.name}</div>
                                <div class="efm-queue-file-size">${EFM.formatFileSize(file.size)}</div>
                            </div>
                        </div>
                        <div class="efm-queue-file-status">
                            <span class="efm-status-pending">${EFM.i18n.pending}</span>
                            <button class="efm-queue-remove" title="${EFM.i18n.remove}">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `);
                
                queueContainer.append(item);
            }
            
            // Upload Button aktivieren
            $('.efm-upload-start-button').prop('disabled', false);
        },
        
        startUpload: function() {
            if (!this.currentUploadFolder || this.uploadQueue.length === 0) {
                return;
            }
            
            const progressBar = $('.efm-progress-fill');
            const progressText = $('.efm-progress-text');
            const uploadButton = $('.efm-upload-start-button');
            
            // Progress anzeigen
            $('.efm-upload-progress').show();
            uploadButton.prop('disabled', true).text(EFM.i18n.uploading);
            
            let uploadedCount = 0;
            const totalFiles = this.uploadQueue.length;
            
            // Dateien nacheinander hochladen
            const uploadNextFile = () => {
                if (uploadedCount >= totalFiles) {
                    // Upload abgeschlossen
                    EFM.showSuccess(EFM.i18n.upload_complete);
                    setTimeout(() => {
                        EFM.closeUploadModal();
                        // Ordnerinhalt aktualisieren
                        EFM.loadFolderContents(EFM.currentUploadFolder);
                    }, 1000);
                    return;
                }
                
                const fileItem = EFM.uploadQueue[uploadedCount];
                const queueItem = $(`.efm-queue-item[data-file-id="${fileItem.id}"]`);
                const statusElement = queueItem.find('.efm-queue-file-status');
                
                // Status aktualisieren
                statusElement.html(`<span class="efm-status-uploading">${EFM.i18n.uploading}...</span>`);
                
                // FormData erstellen
                const formData = new FormData();
                formData.append('action', 'efm_upload_file');
                formData.append('folder_id', EFM.currentUploadFolder);
                formData.append('nonce', efm_frontend.nonce);
                formData.append('file', fileItem.file);
                
                // AJAX Upload
                $.ajax({
                    url: efm_frontend.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = new XMLHttpRequest();
                        
                        // Upload Progress
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                const percentComplete = Math.round((e.loaded / e.total) * 100);
                                const overallProgress = Math.round(((uploadedCount + (percentComplete / 100)) / totalFiles) * 100);
                                
                                progressBar.css('width', overallProgress + '%');
                                progressText.text(overallProgress + '%');
                            }
                        }, false);
                        
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            // Erfolg
                            statusElement.html(`<span class="efm-status-success">${EFM.i18n.upload_success}</span>`);
                            fileItem.status = 'success';
                        } else {
                            // Fehler
                            statusElement.html(`<span class="efm-status-error">${EFM.i18n.upload_error}: ${response.data.message}</span>`);
                            fileItem.status = 'error';
                        }
                        
                        uploadedCount++;
                        uploadNextFile();
                    },
                    error: function() {
                        // Netzwerkfehler
                        statusElement.html(`<span class="efm-status-error">${EFM.i18n.network_error}</span>`);
                        fileItem.status = 'error';
                        uploadedCount++;
                        uploadNextFile();
                    }
                });
            };
            
            // Erste Datei starten
            uploadNextFile();
        },
        
        // Utility Methods
        updateBreadcrumb: function(folderId) {
            // Breadcrumb würde normalerweise vom Server zurückgegeben
            // Hier nur als Platzhalter
            console.log('Breadcrumb update for folder:', folderId);
        },
        
        displayPreviewModal: function(fileData) {
            const modal = $('.efm-preview-modal');
            const content = modal.find('.efm-preview-content');
            const title = modal.find('.efm-preview-title');
            const downloadBtn = modal.find('.efm-download-button');
            
            // Modal füllen
            title.text(fileData.file_name);
            modal.find('.efm-file-size').text(fileData.formatted_size);
            modal.find('.efm-file-type').text(fileData.file_type.toUpperCase());
            modal.find('.efm-upload-date').text(fileData.upload_date);
            modal.find('.efm-download-count').text(fileData.download_count);
            
            // Download Link
            downloadBtn.attr('href', fileData.download_url);
            
            // Vorschau basierend auf Dateityp
            if (fileData.is_image) {
                content.html(`<img src="${fileData.preview_url}" alt="${fileData.file_name}">`);
            } else if (fileData.is_pdf) {
                content.html(`
                    <div class="efm-pdf-preview">
                        <i class="fas fa-file-pdf"></i>
                        <p>${EFM.i18n.pdf_preview}</p>
                        <a href="${fileData.preview_url}" target="_blank">${EFM.i18n.open_pdf}</a>
                    </div>
                `);
            } else {
                content.html(`
                    <div class="efm-file-preview">
                        <i class="fas fa-file ${fileData.icon_class}"></i>
                        <p>${EFM.i18n.no_preview}</p>
                    </div>
                `);
            }
            
            // Modal anzeigen
            modal.fadeIn(200);
            $('body').addClass('efm-modal-open');
        },
        
        formatFileSize: function(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        },
        
        showError: function(message) {
            // Einfache Fehleranzeige
            alert('Error: ' + message);
        },
        
        showSuccess: function(message) {
            // Einfache Erfolgsmeldung
            alert('Success: ' + message);
        },
        
        // Internationalization
        i18n: {
            expand_folder: 'Expand folder',
            collapse_folder: 'Collapse folder',
            loading: 'Loading...',
            load_more: 'Load More',
            pending: 'Pending',
            remove: 'Remove',
            uploading: 'Uploading',
            upload_success: 'Success',
            upload_error: 'Error',
            upload_complete: 'Upload complete',
            network_error: 'Network error',
            load_error: 'Failed to load data',
            pdf_preview: 'PDF Preview',
            open_pdf: 'Open PDF',
            no_preview: 'No preview available'
        }
    };
    
    // Initialisierung wenn DOM bereit
    $(document).ready(function() {
        EFM.init();
    });
    
    // Globale Verfügbarkeit
    window.EFM = EFM;
    
})(jQuery);
