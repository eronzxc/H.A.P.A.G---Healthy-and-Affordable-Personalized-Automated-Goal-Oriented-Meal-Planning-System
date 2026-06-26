<?php
// ============================================================
//  H.A.P.A.G. — Database Configuration
//  config/db.php
//
//  XAMPP defaults:  host=localhost, user=root, pass=''
//  Change DB_PASS before going live.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // ← set your MySQL password here
define('DB_NAME', 'hapag_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * Throws PDOException on connection failure (caught by callers).
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
