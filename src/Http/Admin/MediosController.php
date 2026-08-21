<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Service\AsistenteIa;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Biblioteca de medios (Fase 1, paso 6): subir imágenes con texto
 * alternativo obligatorio y copiar su dirección para usarla en el contenido.
 */
final readonly class MediosController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private AsistenteIa $asistenteIa,
        private AlmacenArchivos $almacen,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->render($peticion->query->has('subida') ? 'La imagen quedó en la biblioteca.' : null, []));
    }

    public function subir(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if (!$this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return $this->pagina($this->render(null, [
                'imagen' => 'Tu sesión expiró antes de enviar el formulario. Intenta de nuevo.',
            ]), Response::HTTP_FORBIDDEN);
        }

        $imagen = $peticion->files->get('imagen');
        $textoAlt = trim($peticion->request->getString('texto_alt'));
        $errores = [];

        if (!$imagen instanceof UploadedFile) {
            $errores['imagen'] = 'Elige una imagen para subir.';
        }

        if ($textoAlt === '') {
            $errores['texto_alt'] = 'Describe la imagen en una frase: es lo que escucha una persona ciega y lo que ve quien navega sin imágenes.';
        }

        if ($errores === [] && $imagen instanceof UploadedFile) {
            try {
                $archivo = $this->almacen->guardar($imagen, publico: true, creadoPor: $this->sesion->usuarioId(), textoAlt: $textoAlt);

                $this->auditoria->registrar(new RegistroAuditoria(
                    accion: AccionAuditoria::Creacion,
                    entidad: 'archivos',
                    entidadId: $archivo->id,
                    descripcion: sprintf('Subió a la biblioteca «%s»', $archivo->nombreOriginal),
                    usuarioId: $this->sesion->usuarioId(),
                    usuarioNombre: $this->sesion->usuarioNombre(),
                    ip: $peticion->getClientIp(),
                    userAgent: $peticion->headers->get('User-Agent'),
                ));

                return new RedirectResponse('/admin/medios?subida=1', Response::HTTP_SEE_OTHER);
            } catch (ArchivoInvalidoException $e) {
                $errores['imagen'] = $e->getMessage();
            }
        }

        return $this->pagina($this->render(null, $errores), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<string, string> $errores
     */
    private function render(?string $mensaje, array $errores): string
    {
        return $this->vistas->render('admin/medios/listado', [
            'archivos' => $this->almacen->imagenesRecientes(),
            'mensaje' => $mensaje,
            'errores' => $errores,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'iaActiva' => $this->asistenteIa->activa(),
        ]);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Biblioteca de medios',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
