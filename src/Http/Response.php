<?php

namespace App\Http;

/**
 * Clase Response
 *
 * Centraliza el envio de respuestas JSON con el codigo HTTP y la
 * cabecera Content-Type: application/json correspondiente, para
 * mantener un formato consistente en toda la API.
 */
class Response
{
    /**
     * Envia una respuesta JSON exitosa.
     *
     * @param mixed $data
     * @param int $status
     * @return void
     */
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Envia una respuesta de error JSON con formato consistente.
     *
     * @param string $mensaje
     * @param int $status
     * @param array $errores Detalle adicional de errores (opcional)
     * @return void
     */
    public static function error(string $mensaje, int $status = 400, array $errores = []): void
    {
        $body = ['error' => $mensaje];

        if (!empty($errores)) {
            $body['errores'] = $errores;
        }

        self::json($body, $status);
    }
}
