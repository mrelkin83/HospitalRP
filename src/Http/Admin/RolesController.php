<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\RolRepository;
use App\Modules\Sistema\Service\CatalogoPermisos;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roles y reparto de permisos.
 *
 * Es lo que permite que el hospital deje de trabajar con una sola cuenta de
 * administrador compartida: la auxiliar de prensa publica noticias, ventanilla
 * atiende PQRSDF y nadie más que el administrador toca usuarios.
 *
 * El rol protegido (administrador) no se edita ni se borra desde aquí. Es la
 * llave maestra: si se le pudiera quitar un permiso por error, la única manera
 * de recuperarla sería un UPDATE a mano en la base de datos.
 */
final readonly class RolesController
{
    private const string TITULO = 'Roles y permisos';
    private const string RUTA = '/admin/roles';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private RolRepository $roles,
        private CatalogoPermisos $catalogo,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/roles/listado', [
            'roles' => $this->roles->todos(),
            'grupos' => $this->catalogo->grupos(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $this->errorDe($peticion->query->getString('error')),
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function crear(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $nombre = trim($peticion->request->getString('nombre'));

        if ($nombre === '') {
            return $this->volverCon('datos');
        }

        $slug = $this->slug($nombre);

        if ($slug === '' || $this->roles->slugOcupado($slug)) {
            return $this->volverCon('repetido');
        }

        $permisos = $this->permisosDe($peticion);
        $descripcion = trim($peticion->request->getString('descripcion'));

        $id = $this->roles->crear(
            mb_substr($nombre, 0, 80),
            $slug,
            $descripcion !== '' ? mb_substr($descripcion, 0, 255) : null,
            $permisos,
        );

        $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'roles', $id, sprintf(
            'Creó el rol «%s» con %d permisos',
            $nombre,
            count($permisos),
        ));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function actualizar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $rol = $this->roles->porId($id);

        if ($rol === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        if ((bool) $rol['protegido']) {
            return $this->volverCon('protegido');
        }

        $nombre = trim($peticion->request->getString('nombre'));

        if ($nombre === '') {
            return $this->volverCon('datos');
        }

        $permisos = $this->permisosDe($peticion);
        $descripcion = trim($peticion->request->getString('descripcion'));

        $this->roles->actualizar(
            $id,
            mb_substr($nombre, 0, 80),
            $descripcion !== '' ? mb_substr($descripcion, 0, 255) : null,
            $permisos,
        );

        // Se auditan los permisos que entran y salen, no un «editó el rol» a
        // secas: cuando alguien pregunte quién pudo publicar algo en marzo, la
        // respuesta tiene que estar en el registro y no en la memoria de nadie.
        $this->pantalla->auditar($peticion, AccionAuditoria::CambioPermisos, 'roles', $id, sprintf(
            'Editó el rol «%s». %s',
            $nombre,
            $this->resumirCambio($rol['permisos'], $permisos),
        ));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $rol = $this->roles->porId($id);

        if ($rol === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        if ((bool) $rol['protegido']) {
            return $this->volverCon('protegido');
        }

        // La llave foránea de `usuarios.rol_id` impediría el borrado
        // igualmente, pero un error de base de datos en pantalla no le dice al
        // funcionario qué hacer; este mensaje sí.
        if ($this->roles->usuariosCon($id) > 0) {
            return $this->volverCon('en_uso');
        }

        $this->roles->eliminar($id);

        $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'roles', $id, sprintf(
            'Eliminó el rol «%s»',
            (string) $rol['nombre'],
        ));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @return list<string>
     */
    private function permisosDe(Request $peticion): array
    {
        $enviados = $peticion->request->all('permisos');
        $permisos = [];

        foreach ($enviados as $permiso) {
            if (is_string($permiso)) {
                $permisos[] = $permiso;
            }
        }

        // Se depura contra el catálogo: un permiso inventado desde el navegador
        // no abriría ninguna puerta, pero ensuciaría la columna y confundiría a
        // quien revise el rol después.
        return $this->catalogo->depurar($permisos);
    }

    /**
     * @param list<string> $antes
     * @param list<string> $despues
     */
    private function resumirCambio(array $antes, array $despues): string
    {
        $agregados = array_diff($despues, $antes);
        $quitados = array_diff($antes, $despues);

        if ($agregados === [] && $quitados === []) {
            return 'Sin cambios en los permisos.';
        }

        $partes = [];

        if ($agregados !== []) {
            $partes[] = 'concedió ' . implode(', ', $agregados);
        }

        if ($quitados !== []) {
            $partes[] = 'revocó ' . implode(', ', $quitados);
        }

        return mb_substr(ucfirst(implode('; ', $partes)) . '.', 0, 400);
    }

    private function slug(string $nombre): string
    {
        $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre);
        $base = strtolower($transliterado === false ? $nombre : $transliterado);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';

        return trim($slug, '-');
    }

    private function volverCon(string $error): Response
    {
        return new RedirectResponse(self::RUTA . '?error=' . $error, Response::HTTP_SEE_OTHER);
    }

    private function errorDe(string $codigo): ?string
    {
        return match ($codigo) {
            'datos' => 'El rol necesita un nombre.',
            'repetido' => 'Ya existe un rol con ese nombre.',
            'protegido' => 'El rol de administrador no se edita ni se elimina: es la llave maestra del panel.',
            'en_uso' => 'Hay usuarios con ese rol. Cámbialos de rol antes de eliminarlo.',
            default => null,
        };
    }
}
