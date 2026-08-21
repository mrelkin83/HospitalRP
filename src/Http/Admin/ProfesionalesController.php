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
use App\Modules\Directorio\Repository\EspecialidadRepository;
use App\Modules\Directorio\Repository\ProfesionalRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directorio médico. Publica datos personales: sin la casilla de
 * autorización marcada, el profesional NO existe para el sitio público, y
 * marcar o retirar esa autorización queda auditado (Ley 1581).
 */
final readonly class ProfesionalesController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private ProfesionalRepository $profesionales,
        private EspecialidadRepository $especialidades,
        private AlmacenArchivos $almacen,
        private AuditoriaService $auditoria,
        private IndexadorService $indexador,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/profesionales/listado', [
            'profesionales' => $this->profesionales->listarParaPanel(),
            'especialidades' => $this->especialidades->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => match (true) {
                $peticion->query->has('invalido') => 'El profesional necesita nombre.',
                $peticion->query->has('foto') => 'La foto no se pudo guardar: ' . $peticion->query->getString('foto'),
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

        if ($nombre === '') {
            return new RedirectResponse('/admin/profesionales?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 200),
            'registro_rethus' => $r->getString('registro_rethus') !== '' ? trim($r->getString('registro_rethus')) : null,
            'perfil' => $r->getString('perfil') !== '' ? trim($r->getString('perfil')) : null,
            'autoriza_publicacion' => $r->getBoolean('autoriza_publicacion') ? 1 : 0,
            'orden' => $r->getInt('orden'),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
        ];

        $foto = $peticion->files->get('foto');

        if ($foto instanceof UploadedFile) {
            try {
                $campos['foto_id'] = $this->almacen->guardar(
                    $foto,
                    publico: true,
                    creadoPor: $this->sesion->usuarioId(),
                    textoAlt: sprintf('Foto de %s', $nombre),
                )->id;
            } catch (ArchivoInvalidoException $e) {
                return new RedirectResponse('/admin/profesionales?foto=' . urlencode($e->getMessage()), Response::HTTP_SEE_OTHER);
            }
        }

        /** @var list<int> $especialidadIds */
        $especialidadIds = array_map(intval(...), (array) $r->all('especialidades'));

        $id = $peticion->attributes->getInt('id');
        $anterior = $id > 0 ? $this->profesionales->porId($id) : null;

        if ($id > 0) {
            if ($anterior === null) {
                return new RedirectResponse('/admin/profesionales', Response::HTTP_SEE_OTHER);
            }

            $this->profesionales->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->profesionales->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->profesionales->sincronizarEspecialidades($id, $especialidadIds);

        // La autorización es el dato jurídicamente sensible: su estado —y todo
        // cambio— queda explícito en la auditoría.
        $autoriza = $campos['autoriza_publicacion'] === 1;
        $cambioAutorizacion = $anterior !== null && $anterior->autorizaPublicacion !== $autoriza;

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'profesionales',
            entidadId: $id,
            descripcion: sprintf(
                '%s a «%s» — publicación %s%s',
                $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
                $nombre,
                $autoriza ? 'AUTORIZADA' : 'NO autorizada',
                $cambioAutorizacion ? ' (CAMBIÓ la autorización)' : '',
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        $this->indexador->reindexar(TipoIndice::Profesional, $id);

        return new RedirectResponse('/admin/profesionales?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/profesionales',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Directorio médico',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
