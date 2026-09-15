<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Enlaces;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Repository\MenuRepository;
use App\Modules\Sistema\Service\CatalogoIconos;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edición de menús del sitio. Sin jerga: etiqueta, dirección, orden y si se
 * ve o no. Los items protegidos (Res. 1519) no tienen botón de borrar.
 */
final readonly class MenusController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private MenuRepository $menus,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $menus = [];

        foreach ($this->menus->menus() as $menu) {
            $menu['items'] = $this->menus->itemsDeMenu($menu['id']);
            // Para el selector «depende de» de cada fila.
            $menu['raices'] = $this->menus->raicesDeMenu($menu['id']);
            $menus[] = $menu;
        }

        return $this->pagina($this->vistas->render('admin/menus/listado', [
            'menus' => $menus,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            // Los accesos rápidos de la portada se editan aquí: llevan frase e
            // icono, y el icono se elige de una lista, no se pega.
            'iconos' => (new CatalogoIconos())->etiquetas(),
        ]));
    }

    public function guardarItem(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $item = $this->menus->itemPorId($id);
        $etiqueta = trim($peticion->request->getString('etiqueta'));
        $url = trim($peticion->request->getString('url'));

        if ($item === null || $etiqueta === '' || !Enlaces::aceptable($url)) {
            return new RedirectResponse('/admin/menus', Response::HTTP_SEE_OTHER);
        }

        $this->menus->actualizarItem(
            $id,
            $etiqueta,
            $url,
            $peticion->request->getInt('orden'),
            $peticion->request->getBoolean('visible'),
            // 0 = enlace de primer nivel. El repositorio rechaza los ciclos.
            $peticion->request->getInt('padre_id') ?: null,
            trim($peticion->request->getString('descripcion')),
            trim($peticion->request->getString('icono')),
        );

        $this->auditar($peticion, AccionAuditoria::Actualizacion, $id, sprintf(
            'Editó el enlace de menú «%s» → «%s» (%s)',
            $item['etiqueta'],
            $etiqueta,
            $url,
        ));

        return new RedirectResponse('/admin/menus?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function crearItem(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $menuId = $peticion->attributes->getInt('id');
        $etiqueta = trim($peticion->request->getString('etiqueta'));
        $url = trim($peticion->request->getString('url'));

        if ($etiqueta !== '' && Enlaces::aceptable($url)) {
            $id = $this->menus->crearItem($menuId, $etiqueta, $url, $peticion->request->getInt('orden'));
            $this->auditar($peticion, AccionAuditoria::Creacion, $id, sprintf(
                'Añadió el enlace de menú «%s» (%s)',
                $etiqueta,
                $url,
            ));
        }

        return new RedirectResponse('/admin/menus?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminarItem(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $item = $this->menus->itemPorId($id);

        if ($item !== null && $this->menus->eliminarItem($id)) {
            $this->auditar($peticion, AccionAuditoria::Eliminacion, $id, sprintf(
                'Eliminó el enlace de menú «%s»',
                $item['etiqueta'],
            ));
        }

        return new RedirectResponse('/admin/menus?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'menu_items',
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
            'volverA' => '/admin/menus',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Menús',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
