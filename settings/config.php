<?php
// =======================
// FrozoFun Admin Config
// =======================

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'frozofun_main');
define('DB_USER', 'frozofun_usama'); // <-- Change to your DB user
define('DB_PASS', 'mEnAAl86UsAmA!@');  // <-- Change to your DB password

// Site settings
define('SITE_NAME', 'FrozoFun Admin');
define('SITE_URL', 'https://admin.frozofun.com'); // <-- Update as needed

// Session & Security
define('SESSION_NAME', 'frozofun_admin_session');
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);

// File upload
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// Timezone
date_default_timezone_set('Asia/Karachi');

// Email (for future)
define('ADMIN_EMAIL', 'admin@yourdomain.com');