<?php

/**
 * Punto de entrada de la API TodoCamisetas (PHP puro, sin frameworks).
 *
 * Todas las solicitudes son redirigidas aqui mediante el archivo
 * .htaccess (Apache) o la configuracion try_files de Nginx.
 *
 * Define el enrutamiento con expresiones regulares: segun la ruta y
 * el metodo HTTP, se invoca el metodo estatico correspondiente del
 * controlador.
 */

require __DIR__ . '/autoload.php';

use App\Router\Router;
use App\Controllers\CamisetaController;
use App\Controllers\ClienteController;
use App\Controllers\TallaController;
use App\Http\Response;

// Manejo de CORS basico (para permitir pruebas desde un frontend)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

// ---------------------------------------------------------------------
// Healthcheck
// ---------------------------------------------------------------------
// GET /api/health -> Verifica que la API esta funcionando
$router->get('#^/api/health$#', function (): void {
    Response::json(['status' => 'ok', 'service' => 'TodoCamisetas API'], 200);
});

// ---------------------------------------------------------------------
// CAMISETAS
// ---------------------------------------------------------------------
// GET    /api/camisetas             -> Lista todas las camisetas (opcional ?cliente_id=)
// POST   /api/camisetas             -> Crea una nueva camiseta
$router->get('#^/api/camisetas$#', [CamisetaController::class, 'index']);
$router->post('#^/api/camisetas$#', [CamisetaController::class, 'store']);

// GET    /api/camisetas/{id}        -> Muestra una camiseta (opcional ?cliente_id=)
// PUT    /api/camisetas/{id}        -> Actualiza una camiseta
// DELETE /api/camisetas/{id}        -> Elimina una camiseta
$router->get('#^/api/camisetas/([0-9]+)$#', [CamisetaController::class, 'show']);
$router->put('#^/api/camisetas/([0-9]+)$#', [CamisetaController::class, 'update']);
$router->delete('#^/api/camisetas/([0-9]+)$#', [CamisetaController::class, 'destroy']);

// GET    /api/camisetas/{id}/tallas               -> Lista tallas y stock de una camiseta
// POST   /api/camisetas/{id}/tallas               -> Asocia una talla a la camiseta
$router->get('#^/api/camisetas/([0-9]+)/tallas$#', [CamisetaController::class, 'tallasIndex']);
$router->post('#^/api/camisetas/([0-9]+)/tallas$#', [CamisetaController::class, 'tallasStore']);

// PUT    /api/camisetas/{id}/tallas/{tallaId}     -> Actualiza stock de una talla
// DELETE /api/camisetas/{id}/tallas/{tallaId}     -> Desvincula una talla de la camiseta
$router->put('#^/api/camisetas/([0-9]+)/tallas/([0-9]+)$#', [CamisetaController::class, 'tallasUpdate']);
$router->delete('#^/api/camisetas/([0-9]+)/tallas/([0-9]+)$#', [CamisetaController::class, 'tallasDestroy']);

// ---------------------------------------------------------------------
// CLIENTES
// ---------------------------------------------------------------------
// GET    /api/clientes              -> Lista todos los clientes
// POST   /api/clientes              -> Crea un nuevo cliente
$router->get('#^/api/clientes$#', [ClienteController::class, 'index']);
$router->post('#^/api/clientes$#', [ClienteController::class, 'store']);

// GET    /api/clientes/{id}         -> Muestra un cliente
// PUT    /api/clientes/{id}         -> Actualiza un cliente
// DELETE /api/clientes/{id}         -> Elimina un cliente
$router->get('#^/api/clientes/([0-9]+)$#', [ClienteController::class, 'show']);
$router->put('#^/api/clientes/([0-9]+)$#', [ClienteController::class, 'update']);
$router->delete('#^/api/clientes/([0-9]+)$#', [ClienteController::class, 'destroy']);

// GET    /api/clientes/{id}/camisetas -> Lista camisetas pedidas por el cliente
$router->get('#^/api/clientes/([0-9]+)/camisetas$#', [ClienteController::class, 'camisetas']);

// ---------------------------------------------------------------------
// TALLAS (catalogo)
// ---------------------------------------------------------------------
// GET    /api/tallas      -> Lista el catalogo de tallas
// POST   /api/tallas      -> Crea una nueva talla
$router->get('#^/api/tallas$#', [TallaController::class, 'index']);
$router->post('#^/api/tallas$#', [TallaController::class, 'store']);

// GET    /api/tallas/{id} -> Muestra una talla
// PUT    /api/tallas/{id} -> Actualiza una talla
// DELETE /api/tallas/{id} -> Elimina una talla
$router->get('#^/api/tallas/([0-9]+)$#', [TallaController::class, 'show']);
$router->put('#^/api/tallas/([0-9]+)$#', [TallaController::class, 'update']);
$router->delete('#^/api/tallas/([0-9]+)$#', [TallaController::class, 'destroy']);

$router->resolve();
