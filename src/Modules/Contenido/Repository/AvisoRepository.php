<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Modules\Contenido\Entity\Aviso;
use Doctrine\DBAL\Connection;

final readonly class AvisoRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPanel(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM avisos ORDER BY activo DESC, prioridad DESC, id DESC LIMIT 100',
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM avisos WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('avisos', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('avisos', $campos, ['id' => $id]);
    }

    /**
     * El aviso que ve el ciudadano ahora mismo: activo, dentro de su ventana,
     * y si hay uno crítico, el crítico gana sin importar la prioridad.
     */
    public function vigente(): ?Aviso
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT * FROM avisos
              WHERE activo = 1
                AND (desde IS NULL OR desde <= NOW())
                AND (hasta IS NULL OR hasta > NOW())
              ORDER BY (nivel = 'critico') DESC, prioridad DESC, id DESC
              LIMIT 1",
        );

        return $fila === false ? null : Aviso::desdeFila($fila);
    }
}
