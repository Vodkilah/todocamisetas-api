<?php

namespace App\Models;

use App\Config\Database;

/**
 * Modelo Cliente
 *
 * Representa la tabla `clientes` (clientes B2B de TodoCamisetas:
 * 90minutos y tdeportes).
 */
class Cliente
{
    /**
     * Retorna todos los clientes.
     *
     * @return array
     */
    public static function all(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM clientes ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    /**
     * Busca un cliente por ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch();

        return $cliente ?: null;
    }

    /**
     * Crea un nuevo cliente.
     *
     * @param array $data
     * @return int ID del cliente creado
     */
    public static function create(array $data): int
    {
        $db = Database::getConnection();

        $sql = 'INSERT INTO clientes
                    (nombre_comercial, rut, direccion, categoria, contacto_nombre, contacto_email, porcentaje_oferta)
                VALUES
                    (:nombre_comercial, :rut, :direccion, :categoria, :contacto_nombre, :contacto_email, :porcentaje_oferta)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'nombre_comercial' => $data['nombre_comercial'],
            'rut' => $data['rut'],
            'direccion' => $data['direccion'],
            'categoria' => $data['categoria'] ?? 'Regular',
            'contacto_nombre' => $data['contacto_nombre'],
            'contacto_email' => $data['contacto_email'],
            'porcentaje_oferta' => $data['porcentaje_oferta'] ?? 0,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Actualiza un cliente existente.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $db = Database::getConnection();

        $campos = [];
        $params = ['id' => $id];

        $permitido = ['nombre_comercial', 'rut', 'direccion', 'categoria', 'contacto_nombre', 'contacto_email', 'porcentaje_oferta'];

        foreach ($permitido as $campo) {
            if (array_key_exists($campo, $data)) {
                $campos[] = "{$campo} = :{$campo}";
                $params[$campo] = $data[$campo];
            }
        }

        if (empty($campos)) {
            return false;
        }

        $sql = 'UPDATE clientes SET ' . implode(', ', $campos) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Elimina el cliente con ID dado.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM clientes WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si existe un cliente con el RUT dado.
     *
     * @param string $rut
     * @param int|null $excludeId
     * @return bool
     */
    public static function rutExists(string $rut, ?int $excludeId = null): bool
    {
        $db = Database::getConnection();

        if ($excludeId !== null) {
            $stmt = $db->prepare('SELECT id FROM clientes WHERE rut = :rut AND id != :id');
            $stmt->execute(['rut' => $rut, 'id' => $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT id FROM clientes WHERE rut = :rut');
            $stmt->execute(['rut' => $rut]);
        }

        return (bool) $stmt->fetch();
    }

    /**
     * Verifica si el cliente tiene pedidos (camisetas) asociados.
     * Se usa para impedir la eliminacion de clientes con camisetas.
     *
     * @param int $id
     * @return bool
     */
    public static function tienePedidos(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM pedidos WHERE cliente_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetch();
    }

    /**
     * Lista las camisetas pedidas por un cliente (con cantidad y
     * precio_final calculado segun la categoria del propio cliente).
     *
     * @param int $clienteId
     * @return array
     */
    public static function getCamisetas(int $clienteId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT p.id AS pedido_id, p.cantidad, c.*
             FROM pedidos p
             INNER JOIN camisetas c ON c.id = p.camiseta_id
             WHERE p.cliente_id = :cliente_id
             ORDER BY p.id ASC'
        );
        $stmt->execute(['cliente_id' => $clienteId]);
        $rows = $stmt->fetchAll();

        $cliente = self::find($clienteId);

        foreach ($rows as &$row) {
            $row['precio'] = (float) $row['precio'];
            $row['precio_oferta'] = $row['precio_oferta'] !== null ? (float) $row['precio_oferta'] : null;
            $row['precio_final'] = Camiseta::calcularPrecioFinal($row, $cliente);
        }

        return $rows;
    }
}
