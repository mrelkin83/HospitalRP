<?php

declare(strict_types=1);

namespace App\Modules\Cumplimiento\Repository;

use Doctrine\DBAL\Connection;

final readonly class PoliticaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return ?array<string, mixed>
     */
    public function activaPorTipo(string $tipo): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM politicas WHERE tipo = ? AND activa = 1 ORDER BY id DESC LIMIT 1',
            [$tipo],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Todas las versiones, agrupables por tipo en el panel.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM politicas ORDER BY tipo, id DESC',
        );
    }

    /**
     * Una versión nueva entra en vigencia y cierra la anterior. Las
     * versiones NUNCA se editan: la evidencia de autorización apunta a la
     * versión exacta que el ciudadano vio (Ley 1581).
     *
     * @param array<string, mixed> $campos tipo, version, titulo, contenido, creado_por
     */
    public function publicarVersion(array $campos): int
    {
        return $this->conexion->transactional(function () use ($campos): int {
            $this->conexion->executeStatement(
                'UPDATE politicas SET activa = 0, vigente_hasta = CURDATE() WHERE tipo = ? AND activa = 1',
                [(string) $campos['tipo']],
            );

            $this->conexion->insert('politicas', $campos + [
                'vigente_desde' => date('Y-m-d'),
                'activa' => 1,
            ]);

            return (int) $this->conexion->lastInsertId();
        });
    }
}
