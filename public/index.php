<?php

declare(strict_types=1);

// Když běžíme přes vestavěný PHP server (php -S ... public/index.php) a
// požadavek cílí na reálný soubor v public/ (např. /assets/style.css),
// vrátíme false - server si pak soubor obslouží sám. Doporučený postup
// z dokumentace PHP pro router skript vestavěného serveru.
if (PHP_SAPI === 'cli-server') {
    $requestedPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $filePath = __DIR__ . $requestedPath;
    if ($requestedPath !== '/' && is_file($filePath)) {
        return false;
    }
}

require __DIR__ . '/../src/bootstrap.php';

use App\Controllers\CourseController;
use App\Controllers\EnrollmentController;
use App\Controllers\ParticipantController;
use App\Database;

session_start();

$pdo = Database::connection();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri = rtrim($uri, '/');
if ($uri === '') {
    $uri = '/';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($uri === '/') {
    redirect('/courses');
}

$courseController = new CourseController($pdo);
$participantController = new ParticipantController($pdo);
$enrollmentController = new EnrollmentController($pdo);

/**
 * Routovací tabulka: [HTTP metoda, regex vzor cesty, [controller, metoda]].
 * Zachycené skupiny z regexu (např. ID) se předají jako argumenty metody.
 */
$routes = [
    ['GET', '#^/courses$#', [$courseController, 'index']],
    ['GET', '#^/courses/new$#', [$courseController, 'create']],
    ['POST', '#^/courses$#', [$courseController, 'store']],
    ['GET', '#^/courses/(\d+)/edit$#', [$courseController, 'edit']],
    ['POST', '#^/courses/(\d+)$#', [$courseController, 'update']],
    ['POST', '#^/courses/(\d+)/delete$#', [$courseController, 'destroy']],
    ['POST', '#^/courses/(\d+)/enroll$#', [$courseController, 'enroll']],
    ['GET', '#^/courses/(\d+)$#', [$courseController, 'show']],

    ['GET', '#^/participants$#', [$participantController, 'index']],
    ['GET', '#^/participants/new$#', [$participantController, 'create']],
    ['POST', '#^/participants$#', [$participantController, 'store']],
    ['GET', '#^/participants/(\d+)/edit$#', [$participantController, 'edit']],
    ['POST', '#^/participants/(\d+)$#', [$participantController, 'update']],
    ['POST', '#^/participants/(\d+)/delete$#', [$participantController, 'destroy']],

    ['POST', '#^/enrollments/(\d+)/cancel$#', [$enrollmentController, 'cancel']],
    ['POST', '#^/enrollments/(\d+)/complete$#', [$enrollmentController, 'complete']],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($method !== $routeMethod) {
        continue;
    }
    if (preg_match($pattern, $uri, $matches) === 1) {
        array_shift($matches);
        call_user_func_array($handler, $matches);
        exit;
    }
}

http_response_code(404);
require __DIR__ . '/../views/errors/404-plain.php';
