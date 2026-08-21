<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Service;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Modules\Documentos\Entity\Documento;
use App\Modules\Documentos\Entity\EstadoDocumento;
use App\Modules\Documentos\Exception\PublicacionIncompletaException;
use App\Modules\Documentos\Repository\DocumentoRepository;
use App\Modules\Documentos\Request\DocumentoDatos;
use RuntimeException;

/**
 * Casos de uso de la gestión documental. La regla que no se negocia: sin
 * resumen, dependencia y fecha, el documento no sale al sitio.
 */
final readonly class DocumentoService
{
    private const string ENTIDAD = 'documentos';

    public function __construct(
        private DocumentoRepository $documentos,
        private AuditoriaService $auditoria,
    ) {
    }

    public function crear(DocumentoDatos $datos, int $archivoId, Actor $actor): int
    {
        $id = $this->documentos->crear($datos, $archivoId, $actor->usuarioId);
        $this->documentos->sincronizarEtiquetas($id, $datos->etiquetasComoLista());

        $this->auditar(AccionAuditoria::Creacion, $id, sprintf('Creó el documento «%s»', $datos->titulo), $actor);

        return $id;
    }

    public function actualizar(int $id, DocumentoDatos $datos, Actor $actor): void
    {
        $this->exigirExistente($id);

        $this->documentos->actualizar($id, $datos, $actor->usuarioId);
        $this->documentos->sincronizarEtiquetas($id, $datos->etiquetasComoLista());

        $this->auditar(AccionAuditoria::Actualizacion, $id, sprintf('Actualizó el documento «%s»', $datos->titulo), $actor);
    }

    /**
     * @throws PublicacionIncompletaException si faltan resumen, dependencia o fecha
     */
    public function publicar(int $id, Actor $actor): void
    {
        $documento = $this->exigirExistente($id);

        $faltantes = [];

        if (trim($documento->resumen) === '') {
            $faltantes[] = 'el resumen';
        }

        if ($documento->dependenciaId === null) {
            $faltantes[] = 'la dependencia responsable';
        }

        if ($documento->fechaDocumento === '') {
            $faltantes[] = 'la fecha del documento';
        }

        if ($faltantes !== []) {
            throw PublicacionIncompletaException::porFaltantes($faltantes);
        }

        // Publicar un borrador lo vuelve vigente: nadie publica "borradores".
        if ($documento->estado === EstadoDocumento::Borrador) {
            $this->documentos->cambiarEstado($id, EstadoDocumento::Vigente, $actor->usuarioId);
        }

        $this->documentos->cambiarPublicado($id, true, $actor->usuarioId);
        $this->auditar(AccionAuditoria::Publicacion, $id, sprintf('Publicó el documento «%s»', $documento->titulo), $actor);
    }

    public function despublicar(int $id, Actor $actor): void
    {
        $documento = $this->exigirExistente($id);

        $this->documentos->cambiarPublicado($id, false, $actor->usuarioId);
        $this->auditar(AccionAuditoria::Despublicacion, $id, sprintf('Retiró del sitio el documento «%s»', $documento->titulo), $actor);
    }

    public function cambiarEstado(int $id, EstadoDocumento $estado, Actor $actor): void
    {
        $documento = $this->exigirExistente($id);

        $this->documentos->cambiarEstado($id, $estado, $actor->usuarioId);
        $this->auditar(AccionAuditoria::CambioEstado, $id, sprintf(
            '«%s»: %s → %s',
            $documento->titulo,
            $documento->estado->etiqueta(),
            $estado->etiqueta(),
        ), $actor);
    }

    public function nuevaVersion(int $id, int $archivoId, ?string $notas, Actor $actor): int
    {
        $documento = $this->exigirExistente($id);

        $version = $this->documentos->nuevaVersion($id, $archivoId, $notas, $actor->usuarioId);
        $this->auditar(AccionAuditoria::Actualizacion, $id, sprintf(
            'Subió la versión %d del documento «%s»%s',
            $version,
            $documento->titulo,
            $notas !== null ? ' — ' . $notas : '',
        ), $actor);

        return $version;
    }

    public function eliminar(int $id, Actor $actor): void
    {
        $documento = $this->exigirExistente($id);

        $this->documentos->eliminarLogicamente($id, $actor->usuarioId);
        $this->auditar(AccionAuditoria::Eliminacion, $id, sprintf('Envió a la papelera el documento «%s»', $documento->titulo), $actor);
    }

    // --- soporte ---

    private function exigirExistente(int $id): Documento
    {
        $documento = $this->documentos->porId($id);

        if ($documento === null) {
            throw new RuntimeException(sprintf('El documento %d no existe o fue eliminado.', $id));
        }

        return $documento;
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
