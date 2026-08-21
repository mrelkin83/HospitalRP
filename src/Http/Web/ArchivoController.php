<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Única puerta hacia `storage/uploads` (CLAUDE.md §6): el servidor web no
 * sirve ese directorio; todo archivo pasa por aquí y por sus validaciones.
 */
final readonly class ArchivoController
{
    public function __construct(
        private AlmacenArchivos $almacen,
        private Sesion $sesion,
    ) {
    }

    public function entregar(Request $peticion): Response
    {
        $archivo = $this->almacen->porId($peticion->attributes->getInt('id'));

        if ($archivo === null) {
            return $this->noEncontrado();
        }

        if (!$archivo->publico) {
            $this->sesion->iniciar();

            // 404 y no 403: la existencia de un archivo privado tampoco es
            // información pública.
            if ($this->sesion->usuarioId() === null) {
                return $this->noEncontrado();
            }
        }

        $rutaFisica = $this->almacen->rutaAbsoluta($archivo);

        if (!is_file($rutaFisica)) {
            return $this->noEncontrado();
        }

        $this->almacen->contarDescarga($archivo);

        $respuesta = new BinaryFileResponse($rutaFisica);
        $respuesta->headers->set('Content-Type', $archivo->mime);
        $respuesta->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $archivo->nombreOriginal,
            // Respaldo ASCII para navegadores viejos con nombres con tildes.
            sprintf('archivo-%d.%s', $archivo->id, $archivo->extension),
        ));

        if ($archivo->publico) {
            $respuesta->setPublic();
            $respuesta->setMaxAge(86400);
        } else {
            $respuesta->headers->set('Cache-Control', 'no-store');
        }

        return $respuesta;
    }

    private function noEncontrado(): Response
    {
        return new Response('No encontrado', Response::HTTP_NOT_FOUND, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
