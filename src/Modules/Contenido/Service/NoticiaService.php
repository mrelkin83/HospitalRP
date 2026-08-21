<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Entity\Noticia;
use App\Modules\Contenido\Exception\TransicionEstadoInvalidaException;
use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Contenido\Request\NoticiaDatos;
use RuntimeException;

/**
 * Casos de uso de noticias del panel. Cada mutación deja su versión en
 * `contenido_versiones` y su rastro en `auditoria`; el HTML se sanea aquí,
 * antes de tocar la base.
 */
final readonly class NoticiaService
{
    private const string ENTIDAD = 'noticias';

    public function __construct(
        private NoticiaRepository $noticias,
        private SanitizadorHtml $sanitizador,
        private VersionadoService $versionado,
        private AuditoriaService $auditoria,
    ) {
    }

    public function crear(NoticiaDatos $datos, ?int $imagenId, Actor $actor): int
    {
        $id = $this->noticias->crear(
            $datos,
            $this->sanitizador->sanear($datos->contenido),
            $imagenId,
            $actor->usuarioId,
        );

        $this->versionar($id, 'Primera versión', $actor);
        $this->auditar(AccionAuditoria::Creacion, $id, sprintf('Creó la noticia «%s»', $datos->titulo), $actor);

        return $id;
    }

    public function actualizar(int $id, NoticiaDatos $datos, ?int $imagenId, Actor $actor): void
    {
        $this->exigirExistente($id);

        $this->noticias->actualizar(
            $id,
            $datos,
            $this->sanitizador->sanear($datos->contenido),
            $imagenId,
            $actor->usuarioId,
        );

        $version = $this->versionar($id, null, $actor);
        $this->auditar(
            AccionAuditoria::Actualizacion,
            $id,
            sprintf('Actualizó la noticia «%s» (versión %d)', $datos->titulo, $version),
            $actor,
        );
    }

    public function cambiarEstado(int $id, EstadoAprobacion $destino, Actor $actor): void
    {
        $noticia = $this->exigirExistente($id);

        if (!$noticia->estado->puedeCambiarA($destino)) {
            throw TransicionEstadoInvalidaException::entre($noticia->estado, $destino);
        }

        $this->noticias->cambiarEstado($id, $destino, $actor->usuarioId);

        $accion = match ($destino) {
            EstadoAprobacion::Publicado => AccionAuditoria::Publicacion,
            EstadoAprobacion::Borrador => $noticia->estado === EstadoAprobacion::Publicado
                ? AccionAuditoria::Despublicacion
                : AccionAuditoria::CambioEstado,
            default => AccionAuditoria::CambioEstado,
        };

        $this->auditar($accion, $id, sprintf(
            '«%s»: %s → %s',
            $noticia->titulo,
            $noticia->estado->etiqueta(),
            $destino->etiqueta(),
        ), $actor);
    }

    /**
     * Criterio de aceptación de la Fase 1: restaurar funciona y queda
     * auditado. La restauración crea una versión NUEVA con el contenido de
     * la vieja: el historial nunca se reescribe.
     */
    public function restaurar(int $id, int $version, Actor $actor): void
    {
        $noticia = $this->exigirExistente($id);
        $snapshot = $this->versionado->snapshot(self::ENTIDAD, $id, $version);

        if ($snapshot === null) {
            throw new RuntimeException(sprintf('La versión %d de la noticia %d no existe.', $version, $id));
        }

        $this->noticias->aplicarSnapshot($id, $snapshot, $actor->usuarioId);

        $nueva = $this->versionar($id, sprintf('Restauración de la versión %d', $version), $actor);
        $this->auditar(AccionAuditoria::Restauracion, $id, sprintf(
            'Restauró la noticia «%s» a la versión %d (quedó como versión %d)',
            $noticia->titulo,
            $version,
            $nueva,
        ), $actor);
    }

    public function eliminar(int $id, Actor $actor): void
    {
        $noticia = $this->exigirExistente($id);

        $this->noticias->eliminarLogicamente($id, $actor->usuarioId);
        $this->auditar(AccionAuditoria::Eliminacion, $id, sprintf(
            'Envió a la papelera la noticia «%s»',
            $noticia->titulo,
        ), $actor);
    }

    // --- soporte ---

    private function exigirExistente(int $id): Noticia
    {
        $noticia = $this->noticias->porId($id);

        if ($noticia === null) {
            throw new RuntimeException(sprintf('La noticia %d no existe o fue eliminada.', $id));
        }

        return $noticia;
    }

    private function versionar(int $id, ?string $nota, Actor $actor): int
    {
        $noticia = $this->exigirExistente($id);

        return $this->versionado->registrar(
            self::ENTIDAD,
            $id,
            $noticia->snapshot(),
            $nota,
            $actor->usuarioId,
        );
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
