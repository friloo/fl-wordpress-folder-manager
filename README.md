# FL-Wordpress Folder Manager - WordPress Plugin

Ein elegantes Ordner-Management-System mit rollenbasierten Upload-Berechtigungen und schöner Frontend-Darstellung für WordPress.

**Autor:** Friederich Loheide  
**Website:** https://loheide.eu  
**Plugin-URI:** https://loheide.eu/fl-wordpress-folder-manager  
**Version:** 1.0.0

## Features

### 🎯 Hauptfunktionen
- **Schönes Frontend** für alle eingeloggten Benutzer
- **Rollenbasierte Upload-Berechtigungen** (nur im Backend konfigurierbar)
- **Alle User sehen alle Ordner** im Frontend
- **Moderne UI** wie Google Drive/OneDrive
- **Drag & Drop Upload** mit Progress-Bar
- **Dateivorschau** für Bilder und PDFs
- **Responsive Design** für alle Geräte

### 🔒 Sicherheitsfeatures
- Nonce-Verifikation für alle AJAX-Requests
- Capability-Checks für Admin-Bereich
- Dateityp-Whitelist (Bilder, PDF, Office-Dokumente, etc.)
- SQL-Injection Protection (prepared statements)
- Download-Links mit Nonce-Schutz
- .htaccess Schutz für Upload-Verzeichnis

### 🚀 Performance
- AJAX-basierte Navigation (keine Page-Reloads)
- Lazy Loading für Ordnerinhalte
- Optimierte Datenbank-Queries
- Caching-ready Architektur

## Installation

1. **Plugin hochladen**
   - Laden Sie die ZIP-Datei in WordPress hoch (Plugins → Add New → Upload Plugin)
   - Oder entpacken Sie das Plugin in `/wp-content/plugins/`

2. **Plugin aktivieren**
   - Aktivieren Sie das Plugin im WordPress Admin-Bereich
   - Die Datenbank-Tabellen werden automatisch erstellt

3. **Shortcode verwenden**
   - Fügen Sie `[folder_structure]` auf einer Seite oder einem Beitrag ein
   - Optionale Parameter: `[folder_structure root="3" depth="2" show_files="true"]`

4. **Backend konfigurieren**
   - Gehen Sie zu "FL Folder Manager" im Admin-Menu
   - Erstellen Sie Ordnerstrukturen
   - Vergeben Sie Upload-Berechtigungen für Rollen

## Verwendung

### Shortcode Parameter
```php
[folder_structure]  // Standard-Ansicht
[folder_structure root="0"]  // Start bei Root-Ordner
[folder_structure depth="2"]  // Maximale Tiefe
[folder_structure show_files="false"]  // Nur Ordner anzeigen
[folder_structure show_breadcrumb="false"]  // Breadcrumb ausblenden
[folder_structure show_search="false"]  // Suchfunktion ausblenden
[folder_structure show_stats="true"]  // Statistiken anzeigen
```

### Beispiel-Szenario "MAV"
```php
// Ordnerstruktur:
Allgemeines/
├── MAV/           ← Nur Rolle "MAV" kann hier hochladen
├── Vertrieb/      ← Nur Rolle "Vertrieb" kann hier hochladen
└── Entwicklung/   ← Nur Rolle "Entwicklung" kann hier hochladen

// Frontend: ALLE User sehen ALLE Ordner
// Upload-Button: Nur bei Berechtigung sichtbar
```

## Backend-Admin

Das Plugin bietet einen vollständigen Admin-Bereich mit:

### 📁 Ordnerverwaltung
- Baumstruktur mit Drag & Drop Reordering
- Erstellen, Bearbeiten, Löschen von Ordnern
- Unterordner-Hierarchie

### 🔐 Berechtigungsmanagement
- Rollenbasierte Upload-Berechtigungen
- Matrix-Ansicht für einfache Konfiguration
- Benutzerdefinierte Rollen (MAV, Vertrieb, etc.)

### ⚙️ Einstellungen
- Maximale Dateigröße konfigurieren
- Erlaubte Dateitypen festlegen
- Frontend-Parameter anpassen
- System-Informationen

## Dateistruktur

```
fl-wordpress-folder-manager/
├── fl-folder-manager.php          # Hauptdatei mit Autoloader & Hooks
├── includes/
│   ├── class-database.php         # DB-Schema & Migrationen
│   ├── class-folder-manager.php   # Ordner-CRUD mit Baumstruktur
│   ├── class-file-uploader.php    # Uploads, Downloads, Sicherheit
│   ├── class-permission-handler.php # Rollen & Berechtigungen
│   └── class-shortcode-handler.php # Template-basiertes Rendering
├── admin/
│   ├── css/admin-style.css        # Modernes Admin-Design
│   ├── js/admin-script.js         # Admin-Interaktionen
│   └── pages/folder-manager.php   # Admin-Page mit Tabs
├── public/
│   ├── css/public-style.css       # Modernes Frontend-Design
│   ├── js/public-script.js        # AJAX, Drag & Drop, Uploads
│   └── templates/folder-structure.php # HTML-Template
├── languages/
│   ├── fl-folder-manager.pot      # Internationalisierung
│   └── fl-folder-manager-de_DE.po # Deutsche Übersetzung
└── README.md                      # Diese Dokumentation
```

## Technische Details

### Datenbank-Tabellen
- `wp_fl_folders` - Ordnerstruktur mit Parent/Child
- `wp_fl_files` - Datei-Informationen mit Metadaten
- `wp_fl_permissions` - Rollenbasierte Upload-Berechtigungen
- `wp_fl_custom_roles` - Benutzerdefinierte Rollen

### AJAX-Endpoints
- `efm_admin_get_folders` - Ordner für Admin laden
- `efm_admin_create_folder` - Ordner erstellen
- `efm_admin_update_folder` - Ordner aktualisieren
- `efm_admin_delete_folder` - Ordner löschen
- `efm_get_folder_structure` - Frontend-Ordnerstruktur
- `efm_get_folder_files` - Dateien für Ordner laden
- `efm_admin_upload_file` - Datei hochladen

### CSS-Klassen-Präfix
Alle CSS-Klassen beginnen mit `efm-` (Elegant Folder Manager) für konsistentes Styling.

## Support

Bei Fragen oder Problemen:
- **Website:** https://loheide.eu
- **Plugin-URI:** https://loheide.eu/fl-wordpress-folder-manager
- **Support:** Kontaktieren Sie mich über meine Website

## Lizenz

Dieses Plugin ist lizenziert unter der GPL v2 oder später.

## Changelog

### 1.0.0 (2024-04-06)
- Erste stabile Version
- Vollständige Ordnerverwaltung
- Rollenbasierte Upload-Berechtigungen
- Modernes Frontend mit Drag & Drop
- Kompletter Admin-Bereich
- Deutsche Übersetzung
- Umfassende Dokumentation
