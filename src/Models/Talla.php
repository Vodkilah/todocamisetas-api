<?php

namespace App\Models;

use App\Config\Database;

/**
 * Modelo Talla
 *
 * Representa el catalogo de tallas (tabla `tallas`), reutilizable
 * entre todas las camisetas mediante la tabla pivote camiseta_tallas.
 */
class Talla
{
    /**
     * Retorna todas las tallas del catalogo.
     *
     * @return array
     */
    public static function all(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM tallas ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    /**
     * Busca una talla por ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM tallas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $talla = $stmt->fetch();

        return $talla ?: null;
    }

    /**
     * Crea una nueva talla en el catalogo.
     *
     * @param string $nombre
     * @return int ID de la talla creada
     */
    public static function create(string $nombre): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('INSERT INTO tallas (nombre) VALUES (:nombre)');
        $stmt->execute(['nombre' => $nombre]);
        return (int) $db->lastInsertId();
    }

    /**
     * Actualiza el nombre de una talla.
     *
     * @param int $id
     * @param string $nombre
     * @return bool
     */
    public static function update(int $id, string $nombre): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE tallas SET nombre = :nombre WHERE id = :id');
        return $stmt->execute(['nombre' => $nombre, 'id' => $id]);
    }

    /**
     * Elimina una talla del catalogo.
     * Gracias a ON DELETE CASCADE en camiseta_tallas, se eliminan
     * tambien sus relaciones con camisetas.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM tallas WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si existe una talla con el nombre dado.
     *
     * @param string $nombre
     * @param int|null $excludeId
     * @return bool
     */
    public static function nombreExists(string $nombre, ?int $excludeId = null): bool
    {
        $db = Database::getConnection();

        if ($excludeId !== null) {
            $stmt = $db->prepare('SELECT id FROM tallas WHERE nombre = :nombre AND id != :id');
            $stmt->execute(['nombre' => $nombre, 'id' => $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT id FROM tallas WHERE nombre = :nombre');
            $stmt->execute(['nombre' => $nombre]);
        }

        return (bool) $stmt->fetch();
    }

    /**
     * Busca una talla por nombre o la crea si no existe.
     * Util al asociar tallas a una camiseta desde el POST de camisetas.
     *
     * @param string $nombre
     * @return int ID de la talla
     */
    public static function findOrCreateByNombre(string $nombre): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM tallas WHERE nombre = :nombre');
        $stmt->execute(['nombre' => $nombre]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        return self::create($nombre);
    }

    /**
     * Verifica si una talla tiene camisetas asociadas.
     *
     * @param int $id
     * @return bool
     */
    public static function tieneCamisetas(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM camiseta_tallas WHERE talla_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetch();
    }
}
