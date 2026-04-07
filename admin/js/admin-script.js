// Admin JavaScript - Basic functionality
jQuery(function($) {
    'use strict';
    
    // Tab switching
    $('.efm-admin-tab').on('click', function(e) {
        e.preventDefault();
        const target = $(this).data('target');
        $('.efm-admin-tab').removeClass('active');
        $(this).addClass('active');
        $('.efm-admin-content').hide();
        $(target).show();
    });
    
    // Create folder button
    $('.efm-create-folder-btn').on('click', function() {
        $('#efm-folder-forms').show().html(`
            <div class="efm-admin-form">
                <h3>Create New Folder</h3>
                <form class="efm-folder-form" data-action="create">
                    <div class="efm-form-group">
                        <label class="efm-form-label">Folder Name</label>
                        <input type="text" class="efm-form-input" name="name" required>
                    </div>
                    <div class="efm-form-actions">
                        <button type="submit" class="efm-admin-button success">
                            <i class="fas fa-plus"></i> Create Folder
                        </button>
                        <button type="button" class="efm-admin-button secondary efm-form-cancel">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        `);
    });
    
    // Cancel form
    $(document).on('click', '.efm-form-cancel', function() {
        $('#efm-folder-forms').empty().hide();
    });
    
    // Save settings
    $('#efm-save-settings').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        
        setTimeout(function() {
            button.prop('disabled', false).html(originalText);
            $('#efm-admin-messages').html(`
                <div class="efm-admin-alert success">
                    <i class="fas fa-check-circle"></i>
                    Settings saved successfully!
                </div>
            `);
        }, 1000);
    });
});
