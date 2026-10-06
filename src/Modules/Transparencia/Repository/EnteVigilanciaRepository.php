<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Repository;

use Doctrine\DBAL\Connection;

/**
 * Entes y autoridades que vigilan a la entidad (ITA 3.13).
 *
 * Siete literales por ente: nombre, dirección, teléfono, correo, enlace al
 * sitio, tipo de control y mecanismos internos de supervisión. No caben en un
 * PDF adjunto a un ítem de Transparencia, y por eso tienen tabla propia.
 */
final readonly class EnteVigilanciaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicados(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM entes_vigilancia WHERE publicado = 1 ORDER BY orden, nombre',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPanel(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM entes_vigilancia ORDER BY orden, nombre',
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM entes_vigilancia WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('entes_vigilancia', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('entes_vigilancia', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->delete('entes_vigilancia', ['id' => $id]);
    }

    /**
     * Semilla idempotente por nombre: volver a sembrar no duplica el ente ni
     * pisa los datos que el hospital ya corrigió desde el panel.
     *
     * @param array<string, mixed> $campos
     */
    public function crearSiNoExiste(string $nombre, array $campos): bool
    {
        if (false !== $this->conexion->fetchOne('SELECT id FROM entes_vigilancia WHERE nombre = ?', [$nombre])) {
            return false;
        }

        $this->conexion->insert('entes_vigilancia', ['nombre' => $nombre, ...$campos]);

        return true;
    }
}
