<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Repository;

use App\Core\Support\Slugs;
use App\Modules\Documentos\Entity\Documento;
use App\Modules\Documentos\Entity\EstadoDocumento;
use App\Modules\Documentos\Request\DocumentoDatos;
use App\Modules\Documentos\Request\FiltrosDocumento;
use Doctrine\DBAL\Connection;

final readonly class DocumentoRepository
{
    private const string SELECT_CON_NOMBRES = 'SELECT d.*, c.nombre AS categoria_nombre, dep.nombre AS dependencia_nombre
          FROM documentos d
          LEFT JOIN categorias c ON c.id = d.categoria_id
          LEFT JOIN dependencias dep ON dep.id = d.dependencia_id';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Buscador público: publicados, no eliminados, del estado pedido
     * (vigente por defecto). El texto usa el índice FULLTEXT.
     *
     * @return array{documentos: list<Documento>, total: int}
     */
    public function buscarPublicos(FiltrosDocumento $filtros, int $porPagina = 20): array
    {
        $condiciones = ['d.publicado = 1', 'd.eliminado_en IS NULL', 'd.estado = ?'];
        $parametros = [$filtros->estado->value];

        if ($filtros->q !== '') {
            $condiciones[] = 'MATCH(d.titulo, d.resumen) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $parametros[] = $filtros->q;
        }

        if ($filtros->dependenciaId !== null) {
            $condiciones[] = 'd.dependencia_id = ?';
            $parametros[] = $filtros->dependenciaId;
        }

        if ($filtros->categoriaId !== null) {
            $condiciones[] = 'd.categoria_id = ?';
            $parametros[] = $filtros->categoriaId;
        }

        if ($filtros->anio !== null) {
            $condiciones[] = 'YEAR(d.fecha_documento) = ?';
            $parametros[] = $filtros->anio;
        }

        $donde = implode(' AND ', $condiciones);

        $total = (int) $this->conexion->fetchOne(
            "SELECT COUNT(*) FROM documentos d WHERE {$donde}",
            $parametros,
        );

        $orden = $filtros->q !== ''
            ? 'MATCH(d.titulo, d.resumen) AGAINST (?) DESC, d.fecha_documento DESC'
            : 'd.fecha_documento DESC, d.id DESC';

        if ($filtros->q !== '') {
            $parametros[] = $filtros->q;
        }

        $filas = $this->conexion->fetchAllAssociative(sprintf(
            '%s WHERE %s ORDER BY %s LIMIT %d OFFSET %d',
            self::SELECT_CON_NOMBRES,
            $donde,
            $orden,
            max(1, $porPagina),
            ($filtros->pagina - 1) * $porPagina,
        ), $parametros);

        return ['documentos' => array_map(Documento::desdeFila(...), $filas), 'total' => $total];
    }

    /**
     * Ficha pública: cualquier estado salvo borrador (un derogado se consulta
     * con su advertencia; los enlaces citados en actos no pueden morir).
     */
    public function publicoPorId(int $id): ?Documento
    {
        $fila = $this->conexion->fetchAssociative(
            self::SELECT_CON_NOMBRES . " WHERE d.id = ? AND d.publicado = 1 AND d.eliminado_en IS NULL AND d.estado <> 'borrador'",
            [$id],
        );

        return $fila === false ? null : Documento::desdeFila($fila, $this->etiquetasDe($id));
    }

    /**
     * @return list<Documento>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            self::SELECT_CON_NOMBRES . ' WHERE d.eliminado_en IS NULL ORDER BY d.actualizado_en DESC LIMIT 300',
        );

        return array_map(Documento::desdeFila(...), $filas);
    }

    public function porId(int $id): ?Documento
    {
        $fila = $this->conexion->fetchAssociative(
            self::SELECT_CON_NOMBRES . ' WHERE d.id = ? AND d.eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : Documento::desdeFila($fila, $this->etiquetasDe($id));
    }

    /**
     * El documento publicado que reemplaza a este (si alguien lo derogó).
     */
    public function reemplazadoPor(int $id): ?Documento
    {
        $fila = $this->conexion->fetchAssociative(
            self::SELECT_CON_NOMBRES . ' WHERE d.reemplaza_a_id = ? AND d.publicado = 1 AND d.eliminado_en IS NULL ORDER BY d.id DESC LIMIT 1',
            [$id],
        );

        return $fila === false ? null : Documento::desdeFila($fila);
    }

    public function crear(DocumentoDatos $datos, int $archivoId, ?int $creadoPor): int
    {
        $this->conexion->insert('documentos', [
            'titulo' => $datos->titulo,
            'slug' => $this->slugUnico($datos->titulo),
            'resumen' => $datos->resumen,
            'archivo_id' => $archivoId,
            'categoria_id' => $datos->categoriaId,
            'dependencia_id' => $datos->dependenciaId,
            'numero_acto' => $datos->numeroActo,
            'fecha_documento' => $datos->fechaDocumento,
            'vigencia_desde' => $datos->vigenciaDesde,
            'vigencia_hasta' => $datos->vigenciaHasta,
            'reemplaza_a_id' => $datos->reemplazaAId,
            'serie_documental' => $datos->serieDocumental,
            'subserie_documental' => $datos->subserieDocumental,
            'tiempo_retencion' => $datos->tiempoRetencion,
            'estado' => EstadoDocumento::Borrador->value,
            'creado_por' => $creadoPor,
            'actualizado_por' => $creadoPor,
        ]);

        $id = (int) $this->conexion->lastInsertId();

        $this->conexion->insert('documento_versiones', [
            'documento_id' => $id,
            'version' => 1,
            'archivo_id' => $archivoId,
            'notas' => 'Versión inicial',
            'creado_por' => $creadoPor,
        ]);

        return $id;
    }

    public function actualizar(int $id, DocumentoDatos $datos, ?int $actualizadoPor): void
    {
        $this->conexion->update('documentos', [
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'categoria_id' => $datos->categoriaId,
            'dependencia_id' => $datos->dependenciaId,
            'numero_acto' => $datos->numeroActo,
            'fecha_documento' => $datos->fechaDocumento,
            'vigencia_desde' => $datos->vigenciaDesde,
            'vigencia_hasta' => $datos->vigenciaHasta,
            'reemplaza_a_id' => $datos->reemplazaAId !== $id ? $datos->reemplazaAId : null,
            'serie_documental' => $datos->serieDocumental,
            'subserie_documental' => $datos->subserieDocumental,
            'tiempo_retencion' => $datos->tiempoRetencion,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    /**
     * Nueva versión del archivo: el documento apunta al PDF nuevo y el viejo
     * queda en el historial. Devuelve el número de versión nuevo.
     */
    public function nuevaVersion(int $id, int $archivoId, ?string $notas, ?int $usuarioId): int
    {
        return $this->conexion->transactional(function () use ($id, $archivoId, $notas, $usuarioId): int {
            $version = 1 + (int) $this->conexion->fetchOne(
                'SELECT COALESCE(MAX(version), 0) FROM documento_versiones WHERE documento_id = ? FOR UPDATE',
                [$id],
            );

            $this->conexion->insert('documento_versiones', [
                'documento_id' => $id,
                'version' => $version,
                'archivo_id' => $archivoId,
                'notas' => $notas,
                'creado_por' => $usuarioId,
            ]);

            $this->conexion->update('documentos', [
                'archivo_id' => $archivoId,
                'version' => $version,
                'actualizado_por' => $usuarioId,
            ], ['id' => $id]);

            return $version;
        });
    }

    /**
     * @return list<array{version: int, archivo_id: int, notas: ?string, creado_en: string}>
     */
    public function versionesDe(int $id): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT version, archivo_id, notas, creado_en FROM documento_versiones
              WHERE documento_id = ? ORDER BY version DESC',
            [$id],
        );

        return array_map(static fn (array $f): array => [
            'version' => (int) $f['version'],
            'archivo_id' => (int) $f['archivo_id'],
            'notas' => $f['notas'] !== null ? (string) $f['notas'] : null,
            'creado_en' => (string) $f['creado_en'],
        ], $filas);
    }

    public function cambiarPublicado(int $id, bool $publicado, ?int $usuarioId): void
    {
        $this->conexion->update('documentos', [
            'publicado' => $publicado ? 1 : 0,
            'actualizado_por' => $usuarioId,
        ], ['id' => $id]);
    }

    public function cambiarEstado(int $id, EstadoDocumento $estado, ?int $usuarioId): void
    {
        $this->conexion->update('documentos', [
            'estado' => $estado->value,
            'actualizado_por' => $usuarioId,
        ], ['id' => $id]);
    }

    public function eliminarLogicamente(int $id, ?int $usuarioId): void
    {
        $this->conexion->executeStatement(
            'UPDATE documentos SET eliminado_en = NOW(), publicado = 0, actualizado_por = ? WHERE id = ?',
            [$usuarioId, $id],
        );
    }

    public function contarDescarga(int $id): void
    {
        $this->conexion->executeStatement('UPDATE documentos SET descargas = descargas + 1 WHERE id = ?', [$id]);
    }

    /**
     * Encuentra o crea cada etiqueta y sincroniza la relación, manteniendo el
     * contador de usos de las etiquetas afectadas.
     *
     * @param list<string> $nombres
     */
    public function sincronizarEtiquetas(int $id, array $nombres): void
    {
        $afectadas = array_map(
            intval(...),
            $this->conexion->fetchFirstColumn('SELECT etiqueta_id FROM documento_etiqueta WHERE documento_id = ?', [$id]),
        );

        $this->conexion->delete('documento_etiqueta', ['documento_id' => $id]);

        foreach ($nombres as $nombre) {
            $slug = Slugs::desde($nombre, 90);
            $etiquetaId = $this->conexion->fetchOne('SELECT id FROM etiquetas WHERE slug = ?', [$slug]);

            if ($etiquetaId === false) {
                $this->conexion->insert('etiquetas', ['nombre' => $nombre, 'slug' => $slug]);
                $etiquetaId = (int) $this->conexion->lastInsertId();
            }

            $this->conexion->executeStatement(
                'INSERT IGNORE INTO documento_etiqueta (documento_id, etiqueta_id) VALUES (?, ?)',
                [$id, (int) $etiquetaId],
            );
            $afectadas[] = (int) $etiquetaId;
        }

        foreach (array_unique($afectadas) as $etiquetaId) {
            $this->conexion->executeStatement(
                'UPDATE etiquetas SET usos = (SELECT COUNT(*) FROM documento_etiqueta WHERE etiqueta_id = ?) WHERE id = ?',
                [$etiquetaId, $etiquetaId],
            );
        }
    }

    /**
     * @return list<string>
     */
    public function etiquetasDe(int $id): array
    {
        return array_map(
            strval(...),
            $this->conexion->fetchFirstColumn(
                'SELECT e.nombre FROM documento_etiqueta de JOIN etiquetas e ON e.id = de.etiqueta_id
                  WHERE de.documento_id = ? ORDER BY e.nombre',
                [$id],
            ),
        );
    }

    /**
     * Años con documentos publicados, para el filtro del buscador.
     *
     * @return list<int>
     */
    public function aniosDisponibles(): array
    {
        return array_map(
            intval(...),
            $this->conexion->fetchFirstColumn(
                'SELECT DISTINCT YEAR(fecha_documento) FROM documentos
                  WHERE publicado = 1 AND eliminado_en IS NULL
                  ORDER BY 1 DESC',
            ),
        );
    }

    private function slugUnico(string $titulo): string
    {
        $base = Slugs::desde($titulo, 300);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM documentos WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}
