<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\Service;

use App\Modules\Busqueda\Entity\TipoIndice;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;
use Doctrine\DBAL\Connection;

/**
 * Mantiene `indice_busqueda` como copia desnormalizada de lo publicado.
 *
 * Dos caminos: `reconstruirTodo()` (comando `busqueda:indexar`, también corre
 * tras cada publicación programada) y `reindexar()` puntual, que los servicios
 * editoriales llaman al publicar/actualizar/despublicar una entidad.
 *
 * Aquí se consulta directamente cada tabla en lugar de pasar por los
 * repositorios de los módulos: la regla «los módulos no se importan entre sí»
 * (CLAUDE.md §4) convierte al indexador en consumidor de la base publicada,
 * igual que el sitemap. La condición de visibilidad de cada tipo replica la
 * del repositorio dueño y se verifica por test.
 */
final readonly class IndexadorService
{
    public function __construct(
        private Connection $conexion,
        private IndiceBusquedaRepository $indice,
    ) {
    }

    /**
     * Reindexa todo. Devuelve el conteo por tipo para el comando.
     *
     * @return array<string, int>
     */
    public function reconstruirTodo(): array
    {
        $conteos = [];

        foreach (TipoIndice::cases() as $tipo) {
            $consulta = $this->consulta($tipo);

            if ($consulta === null) {
                continue; // el módulo del tipo aún no existe (convocatorias)
            }

            $filas = $this->conexion->fetchAllAssociative($consulta);

            $this->conexion->transactional(function () use ($tipo, $filas): void {
                $this->indice->vaciarTipo($tipo);

                foreach ($filas as $fila) {
                    $this->guardarFila($tipo, $fila);
                }
            });

            $conteos[$tipo->value] = count($filas);
        }

        return $conteos;
    }

    /**
     * Reindexa una sola entidad tras una mutación. Si ya no es visible al
     * público (despublicada, eliminada, desactivada), sale del índice.
     */
    public function reindexar(TipoIndice $tipo, int $entidadId): void
    {
        $consulta = $this->consulta($tipo);

        if ($consulta === null) {
            return;
        }

        $fila = $this->conexion->fetchAssociative(
            $consulta . ' AND ' . $this->aliasId($tipo) . ' = ?',
            [$entidadId],
        );

        if ($fila === false) {
            $this->indice->eliminar($tipo, $entidadId);

            return;
        }

        $this->guardarFila($tipo, $fila);
    }

    // --- soporte ---

    /** @param array<string, mixed> $fila */
    private function guardarFila(TipoIndice $tipo, array $fila): void
    {
        $this->indice->guardar(
            $tipo,
            (int) $fila['id'],
            mb_substr((string) $fila['titulo'], 0, 500),
            $fila['resumen'] !== null ? mb_substr($this->aTextoPlano((string) $fila['resumen']), 0, 1000) : null,
            $fila['contenido'] !== null ? $this->aTextoPlano((string) $fila['contenido']) : null,
            (string) $fila['url'],
        );
    }

    private function aTextoPlano(string $html): string
    {
        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * SELECT por tipo con columnas normalizadas (id, titulo, resumen,
     * contenido, url) y la condición de visibilidad pública del módulo dueño.
     * Sin placeholders: `reindexar()` concatena su filtro por id al final,
     * por eso ninguna consulta termina en GROUP BY ni ORDER BY.
     */
    private function consulta(TipoIndice $tipo): ?string
    {
        return match ($tipo) {
            TipoIndice::Pagina => "SELECT p.id, p.titulo, p.resumen, p.contenido, p.ruta AS url
                FROM paginas p
                WHERE p.estado_aprobacion = 'publicado' AND p.eliminado_en IS NULL AND p.noindex = 0
                  AND (p.publicar_en IS NULL OR p.publicar_en <= NOW())
                  AND (p.despublicar_en IS NULL OR p.despublicar_en > NOW())",

            TipoIndice::Noticia => "SELECT n.id, n.titulo, n.resumen, n.contenido,
                       CONCAT('/noticias/', n.slug) AS url
                FROM noticias n
                WHERE n.estado_aprobacion = 'publicado' AND n.eliminado_en IS NULL
                  AND (n.publicar_en IS NULL OR n.publicar_en <= NOW())
                  AND (n.despublicar_en IS NULL OR n.despublicar_en > NOW())",

            TipoIndice::Documento => "SELECT d.id, d.titulo, d.resumen,
                       CONCAT_WS(' ', d.numero_acto, d.serie_documental, d.subserie_documental) AS contenido,
                       CONCAT('/documentos/', d.id, '-', d.slug) AS url
                FROM documentos d
                WHERE d.publicado = 1 AND d.eliminado_en IS NULL AND d.estado <> 'borrador'",

            TipoIndice::Especialidad => "SELECT e.id, e.nombre AS titulo, e.descripcion AS resumen,
                       NULL AS contenido, CONCAT('/especialidades/', e.slug) AS url
                FROM especialidades e
                WHERE e.activa = 1",

            TipoIndice::Servicio => "SELECT s.id, s.nombre AS titulo, s.descripcion AS resumen,
                       s.requisitos AS contenido, CONCAT('/servicios/', s.slug) AS url
                FROM servicios s
                WHERE s.activo = 1",

            TipoIndice::Profesional => "SELECT pr.id, pr.nombre AS titulo, pr.perfil AS resumen,
                       (SELECT GROUP_CONCAT(e.nombre SEPARATOR ' ')
                          FROM profesional_especialidad pe
                          JOIN especialidades e ON e.id = pe.especialidad_id
                         WHERE pe.profesional_id = pr.id) AS contenido,
                       CONCAT('/directorio/', pr.slug) AS url
                FROM profesionales pr
                WHERE pr.activo = 1 AND pr.autoriza_publicacion = 1",

            TipoIndice::Sede => "SELECT sd.id, sd.nombre AS titulo, sd.descripcion AS resumen,
                       CONCAT_WS(' ', sd.direccion, sd.municipio, sd.telefono) AS contenido,
                       CONCAT('/sedes/', sd.slug) AS url
                FROM sedes sd
                WHERE sd.activa = 1",

            TipoIndice::Faq => "SELECT f.id, f.pregunta AS titulo, NULL AS resumen,
                       f.respuesta AS contenido,
                       '/atencion-ciudadania/preguntas-frecuentes' AS url
                FROM faq f
                WHERE f.publicada = 1",

            // El módulo de convocatorias llega en una fase posterior; cuando
            // exista su página pública, este tipo se activa aquí.
            TipoIndice::Convocatoria => null,

            TipoIndice::TipoTramite => "SELECT t.id, t.nombre AS titulo, t.descripcion AS resumen,
                       CONCAT_WS(' ', t.instrucciones, t.fundamento_legal) AS contenido,
                       CONCAT('/atencion-ciudadania/tramites/', t.slug) AS url
                FROM tipos_tramite t
                WHERE t.activo = 1",

            TipoIndice::Dataset => "SELECT ds.id, ds.titulo, ds.descripcion AS resumen,
                       NULL AS contenido, '/transparencia/datos-abiertos' AS url
                FROM datasets ds
                WHERE ds.publicado = 1",
        };
    }

    private function aliasId(TipoIndice $tipo): string
    {
        return match ($tipo) {
            TipoIndice::Pagina => 'p.id',
            TipoIndice::Noticia => 'n.id',
            TipoIndice::Documento => 'd.id',
            TipoIndice::Especialidad => 'e.id',
            TipoIndice::Servicio => 's.id',
            TipoIndice::Profesional => 'pr.id',
            TipoIndice::Sede => 'sd.id',
            TipoIndice::Faq => 'f.id',
            TipoIndice::Convocatoria => 'c.id',
            TipoIndice::TipoTramite => 't.id',
            TipoIndice::Dataset => 'ds.id',
        };
    }
}
