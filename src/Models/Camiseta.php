<?php

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo Camiseta
 *
 * Representa la tabla `camisetas` y encapsula toda la logica de acceso
 * a datos relacionada (consultas preparadas con PDO).
 */
class Camiseta
{
    /**
     * Retorna todas las camisetas.
     * Si se entrega $clienteId, cada camiseta incluye el campo
     * calculado `precio_final` segun la categoria del cliente.
     *
     * @param int|null $clienteId
     * @return array
     */
    public static function all(?int $clienteId = null): array
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT * FROM camisetas ORDER BY id ASC');
        $camisetas = $stmt->fetchAll();

        $cliente = $clienteId ? Cliente::find($clienteId) : null;

        foreach ($camisetas as &$camiseta) {
            $camiseta['precio'] = (float) $camiseta['precio'];
            $camiseta['precio_oferta'] = $camiseta['precio_oferta'] !== null
                ? (float) $camiseta['precio_oferta']
                : null;
            $camiseta['precio_final'] = self::calcularPrecioFinal($camiseta, $cliente);
        }

        return $camisetas;
    }

    /**
     * Busca una camiseta por ID, incluyendo sus tallas con stock y el
     * precio_final calculado segun el cliente (si se entrega).
     *
     * @param int $id
     * @param int|null $clienteId
     * @return array|null
     */
    public static function find(int $id, ?int $clienteId = null): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT * FROM camisetas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $camiseta = $stmt->fetch();

        if (!$camiseta) {
            return null;
        }

        $camiseta['precio'] = (float) $camiseta['precio'];
        $camiseta['precio_oferta'] = $camiseta['precio_oferta'] !== null
            ? (float) $camiseta['precio_oferta']
            : null;

        $cliente = $clienteId ? Cliente::find($clienteId) : null;
        $camiseta['precio_final'] = self::calcularPrecioFinal($camiseta, $cliente);
        $camiseta['tallas'] = self::getTallas($id);

        return $camiseta;
    }

    /**
     * Crea una nueva camiseta.
     *
     * @param array $data
     * @return int ID de la camiseta creada
     */
    public static function create(array $data): int
    {
        $db = Database::getConnection();

        $sql = 'INSERT INTO camisetas
                    (titulo, club, pais, tipo, color, precio, precio_oferta, detalles, codigo_producto)
                VALUES
                    (:titulo, :club, :pais, :tipo, :color, :precio, :precio_oferta, :detalles, :codigo_producto)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'titulo' => $data['titulo'],
            'club' => $data['club'],
            'pais' => $data['pais'],
            'tipo' => $data['tipo'],
            'color' => $data['color'],
            'precio' => $data['precio'],
            'precio_oferta' => $data['precio_oferta'] ?? null,
            'detalles' => $data['detalles'] ?? null,
            'codigo_producto' => $data['codigo_producto'],
        ]);

        $camisetaId = (int) $db->lastInsertId();

        // Si se entregaron tallas iniciales, las asociamos.
        if (!empty($data['tallas']) && is_array($data['tallas'])) {
            foreach ($data['tallas'] as $talla) {
                $tallaId = Talla::findOrCreateByNombre($talla['nombre']);
                self::asignarTalla($camisetaId, $tallaId, (int) ($talla['stock'] ?? 0));
            }
        }

        return $camisetaId;
    }

    /**
     * Actualiza una camiseta existente.
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

        $permitido = ['titulo', 'club', 'pais', 'tipo', 'color', 'precio', 'precio_oferta', 'detalles', 'codigo_producto'];

        foreach ($permitido as $campo) {
            if (array_key_exists($campo, $data)) {
                $campos[] = "{$campo} = :{$campo}";
                $params[$campo] = $data[$campo];
            }
        }

        if (empty($campos)) {
            return false;
        }

        $sql = 'UPDATE camisetas SET ' . implode(', ', $campos) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Elimina la camiseta con ID dado.
     * Gracias a ON DELETE CASCADE en camiseta_tallas, sus tallas
     * asociadas se eliminan automaticamente. Si la camiseta tiene
     * pedidos asociados, la FK con ON DELETE RESTRICT impedira el
     * borrado (se captura en el controlador).
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM camisetas WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si existe una camiseta con el codigo de producto dado.
     *
     * @param string $codigo
     * @param int|null $excludeId ID a excluir (para updates)
     * @return bool
     */
    public static function codigoExists(string $codigo, ?int $excludeId = null): bool
    {
        $db = Database::getConnection();

        if ($excludeId !== null) {
            $stmt = $db->prepare('SELECT id FROM camisetas WHERE codigo_producto = :codigo AND id != :id');
            $stmt->execute(['codigo' => $codigo, 'id' => $excludeId]);
        } else {
            $stmt = $db->prepare('SELECT id FROM camisetas WHERE codigo_producto = :codigo');
            $stmt->execute(['codigo' => $codigo]);
        }

        return (bool) $stmt->fetch();
    }

    /**
     * Verifica si la camiseta tiene pedidos asociados.
     *
     * @param int $id
     * @return bool
     */
    public static function tienePedidos(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM pedidos WHERE camiseta_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetch();
    }

    // -----------------------------------------------------------------
    // Gestion de tallas (relacion muchos a muchos)
    // -----------------------------------------------------------------

    /**
     * Retorna las tallas asociadas a una camiseta, con su stock.
     *
     * @param int $camisetaId
     * @return array
     */
    public static function getTallas(int $camisetaId): array
    {
        $db = Database::getConnection();
        $sql = 'SELECT ct.id AS relacion_id, t.id AS talla_id, t.nombre, ct.stock
                FROM camiseta_tallas ct
                INNER JOIN tallas t ON t.id = ct.talla_id
                WHERE ct.camiseta_id = :camiseta_id
                ORDER BY t.id ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute(['camiseta_id' => $camisetaId]);
        return $stmt->fetchAll();
    }

    /**
     * Asigna (o actualiza el stock de) una talla a una camiseta.
     *
     * @param int $camisetaId
     * @param int $tallaId
     * @param int $stock
     * @return int ID de la relacion camiseta_tallas
     */
    public static function asignarTalla(int $camisetaId, int $tallaId, int $stock): int
    {
        $db = Database::getConnection();

        $sql = 'INSERT INTO camiseta_tallas (camiseta_id, talla_id, stock)
                VALUES (:camiseta_id, :talla_id, :stock)
                ON DUPLICATE KEY UPDATE stock = :stock2';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'camiseta_id' => $camisetaId,
            'talla_id' => $tallaId,
            'stock' => $stock,
            'stock2' => $stock,
        ]);

        $stmtId = $db->prepare('SELECT id FROM camiseta_tallas WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id');
        $stmtId->execute(['camiseta_id' => $camisetaId, 'talla_id' => $tallaId]);
        $row = $stmtId->fetch();

        return (int) $row['id'];
    }

    /**
     * Actualiza el stock de una relacion camiseta-talla especifica.
     *
     * @param int $camisetaId
     * @param int $tallaId
     * @param int $stock
     * @return bool
     */
    public static function actualizarStockTalla(int $camisetaId, int $tallaId, int $stock): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE camiseta_tallas SET stock = :stock
             WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id'
        );
        $stmt->execute([
            'stock' => $stock,
            'camiseta_id' => $camisetaId,
            'talla_id' => $tallaId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina la relacion entre una camiseta y una talla.
     *
     * @param int $camisetaId
     * @param int $tallaId
     * @return bool
     */
    public static function quitarTalla(int $camisetaId, int $tallaId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'DELETE FROM camiseta_tallas WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id'
        );
        $stmt->execute(['camiseta_id' => $camisetaId, 'talla_id' => $tallaId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si una relacion camiseta-talla existe.
     *
     * @param int $camisetaId
     * @param int $tallaId
     * @return bool
     */
    public static function tieneTalla(int $camisetaId, int $tallaId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id FROM camiseta_tallas WHERE camiseta_id = :camiseta_id AND talla_id = :talla_id'
        );
        $stmt->execute(['camiseta_id' => $camisetaId, 'talla_id' => $tallaId]);

        return (bool) $stmt->fetch();
    }

    /**
     * Verifica si una camiseta existe por su ID.
     *
     * @param int $id
     * @return bool
     */
    public static function exists(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM camisetas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetch();
    }

    // -----------------------------------------------------------------
    // Logica de negocio: precio final
    // -----------------------------------------------------------------

    /**
     * Calcula el precio_final de una camiseta segun la categoria del
     * cliente que realiza la consulta.
     *
     * Reglas de negocio (definidas en el caso TodoCamisetas):
     *  - Si el cliente es de categoria "Preferencial" y la camiseta
     *    tiene precio_oferta definido (no NULL), precio_final = precio_oferta.
     *  - En cualquier otro caso (cliente Regular, sin cliente, o sin
     *    precio_oferta definido), precio_final = precio (precio base).
     *
     * @param array $camiseta
     * @param array|null $cliente
     * @return float
     */
    public static function calcularPrecioFinal(array $camiseta, ?array $cliente): float
    {
        $precioBase = (float) $camiseta['precio'];
        $precioOferta = $camiseta['precio_oferta'] !== null ? (float) $camiseta['precio_oferta'] : null;

        if ($cliente !== null && $cliente['categoria'] === 'Preferencial' && $precioOferta !== null) {
            return $precioOferta;
        }

        return $precioBase;
    }
}
