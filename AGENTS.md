# FL-Wordpress Folder Manager - Agent Memory

## Repository Overview

This is a WordPress plugin for elegant folder management with role-based upload permissions and frontend display.

## Key Fixes Applied (2026-04-07)

### Critical Issues Fixed

1. **Main plugin file duplicate code** - Removed duplicate class methods and duplicate initialization code that caused syntax errors on activation.

2. **Missing database table `efm_user_roles`** - Added table creation to `Database::create_tables()` and fixed the query in `Database::user_can_upload_to_folder()` to use the correct table and column names.

3. **FileUploader class syntax error** - Fixed unmatched closing brace and merged missing methods (`get_file_icon`, `handle_download`, `get_file_preview_url`, `get_file_info`) into the class.

4. **Activation hook improvement** - Modified `activate()` method to call `maybe_create_tables()` immediately to ensure tables exist when plugin is activated.

### Plugin Structure

- Main class: `ElegantFolderManager` in `fl-folder-manager.php`
- Database layer: `EFM\Database` with table creation and queries
- File handling: `EFM\FileUploader` for uploads and downloads
- Folder management: `EFM\FolderManager` for folder operations
- Permissions: `EFM\PermissionHandler` for role-based permissions
- Shortcodes: `EFM\ShortcodeHandler` for frontend display

### Database Schema

Tables created:
- `efm_folders` - folder hierarchy
- `efm_files` - uploaded files
- `efm_permissions` - upload permissions per folder/role
- `efm_custom_roles` - custom role definitions
- `efm_user_roles` - user to custom role assignments (added in fix)

### Activation Notes

- Tables are created both on activation and during initialization (idempotent)
- Root folder is automatically created if it doesn't exist
- Default custom roles (MAV, Vertrieb, Entwicklung, Marketing, HR) are created

### Common Issues to Watch For

1. **Upload directory permissions** - Plugin creates `wp-content/uploads/efm-uploads/` - ensure writable
2. **AJAX handlers** - All AJAX actions are registered via `setup_ajax_handlers()`
3. **Shortcode** - Use `[folder_structure]` in posts/pages
4. **User role assignments** - Custom roles must be assigned via admin interface

### Testing Checklist

- [ ] Plugin activates without errors
- [ ] Database tables are created
- [ ] Admin menu items appear
- [ ] Frontend shortcode renders
- [ ] File uploads work with proper permissions
- [ ] Folder creation/deletion works

### Development Notes

- Uses WordPress coding standards
- Namespace: `EFM`
- Constants: `EFM_VERSION`, `EFM_PLUGIN_DIR`, `EFM_PLUGIN_URL`
- Autoloader for classes in `includes/` directory