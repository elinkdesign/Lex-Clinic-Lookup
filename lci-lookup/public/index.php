<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(function () {
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $request = Request::capture();

    try {
        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo "Exception: ".get_class($e).PHP_EOL;
        echo $e->getMessage().PHP_EOL.PHP_EOL;
        echo $e->getTraceAsString();
    }
})();
