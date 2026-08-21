<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Modules\Tramites\Repository\TipoTramiteRepository;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Ports\NotificadorCorreo;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Gestión de la bandeja: cambio de estado con nota (pública o interna),
 * asignación y notificación al ciudadano. Todo queda en el seguimiento y
 * en la auditoría.
 */
final readonly class GestionTramiteService
{
    public function __construct(
        private TramiteRepository $tramites,
        private TipoTramiteRepository $tipos,
        private CalculadoraDiasHabiles $diasHabiles,
        private AuditoriaService $auditoria,
        private NotificadorCorreo $correo,
    ) {
    }

    public function cambiarEstado(int $tramiteId, int $estadoNuevoId, ?string $nota, bool $visibleCiudadano, Actor $actor): void
    {
        $tramite = $this->exigirTramite($tramiteId);
        $tipo = $this->tipos->porId((int) $tramite['tipo_tramite_id']);

        if ($tipo === null) {
            throw new RuntimeException('El tipo del trámite no existe.');
        }

        $estadoNuevo = null;

        foreach ($tipo->estados as $estado) {
            if ($estado['id'] === $estadoNuevoId) {
                $estadoNuevo = $estado;
            }
        }

        if ($estadoNuevo === null) {
            throw new RuntimeException('El estado no pertenece a este tipo de trámite.');
        }

        $campos = ['estado_id' => $estadoNuevoId];
        $ahora = new DateTimeImmutable();

        if ($estadoNuevo['codigo'] === 'respondido' && $tramite['respondido_en'] === null) {
            $campos['respondido_en'] = $ahora->format('Y-m-d H:i:s');
            $campos['dias_habiles_usados'] = $this->diasHabiles->habilesEntre(
                new DateTimeImmutable((string) $tramite['recibido_en']),
                $ahora,
            );
        }

        if ($estadoNuevo['es_final']) {
            $campos['cerrado_en'] = $ahora->format('Y-m-d H:i:s');
        }

        $this->tramites->actualizar($tramiteId, $campos);
        $this->tramites->agregarSeguimiento(
            $tramiteId,
            (int) $tramite['estado_id'],
            $estadoNuevoId,
            $nota,
            $visibleCiudadano,
            $actor->usuarioId,
        );

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::CambioEstado,
            entidad: 'tramites',
            entidadId: $tramiteId,
            descripcion: sprintf(
                '%s: %s → %s%s',
                (string) $tramite['radicado'],
                (string) $tramite['estado_nombre'],
                $estadoNuevo['nombre'],
                $visibleCiudadano ? '' : ' (nota interna)',
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        // Notificar al ciudadano si el estado lo pide y hay a quién.
        if ($estadoNuevo['notifica_ciudadano']
            && ((int) $tramite['anonimo']) !== 1
            && $tramite['correo'] !== null
        ) {
            try {
                $this->correo->enviar(
                    (string) $tramite['correo'],
                    sprintf('Actualización de tu radicado %s', (string) $tramite['radicado']),
                    sprintf(
                        "Hola %s:\n\nTu solicitud con radicado %s cambió de estado: ahora está «%s».\n%s%s\n"
                        . "Consulta el detalle con tu radicado y tu documento en:\n"
                        . "https://hrplopez.gov.co/atencion-ciudadania/pqrsdf/consultar\n\n"
                        . "E.S.E. Hospital Rosario Pumarejo de López",
                        (string) ($tramite['nombre'] ?? ''),
                        (string) $tramite['radicado'],
                        $estadoNuevo['nombre'],
                        $estadoNuevo['descripcion_publica'] !== null ? $estadoNuevo['descripcion_publica'] . "\n" : '',
                        $visibleCiudadano && $nota !== null && $nota !== '' ? "\nNota del hospital: " . $nota . "\n" : '',
                    ),
                );
            } catch (Throwable $e) {
                $this->auditoria->registrar(new RegistroAuditoria(
                    accion: AccionAuditoria::CambioEstado,
                    entidad: 'tramites',
                    entidadId: $tramiteId,
                    descripcion: sprintf('FALLO al notificar cambio de estado: %s', mb_substr($e->getMessage(), 0, 300)),
                    usuarioNombre: 'sistema',
                ));
            }
        }
    }

    public function asignar(int $tramiteId, ?int $usuarioId, ?int $dependenciaId, Actor $actor): void
    {
        $tramite = $this->exigirTramite($tramiteId);

        $this->tramites->actualizar($tramiteId, [
            'asignado_a' => $usuarioId,
            'dependencia_id' => $dependenciaId,
        ]);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Actualizacion,
            entidad: 'tramites',
            entidadId: $tramiteId,
            descripcion: sprintf('%s: asignación actualizada', (string) $tramite['radicado']),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }

    public function agregarNota(int $tramiteId, string $nota, bool $visibleCiudadano, Actor $actor): void
    {
        $tramite = $this->exigirTramite($tramiteId);

        $this->tramites->agregarSeguimiento($tramiteId, null, null, $nota, $visibleCiudadano, $actor->usuarioId);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Actualizacion,
            entidad: 'tramites',
            entidadId: $tramiteId,
            descripcion: sprintf(
                '%s: nota %s agregada',
                (string) $tramite['radicado'],
                $visibleCiudadano ? 'pública' : 'interna',
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function exigirTramite(int $id): array
    {
        $tramite = $this->tramites->porId($id);

        if ($tramite === null) {
            throw new RuntimeException(sprintf('El trámite %d no existe.', $id));
        }

        return $tramite;
    }
}
