<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth\AutorizacionService;
use App\Core\Auth\Sesion;
use App\Core\Config\ConfigAplicacion;
use App\Http\Exception\ControladorInvalidoException;
use App\Modules\Sistema\Repository\RedireccionRepository;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * Ciclo petición → respuesta.
 *
 * Deliberadamente pequeño: empareja la ruta, resuelve el controlador en el
 * contenedor y aplica las cabeceras de seguridad. No se trajo
 * symfony/http-kernel porque eso era acercarse a "Symfony completo",
 * que CLAUDE.md §3 prohíbe.
 */
final readonly class Kernel
{
    public function __construct(
        private ContainerInterface $contenedor,
        private RouteCollection $rutas,
        private ConfigAplicacion $config,
    ) {
    }

    public function manejar(Request $peticion): Response
    {
        try {
            $respuesta = $this->despachar($peticion);
        } catch (Throwable $error) {
            $respuesta = $this->respuestaDeError($error);
        }

        return $this->aplicarCabecerasDeSeguridad(
            $respuesta,
            $peticion->attributes->get('_acceso') === 'privado',
        );
    }

    private function despachar(Request $peticion): Response
    {
        $comparador = new UrlMatcher($this->rutas, (new RequestContext())->fromRequest($peticion));

        try {
            $parametros = $comparador->match($peticion->getPathInfo());
        } catch (ResourceNotFoundException) {
            // Antes del 404: las URL del sitio Joomla anterior siguen citadas
            // en oficios y buscadores, y responden 301 hacia su equivalente.
            $redireccion = $this->redirigirSiCorresponde($peticion);

            if ($redireccion !== null) {
                return $redireccion;
            }

            return $this->paginaDeEstado(
                Response::HTTP_NOT_FOUND,
                'Página no encontrada',
                'La página que buscas no existe o cambió de dirección.',
            );
        } catch (MethodNotAllowedException) {
            return $this->paginaDeEstado(
                Response::HTTP_METHOD_NOT_ALLOWED,
                'Método no permitido',
                'Esta dirección no acepta ese tipo de solicitud.',
            );
        }

        $peticion->attributes->add($parametros);

        $rechazo = $this->controlarAcceso($parametros);

        if ($rechazo !== null) {
            return $rechazo;
        }

        return $this->invocar($parametros['_controlador'] ?? null, $peticion);
    }

    private function redirigirSiCorresponde(Request $peticion): ?Response
    {
        if ($peticion->getMethod() !== 'GET') {
            return null;
        }

        /** @var RedireccionRepository $redirecciones */
        $redirecciones = $this->contenedor->get(RedireccionRepository::class);

        // La URI cruda, no getPathInfo(): las URL del Joomla contienen
        // «index.php» y Symfony lo interpretaría como base y recortaría
        // el camino («/sitio/index.php/es/x» quedaría como «/es/x»).
        $ruta = rawurldecode((string) parse_url($peticion->getRequestUri(), PHP_URL_PATH));
        $destino = $redirecciones->destinoPara($ruta);

        if ($destino === null) {
            return null;
        }

        $redirecciones->registrarHit($destino['id']);

        return new RedirectResponse($destino['destino'], $destino['codigo']);
    }

    /**
     * Control de acceso declarado en la ruta, no repetido en cada controlador:
     * `_acceso: privado` exige sesión; `_permiso: modulo.accion` exige además
     * ese permiso del rol. Devuelve null cuando el acceso procede.
     *
     * @param array<string, mixed> $parametros
     */
    private function controlarAcceso(array $parametros): ?Response
    {
        if (($parametros['_acceso'] ?? 'publico') !== 'privado') {
            return null;
        }

        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();

        $usuarioId = $sesion->usuarioId();

        if ($usuarioId === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        // Contraseña provisional: el panel no deja hacer nada más que
        // cambiarla. La clave la conoce quien creó o restableció la cuenta, así
        // que mientras siga vigente el funcionario no es el único que puede
        // entrar como él, y eso invalida la trazabilidad de todo lo que firme.
        $rechazoPassword = $this->exigirCambioDePassword($sesion, $parametros);

        if ($rechazoPassword !== null) {
            return $rechazoPassword;
        }

        $permiso = $parametros['_permiso'] ?? null;

        if (is_string($permiso) && $permiso !== '') {
            /** @var AutorizacionService $autorizacion */
            $autorizacion = $this->contenedor->get(AutorizacionService::class);

            if (!$autorizacion->puede($usuarioId, $permiso)) {
                return $this->paginaDeEstado(
                    Response::HTTP_FORBIDDEN,
                    'Acceso denegado',
                    'Tu cuenta no tiene permiso para entrar a esta sección. Si crees que deberías tenerlo, pídelo al administrador del sitio.',
                );
            }
        }

        return null;
    }

    /**
     * Encierra al usuario en la pantalla de cambio de contraseña mientras la
     * suya sea provisional. Devuelve null cuando puede seguir.
     *
     * Se exceptúan la propia pantalla de cambio y el cierre de sesión: sin lo
     * segundo, una cuenta con clave provisional no podría ni salir del panel.
     *
     * @param array<string, mixed> $parametros
     */
    private function exigirCambioDePassword(Sesion $sesion, array $parametros): ?Response
    {
        if (!$sesion->debeCambiarPassword()) {
            return null;
        }

        $ruta = $parametros['_route'] ?? null;

        $permitidas = [
            'admin.perfil.password',
            'admin.perfil.password.guardar',
            'admin.salir',
        ];

        if (is_string($ruta) && in_array($ruta, $permitidas, true)) {
            return null;
        }

        return new RedirectResponse('/admin/perfil/password', Response::HTTP_SEE_OTHER);
    }

    private function invocar(mixed $referencia, Request $peticion): Response
    {
        if (!is_array($referencia) || count($referencia) !== 2) {
            throw ControladorInvalidoException::referenciaMalFormada($peticion->getPathInfo());
        }

        [$clase, $metodo] = $referencia;

        if (!is_string($clase) || !is_string($metodo) || !$this->contenedor->has($clase)) {
            throw ControladorInvalidoException::noRegistrado(
                is_string($clase) ? $clase : get_debug_type($clase),
            );
        }

        $controlador = $this->contenedor->get($clase);

        if (!is_object($controlador) || !method_exists($controlador, $metodo)) {
            throw ControladorInvalidoException::metodoInexistente($clase, $metodo);
        }

        $respuesta = $controlador->{$metodo}($peticion);

        if (!$respuesta instanceof Response) {
            throw ControladorInvalidoException::noDevolvioRespuesta(
                $clase,
                $metodo,
                get_debug_type($respuesta),
            );
        }

        return $respuesta;
    }

    /**
     * Cabeceras exigidas por CLAUDE.md §6 y el Anexo 3 de la Res. 1519.
     *
     * La CSP arranca cerrada. Cuando entren CKEditor, Alpine o el mapa, cada
     * permiso se abre uno por uno y con motivo, nunca con un `unsafe-inline`
     * global: eso deja el sitio institucional abierto a XSS.
     */
    private function aplicarCabecerasDeSeguridad(Response $respuesta, bool $esPanel = false): Response
    {
        $cabeceras = $respuesta->headers;

        $cabeceras->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            // Solo en el panel (autenticado): CKEditor posiciona sus menús con
            // estilos inline. El sitio público conserva style-src estricto.
            $esPanel ? "style-src 'self' 'unsafe-inline'" : "style-src 'self'",
            "script-src 'self'",
            // Fuentes variables autoalojadas: nada de CDN de terceros (privacidad).
            "font-src 'self'",
        ]));

        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cabeceras->set('X-Frame-Options', 'DENY');
        $cabeceras->set('Cross-Origin-Opener-Policy', 'same-origin');
        $cabeceras->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        // HSTS solo bajo HTTPS. Enviarlo por HTTP en local dejaría el dominio
        // de desarrollo inaccesible en el navegador durante meses.
        if ($this->config->entorno->exigeHttps()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }

    private function respuestaDeError(Throwable $error): Response
    {
        if ($this->config->debug) {
            return $this->paginaDeEstado(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Error de la aplicación',
                sprintf(
                    '%s: %s (%s:%d)',
                    $error::class,
                    $error->getMessage(),
                    $error->getFile(),
                    $error->getLine(),
                ),
            );
        }

        // En producción el ciudadano nunca ve la excepción: expondría rutas,
        // credenciales y estructura de tablas.
        return $this->paginaDeEstado(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'Ocurrió un problema',
            'Estamos presentando una falla técnica. Intenta de nuevo en unos minutos.',
        );
    }

    private function paginaDeEstado(int $codigo, string $titulo, string $mensaje): Response
    {
        $html = <<<HTML
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>{$this->escapar($titulo)}</title>
            </head>
            <body>
                <main>
                    <h1>{$this->escapar($titulo)}</h1>
                    <p>{$this->escapar($mensaje)}</p>
                    <p><a href="/">Volver al inicio</a></p>
                </main>
            </body>
            </html>
            HTML;

        return new Response($html, $codigo, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
