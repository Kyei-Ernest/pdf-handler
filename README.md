# PDF Library Module

A plug-and-play PHP + MySQL module for a digital library. Drop it into any PHP project to add PDF upload, browsing, and a full Google Drive–style viewer — with zero framework dependencies.

---

## 📁 File Structure

```
pdf_handler/
├── config.example.php  ← Template — copy to config.php and edit
├── config.php          ← Your live credentials (gitignored)
├── install.php         ← Web installer (delete after setup!)
├── database.sql        ← Run manually if not using install.php
├── index.php           ← Document listing / search / filter
├── upload.php          ← Upload PDFs with metadata
├── viewer.php          ← PDF.js powered viewer
├── download.php        ← Secure file download
├── delete.php          ← Soft-delete a document
├── .gitignore          ← Keeps config.php & uploads/ out of git
└── uploads/            ← Auto-created on install, must be writable
```

---

## ⚡ Quick Setup (Recommended — Web Installer)

1. **Copy the module folder** into your web root (e.g. `/var/www/html/pdf_handler/`)
2. **Set permissions**: the web server must be able to write to the module directory so `install.php` can create `config.php` and `uploads/`
3. Open **`http://yoursite.com/pdf_handler/install.php`** in your browser
4. Fill in your database credentials and click **Run Installation**
5. **Delete `install.php`** once setup is complete

> The installer creates the database, all tables, default categories, the `uploads/` directory, and writes `config.php` automatically.

---

## 🔧 Manual Setup

If you prefer to set up manually instead of using the installer:

```bash
# 1. Create config.php from the template
cp config.example.php config.php
# Edit config.php and fill in your DB credentials and paths

# 2. Import the database schema
mysql -u YOUR_USER -p < database.sql

# 3. Create writable uploads folder
mkdir -p uploads
chmod 775 uploads
chown www-data:www-data uploads   # Linux/Apache
```

---

## 🔗 Integrating into an Existing App

This module is designed as a **drop-in**. There are three integration points, all optional.

### 1. Reuse your existing database connection

Instead of managing its own PDO, the module will use yours if you define `db_connect()` before requiring `config.php`:

```php
// In your bootstrap / before requiring config.php:
function db_connect(): PDO {
    return MyApp::getDatabase();   // return your existing PDO instance
}

require_once '/path/to/pdf_handler/config.php';
```

### 2. Protect pages with your auth system

Define `pdf_lib_auth_guard()` anywhere before the module loads and it will be called at the top of every page:

```php
function pdf_lib_auth_guard(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }
}
```

### 3. Pre-fill the "Uploaded by" field

Return the current user's name from `pdf_lib_current_user()`:

```php
function pdf_lib_current_user(): ?string {
    return $_SESSION['user']['name'] ?? null;
}
```

### Minimal integration example

```php
<?php
// In your app's bootstrap (loaded before the module):

// 1. Reuse your DB
function db_connect(): PDO { return MyApp::db(); }

// 2. Apply your auth
function pdf_lib_auth_guard(): void {
    if (!MyApp::isLoggedIn()) { header('Location: /login'); exit; }
}

// 3. Pass the current user
function pdf_lib_current_user(): ?string {
    return MyApp::currentUser()?->name;
}

// Then just link users to:
// http://yourapp.com/pdf_handler/index.php
```

### Changing the library name / branding

Edit `MODULE_NAME` in `config.php`:

```php
define('MODULE_NAME', 'Document Vault');  // shown in navbar & page titles
```

---

## ✨ Features

| Feature | Details |
|---|---|
| **Quick Install** | Web wizard at `install.php` — no CLI needed |
| **Upload** | Drag & drop or click-to-browse; validates PDF magic bytes |
| **Listing** | Grid view with search, category filter, sort, pagination |
| **Viewer** | PDF.js powered, multi-page scroll, zoom, fit-page, thumbnails |
| **Sidebar** | Document metadata + related documents in same category |
| **Download** | Secure `readfile()` delivery, tracks download count |
| **Delete** | Soft-delete (`is_active = 0`) + optional file removal |
| **Keyboard** | `↑/↓` page nav, `+/-` zoom |
| **Mobile** | Fully responsive Bootstrap 5 layout |
| **Auth hook** | `pdf_lib_auth_guard()` — integrate any auth system |
| **DB hook** | Override `db_connect()` to reuse your existing PDO |
| **Branding** | `MODULE_NAME` constant for custom navbar label |

---

## 🔒 Security Notes

- Credentials live in `config.php` which is gitignored via `.gitignore`
- Uploaded files stored with random filenames (no path traversal risk)
- File type validated via **magic bytes** (`%PDF-`), not just extension
- All user input sanitized with `htmlspecialchars()` and PDO prepared statements
- Downloads served via `readfile()` — no direct URL required
- Delete `install.php` after setup to prevent re-installation

---

## 📋 Requirements

- PHP **8.0+** with `pdo`, `pdo_mysql`, `fileinfo` extensions
- MySQL / MariaDB **5.7+**
- Apache or Nginx with PHP handler configured
- `upload_max_filesize = 50M` / `post_max_size = 55M` in `php.ini` (for large PDFs)

---

## 📦 Dependencies (CDN — no install needed)

- [Bootstrap 5.3](https://getbootstrap.com/)
- [Bootstrap Icons](https://icons.getbootstrap.com/)
- [PDF.js 4.4](https://mozilla.github.io/pdf.js/) — Mozilla's PDF rendering engine
