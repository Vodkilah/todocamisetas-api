<?php

namespace App\Controllers;

use App\Http\Response;
use App\Models\Camiseta;
use App\Models\Talla;
use PDOException;

/**
 * CamisetaController
 *
 * Gestiona las operaciones CRUD sobre camisetas (productos) y la
 * gestion de tallas asociadas a cada camiseta (relacion N:M).
 *
 * Todos los metodos son estaticos y son invocados directamente por
 * el Router segun la ruta y metodo HTTP correspondiente.
 */
class CamisetaController
{
    /** Tipos de camiseta permitidos segun el caso TodoCamisetas */
    private const TIPOS_VALIDOS = ['Local', 'Visita', '3era Camiseta', 'Femenino Local', 'Niño', 'Niño Local', 'Niño Visita'];

    /**
     * GET /api/camisetas
     * GET /api/camisetas?cliente_id=1
     *
     * Lista todas las camisetas. Si se entrega cliente_id por query
     * string, cada camiseta incluye su precio_final calculado segun
     * la categoria de ese cliente.
     */
    public static function index(): void
    {
        $clienteId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null;

        if ($clienteId !== null) {
            $cliente = \App\Models\Cliente::find($clienteId);
            if (!$cliente) {
                Response::error("El cliente con id {$clienteId} no existe", 404);
                return;
            }
        }

        $camisetas = Camiseta::all($clienteId);

        Response::json([
            'data' => $camisetas,
            'total' => count($camisetas),
        ], 200);
    }

    /**
     * GET /api/camisetas/{id}
     * GET /api/camisetas/{id}?cliente_id=1
     *
     * Muestra el detalle de una camiseta, incluyendo sus tallas con
     * stock y el precio_final calculado segun el cliente (si aplica).
     */
    public static function show(int $id): void
    {
        $clienteId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null;

        if ($clienteId !== null && !\App\Models\Cliente::find($clienteId)) {
            Response::error("El cliente con id {$clienteId} no existe", 404);
            return;
        }

        $camiseta = Camiseta::find($id, $clienteId);

        if (!$camiseta) {
            Response::error("Camiseta con id {$id} no encontrada", 404);
            return;
        }

        Response::json(['data' => $camiseta], 200);
    }

    /**
     * POST /api/camisetas
     *
     * Crea una nueva camiseta. Campos obligatorios: titulo, club,
     * pais, tipo, color, precio, codigo_producto.
     */
    public static function store(): void
    {
        $data = self::leerJson();

        $errores = self::validar($data);

        if (!empty($errores)) {
            Response::error('Datos invalidos', 422, $errores);
            return;
        }

        if (Camiseta::codigoExists($data['codigo_producto'])) {
            Response::error('Ya existe una camiseta con ese codigo_producto', 409);
            return;
        }

        try {
            $id = Camiseta::create($data);
        } catch (PDOException $e) {
            Response::error('No fue posible crear la camiseta', 500, [$e->getMessage()]);
            return;
        }

        $camiseta = Camiseta::find($id);

        Response::json([
            'mensaje' => 'Camiseta creada correctamente',
            'data' => $camiseta,
        ], 201);
    }

    /**
     * PUT /api/camisetas/{id}
     *
     * Actualiza una camiseta existente. Acepta actualizacion parcial
     * (solo los campos enviados son modificados), pero si se incluyen
     * campos, estos deben ser validos.
     */
    public static function update(int $id): void
    {
        if (!Camiseta::exists($id)) {
            Response::error("Camiseta con id {$id} no encontrada", 404);
            return;
        }

        $data = self::leerJson();

        if (empty($data)) {
            Response::error('Debe enviar al menos un campo para actualizar', 422);
            return;
        }

        $errores = self::validar($data, true);

        if (!empty($errores)) {
            Response::error('Datos invalidos', 422, $errores);
            return;
        }

        if (isset($data['codigo_producto']) && Camiseta::codigoExists($data['codigo_producto'], $id)) {
            Response::error('Ya existe otra camiseta con ese codigo_producto', 409);
            return;
        }

        Camiseta::update($id, $data);

        Response::json([
            'mensaje' => 'Camiseta actualizada correctamente',
            'data' => Camiseta::find($id),
        ], 200);
    }

    /**
     * DELETE /api/camisetas/{id}
     *
     * Elimina una camiseta. No se permite eliminar una camiseta que
     * tenga pedidos asociados (regla de integridad de negocio).
     */
    public static function destroy(int $id): void
    {
        if (!Camiseta::exists($id)) {
            Response::error("Camiseta con id {$id} no encontrada", 404);
            return;
        }

        if (Camiseta::tienePedidos($id)) {
            Response::error('No se puede eliminar la camiseta: tiene pedidos asociados', 409);
            return;
        }

        Camiseta::delete($id);

        Response::json(['mensaje' => 'Camiseta eliminada correctamente'], 200);
    }

    // -----------------------------------------------------------------
    // Gestion de tallas por camiseta
    // -----------------------------------------------------------------

    /**
     * GET /api/camisetas/{id}/tallas
     *
     * Lista las tallas (con stock) asociadas a una camiseta.
     */
    public static function tallasIndex(int $camisetaId): void
    {
        if (!Camiseta::exists($camisetaId)) {
            Response::error("Camiseta con id {$camisetaId} no encontrada", 404);
            return;
        }

        Response::json(['data' => Camiseta::getTallas($camisetaId)], 200);
    }

