<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Ports\ProveedorIaException;
use JsonException;

/**
 * POST JSON con curl para los adaptadores de IA. Se usa la extensión curl
 * directamente: symfony/http-client no está en la lista de componentes
 * permitidos (CLAUDE.md §3) y para dos llamadas no se amplía.
 */
final readonly class ClienteHttpJson
{
    private const int TIMEOUT_SEGUNDOS = 60;

    /**
     * @param list<string> $cabeceras
     * @param array<string, mixed> $cuerpo
     * @return array<string, mixed>
     */
    public function post(string $proveedor, string $url, array $cabeceras, array $cuerpo): array
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw ProveedorIaException::porRed($proveedor, 'curl no disponible');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEGUNDOS,
            CURLOPT_HTTPHEADER => [...$cabeceras, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_THROW_ON_ERROR),
        ]);

        $respuesta = curl_exec($curl);
        $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errorRed = curl_error($curl);
        curl_close($curl);

        if (!is_string($respuesta)) {
            throw ProveedorIaException::porRed($proveedor, $errorRed !== '' ? $errorRed : 'sin respuesta');
        }

        if ($estado < 200 || $estado >= 300) {
            throw ProveedorIaException::porHttp($proveedor, $estado, $respuesta);
        }

        try {
            $datos = json_decode($respuesta, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ProveedorIaException::porRespuestaInvalida($proveedor);
        }

        if (!is_array($datos)) {
            throw ProveedorIaException::porRespuestaInvalida($proveedor);
        }

        return $datos;
    }
}
