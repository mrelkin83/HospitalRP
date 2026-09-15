<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Modules\Contenido\Entity\Popup;
use Doctrine\DBAL\Connection;

final readonly class PopupRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarParaPanel(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM popups ORDER BY activo DESC, prioridad DESC, id DESC LIMIT 100',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM popups WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $this->conexion->insert('popups', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('popups', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->delete('popups', ['id' => $id]);
    }

    /**
     * El popup que toca enseñar en esta página, o ninguno.
     *
     * Devuelve UNO aunque haya varios vigentes: dos ventanas emergentes a la
     * vez, una encima de otra, son una forma de echar al visitante. Gana la de
     * más prioridad y, a igualdad, la más reciente.
     *
     * `$esPortada` decide si entran además los de ubicación «portada». El
     * filtro se hace en la consulta y no en la plantilla para no traer a la
     * vista un popup que después habría que descartar.
     */
    public function vigente(bool $esPortada): ?Popup
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM popups
              WHERE activo = 1
                AND (desde IS NULL OR desde <= NOW())
                AND (hasta IS NULL OR hasta > NOW())
                AND ubicacion IN (?, ?)
              ORDER BY prioridad DESC, id DESC
              LIMIT 1',
            ['todas', $esPortada ? 'portada' : 'todas'],
        );

        return $fila === false ? null : Popup::desdeFila($fila);
    }
}
