<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array($path, ['/static/style.css', '/static/studio.css', '/static/osint.css', '/static/app.css'], true)) {
    header('X-Content-Type-Options: nosniff');
    return false;
}
require __DIR__ . '/public/index.php';
