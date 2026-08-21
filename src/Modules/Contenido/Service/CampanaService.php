<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Modules\Contenido\Entity\Campana;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Exception\TransicionEstadoInvalidaException;
use App\Modules\Contenido\Repository\CampanaRepository;
use App\Modules\Contenido\Request\CampanaDatos;
use RuntimeException;

/**
 * Casos de uso de campañas: mismo contrato que noticias y páginas.
 */
final readonly class CampanaService
{
    private const string ENTIDAD = 'campanas';

    public function __construct(
        private CampanaRepository $campanas,
        private SanitizadorHtml $sanitizador,
        private VersionadoService $versionado,
        private AuditoriaService $auditoria,
    ) {
    }

    public function crear(CampanaDatos $datos, ?int $imagenId, Actor $actor): int
    {
        $id = $this->campanas->crear($datos, $this->sanitizador->sanear($datos->contenido), $imagenId, $actor->usuarioId);

        $this->versionar($id, 'Primera versión', $actor);
        $this->auditar(AccionAuditoria::Creacion, $id, sprintf('Creó la campaña «%s»', $datos->titulo), $actor);

        return $id;
    }

    public function actualizar(int $id, CampanaDatos $datos, ?int $imagenId, Actor $actor): void
    {
        $this->exigirExistente($id);
        $this->campanas->actualizar($id, $datos, $this->sanitizador->sanear($datos->contenido), $imagenId, $actor->usuarioId);

        $version = $this->versionar($id, null, $actor);
        $this->auditar(AccionAuditoria::Actualizacion, $id, sprintf('Actualizó la campaña «%s» (versión %d)', $datos->titulo, $version), $actor);
    }

    public function cambiarEstado(int $id, EstadoAprobacion $destino, Actor $actor): void
    {
        $campana = $this->exigirExistente($id);

        if (!$campana->estado->puedeCambiarA($destino)) {
            throw TransicionEstadoInvalidaException::entre($campana->estado, $destino);
        }

        $this->campanas->cambiarEstado($id, $destino);

        $accion = match ($destino) {
            EstadoAprobacion::Publicado => AccionAuditoria::Publicacion,
            EstadoAprobacion::Borrador => $campana->estado === EstadoAprobacion::Publicado
                ? AccionAuditoria::Despublicacion
                : AccionAuditoria::CambioEstado,
            default => AccionAuditoria::CambioEstado,
        };

        $this->auditar($accion, $id, sprintf(
            '«%s»: %s → %s',
            $campana->titulo,
            $campana->estado->etiqueta(),
            $destino->etiqueta(),
        ), $actor);
    }

    public function restaurar(int $id, int $version, Actor $actor): void
    {
        $campana = $this->exigirExistente($id);
        $snapshot = $this->versionado->snapshot(self::ENTIDAD, $id, $version);

        if ($snapshot === null) {
            throw new RuntimeException(sprintf('La versión %d de la campaña %d no existe.', $version, $id));
        }

        $this->campanas->aplicarSnapshot($id, $snapshot, $actor->usuarioId);

        $nueva = $this->versionar($id, sprintf('Restauración de la versión %d', $version), $actor);
        $this->auditar(AccionAuditoria::Restauracion, $id, sprintf(
            'Restauró la campaña «%s» a la versión %d (quedó como versión %d)',
            $campana->titulo,
            $version,
            $nueva,
        ), $actor);
    }

    public function eliminar(int $id, Actor $actor): void
    {
        $campana = $this->exigirExistente($id);

        $this->campanas->eliminarLogicamente($id, $actor->usuarioId);
        $this->auditar(AccionAuditoria::Eliminacion, $id, sprintf('Envió a la papelera la campaña «%s»', $campana->titulo), $actor);
    }

    // --- soporte ---

    private function exigirExistente(int $id): Campana
    {
        $campana = $this->campanas->porId($id);

        if ($campana === null) {
            throw new RuntimeException(sprintf('La campaña %d no existe o fue eliminada.', $id));
        }

        return $campana;
    }

    private function versionar(int $id, ?string $nota, Actor $actor): int
    {
        $campana = $this->exigirExistente($id);

        return $this->versionado->registrar(self::ENTIDAD, $id, $campana->snapshot(), $nota, $actor->usuarioId);
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
