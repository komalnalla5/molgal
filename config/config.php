<?php
// config.php  -- place this file in EVERY domain folder (molben.org, molbenz.com, etc.)
// Same DB as mubychemadmin's config/db-connect.php (shared central database).

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'molgal');   // <-- same DB name as admin panel

    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        die('Database connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
}

// ---- THIS domain's site_id from the `oursites` table ----
// Check phpMyAdmin -> oursites table -> find the row for THIS domain -> copy its `id` here.
// Every domain folder will have a DIFFERENT number here.
if (!defined('SITE_ID')) {
    define('SITE_ID', 1); // <-- CHANGE THIS per domain
}

// ---- Admin panel's live URL (where blog images are actually stored/uploaded) ----
// While testing locally this can stay as your local admin panel URL, e.g. http://localhost/mubychemadmin/
// Update it to the real domain once mubychemadmin goes live on the server.
if (!defined('ADMIN_BASE_URL')) {
    define('ADMIN_BASE_URL', 'http://localhost/mubychemadmin/');
}