    /**
     * POST /api/camisetas/{id}/tallas
     *
     * Asocia una talla a la camiseta con un stock dado. Si la talla
     * (por nombre) no existe en el catalogo, se crea automaticamente.
     * Body: { "nombre": "M", "stock": 10 } o { "talla_id": 2, "stock": 10 }
     */
    public static function tallasStore(int $camisetaId): void
    {
        if (!Camiseta::exists($camisetaId)) {
            Response::error("Camiseta con id {$camisetaId} no encontrada", 404);
            return;
        }

        $data = self::leerJson();
        $errores = [];

        if (empty($data['nombre']) && empty($data['talla_id'])) {
            $errores[] = 'Debe indicar "nombre" (de la talla) o "talla_id"';
        }

        if (!isset($data['stock']) || !is_numeric($data['stock']) || (int) $data['stock'] < 0) {
            $errores[] = 'El campo "stock" es obligatorio y debe ser un entero >= 0';
        }

        if (!empty($errores)) {
            Response::error('Datos invalidos', 422, $errores);
            return;
        }

        if (!empty($data['talla_id'])) {
            $tallaId = (int) $data['talla_id'];
            if (!Talla::find($tallaId)) {
                Response::error("La talla con id {$tallaId} no existe", 404);
                return;
            }
        } else {
            $tallaId = Talla::findOrCreateByNombre(trim($data['nombre']));
        }

        Camiseta::asignarTalla($camisetaId, $tallaId, (int) $data['stock']);

        Response::json([
            'mensaje' => 'Talla asociada correctamente a la camiseta',
            'data' => Camiseta::getTallas($camisetaId),
        ], 201);
    }

    /**
     * PUT /api/camisetas/{id}/tallas/{tallaId}
     *
     * Actualiza el stock de una talla especifica para la camiseta.
     * Body: { "stock": 15 }
     */
    public static function tallasUpdate(int $camisetaId, int $tallaId): void
    {
        if (!Camiseta::exists($camisetaId)) {
            Response::error("Camiseta con id {$camisetaId} no encontrada", 404);
            return;
        }

        if (!Talla::find($tallaId)) {
            Response::error("Talla con id {$tallaId} no encontrada", 404);
            return;
        }

        if (!Camiseta::tieneTalla($camisetaId, $tallaId)) {
            Response::error('Esta camiseta no tiene asociada esa talla', 404);
            return;
        }

        $data = self::leerJson();

        if (!isset($data['stock']) || !is_numeric($data['stock']) || (int) $data['stock'] < 0) {
            Response::error('El campo "stock" es obligatorio y debe ser un entero >= 0', 422);
            return;
        }

        Camiseta::actualizarStockTalla($camisetaId, $tallaId, (int) $data['stock']);

        Response::json([
            'mensaje' => 'Stock de talla actualizado correctamente',
            'data' => Camiseta::getTallas($camisetaId),
        ], 200);
    }

    /**
     * DELETE /api/camisetas/{id}/tallas/{tallaId}
     *
     * Elimina la asociacion entre una camiseta y una talla.
     */
    public static function tallasDestroy(int $camisetaId, int $tallaId): void
    {
        if (!Camiseta::exists($camisetaId)) {
            Response::error("Camiseta con id {$camisetaId} no encontrada", 404);
            return;
        }

        if (!Camiseta::tieneTalla($camisetaId, $tallaId)) {
            Response::error('Esta camiseta no tiene asociada esa talla', 404);
            return;
        }

        Camiseta::quitarTalla($camisetaId, $tallaId);

        Response::json(['mensaje' => 'Talla desvinculada de la camiseta correctamente'], 200);
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    /**
     * Lee y decodifica el body JSON de la solicitud actual.
     *
     * @return array
     */
    private static function leerJson(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Valida los datos de una camiseta.
     *
     * @param array $data
     * @param bool $esUpdate Si es true, solo valida los campos presentes
     * @return array Lista de mensajes de error (vacia si todo es valido)
     */
    private static function validar(array $data, bool $esUpdate = false): array
    {
        $errores = [];

        $obligatorios = ['titulo', 'club', 'pais', 'tipo', 'color', 'precio', 'codigo_producto'];

        if (!$esUpdate) {
            foreach ($obligatorios as $campo) {
                if (!array_key_exists($campo, $data) || $data[$campo] === '' || $data[$campo] === null) {
                    $errores[] = "El campo '{$campo}' es obligatorio";
                }
            }
        }

        if (array_key_exists('precio', $data) && (!is_numeric($data['precio']) || $data['precio'] < 0)) {
            $errores[] = "El campo 'precio' debe ser un numero mayor o igual a 0";
        }

        if (array_key_exists('precio_oferta', $data) && $data['precio_oferta'] !== null
            && (!is_numeric($data['precio_oferta']) || $data['precio_oferta'] < 0)) {
            $errores[] = "El campo 'precio_oferta' debe ser un numero mayor o igual a 0 o null";
        }

        if (array_key_exists('tipo', $data) && $data['tipo'] !== null && $data['tipo'] !== '') {
            // Se acepta cualquier valor de tipo, pero se sugiere uno de los predefinidos.
        }

        if (array_key_exists('codigo_producto', $data) && $data['codigo_producto'] !== null) {
            if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', (string) $data['codigo_producto'])) {
                $errores[] = "El campo 'codigo_producto' debe tener entre 3 y 30 caracteres alfanumericos";
            }
        }

        return $errores;
    }
}
