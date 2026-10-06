<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Tramites\Repository\TipoTramiteRepository;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Modules\Tramites\Request\SolicitudTramite;
use App\Modules\Tramites\Service\ProteccionSpam;
use App\Modules\Tramites\Service\RadicacionService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trámites de cara al ciudadano: listado, formulario dinámico por tipo,
 * radicación y consulta pública por radicado + documento. Sin cuenta de
 * usuario: el radicado y el documento son la llave.
 */
final readonly class TramitesPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private TipoTramiteRepository $tipos,
        private TramiteRepository $tramites,
        private RadicacionService $radicacion,
        private AlmacenArchivos $almacen,
        private ProteccionSpam $spam,
    ) {
    }

    public function indiceAtencion(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Atención y Servicios a la Ciudadanía',
            descripcion: 'Trámites, PQRSDF, consulta de radicados y canales de atención de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/tramites/atencion', [
                'tipos' => $this->tipos->activos(),
            ]),
        );
    }

    public function listado(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Trámites y servicios',
            descripcion: 'Radica tus trámites en línea: PQRSDF, citas, historia clínica, certificados y protección de datos.',
            contenido: $this->vistas->render('publico/tramites/listado', [
                'tipos' => $this->tipos->activos(),
            ]),
        );
    }

    public function formulario(Request $peticion): Response
    {
        $tipo = $this->tipos->activoPorSlug((string) $peticion->attributes->get('slug'));

        if ($tipo === null) {
            return $this->noEncontrado();
        }

        return $this->renderFormulario($tipo, null, []);
    }

    public function radicar(Request $peticion): Response
    {
        $tipo = $this->tipos->activoPorSlug((string) $peticion->attributes->get('slug'));

        if ($tipo === null) {
            return $this->noEncontrado();
        }

        $solicitud = SolicitudTramite::desdePeticion($peticion, $tipo);

        // Anti-SPAM (ITA 13.3.c). Va ANTES de validar y de guardar adjuntos:
        // no tiene sentido escribir en disco un envío que vamos a rechazar.
        //
        // Al robot se le responde como a cualquier error de validación, sin
        // decirle qué lo delató: si el mensaje nombrara el campo trampa,
        // bastaría una pasada para aprender a dejarlo vacío.
        if ($this->spam->pareceRobot($peticion)) {
            return $this->renderFormulario(
                $tipo,
                $solicitud,
                ['descripcion' => 'No pudimos procesar el formulario. Revisa los datos e intenta de nuevo.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $ip = $peticion->getClientIp();

        if ($this->spam->excedeLimite($ip)) {
            return $this->renderFormulario(
                $tipo,
                $solicitud,
                ['descripcion' => $this->spam->mensajeDeLimite()],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $this->spam->registrarIntento($ip);

        $errores = $solicitud->validar($tipo);

        // Adjuntos del ciudadano: privados (solo el panel los ve), validados
        // por contenido real como todo el almacén.
        $adjuntoIds = [];
        /** @var list<UploadedFile> $subidos */
        $subidos = array_filter((array) $peticion->files->all('adjuntos'), static fn ($a): bool => $a instanceof UploadedFile);

        if ($tipo->requiereAdjunto && $subidos === []) {
            $errores['adjuntos'] = 'Este trámite requiere al menos un archivo adjunto.';
        }

        if (count($subidos) > $tipo->maxAdjuntos) {
            $errores['adjuntos'] = sprintf('Máximo %d archivos adjuntos.', $tipo->maxAdjuntos);
        }

        if ($errores === []) {
            foreach ($subidos as $subido) {
                try {
                    $adjuntoIds[] = $this->almacen->guardar($subido, publico: false, creadoPor: null)->id;
                } catch (ArchivoInvalidoException $e) {
                    $errores['adjuntos'] = $e->getMessage();
                    break;
                }
            }
        }

        if ($errores !== []) {
            return $this->renderFormulario($tipo, $solicitud, $errores, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resultado = $this->radicacion->radicar(
            $tipo,
            $solicitud,
            $adjuntoIds,
            $peticion->getClientIp(),
            $peticion->headers->get('User-Agent'),
        );

        return new RedirectResponse(sprintf(
            '/atencion-ciudadania/tramites/%s/radicado?r=%s&v=%s',
            $tipo->slug,
            urlencode($resultado['radicado']),
            urlencode($resultado['vence_en']),
        ), Response::HTTP_SEE_OTHER);
    }

    public function radicadoExitoso(Request $peticion): Response
    {
        $tipo = $this->tipos->activoPorSlug((string) $peticion->attributes->get('slug'));
        $radicado = $peticion->query->getString('r');

        if ($tipo === null || preg_match('/^[A-Z]+-\d{4}-\d{6}$/', $radicado) !== 1) {
            return $this->noEncontrado();
        }

        return $this->layout->responder(
            titulo: 'Solicitud radicada',
            descripcion: null,
            contenido: $this->vistas->render('publico/tramites/exito', [
                'tipo' => $tipo,
                'radicado' => $radicado,
                'venceEn' => $peticion->query->getString('v'),
            ]),
            noindex: true,
        );
    }

    public function consultar(Request $peticion): Response
    {
        $resultado = null;
        $noEncontrado = false;

        // POST a propósito: el radicado y el documento no viajan en la URL
        // (quedarían en historiales y registros de servidores).
        if ($peticion->isMethod('POST')) {
            $radicado = trim($peticion->request->getString('radicado'));
            $documento = trim($peticion->request->getString('numero_documento'));

            $tramite = $this->tramites->consultaPublica($radicado, $documento);

            if ($tramite === null) {
                $noEncontrado = true;
            } else {
                $resultado = [
                    'tramite' => $tramite,
                    // Solo los seguimientos marcados visibles: las notas
                    // internas no existen para el ciudadano.
                    'seguimientos' => $this->tramites->seguimientos((int) $tramite['id'], soloPublicos: true),
                ];
            }
        }

        return $this->layout->responder(
            titulo: 'Consulta tu trámite',
            descripcion: 'Consulta el estado de tu solicitud con el número de radicado y tu documento.',
            contenido: $this->vistas->render('publico/tramites/consultar', [
                'resultado' => $resultado,
                'noEncontrado' => $noEncontrado,
            ]),
        );
    }

    /**
     * /citas y /atencion-ciudadania/pqrsdf: atajos de la home y los menús
     * hacia sus trámites.
     */
    public function atajoCitas(Request $peticion): Response
    {
        return new RedirectResponse('/atencion-ciudadania/tramites/solicitud-de-cita', Response::HTTP_FOUND);
    }

    public function atajoPqrsdf(Request $peticion): Response
    {
        return new RedirectResponse('/atencion-ciudadania/tramites/pqrsdf', Response::HTTP_FOUND);
    }

    // --- soporte ---

    /**
     * @param array<string, string> $errores
     */
    private function renderFormulario(
        \App\Modules\Tramites\Entity\TipoTramite $tipo,
        ?SolicitudTramite $enviado,
        array $errores,
        int $estado = Response::HTTP_OK,
    ): Response {
        return $this->layout->responder(
            titulo: $tipo->nombre,
            descripcion: $tipo->descripcion,
            contenido: $this->vistas->render('publico/tramites/formulario', [
                'tipo' => $tipo,
                'enviado' => $enviado,
                'errores' => $errores,
            ]),
            estado: $estado,
        );
    }

    private function noEncontrado(): Response
    {
        return $this->layout->responder(
            titulo: 'Trámite no encontrado',
            descripcion: null,
            contenido: $this->vistas->render('publico/mensaje', [
                'encabezado' => 'Trámite no encontrado',
                'mensaje' => 'El trámite que buscas no existe o dejó de estar disponible.',
            ]),
            noindex: true,
            estado: Response::HTTP_NOT_FOUND,
        );
    }
}
