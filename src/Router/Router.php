<?php

namespace App\Router;

use App\Http\Response;

/**
 * Router
 *
 * Enrutador simple basado en expresiones regulares. Cada ruta se
 * define con: metodo HTTP, patron (regex) y un callable que apunta
 * a un metodo estatico de un controlador.
 *
 * Los grupos capturados en el regex (p.ej. ([0-9]+) para {id}) se
 * pasan como argumentos posicionales al metodo del controlador.
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable}> */
    private array $routes = [];

    /**
     * Registra una ruta GET.
     */
    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /**
     * Registra una ruta POST.
     */
    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Registra una ruta PUT.
     */
    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    /**
     * Registra una ruta DELETE.
     */
    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Resuelve la solicitud actual: busca una ruta cuyo metodo y
     * patron coincidan con la URI solicitada y ejecuta su handler.
     *
     * Si ninguna ruta coincide con la URI pero si existe para otro
     * metodo HTTP, responde 405 (Method Not Allowed).
     * Si no existe ninguna coincidencia, responde 404.
     */
    public function resolve(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Quita el prefijo del script (p.ej /index.php) y el query string.
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        $coincideUri = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                $coincideUri = true;

                if ($route['method'] === $method) {
                    array_shift($matches); // elimina la coincidencia completa
                    // Convierte los parametros capturados (string) a int cuando corresponda
                    $params = array_map(static function ($value) {
                        return ctype_digit($value) ? (int) $value : $value;
                    }, $matches);

                    call_user_func($route['handler'], ...$params);
                    return;
                }
            }
        }

        if ($coincideUri) {
            Response::error('Metodo HTTP no permitido para este recurso', 405);
            return;
        }

        Response::error('Recurso no encontrado', 404);
    }
}
