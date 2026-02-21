<?php
// =============================================================================
// PDF Library Module — Configuration Template
// =============================================================================
// 1. Copy this file to config.php
// 2. Fill in your values
// 3. Add config.php to .gitignore so credentials are never committed

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'pdf_library');   // name of the MySQL database
define('DB_USER',    'your_db_user');  // MySQL username
define('DB_PASS',    'your_db_pass');  // MySQL password
define('DB_CHARSET', 'utf8mb4');

// ── Upload settings ───────────────────────────────────────────────────────────
define('UPLOAD_DIR',   __DIR__ . '/uploads/');    // absolute path, must be writable
define('UPLOAD_URL',   '/pdf_handler/uploads/');  // URL path to uploads folder
define('MAX_FILE_SIZE', 52428800);                 // 50 MB in bytes
define('ALLOWED_TYPES', ['application/pdf']);

// ── Module URL ────────────────────────────────────────────────────────────────
// Base URL of the module folder (no trailing slash).
// Example: if you visit http://example.com/docs/pdf_handler/ set this to '/docs/pdf_handler'
define('MODULE_URL', '/pdf_handler');

// ── Branding ──────────────────────────────────────────────────────────────────
// Change MODULE_NAME to whatever label you want displayed in the nav bar.
define('MODULE_NAME', 'PDF Library');

// ── Session ───────────────────────────────────────────────────────────────────
// Change if embedding into an existing app that already has its own session.
define('SESSION_NAME', 'pdf_lib_session');

// ── Integration hooks (optional) ─────────────────────────────────────────────
// Uncomment and customise these if you want to plug in your own logic.

// 1. Use your existing database connection instead of creating a new one.
//    The function must return a PDO instance.
// function db_connect(): PDO {
//     return MyApp::getDb();  // return YOUR existing PDO
// }

// 2. Protect all module pages behind your own auth system.
//    Called at the top of every page — throw, redirect, or die() to block access.
// function pdf_lib_auth_guard(): void {
//     if (!isset($_SESSION['user'])) {
//         header('Location: /login.php');
//         exit;
//     }
// }

// 3. Get the currently logged-in username to pre-fill "Uploaded by".
//    Return a string or null.
// function pdf_lib_current_user(): ?string {
//     return $_SESSION['user']['name'] ?? null;
// }

// =============================================================================
// PDO Connection Factory — only define if not using integration hook above
// =============================================================================
if (!function_exists('db_connect')) {
    function db_connect(): PDO {
        static $pdo = null;
        if ($pdo !== null) return $pdo;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
        return $pdo;
    }
}
