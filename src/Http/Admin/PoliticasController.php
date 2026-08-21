<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Service\SanitizadorHtml;
use App\Modules\Cumplimiento\Repository\PoliticaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Políticas versionadas con vigencia. Regla dura: una versión publicada no
 * se edita jamás — se publica una versión nueva que cierra la anterior. La
 * evidencia de autorización apunta a la versión exacta que se aceptó.
 */
final readonly class PoliticasController
{
    private const array TIPOS = [
        'tratamiento_datos' => 'Tratamiento de datos personales',
        'aviso_privacidad' => 'Aviso de privacidad',
        'cookies' => 'Cookies',
        'accesibilidad' => 'Declaración de accesibilidad',
        'terminos' => 'Términos y condiciones',
        'seguridad' => 'Seguridad de la información',
    ];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private PoliticaRepository $politicas,
        private SanitizadorHtml $sanitizador,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $porTipo = [];

        foreach ($this->politicas->listar() as $politica) {
            $porTipo[(string) $politica['tipo']][] = $politica;
        }

        return $this->pagina($this->vistas->render('admin/politicas/listado', [
            'porTipo' => $porTipo,
            'tipos' => self::TIPOS,
            'mensaje' => $peticion->query->has('publicada') ? 'La versión nueva quedó vigente y la anterior se cerró.' : null,
            'error' => $peticion->query->has('invalido') ? 'La política necesita tipo, versión, título y contenido.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function publicarVersion(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if (!$this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return $this->pagina($this->vistas->render('admin/mensaje', [
                'tipo' => 'error',
                'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
                'volverA' => '/admin/politicas',
            ]), Response::HTTP_FORBIDDEN);
        }

        $r = $peticion->request;
        $tipo = $r->getString('tipo');
        $version = trim($r->getString('version'));
        $titulo = trim($r->getString('titulo'));
        $contenido = trim($r->getString('contenido'));

        if (!isset(self::TIPOS[$tipo]) || $version === '' || $titulo === '' || $contenido === '') {
            return new RedirectResponse('/admin/politicas?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $id = $this->politicas->publicarVersion([
            'tipo' => $tipo,
            'version' => mb_substr($version, 0, 20),
            'titulo' => mb_substr($titulo, 0, 255),
            'contenido' => $this->sanitizador->sanear($contenido),
            'creado_por' => $this->sesion->usuarioId(),
        ]);

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Publicacion,
            entidad: 'politicas',
            entidadId: $id,
            descripcion: sprintf(
                'Publicó la versión «%s» de la política de %s (la anterior quedó cerrada)',
                $version,
                self::TIPOS[$tipo],
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        return new RedirectResponse('/admin/politicas?publicada=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Políticas',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
            'conEditor' => true,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
