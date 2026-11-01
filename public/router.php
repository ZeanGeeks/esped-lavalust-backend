<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . DIRECTORY_SEPARATOR . ltrim((string) $path, '/\\');
$publicRoot = realpath(__DIR__);
$realFile = realpath($file);

if (
    $path !== '/'
    && $realFile !== false
    && $publicRoot !== false
    && strpos($realFile, $publicRoot . DIRECTORY_SEPARATOR) === 0
    && is_file($realFile)
) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/router.php';
require __DIR__ . '/index.php';
