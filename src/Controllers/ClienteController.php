<?php

namespace App\Controllers;

use App\Http\Response;
use App\Models\Cliente;
use PDOException;

/**
 * ClienteController
 *
 * Gestiona las operaciones CRUD sobre clientes B2B (90minutos,
 * tdeportes, etc.) y el listado de camisetas pedidas por cada uno.
 */
class ClienteController
{
    private const CATEGORIAS_VALIDAS = ['Regular', 'Preferencial'];

    /**
     * GET /api/clientes
     *
     * Lista todos los clientes.
     */
    public static function index(): void
    {
        Response::json([
            'data' => Cliente::all(),
            'total' => count(Cliente::all()),
        ], 200);
    }

    /**
     * GET /api/clientes/{id}
     *
     * Muestra el detalle de un cliente.
     */
    public static function show(int $id): void
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            Response::error("Cliente con id {$id} no encontrado", 404);
            return;
        }

        Response::json(['data' => $cliente], 200);
    }

    /**
     * POST /api/clientes
     *
     * Crea un nuevo cliente B2B.
     */
    public static function store(): void
    {
        $data = self::leerJson();

        $errores = self::validar($data);

        if (!empty($errores)) {
            Response::error('Datos invalidos', 422, $errores);
            return;
        }

        if (Cliente::rutExists($data['rut'])) {
            Response::error('Ya existe un cliente con ese RUT', 409);
            return;
        }

        try {
            $id = Cliente::create($data);
        } catch (PDOException $e) {
            Response::error('No fue posible crear el cliente', 500, [$e->getMessage()]);
            return;
        }

        Response::json([
            'mensaje' => 'Cliente creado correctamente',
            'data' => Cliente::find($id),
        ], 201);
    }

    /**
     * PUT /api/clientes/{id}
     *
     * Actualiza un cliente existente (actualizacion parcial).
     */
    public static function update(int $id): void
    {
        if (!Cliente::find($id)) {
            Response::error("Cliente con id {$id} no encontrado", 404);
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

        if (isset($data['rut']) && Cliente::rutExists($data['rut'], $id)) {
            Response::error('Ya existe otro cliente con ese RUT', 409);
            return;
        }

        Cliente::update($id, $data);

        Response::json([
            'mensaje' => 'Cliente actualizado correctamente',
            'data' => Cliente::find($id),
        ], 200);
    }

    /**
     * DELETE /api/clientes/{id}
     *
     * Elimina un cliente. No se permite eliminar un cliente que tenga
     * pedidos (camisetas) asociados.
     */
    public static function destroy(int $id): void
    {
        if (!Cliente::find($id)) {
            Response::error("Cliente con id {$id} no encontrado", 404);
            return;
        }

        if (Cliente::tienePedidos($id)) {
            Response::error('No se puede eliminar el cliente: tiene camisetas/pedidos asociados', 409);
            return;
        }

        Cliente::delete($id);

        Response::json(['mensaje' => 'Cliente eliminado correctamente'], 200);
    }

    /**
     * GET /api/clientes/{id}/camisetas
     *
     * Lista las camisetas pedidas por un cliente, con el precio_final
     * calculado segun su categoria.
     */
    public static function camisetas(int $id): void
    {
        if (!Cliente::find($id)) {
            Response::error("Cliente con id {$id} no encontrado", 404);
            return;
        }

        Response::json(['data' => Cliente::getCamisetas($id)], 200);
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    private static function leerJson(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Valida los datos de un cliente.
     *
     * @param array $data
     * @param bool $esUpdate
     * @return array
     */
    private static function validar(array $data, bool $esUpdate = false): array
    {
        $errores = [];

        $obligatorios = ['nombre_comercial', 'rut', 'direccion', 'contacto_nombre', 'contacto_email'];

        if (!$esUpdate) {
            foreach ($obligatorios as $campo) {
                if (!array_key_exists($campo, $data) || $data[$campo] === '' || $data[$campo] === null) {
                    $errores[] = "El campo '{$campo}' es obligatorio";
                }
            }
        }

        if (array_key_exists('categoria', $data) && $data['categoria'] !== null
            && !in_array($data['categoria'], self::CATEGORIAS_VALIDAS, true)) {
            $errores[] = "El campo 'categoria' debe ser 'Regular' o 'Preferencial'";
        }

        if (array_key_exists('contacto_email', $data) && $data['contacto_email'] !== null
            && !filter_var($data['contacto_email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El campo 'contacto_email' debe ser un correo valido";
        }

        if (array_key_exists('porcentaje_oferta', $data) && $data['porcentaje_oferta'] !== null) {
            $valor = $data['porcentaje_oferta'];
            if (!is_numeric($valor) || $valor < 0 || $valor > 100) {
                $errores[] = "El campo 'porcentaje_oferta' debe ser un numero entre 0 y 100";
            }
        }

        return $errores;
    }
}
