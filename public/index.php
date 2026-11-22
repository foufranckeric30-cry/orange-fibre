<?php
declare(strict_types=1);

use App\Controllers\HomeController;

require_once dirname(__DIR__) . '/app/Controllers/HomeController.php';

try {
    $pdo = require dirname(__DIR__) . '/config/database.php';

    $controller = new HomeController($pdo);
    $controller->index();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Une erreur est survenue. Veuillez réessayer plus tard.';
}
