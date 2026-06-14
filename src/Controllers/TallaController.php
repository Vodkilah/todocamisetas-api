<?php

namespace App\Controllers;

use App\Http\Response;
use App\Models\Talla;
use PDOException;

/**
 * TallaController
 *
 * Gestiona el catalogo global de tallas (S, M, L, XL, etc.), que se
 * relaciona de forma N:M con las camisetas mediante camiseta_tallas.
 */
class TallaController
{
    /**
     * GET /api/tallas
     *
     * Lista todas las tallas del catalogo.
     */
    public static function index(): void
    {
        Response::json(['data' => Talla::all()], 200);
    }

    /**
     * GET /api/tallas/{id}
     */
    public static function show(int $id): void
    {
        $talla = Talla::find($id);

        if (!$talla) {
            Response::error("Talla con id {$id} no encontrada", 404);
            return;
        }

        Response::json(['data' => $talla], 200);
    }

    /**
     * POST /api/tallas
     *
     * Crea una nueva talla en el catalogo. Body: { "nombre": "XXL" }
     */
    public static function store(): void
    {
        $data = self::leerJson();

        if (empty($data['nombre']) || !is_string($data['nombre'])) {
            Response::error('Datos invalidos', 422, ["El campo 'nombre' es obligatorio"]);
            return;
        }

        $nombre = trim($data['nombre']);

        if (Talla::nombreExists($nombre)) {
            Response::error('Ya existe una talla con ese nombre', 409);
            return;
        }

        try {
            $id = Talla::create($nombre);
        } catch (PDOException $e) {
            Response::error('No fue posible crear la talla', 500, [$e->getMessage()]);
            return;
        }

        Response::json([
            'mensaje' => 'Talla creada correctamente',
            'data' => Talla::find($id),
        ], 201);
    }

    /**
     * PUT /api/tallas/{id}
     *
     * Actualiza el nombre de una talla. Body: { "nombre": "XXL" }
     */
    public static function update(int $id): void
    {
        if (!Talla::find($id)) {
            Response::error("Talla con id {$id} no encontrada", 404);
            return;
        }

        $data = self::leerJson();

        if (empty($data['nombre']) || !is_string($data['nombre'])) {
            Response::error('Datos invalidos', 422, ["El campo 'nombre' es obligatorio"]);
            return;
        }

        $nombre = trim($data['nombre']);

        if (Talla::nombreExists($nombre, $id)) {
            Response::error('Ya existe otra talla con ese nombre', 409);
            return;
        }

        Talla::update($id, $nombre);

        Response::json([
            'mensaje' => 'Talla actualizada correctamente',
            'data' => Talla::find($id),
        ], 200);
    }

    /**
     * DELETE /api/tallas/{id}
     *
     * Elimina una talla del catalogo. Gracias a ON DELETE CASCADE en
     * camiseta_tallas, tambien se eliminan sus relaciones con camisetas.
     */
    public static function destroy(int $id): void
    {
        if (!Talla::find($id)) {
            Response::error("Talla con id {$id} no encontrada", 404);
            return;
        }

        Talla::delete($id);

        Response::json(['mensaje' => 'Talla eliminada correctamente'], 200);
    }

    private static function leerJson(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }
}
