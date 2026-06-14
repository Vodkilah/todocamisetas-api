<?php

namespace App\Config;

use PDO;
use PDOException;

/**
 * Clase Database
 *
 * Encapsula la conexion PDO a MySQL usando el patron Singleton.
 * Se eligio PDO porque:
 *  - Permite usar consultas preparadas (proteccion contra SQL Injection).
 *  - Es agnostico al motor de base de datos (portable).
 *  - Maneja errores mediante excepciones, facilitando el control centralizado.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'db';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_DATABASE') ?: 'todocamisetas';
            $user = getenv('DB_USERNAME') ?: 'todocamisetas_user';
            $pass = getenv('DB_PASSWORD') ?: 'todocamisetas_pass';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'No fue posible conectar a la base de datos',
                    'detalle' => $e->getMessage(),
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
