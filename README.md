# PDF Library Module
A plug-and-play PHP + MySQL module for a digital library that lets users upload, browse, and read PDF documents via a modern Google Drive–style viewer.

---

## 📁 File Structure

```
pdf_library/
├── config.php        ← Database credentials & settings  (EDIT THIS)
├── database.sql      ← Run once to create MySQL tables
├── index.php         ← Document listing / search / filter
├── upload.php        ← Upload new PDFs with metadata
├── viewer.php        ← Google Drive–style PDF viewer
├── download.php      ← Secure file download handler
├── delete.php        ← Soft-delete a document
└── uploads/          ← Uploaded PDF files (auto-created, must be writable)
```

---

## ⚡ Quick Setup

### 1. Database
```sql
-- In MySQL / phpMyAdmin run:
SOURCE /path/to/pdf_library/database.sql;
```
This creates the `pdf_library` database with `pdf_documents` and `pdf_categories` tables.

### 2. Configure
Edit **`config.php`**:
```php
define('DB_USER',    'your_db_user');
define('DB_PASS',    'your_db_password');
define('UPLOAD_URL', '/pdf_library/uploads/');  // adjust to your URL path
define('MODULE_URL', '/pdf_library');            // adjust to your URL path
```

### 3. Permissions
```bash
# The uploads folder must be writable by the web server
chmod 775 pdf_library/uploads/
chown www-data:www-data pdf_library/uploads/   # Linux/Apache
```

### 4. PHP Requirements
- PHP **8.0+**
- Extensions: `pdo`, `pdo_mysql`, `fileinfo`
- Max upload size: set in `php.ini` → `upload_max_filesize = 50M` and `post_max_size = 55M`

---

## 🔗 Integrating Into Your Existing Library

Simply link to `index.php` from your main app's navigation. Since `config.php` defines `MODULE_URL`, all internal links are relative and easy to adjust.

To share your existing MySQL connection, replace `db_connect()` in `config.php` with a function that returns your existing PDO instance.

---

## ✨ Features

| Feature | Details |
|---|---|
| **Upload** | Drag & drop or click-to-browse; validates real PDF magic bytes |
| **Listing** | Grid view with search, category filter, sort, pagination |
| **Viewer** | PDF.js powered, multi-page scroll, zoom in/out, fit-page, thumbnails panel |
| **Sidebar** | Document metadata + related documents in same category |
| **Download** | Secure `readfile()` delivery, tracks download count |
| **Delete** | Soft-delete (sets `is_active = 0`) + optional file removal |
| **Keyboard** | `↑/↓` page nav, `+/-` zoom |
| **Mobile** | Fully responsive Bootstrap 5 layout |

---

## 🔒 Security Notes

- Uploaded files are stored with random filenames (no path traversal risk)
- File type validated via **magic bytes** (`%PDF-`), not just extension
- All user input sanitized with `htmlspecialchars()` and PDO prepared statements
- Downloads served via `readfile()` — no direct URL required

---

## 📦 Dependencies (CDN — no install needed)
- [Bootstrap 5.3](https://getbootstrap.com/)
- [Bootstrap Icons](https://icons.getbootstrap.com/)
- [PDF.js 4.4](https://mozilla.github.io/pdf.js/) — Mozilla's PDF rendering engine
