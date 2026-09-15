<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Core\Support\Slugs;
use App\Modules\Contenido\Entity\Audiencia;
use App\Modules\Contenido\Entity\Campana;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Request\CampanaDatos;
use Doctrine\DBAL\Connection;

final readonly class CampanaRepository
{
    /** Lo último publicado primero. */
    public const string ORDEN_RECIENTES = 'recientes';

    /** Lo que está por terminar primero: útil con jornadas y plazos. */
    public const string ORDEN_VENCEN_ANTES = 'vencen_antes';

    public function __construct(private Connection $conexion)
    {
    }

    public function porId(int $id): ?Campana
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM campanas WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : Campana::desdeFila($fila);
    }

    /**
     * @return list<Campana>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM campanas WHERE eliminado_en IS NULL ORDER BY actualizado_en DESC LIMIT 200',
        );

        return array_map(Campana::desdeFila(...), $filas);
    }

    /**
     * Campañas que puede ver el público: publicadas y dentro de su vigencia.
     *
     * Existe porque la portada llamaba a `listarParaPanel()`, que devuelve
     * TODO lo no eliminado —borradores y campañas en revisión incluidos— y
     * caía en mostrarlos cuando no había ninguna publicada. Una campaña a
     * medio redactar no puede salir al sitio por un «si no hay nada, muestra
     * lo que sea».
     *
     * La vigencia también se respeta: una jornada que ya terminó, anunciada
     * como vigente, manda gente al hospital en balde.
     *
     * Con `$audiencia` se acota a una de las dos pestañas de la portada; sin
     * ella devuelve las de toda audiencia, que es lo que quiere un buscador.
     *
     * @return list<Campana>
     */
    public function publicasVigentes(int $limite = 6, ?Audiencia $audiencia = null, string $orden = self::ORDEN_RECIENTES): array
    {
        // El criterio lo elige el hospital en Ajustes del sitio. Solo dos, y
        // ambos con desempate: «recientes» pone delante lo último publicado;
        // «vencen_antes» pone delante lo que está a punto de terminar, que es
        // lo útil cuando hay jornadas con fecha de cierre. Una campaña sin fin
        // no urge, así que se va al final (`fin IS NULL` ordena después).
        $ordenSql = $orden === self::ORDEN_VENCEN_ANTES
            ? 'fin IS NULL, fin ASC, COALESCE(inicio, creado_en) DESC, id DESC'
            : 'COALESCE(inicio, creado_en) DESC, creado_en DESC, id DESC';

        $filas = $this->conexion->fetchAllAssociative(
            "SELECT * FROM campanas
              WHERE eliminado_en IS NULL
                AND estado_aprobacion = 'publicado'
                AND (inicio IS NULL OR inicio <= CURDATE())
                AND (fin IS NULL OR fin >= CURDATE())
                AND (:audiencia IS NULL OR audiencia = :audiencia)
              -- El desempate no es cosmético. `inicio` es DATE: todo lo que
              -- arranca el mismo día empata, y sin segundo criterio MySQL
              -- devuelve el orden que quiera. Con el LIMIT de la portada eso
              -- significaba que una campaña recién publicada podía no salir
              -- nunca, aunque fuera la más nueva.
              ORDER BY " . $ordenSql . "
              LIMIT " . max(1, min($limite, 24)),
            ['audiencia' => $audiencia?->value],
        );

        return array_map(Campana::desdeFila(...), $filas);
    }

    /**
     * La campaña que se sirve en `/campanas/{slug}`.
     *
     * Mismas condiciones que el listado —publicada y vigente—: una URL
     * adivinada no puede destapar un borrador ni revivir una jornada que ya
     * terminó.
     */
    public function publicaPorSlug(string $slug): ?Campana
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT * FROM campanas
              WHERE slug = ?
                AND eliminado_en IS NULL
                AND estado_aprobacion = 'publicado'
                AND (inicio IS NULL OR inicio <= CURDATE())
                AND (fin IS NULL OR fin >= CURDATE())",
            [$slug],
        );

        return $fila === false ? null : Campana::desdeFila($fila);
    }

    public function crear(CampanaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $creadoPor): int
    {
        $this->conexion->insert('campanas', [
            'slug' => $this->slugUnico($datos->titulo),
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'poblacion_objetivo' => $datos->poblacionObjetivo,
            'audiencia' => $datos->audiencia->value,
            'inicio' => $datos->inicio,
            'fin' => $datos->fin,
            'estado_aprobacion' => EstadoAprobacion::Borrador->value,
            'creado_por' => $creadoPor,
            'actualizado_por' => $creadoPor,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, CampanaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $actualizadoPor): void
    {
        $this->conexion->update('campanas', [
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'poblacion_objetivo' => $datos->poblacionObjetivo,
            'audiencia' => $datos->audiencia->value,
            'inicio' => $datos->inicio,
            'fin' => $datos->fin,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function aplicarSnapshot(int $id, array $campos, ?int $actualizadoPor): void
    {
        $this->conexion->update('campanas', [
            'titulo' => (string) $campos['titulo'],
            'resumen' => $campos['resumen'] !== null ? (string) $campos['resumen'] : null,
            'contenido' => (string) $campos['contenido'],
            'imagen_id' => $campos['imagen_id'] !== null ? (int) $campos['imagen_id'] : null,
            'poblacion_objetivo' => $campos['poblacion_objetivo'] !== null ? (string) $campos['poblacion_objetivo'] : null,
            // Las versiones anteriores a la columna no traen la clave: se
            // restauran como comunidad, que es lo que eran.
            'audiencia' => Audiencia::desde(isset($campos['audiencia']) ? (string) $campos['audiencia'] : null)->value,
            'inicio' => $campos['inicio'] !== null ? (string) $campos['inicio'] : null,
            'fin' => $campos['fin'] !== null ? (string) $campos['fin'] : null,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    public function cambiarEstado(int $id, EstadoAprobacion $estado): void
    {
        $this->conexion->update('campanas', ['estado_aprobacion' => $estado->value], ['id' => $id]);
    }

    public function eliminarLogicamente(int $id, ?int $usuarioId): void
    {
        $this->conexion->executeStatement(
            'UPDATE campanas SET eliminado_en = NOW(), actualizado_por = ? WHERE id = ?',
            [$usuarioId, $id],
        );
    }

    private function slugUnico(string $titulo): string
    {
        $base = Slugs::desde($titulo);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM campanas WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}
