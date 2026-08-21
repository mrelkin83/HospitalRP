<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Repository\DependenciaRepository;
use App\Modules\Transparencia\Repository\DatasetRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Datos abiertos: conjuntos de datos con recursos en formato abierto (CSV,
 * JSON, XML, XLSX). Nunca PDF: eso es un documento, no un dato abierto.
 */
final readonly class DatasetsController
{
    private const array FORMATOS = ['csv', 'json', 'xml', 'xlsx'];
    private const array PERIODICIDADES = ['ninguna', 'mensual', 'trimestral', 'semestral', 'anual'];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private DatasetRepository $datasets,
        private DependenciaRepository $dependencias,
        private AlmacenArchivos $almacen,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/datasets/listado', [
            'datasets' => $this->datasets->listarParaPanel(),
            'dependencias' => $this->dependencias->activas(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => match (true) {
                $peticion->query->has('invalido') => 'El conjunto de datos necesita título y descripción.',
                $peticion->query->has('recurso') => 'El recurso no se pudo guardar: ' . $peticion->query->getString('recurso'),
                default => null,
            },
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $titulo = trim($r->getString('titulo'));
        $descripcion = trim($r->getString('descripcion'));

        if ($titulo === '' || $descripcion === '') {
            return new RedirectResponse('/admin/datasets?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $periodicidad = $r->getString('periodicidad');
        $campos = [
            'titulo' => mb_substr($titulo, 0, 300),
            'descripcion' => $descripcion,
            'dependencia_id' => $r->getString('dependencia_id') !== '' ? $r->getInt('dependencia_id') : null,
            'periodicidad' => in_array($periodicidad, self::PERIODICIDADES, true) ? $periodicidad : 'anual',
            'cobertura_desde' => $r->getString('cobertura_desde') !== '' ? $r->getString('cobertura_desde') : null,
            'cobertura_hasta' => $r->getString('cobertura_hasta') !== '' ? $r->getString('cobertura_hasta') : null,
            'publicado' => $r->getBoolean('publicado') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->datasets->porId($id) === null) {
                return new RedirectResponse('/admin/datasets', Response::HTTP_SEE_OTHER);
            }

            $this->datasets->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->datasets->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->auditar($peticion, $accion, $id, sprintf(
            '%s el conjunto de datos «%s»%s',
            $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
            $titulo,
            $campos['publicado'] === 1 ? ' (publicado)' : '',
        ));

        return new RedirectResponse('/admin/datasets?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function agregarRecurso(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $datasetId = $peticion->attributes->getInt('id');

        if ($this->datasets->porId($datasetId) === null) {
            return new RedirectResponse('/admin/datasets', Response::HTTP_SEE_OTHER);
        }

        $r = $peticion->request;
        $formato = $r->getString('formato');

        if (!in_array($formato, self::FORMATOS, true)) {
            return new RedirectResponse('/admin/datasets?recurso=' . urlencode('elige el formato.'), Response::HTTP_SEE_OTHER);
        }

        $archivo = $peticion->files->get('archivo');
        $url = trim($r->getString('url_externa'));
        $archivoId = null;

        if ($archivo instanceof UploadedFile) {
            // El formato elegido debe coincidir con la extensión real del
            // archivo: un "CSV" que es otra cosa confunde al reutilizador.
            if (strtolower($archivo->getClientOriginalExtension()) !== $formato) {
                return new RedirectResponse('/admin/datasets?recurso=' . urlencode(sprintf(
                    'el archivo no es .%s.',
                    $formato,
                )), Response::HTTP_SEE_OTHER);
            }

            try {
                $archivoId = $this->almacen->guardar($archivo, publico: true, creadoPor: $this->sesion->usuarioId())->id;
            } catch (ArchivoInvalidoException $e) {
                return new RedirectResponse('/admin/datasets?recurso=' . urlencode($e->getMessage()), Response::HTTP_SEE_OTHER);
            }
        } elseif ($url === '') {
            return new RedirectResponse('/admin/datasets?recurso=' . urlencode('adjunta un archivo o indica una URL.'), Response::HTTP_SEE_OTHER);
        }

        $id = $this->datasets->crearRecurso([
            'dataset_id' => $datasetId,
            'formato' => $formato,
            'archivo_id' => $archivoId,
            'url_externa' => $archivoId === null ? $url : null,
        ]);

        $this->auditar($peticion, AccionAuditoria::Creacion, $id, sprintf(
            'Añadió un recurso %s al conjunto de datos %d',
            strtoupper($formato),
            $datasetId,
        ));

        return new RedirectResponse('/admin/datasets?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminarRecurso(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $this->datasets->eliminarRecurso($id);
        $this->auditar($peticion, AccionAuditoria::Eliminacion, $id, sprintf('Eliminó el recurso %d de datos abiertos', $id));

        return new RedirectResponse('/admin/datasets?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'datasets',
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/datasets',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Datos abiertos',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
