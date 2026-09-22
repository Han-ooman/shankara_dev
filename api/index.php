<?php
// api/index.php - API Entry Point

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/Database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Auth.php';
require_once __DIR__ . '/helpers/Request.php';
require_once __DIR__ . '/helpers/Log.php';

// Load all controllers
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ClusterController.php';
require_once __DIR__ . '/controllers/TipeRumahController.php';
require_once __DIR__ . '/controllers/RumahController.php';
require_once __DIR__ . '/controllers/KonsumenController.php';
require_once __DIR__ . '/controllers/TransaksiController.php';
require_once __DIR__ . '/controllers/PembayaranController.php';
require_once __DIR__ . '/controllers/PembatalanController.php';
require_once __DIR__ . '/controllers/PemberkasanController.php';
require_once __DIR__ . '/controllers/LaporanController.php';

$routes = require_once __DIR__ . '/routes/api.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/shandev/api'; // Sesuaikan dengan path installasi

// Remove base path
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
if ($uri === '') $uri = '/';

$routeMap = $routes[$method] ?? [];
$matched = false;

foreach ($routeMap as $pattern => $handler) {
    // Convert pattern to regex
    $regex = preg_replace('/\{(\w+)(?::([^\}]+))?\}/', '(?<$1>$2)', $pattern);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $matches)) {
        $matched = true;
        [$className, $methodName] = $handler;

        // Extract params from matches (named groups)
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }

        try {
            $controller = new $className();
            if (!method_exists($controller, $methodName)) {
                Response::error("Method $methodName tidak ditemukan di $className", 500);
            }

            // Log API call
            $controller->logApi($method, $uri, $params);

            // Call controller method with params
            $reflection = new ReflectionMethod($controller, $methodName);
            $methodParams = $reflection->getParameters();
            $args = [];
            foreach ($methodParams as $param) {
                $name = $param->getName();
                if (isset($params[$name])) {
                    $args[] = $params[$name];
                } elseif ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    Response::error("Parameter '$name' diperlukan untuk $methodName", 400);
                }
            }
            $reflection->invokeArgs($controller, $args);
        } catch (Throwable $e) {
            error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            if ($e->getCode() >= 400 && $e->getCode() < 600) {
                Response::error($e->getMessage(), $e->getCode());
            }
            Response::serverError($e->getMessage());
        }
        break;
    }
}

if (!$matched) {
    Response::notFound("Endpoint $method $uri");
}