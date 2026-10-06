<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Repository;

use App\Modules\Transparencia\Entity\TipoInstrumento;
use Doctrine\DBAL\Connection;

/**
 * Instrumentos de gestión de la información (Ley 1712 art. 6 lit. d, ITA 9.1).
 *
 * Las filas se devuelven tal cual vienen de la base: la vista y el CSV deciden
 * qué columnas pintar consultando `TipoInstrumento::columnas()`, de modo que
 * añadir una columna al criterio ITA sea tocar el enum y la migración, nunca
 * las tres plantillas.
 */
final readonly class InstrumentoInformacionRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicados(TipoInstrumento $tipo): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT i.*, d.nombre AS dependencia_nombre
               FROM instrumentos_informacion i
               LEFT JOIN dependencias d ON d.id = i.dependencia_id
              WHERE i.tipo = ? AND i.publicado = 1
              ORDER BY i.orden, i.categoria, i.nombre',
            [$tipo->value],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPanel(TipoInstrumento $tipo): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT i.*, d.nombre AS dependencia_nombre
               FROM instrumentos_informacion i
               LEFT JOIN dependencias d ON d.id = i.dependencia_id
              WHERE i.tipo = ?
              ORDER BY i.orden, i.categoria, i.nombre',
            [$tipo->value],
        );
    }

    /**
     * Cuántas filas publicadas tiene cada instrumento.
     *
     * La vista pública lo usa para no anunciar un instrumento vacío como si
     * estuviera publicado: el ITA puntúa contenido, no encabezados de tabla.
     *
     * @return array<string, int> valor del tipo => filas publicadas
     */
    public function conteoPublicado(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT tipo, COUNT(*) AS total
               FROM instrumentos_informacion
              WHERE publicado = 1
              GROUP BY tipo',
        );

        $conteo = [];

        foreach (TipoInstrumento::todos() as $tipo) {
            $conteo[$tipo->value] = 0;
        }

        foreach ($filas as $fila) {
            $conteo[(string) $fila['tipo']] = (int) $fila['total'];
        }

        return $conteo;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM instrumentos_informacion WHERE id = ?',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('instrumentos_informacion', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('instrumentos_informacion', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->delete('instrumentos_informacion', ['id' => $id]);
    }
}
