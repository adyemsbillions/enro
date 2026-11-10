<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');

$configFile = BASE_PATH . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config.php — copy config.example.php to config.php');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set(config('timezone', 'Africa/Lagos'));
error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/wallet.php';
require __DIR__ . '/services/Mailer.php';
require __DIR__ . '/services/Vtpass.php';
require __DIR__ . '/services/Paystack.php';
require __DIR__ . '/services/Push.php';
require __DIR__ . '/migrate.php';
require __DIR__ . '/actions.php';
require __DIR__ . '/broadcasts.php';
require __DIR__ . '/unlock.php';

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}
