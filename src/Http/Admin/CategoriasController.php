<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Slugs;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\CategoriaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Categorías de noticias, campañas, documentos y FAQ.
 */
final readonly class CategoriasController
{
    private const array TIPOS = ['noticia', 'documento', 'campana', 'faq'];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private CategoriaRepository $categorias,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/categorias/listado', [
            'categorias' => $this->categorias->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'La categoría necesita nombre y tipo.' : null,
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
        $tipo = $r->getString('tipo');

        if ($nombre === '' || !in_array($tipo, self::TIPOS, true)) {
            return new RedirectResponse('/admin/categorias?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 120),
            'orden' => $r->getInt('orden'),
            'activa' => $r->getBoolean('activa') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->categorias->porId($id) === null) {
                return new RedirectResponse('/admin/categorias', Response::HTTP_SEE_OTHER);
            }

            // El tipo y el slug no cambian al editar: hay contenido colgando de ellos.
            $this->categorias->actualizar($id, $campos);
            $this->auditar($peticion, AccionAuditoria::Actualizacion, $id, sprintf('Editó la categoría «%s»', $nombre));
        } else {
            $campos['tipo'] = $tipo;
            $campos['slug'] = Slugs::desde($nombre, 130);
            $id = $this->categorias->crear($campos);
            $this->auditar($peticion, AccionAuditoria::Creacion, $id, sprintf('Creó la categoría «%s» (%s)', $nombre, $tipo));
        }

        return new RedirectResponse('/admin/categorias?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'categorias',
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
            'volverA' => '/admin/categorias',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Categorías',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
