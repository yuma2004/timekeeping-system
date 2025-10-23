<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'development',
        'debug' => (bool) (getenv('APP_DEBUG') ?: false),
        'base_url' => getenv('APP_BASE_URL') ?: '/',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Tokyo',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'attendance_app',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'security' => [
        'event_hmac_key' => getenv('EVENT_HMAC_KEY') ?: 'change-me-event-hmac',
        'csrf_key' => getenv('CSRF_KEY') ?: 'change-me-csrf',
        'password_algo' => PASSWORD_DEFAULT,
    ],
    'session' => [
        'secure' => getenv('SESSION_SECURE') !== '0',
        'domain' => getenv('SESSION_DOMAIN') ?: '',
        'name' => getenv('SESSION_NAME') ?: 'attendance_sid',
    ],
];
