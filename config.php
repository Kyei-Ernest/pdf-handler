<?php
// =============================================================================
// PDF Library Module — Database Configuration
// =============================================================================
// Copy config.example.php to config.php and update credentials before deploying.

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'pdf_library');
define('DB_USER',    'pdflib_user');   // ← app database user
define('DB_PASS',    'pdflib_pass123'); // ← app database password
define('DB_CHARSET', 'utf8mb4');

// Upload settings
define('UPLOAD_DIR',   __DIR__ . '/uploads/');
define('UPLOAD_URL',   '/pdf_handler/uploads/');  // ← adjusted to deployment path
define('MAX_FILE_SIZE', 52428800);   // 50 MB in bytes
define('ALLOWED_TYPES', ['application/pdf']);

// Module base URL (no trailing slash)
define('MODULE_URL', '/pdf_handler');  // ← adjusted to deployment path

// Branding — shown in the navbar/title
define('MODULE_NAME', 'PDF Library');

// Session name (change if embedding into existing app)
define('SESSION_NAME', 'pdf_lib_session');

// =============================================================================
// Integration hooks — define these in YOUR app before requiring config.php
// =============================================================================
// pdf_lib_auth_guard() — called on every page; redirect/die to protect access
// pdf_lib_current_user() — return the logged-in username string or null

// =============================================================================
// PDO Connection Factory
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
