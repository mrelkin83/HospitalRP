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
use App\Modules\Directorio\Repository\ServicioRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicios asistenciales (laboratorio, imágenes, farmacia…) y sus sedes.
 */
final readonly class ServiciosController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private ServicioRepository $servicios,
        private SedeRepository $sedes,
        private AuditoriaService $auditoria,
        private IndexadorService $indexador,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/servicios/listado', [
            'servicios' => $this->servicios->listarParaPanel(),
            'sedes' => $this->sedes->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $this->errorDe($peticion),
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

        if ($nombre === '') {
            return new RedirectResponse('/admin/servicios?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 160),
            'descripcion' => $r->getString('descripcion') !== '' ? trim($r->getString('descripcion')) : null,
            'requisitos' => $r->getString('requisitos') !== '' ? trim($r->getString('requisitos')) : null,
            'destacados' => $this->destacadosDe($r->getString('destacados')),
            'horario' => $r->getString('horario') !== '' ? mb_substr(trim($r->getString('horario')), 0, 500) : null,
            'telefono' => $r->getString('telefono') !== '' ? trim($r->getString('telefono')) : null,
            'orden' => $r->getInt('orden'),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
        ];

        /** @var list<int> $sedeIds */
        $sedeIds = array_map(intval(...), (array) $r->all('sedes'));

        $id = $peticion->attributes->getInt('id');

        $errorImagen = null;
        $imagenId = $this->procesarImagen($peticion, $errorImagen);

        if ($errorImagen !== null) {
            return new RedirectResponse('/admin/servicios?' . $errorImagen . '=1', Response::HTTP_SEE_OTHER);
        }

        if ($imagenId !== null) {
            $campos['imagen_id'] = $imagenId;
        }

        if ($id > 0) {
            if ($this->servicios->porId($id) === null) {
                return new RedirectResponse('/admin/servicios', Response::HTTP_SEE_OTHER);
            }

            $this->servicios->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->servicios->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->servicios->sincronizarSedes($id, $sedeIds);

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'servicios',
            entidadId: $id,
            descripcion: sprintf(
                '%s el servicio «%s» (%d sedes)',
                $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
                $nombre,
                count($sedeIds),
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        $this->indexador->reindexar(TipoIndice::Servicio, $id);

        return new RedirectResponse('/admin/servicios?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * Foto del servicio. Devuelve null cuando no se subió nada: el llamador
     * deja entonces la imagen que ya tuviera, para que guardar un cambio de
     * horario no borre la foto.
     *
     * El texto alternativo es obligatorio cuando hay foto (CLAUDE.md §6,
     * Frontend: `alt` en toda imagen). Sin él no se guarda el archivo.
     */
    private function procesarImagen(Request $peticion, ?string &$error): ?int
    {
        $imagen = $peticion->files->get('imagen');

        if (!$imagen instanceof UploadedFile) {
            return null;
        }

        $textoAlt = trim($peticion->request->getString('imagen_alt'));

        if ($textoAlt === '') {
            $error = 'alt';

            return null;
        }

        try {
            return $this->almacen
                ->guardar($imagen, publico: true, creadoPor: $this->sesion->usuarioId(), textoAlt: $textoAlt)
                ->id;
        } catch (ArchivoInvalidoException) {
            $error = 'imagen';

            return null;
        }
    }

    /**
     * Cifras destacadas, una por línea, con la forma `21 | consultorios`.
     *
     * Se escriben así y no en un formulario repetible porque son dos o tres
     * líneas por servicio y un funcionario las escribe más rápido en un cuadro
     * de texto que añadiendo filas (CLAUDE.md §6, Panel). Una línea sin barra
     * se guarda completa como texto, sin cifra: no se pierde lo que escribió.
     */
    private function destacadosDe(string $texto): ?string
    {
        $destacados = [];

        foreach (preg_split('/\R/u', trim($texto)) ?: [] as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            [$cifra, $resto] = array_pad(explode('|', $linea, 2), 2, null);

            $destacados[] = $resto === null
                ? ['cifra' => '', 'texto' => mb_substr($cifra, 0, 160)]
                : ['cifra' => mb_substr(trim($cifra), 0, 20), 'texto' => mb_substr(trim($resto), 0, 160)];
        }

        return $destacados === []
            ? null
            : json_encode($destacados, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function errorDe(Request $peticion): ?string
    {
        return match (true) {
            $peticion->query->has('invalido') => 'El servicio necesita nombre.',
            $peticion->query->has('alt') => 'Describe la foto en una frase: es lo que escucha una persona ciega y lo que ve quien navega sin imágenes.',
            $peticion->query->has('imagen') => 'No se pudo guardar la foto. Debe ser JPG, PNG o WebP y pesar menos de 20 MB.',
            default => null,
        };
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
            'volverA' => '/admin/servicios',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Servicios',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
