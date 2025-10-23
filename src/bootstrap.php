<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use App\Support\Config;

$config = require __DIR__ . '/../config/env.php';
Config::init($config);

date_default_timezone_set(Config::get('app.timezone', 'Asia/Tokyo'));
mb_internal_encoding('UTF-8');

if (!headers_sent()) {
    session_name(Config::get('session.name', 'attendance_sid'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => Config::get('session.domain', ''),
        'secure' => Config::get('session.secure', true),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
