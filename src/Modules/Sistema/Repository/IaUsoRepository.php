<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use App\Modules\Sistema\Entity\FuncionIa;
use Doctrine\DBAL\Connection;

/**
 * Registro de uso y consumo de la IA (criterio de la Fase 8): cada llamada
 * queda contada con tokens y duración, también las fallidas.
 */
final readonly class IaUsoRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    public function registrar(
        FuncionIa $funcion,
        string $proveedor,
        string $modelo,
        int $tokensEntrada,
        int $tokensSalida,
        int $duracionMs,
        bool $exito,
        ?string $error,
        ?int $usuarioId,
    ): void {
        $this->conexion->insert('ia_usos', [
            'funcion' => $funcion->value,
            'proveedor' => $proveedor,
            'modelo' => $modelo,
            'tokens_entrada' => $tokensEntrada,
            'tokens_salida' => $tokensSalida,
            'duracion_ms' => $duracionMs,
            'exito' => $exito ? 1 : 0,
            'error' => $error !== null ? mb_substr($error, 0, 255) : null,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Consumo agregado por función para la página del panel.
     *
     * @return list<array<string, mixed>>
     */
    public function consumoPorFuncion(int $dias = 30): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT funcion, COUNT(*) AS llamadas,
                    SUM(exito = 0) AS fallidas,
                    SUM(tokens_entrada) AS tokens_entrada,
                    SUM(tokens_salida) AS tokens_salida,
                    MAX(creado_en) AS ultima_vez
               FROM ia_usos
              WHERE creado_en >= NOW() - INTERVAL ? DAY
              GROUP BY funcion
              ORDER BY llamadas DESC',
            [$dias],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recientes(int $limite = 20): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT u.*, us.nombre AS usuario_nombre
               FROM ia_usos u
               LEFT JOIN usuarios us ON us.id = u.usuario_id
              ORDER BY u.id DESC
              LIMIT ' . $limite,
        );
    }
}
