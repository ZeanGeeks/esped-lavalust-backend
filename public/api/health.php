<?php
header('Content-Type: application/json; charset=utf-8');
$frontend_origin = getenv('FRONTEND_URL') ?: '';
if ($frontend_origin === '') {
	$env_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
	if (is_readable($env_path)) {
		foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			if (preg_match('/^\s*FRONTEND_URL\s*=\s*(.*?)\s*$/', $line, $matches)) {
				$frontend_origin = trim($matches[1], " \t\n\r\0\x0B\"'");
				break;
			}
		}
	}
}
$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($frontend_origin !== '' && $request_origin === $frontend_origin) {
	header('Access-Control-Allow-Origin: ' . $frontend_origin);
	header('Access-Control-Allow-Credentials: true');
	header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header('Allow: GET, OPTIONS');
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

echo json_encode([
	'status' => 'ok',
	'service' => 'esped-resort-api',
	'timestamp' => gmdate('c'),
]);
