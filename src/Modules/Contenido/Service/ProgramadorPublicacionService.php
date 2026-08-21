<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Modules\Busqueda\Entity\TipoIndice;
use App\Modules\Busqueda\Service\IndexadorService;
use Doctrine\DBAL\Connection;

/**
 * Hace funcionales `publicar_en` y `despublicar_en` (CLAUDE.md §6): corre
 * cada minuto desde el programador del sistema y mueve el estado.
 *
 *  - aprobado + publicar_en vencido  → publicado
 *  - publicado + despublicar_en vencido → borrador (y limpia la fecha para
 *    que una republicación manual no se despublique sola otra vez)
 *
 * Solo toca piezas ya APROBADAS: programar no salta el flujo de aprobación.
 * `campanas` no está aquí: su vigencia (inicio/fin) es informativa, no una
 * ventana de publicación.
 */
final readonly class ProgramadorPublicacionService
{
    private const array TABLAS = ['noticias', 'paginas'];

    public function __construct(
        private Connection $conexion,
        private AuditoriaService $auditoria,
        private IndexadorService $indexador,
    ) {
    }

    /**
     * @return array{publicadas: int, despublicadas: int}
     */
    public function ejecutar(): array
    {
        $publicadas = 0;
        $despublicadas = 0;

        foreach (self::TABLAS as $tabla) {
            $publicadas += $this->publicarVencidas($tabla);
            $despublicadas += $this->despublicarVencidas($tabla);
        }

        return ['publicadas' => $publicadas, 'despublicadas' => $despublicadas];
    }

    private function publicarVencidas(string $tabla): int
    {
        /** @var list<array{id: int|string, titulo: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT id, titulo FROM {$tabla}
              WHERE estado_aprobacion = 'aprobado'
                AND publicar_en IS NOT NULL AND publicar_en <= NOW()
                AND eliminado_en IS NULL",
        );

        foreach ($filas as $fila) {
            $this->conexion->executeStatement(
                "UPDATE {$tabla} SET estado_aprobacion = 'publicado' WHERE id = ?",
                [$fila['id']],
            );

            $this->auditar(AccionAuditoria::Publicacion, $tabla, (int) $fila['id'], sprintf(
                'Publicada según programación: «%s»',
                $fila['titulo'],
            ));
            $this->indexador->reindexar($this->tipoIndice($tabla), (int) $fila['id']);
        }

        return count($filas);
    }

    private function despublicarVencidas(string $tabla): int
    {
        /** @var list<array{id: int|string, titulo: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT id, titulo FROM {$tabla}
              WHERE estado_aprobacion = 'publicado'
                AND despublicar_en IS NOT NULL AND despublicar_en <= NOW()
                AND eliminado_en IS NULL",
        );

        foreach ($filas as $fila) {
            $this->conexion->executeStatement(
                "UPDATE {$tabla}
                    SET estado_aprobacion = 'borrador', despublicar_en = NULL
                  WHERE id = ?",
                [$fila['id']],
            );

            $this->auditar(AccionAuditoria::Despublicacion, $tabla, (int) $fila['id'], sprintf(
                'Despublicada según programación: «%s». Vuelve a borrador.',
                $fila['titulo'],
            ));
            $this->indexador->reindexar($this->tipoIndice($tabla), (int) $fila['id']);
        }

        return count($filas);
    }

    private function tipoIndice(string $tabla): TipoIndice
    {
        return $tabla === 'noticias' ? TipoIndice::Noticia : TipoIndice::Pagina;
    }

    private function auditar(AccionAuditoria $accion, string $entidad, int $id, string $descripcion): void
    {
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: $entidad,
            entidadId: $id,
            descripcion: $descripcion,
            usuarioNombre: 'tarea programada',
        ));
    }
}
