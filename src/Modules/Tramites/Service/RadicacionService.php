<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Config\ConfigDinamica;
use App\Modules\Tramites\Entity\TipoTramite;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Modules\Tramites\Request\SolicitudTramite;
use App\Ports\NotificadorCorreo;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Radicación de un trámite: en UNA transacción quedan el radicado (único),
 * el trámite con su vencimiento legal, los valores dinámicos, los adjuntos
 * y la evidencia de autorización de datos. La auditoría y el correo van
 * después: si el correo falla, la radicación NO se pierde.
 */
final readonly class RadicacionService
{
    public function __construct(
        private Connection $conexion,
        private TramiteRepository $tramites,
        private GeneradorRadicado $radicados,
        private CalculadoraDiasHabiles $diasHabiles,
        private AuditoriaService $auditoria,
        private NotificadorCorreo $correo,
        private ConfigDinamica $config,
    ) {
    }

    /**
     * @param list<int> $adjuntoIds Archivos ya guardados por el almacén
     * @return array{radicado: string, vence_en: string, tramite_id: int}
     */
    public function radicar(
        TipoTramite $tipo,
        SolicitudTramite $solicitud,
        array $adjuntoIds,
        ?string $ip,
        ?string $userAgent,
    ): array {
        // Defensa en profundidad: el formulario ya validó, pero ninguna ruta
        // de código puede radicar sin autorización de datos (Ley 1581).
        if (!$solicitud->autorizaDatos) {
            throw new DomainException('No se puede radicar sin autorización de tratamiento de datos.');
        }

        $estadoInicial = $tipo->estadoInicial();

        if ($estadoInicial === null) {
            throw new RuntimeException(sprintf('El tipo «%s» no tiene estado inicial configurado.', $tipo->nombre));
        }

        $politicaId = $this->politicaActivaId();
        $recibido = new DateTimeImmutable();
        $venceEn = $this->diasHabiles->vencimiento($recibido, $tipo->diasTermino, $tipo->diasHabiles);
        $ipBinaria = $ip !== null ? (inet_pton($ip) ?: null) : null;

        $resultado = $this->conexion->transactional(function () use (
            $tipo, $solicitud, $adjuntoIds, $estadoInicial, $politicaId, $recibido, $venceEn, $ipBinaria, $userAgent,
        ): array {
            $radicado = $this->radicados->siguiente($tipo->prefijoRadicado, (int) $recibido->format('Y'));

            $tramiteId = $this->tramites->crear([
                'radicado' => $radicado,
                'tipo_tramite_id' => $tipo->id,
                'canal' => 'web',
                'anonimo' => $solicitud->anonimo ? 1 : 0,
                'nombre' => $solicitud->anonimo ? null : $solicitud->nombre,
                'tipo_documento' => $solicitud->anonimo ? null : $solicitud->tipoDocumento,
                'numero_documento' => $solicitud->anonimo ? null : $solicitud->numeroDocumento,
                'correo' => $solicitud->anonimo ? null : $solicitud->correo,
                'telefono' => $solicitud->anonimo ? null : $solicitud->telefono,
                'direccion' => $solicitud->anonimo ? null : $solicitud->direccion,
                'barrio' => $solicitud->anonimo ? null : $solicitud->barrio,
                'municipio' => $solicitud->anonimo ? null : $solicitud->municipio,
                'pais' => $solicitud->anonimo ? null : $solicitud->pais,
                'asunto' => $solicitud->asunto,
                'descripcion' => $solicitud->descripcion,
                'dependencia_id' => $tipo->dependenciaId,
                'estado_id' => $estadoInicial['id'],
                'medio_respuesta' => $solicitud->medioRespuesta,
                'recibido_en' => $recibido->format('Y-m-d H:i:s'),
                'dias_termino' => $tipo->diasTermino,
                'vence_en' => $venceEn->format('Y-m-d'),
                'ip' => $ipBinaria,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            ]);

            foreach ($solicitud->valores as $campoId => $valor) {
                $this->tramites->guardarValor($tramiteId, $campoId, $valor);
            }

            foreach ($adjuntoIds as $archivoId) {
                $this->tramites->guardarAdjunto($tramiteId, $archivoId, 'ciudadano');
            }

            // Evidencia de la autorización: qué política, cuándo, desde dónde
            // (Ley 1581; CLAUDE.md §6, Datos personales).
            $this->conexion->insert('autorizaciones_datos', [
                'origen' => 'tramite',
                'origen_id' => $tramiteId,
                'numero_documento' => $solicitud->anonimo ? null : $solicitud->numeroDocumento,
                'correo' => $solicitud->anonimo ? null : $solicitud->correo,
                'politica_id' => $politicaId,
                'finalidad' => sprintf('Gestión y respuesta del trámite «%s»', $tipo->nombre),
                'aceptado' => 1,
                'ip' => $ipBinaria ?? "\0\0\0\0",
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            ]);

            $this->tramites->agregarSeguimiento(
                $tramiteId,
                null,
                $estadoInicial['id'],
                'Solicitud radicada por el sitio web.',
                visibleCiudadano: true,
                usuarioId: null,
            );

            return ['radicado' => $radicado, 'vence_en' => $venceEn->format('Y-m-d'), 'tramite_id' => $tramiteId];
        });

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Creacion,
            entidad: 'tramites',
            entidadId: $resultado['tramite_id'],
            descripcion: sprintf(
                'Radicado %s (%s)%s',
                $resultado['radicado'],
                $tipo->nombre,
                $solicitud->anonimo ? ' — anónimo' : '',
            ),
            usuarioNombre: 'ciudadano (web)',
            ip: $ip,
            userAgent: $userAgent,
        ));

        $this->notificarSinRomper($tipo, $solicitud, $resultado);
        $this->procesarRevocacionSiAplica($tipo, $solicitud, $resultado);

        return $resultado;
    }

    /**
     * Criterio de la Fase 6: revocar una autorización queda registrado y
     * notifica al responsable. Al radicar la revocatoria de habeas data se
     * marcan `revocado_en` las autorizaciones previas del mismo documento
     * (salvo la de este trámite: sin ella no se podría tramitar la propia
     * revocatoria) y se avisa al oficial de cumplimiento.
     *
     * Modelo de confianza: la identidad NO se verifica en línea (mismo
     * modelo que la consulta pública). Marcar `revocado_en` no destruye la
     * evidencia —la fila queda intacta, con su aceptación original— y
     * revocar en falso es el error conservador para la privacidad; el
     * hospital verifica la identidad al responder el trámite por la bandeja
     * antes de suprimir dato alguno.
     *
     * @param array{radicado: string, vence_en: string, tramite_id: int} $resultado
     */
    private function procesarRevocacionSiAplica(TipoTramite $tipo, SolicitudTramite $solicitud, array $resultado): void
    {
        if ($tipo->slug !== 'habeas-data'
            || $solicitud->numeroDocumento === null
            || !in_array('Revocar mi autorización', $solicitud->valores, true)
        ) {
            return;
        }

        $revocadas = $this->conexion->executeStatement(
            'UPDATE autorizaciones_datos SET revocado_en = NOW()
              WHERE numero_documento = ? AND revocado_en IS NULL AND (origen_id IS NULL OR origen_id <> ? OR origen <> \'tramite\')',
            [$solicitud->numeroDocumento, $resultado['tramite_id']],
        );

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::RevocacionAutorizacion,
            entidad: 'autorizaciones_datos',
            entidadId: $resultado['tramite_id'],
            descripcion: sprintf(
                'Revocatoria radicada (%s): %d autorizaciones marcadas como revocadas',
                $resultado['radicado'],
                (int) $revocadas,
            ),
            usuarioNombre: 'ciudadano (web)',
        ));

        $responsable = $this->config->texto('contacto.linea_etica_correo', $this->config->texto('contacto.correo'));

        if ($responsable === '') {
            return;
        }

        try {
            $this->correo->enviar(
                $responsable,
                sprintf('[Habeas data] Revocatoria de autorización — %s', $resultado['radicado']),
                sprintf(
                    "Se radicó una revocatoria de autorización de tratamiento de datos.\n\n"
                    . "Radicado: %s\nAutorizaciones marcadas como revocadas: %d\n\n"
                    . "Gestión: https://hrplopez.gov.co/admin/tramites",
                    $resultado['radicado'],
                    (int) $revocadas,
                ),
            );
        } catch (Throwable $e) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::CambioEstado,
                entidad: 'tramites',
                entidadId: $resultado['tramite_id'],
                descripcion: sprintf('FALLO al notificar la revocatoria: %s', mb_substr($e->getMessage(), 0, 300)),
                usuarioNombre: 'sistema',
            ));
        }
    }

    // --- soporte ---

    private function politicaActivaId(): int
    {
        $id = $this->conexion->fetchOne(
            "SELECT id FROM politicas WHERE tipo = 'tratamiento_datos' AND activa = 1 ORDER BY id DESC LIMIT 1",
        );

        if ($id === false) {
            throw new RuntimeException(
                'No hay política de tratamiento de datos activa: sin política no hay autorización válida. Corre semillas:tramites.',
            );
        }

        return (int) $id;
    }

    /**
     * @param array{radicado: string, vence_en: string, tramite_id: int} $resultado
     */
    private function notificarSinRomper(TipoTramite $tipo, SolicitudTramite $solicitud, array $resultado): void
    {
        if ($solicitud->anonimo || $solicitud->correo === null) {
            return;
        }

        try {
            $this->correo->enviar(
                $solicitud->correo,
                sprintf('Radicado %s — %s', $resultado['radicado'], $tipo->nombre),
                sprintf(
                    "Hola %s:\n\nTu solicitud «%s» quedó radicada con el número %s.\n\n"
                    . "Fecha límite de respuesta: %s.\n\n"
                    . "Puedes consultar el estado en cualquier momento con tu radicado y tu número de documento en:\n"
                    . "https://hrplopez.gov.co/atencion-ciudadania/pqrsdf/consultar\n\n"
                    . "E.S.E. Hospital Rosario Pumarejo de López",
                    $solicitud->nombre ?? '',
                    $solicitud->asunto,
                    $resultado['radicado'],
                    $resultado['vence_en'],
                ),
            );
        } catch (Throwable $e) {
            // El correo caído no puede tumbar una radicación: se audita y sigue.
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::CambioEstado,
                entidad: 'tramites',
                entidadId: $resultado['tramite_id'],
                descripcion: sprintf('FALLO al enviar correo de radicación: %s', mb_substr($e->getMessage(), 0, 300)),
                usuarioNombre: 'sistema',
            ));
        }
    }
}
