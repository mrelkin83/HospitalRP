<?php

declare(strict_types=1);

namespace App\Http\Admin\Soporte;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\AutorizacionService;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Andamiaje común de las pantallas del panel: envolver en la plantilla base,
 * validar el token CSRF y registrar auditoría.
 *
 * Los controladores de las fases 1 a 8 llevan estas tres piezas copiadas una
 * por una. Al sumar cuatro módulos de golpe en la Fase 9, repetirlas cuatro
 * veces más deja de ser aceptable: es el mismo código con el nombre de la
 * entidad cambiado, y un descuido en la copia número ocho es un formulario
 * del panel sin CSRF.
 *
 * Es un colaborador inyectado, no una clase base: los controladores siguen
 * siendo `final readonly` y sin herencia, como manda CLAUDE.md §5.
 */
final readonly class PantallaAdmin
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private AuditoriaService $auditoria,
        private AutorizacionService $autorizacion,
    ) {
    }

    /** Envuelve el contenido en la plantilla del panel. Nunca se cachea. */
    public function pagina(string $titulo, string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $usuarioId = $this->sesion->usuarioId();

        $html = $this->vistas->render('admin/base', [
            'titulo' => $titulo,
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
            // El menú se dibuja con lo que este usuario puede abrir. Enseñar
            // secciones que van a responder 403 no es informativo: es hacerle
            // perder el tiempo y sembrar la duda de si el panel está roto.
            'puede' => fn (string $permiso): bool => $usuarioId !== null
                && $this->autorizacion->puede($usuarioId, $permiso),
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }

    /**
     * Devuelve null si el token es válido; si no, la respuesta 403 ya armada.
     *
     * Se devuelve la respuesta en vez de lanzar para que el controlador
     * decida a dónde vuelve el funcionario: un «sesión expirada» sin botón de
     * regreso obliga a rehacer el formulario a ciegas.
     */
    public function rechazoCsrf(Request $peticion, string $titulo, string $volverA): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($titulo, $this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => $volverA,
        ]), Response::HTTP_FORBIDDEN);
    }

    public function tokenCsrf(): string
    {
        $this->sesion->iniciar();

        return $this->sesion->tokenCsrf();
    }

    public function usuarioId(): ?int
    {
        $this->sesion->iniciar();

        return $this->sesion->usuarioId();
    }

    /**
     * Dato de un solo uso entre dos peticiones (patrón flash).
     *
     * @see Sesion::guardarFlash() para el porqué de no usar la URL
     */
    public function guardarEnLaSesion(string $clave, mixed $valor): void
    {
        $this->sesion->iniciar();
        $this->sesion->guardarFlash($clave, $valor);
    }

    public function sacarDeLaSesion(string $clave): mixed
    {
        $this->sesion->iniciar();

        return $this->sesion->sacarFlash($clave);
    }

    /** Toda acción con efecto institucional deja rastro (CLAUDE.md §2.3). */
    public function auditar(
        Request $peticion,
        AccionAuditoria $accion,
        string $entidad,
        int $id,
        string $descripcion,
    ): void {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: $entidad,
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }
}
