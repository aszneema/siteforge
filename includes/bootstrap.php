<?php
declare(strict_types=1);

/**
 * Loaded by every entry point. Sets up config, storage, session, database
 * and a small PSR-4-ish autoloader for the generator classes.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('SiteForge requires PHP 8.1 or newer. This server runs ' . PHP_VERSION . '.');
}

define('SF_ROOT', dirname(__DIR__));

$config = require SF_ROOT . '/config.php';

// Storage directories -------------------------------------------------------
foreach ([
    $config['storage_path'],
    $config['storage_path'] . '/projects',
    $config['storage_path'] . '/uploads',
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// Keep storage un-servable when the app sits inside a web root.
$guard = $config['storage_path'] . '/.htaccess';
if (!file_exists($guard)) {
    @file_put_contents($guard, "Require all denied\nDeny from all\n");
}

// Composer autoload (Anthropic SDK). Optional: the offline builder works
// without it, so a missing vendor/ directory must not be fatal.
if (is_file(SF_ROOT . '/vendor/autoload.php')) {
    require_once SF_ROOT . '/vendor/autoload.php';
}

// Generator classes ---------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'SiteForge\\')) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen('SiteForge\\')));
    $file = SF_ROOT . '/includes/generator/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once SF_ROOT . '/includes/helpers.php';
require_once SF_ROOT . '/includes/db.php';
require_once SF_ROOT . '/includes/auth.php';

// Session -------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$GLOBALS['wm_config'] = $config;

db();          // opens the connection and runs migrations on first use
auth_touch();  // refreshes the current user from the session
