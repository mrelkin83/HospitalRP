<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Modules\Busqueda\Entity\TipoIndice;
use App\Modules\Busqueda\Service\IndexadorService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\SedeRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sedes del hospital: dirección, teléfonos, horarios, coordenadas y la
 * bandera de urgencias que alimenta la home y la página de urgencias.
 */
final readonly class SedesController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private SedeRepository $sedes,
        private AlmacenArchivos $almacen,
        private AuditoriaService $auditoria,
        private IndexadorService $indexador,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/sedes/listado', [
            'sedes' => $this->sedes->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => match (true) {
                $peticion->query->has('invalido') => 'La sede necesita nombre, dirección y municipio.',
                $peticion->query->has('imagen') => 'La imagen no se pudo guardar: ' . $peticion->query->getString('imagen'),
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
        $nombre = trim($r->getString('nombre'));
        $direccion = trim($r->getString('direccion'));
        $municipio = trim($r->getString('municipio'));

        if ($nombre === '' || $direccion === '' || $municipio === '') {
            return new RedirectResponse('/admin/sedes?invalido=1', Response::HTTP_SEE_OTHER);
        }

        // El horario se escribe una línea por franja y se guarda como JSON.
        $horario = array_values(array_filter(array_map(
            trim(...),
            explode("\n", $r->getString('horario')),
        ), static fn (string $l): bool => $l !== ''));

        $campos = [
            'nombre' => mb_substr($nombre, 0, 160),
            'descripcion' => $r->getString('descripcion') !== '' ? trim($r->getString('descripcion')) : null,
            'direccion' => mb_substr($direccion, 0, 255),
            'municipio' => mb_substr($municipio, 0, 120),
            'departamento' => trim($r->getString('departamento')) !== '' ? trim($r->getString('departamento')) : 'Cesar',
            'telefono' => $r->getString('telefono') !== '' ? trim($r->getString('telefono')) : null,
            'correo' => $r->getString('correo') !== '' ? trim($r->getString('correo')) : null,
            'horario' => json_encode($horario, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'latitud' => $r->getString('latitud') !== '' ? trim($r->getString('latitud')) : null,
            'longitud' => $r->getString('longitud') !== '' ? trim($r->getString('longitud')) : null,
            'tiene_urgencias' => $r->getBoolean('tiene_urgencias') ? 1 : 0,
            'orden' => $r->getInt('orden'),
            'activa' => $r->getBoolean('activa') ? 1 : 0,
        ];

        // Imagen opcional; exige texto alternativo como toda la biblioteca.
        $imagen = $peticion->files->get('imagen');

        if ($imagen instanceof UploadedFile) {
            $textoAlt = trim($r->getString('imagen_alt'));

            if ($textoAlt === '') {
                return new RedirectResponse('/admin/sedes?imagen=' . urlencode('describe la imagen (texto alternativo).'), Response::HTTP_SEE_OTHER);
            }

            try {
                $campos['imagen_id'] = $this->almacen->guardar($imagen, publico: true, creadoPor: $this->sesion->usuarioId(), textoAlt: $textoAlt)->id;
            } catch (ArchivoInvalidoException $e) {
                return new RedirectResponse('/admin/sedes?imagen=' . urlencode($e->getMessage()), Response::HTTP_SEE_OTHER);
            }
        }

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->sedes->porId($id) === null) {
                return new RedirectResponse('/admin/sedes', Response::HTTP_SEE_OTHER);
            }

            $this->sedes->actualizar($id, $campos);
            $this->auditar($peticion, AccionAuditoria::Actualizacion, $id, sprintf('Editó la sede «%s»', $nombre));
        } else {
            $id = $this->sedes->crear($campos);
            $this->auditar($peticion, AccionAuditoria::Creacion, $id, sprintf('Creó la sede «%s»', $nombre));
        }

        $this->indexador->reindexar(TipoIndice::Sede, $id);

        return new RedirectResponse('/admin/sedes?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'sedes',
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
            'volverA' => '/admin/sedes',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Sedes',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
