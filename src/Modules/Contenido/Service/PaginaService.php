<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Entity\Pagina;
use App\Modules\Contenido\Exception\TransicionEstadoInvalidaException;
use App\Modules\Contenido\Repository\PaginaRepository;
use App\Modules\Contenido\Request\PaginaDatos;
use RuntimeException;

/**
 * Casos de uso de páginas institucionales: mismo contrato que las noticias
 * (saneado antes de guardar, versión por guardado, auditoría por acción).
 */
final readonly class PaginaService
{
    private const string ENTIDAD = 'paginas';

    public function __construct(
        private PaginaRepository $paginas,
        private SanitizadorHtml $sanitizador,
        private VersionadoService $versionado,
        private AuditoriaService $auditoria,
    ) {
    }

    public function crear(PaginaDatos $datos, ?int $imagenId, Actor $actor): int
    {
        $id = $this->paginas->crear(
            $datos,
            $this->sanitizador->sanear($datos->contenido),
            $imagenId,
            $actor->usuarioId,
        );

        $this->versionar($id, 'Primera versión', $actor);
        $this->auditar(AccionAuditoria::Creacion, $id, sprintf('Creó la página «%s» (%s)', $datos->titulo, $datos->ruta), $actor);

        return $id;
    }

    public function actualizar(int $id, PaginaDatos $datos, ?int $imagenId, Actor $actor): void
    {
        $this->exigirExistente($id);

        $this->paginas->actualizar(
            $id,
            $datos,
            $this->sanitizador->sanear($datos->contenido),
            $imagenId,
            $actor->usuarioId,
        );

        $version = $this->versionar($id, null, $actor);
        $this->auditar(AccionAuditoria::Actualizacion, $id, sprintf(
            'Actualizó la página «%s» (versión %d)',
            $datos->titulo,
            $version,
        ), $actor);
    }

    public function cambiarEstado(int $id, EstadoAprobacion $destino, Actor $actor): void
    {
        $pagina = $this->exigirExistente($id);

        if (!$pagina->estado->puedeCambiarA($destino)) {
            throw TransicionEstadoInvalidaException::entre($pagina->estado, $destino);
        }

        $this->paginas->cambiarEstado($id, $destino, $actor->usuarioId);

        $accion = match ($destino) {
            EstadoAprobacion::Publicado => AccionAuditoria::Publicacion,
            EstadoAprobacion::Borrador => $pagina->estado === EstadoAprobacion::Publicado
                ? AccionAuditoria::Despublicacion
                : AccionAuditoria::CambioEstado,
            default => AccionAuditoria::CambioEstado,
        };

        $this->auditar($accion, $id, sprintf(
            '«%s»: %s → %s',
            $pagina->titulo,
            $pagina->estado->etiqueta(),
            $destino->etiqueta(),
        ), $actor);
    }

    public function restaurar(int $id, int $version, Actor $actor): void
    {
        $pagina = $this->exigirExistente($id);
        $snapshot = $this->versionado->snapshot(self::ENTIDAD, $id, $version);

        if ($snapshot === null) {
            throw new RuntimeException(sprintf('La versión %d de la página %d no existe.', $version, $id));
        }

        $this->paginas->aplicarSnapshot($id, $snapshot, $actor->usuarioId);

        $nueva = $this->versionar($id, sprintf('Restauración de la versión %d', $version), $actor);
        $this->auditar(AccionAuditoria::Restauracion, $id, sprintf(
            'Restauró la página «%s» a la versión %d (quedó como versión %d)',
            $pagina->titulo,
            $version,
            $nueva,
        ), $actor);
    }

    /**
     * @return bool false si la página está protegida por normativa
     */
    public function eliminar(int $id, Actor $actor): bool
    {
        $pagina = $this->exigirExistente($id);

        if (!$this->paginas->eliminarLogicamente($id, $actor->usuarioId)) {
            return false;
        }

        $this->auditar(AccionAuditoria::Eliminacion, $id, sprintf(
            'Envió a la papelera la página «%s» (%s)',
            $pagina->titulo,
            $pagina->ruta,
        ), $actor);

        return true;
    }

    // --- soporte ---

    private function exigirExistente(int $id): Pagina
    {
        $pagina = $this->paginas->porId($id);

        if ($pagina === null) {
            throw new RuntimeException(sprintf('La página %d no existe o fue eliminada.', $id));
        }

        return $pagina;
    }

    private function versionar(int $id, ?string $nota, Actor $actor): int
    {
        $pagina = $this->exigirExistente($id);

        return $this->versionado->registrar(self::ENTIDAD, $id, $pagina->snapshot(), $nota, $actor->usuarioId);
    }

    private function auditar(AccionAuditoria $accion, int $id, string $descripcion, Actor $actor): void
    {
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: self::ENTIDAD,
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }
}
