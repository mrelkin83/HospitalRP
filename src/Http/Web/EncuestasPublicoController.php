<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Encuestas\Repository\EncuestaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encuestas de satisfacción, aprobadas el 2026-09-14 (CLAUDE.md §7).
 *
 * Son anónimas: no se pide ni se guarda nombre, documento, correo ni teléfono.
 * La «huella» que impide responder dos veces es un SHA-256 de la IP, el
 * navegador y una sal del servidor — no se puede revertir para saber quién
 * respondió, y por eso tampoco sirve como evidencia de nada.
 *
 * El aviso al ciudadano no es decorativo: si alguien escribe su cédula en una
 * pregunta abierta, ese dato queda guardado. Decirlo antes es la única
 * defensa real.
 */
final readonly class EncuestasPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private EncuestaRepository $encuestas,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Encuestas de satisfacción',
            descripcion: 'Encuestas de satisfacción de la E.S.E. Hospital Rosario Pumarejo de López. '
                . 'Tu respuesta es anónima.',
            contenido: $this->vistas->render('publico/encuestas/listado', [
                'encuestas' => $this->encuestas->abiertas(),
            ]),
        );
    }

    public function formulario(Request $peticion): Response
    {
        $encuesta = $this->encuestas->abiertaPorSlug((string) $peticion->attributes->get('slug'));

        if ($encuesta === null) {
            return $this->cerrada();
        }

        $id = (int) $encuesta['id'];

        if ($this->encuestas->yaRespondio($id, $this->huella($peticion))) {
            return $this->mensajeGracias('Ya registramos tu respuesta a esta encuesta. Gracias por participar.');
        }

        return $this->layout->responder(
            titulo: (string) $encuesta['titulo'],
            descripcion: mb_substr((string) ($encuesta['descripcion'] ?? ''), 0, 300) ?: null,
            contenido: $this->vistas->render('publico/encuestas/formulario', [
                'encuesta' => $encuesta,
                'preguntas' => $this->encuestas->preguntasDe($id),
                'errores' => [],
            ]),
        );
    }

    public function responder(Request $peticion): Response
    {
        $encuesta = $this->encuestas->abiertaPorSlug((string) $peticion->attributes->get('slug'));

        if ($encuesta === null) {
            return $this->cerrada();
        }

        // Trampa para robots: un campo que ningún humano ve ni llena. Si
        // llega con texto, se responde «gracias» sin guardar nada.
        if (trim((string) $peticion->request->get('sitio_web', '')) !== '') {
            return $this->mensajeGracias('Gracias por participar.');
        }

        $id = (int) $encuesta['id'];
        $preguntas = $this->encuestas->preguntasDe($id);
        $errores = [];
        $valores = [];

        foreach ($preguntas as $pregunta) {
            $bruto = $peticion->request->all()['p' . $pregunta['id']] ?? null;
            $lista = array_values(array_filter(
                array_map(static fn (mixed $v): string => trim((string) $v), (array) $bruto),
                static fn (string $v): bool => $v !== '',
            ));

            // Una opción que no está en el cuestionario no se guarda: si no,
            // cualquiera inyecta valores al conteo agregado.
            if ($pregunta['tipo'] !== 'texto' && $pregunta['opciones'] !== []) {
                $lista = array_values(array_intersect($lista, $pregunta['opciones']));
            }

            if ($pregunta['tipo'] !== 'opcion_multiple') {
                $lista = array_slice($lista, 0, 1);
            }

            if ($pregunta['obligatoria'] && $lista === []) {
                $errores[$pregunta['id']] = 'Esta pregunta es obligatoria.';
            }

            if ($lista !== []) {
                $valores[$pregunta['id']] = $lista;
            }
        }

        if ($errores !== []) {
            return $this->layout->responder(
                titulo: (string) $encuesta['titulo'],
                descripcion: null,
                contenido: $this->vistas->render('publico/encuestas/formulario', [
                    'encuesta' => $encuesta,
                    'preguntas' => $preguntas,
                    'errores' => $errores,
                ]),
                noindex: true,
                estado: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->encuestas->guardarRespuesta($id, $this->huella($peticion), $valores);

        return new RedirectResponse('/encuestas/' . $encuesta['slug'] . '/gracias', Response::HTTP_SEE_OTHER);
    }

    public function gracias(Request $peticion): Response
    {
        return $this->mensajeGracias();
    }

    private function mensajeGracias(
        string $mensaje = 'Gracias por responder. Tu opinión nos ayuda a mejorar la atención.',
    ): Response {
        return $this->layout->responder(
            titulo: 'Gracias por participar',
            descripcion: null,
            contenido: $this->vistas->render('publico/mensaje', [
                'encabezado' => 'Gracias por participar',
                'mensaje' => $mensaje,
            ]),
            noindex: true,
        );
    }

    private function cerrada(): Response
    {
        return $this->layout->responder(
            titulo: 'Encuesta no disponible',
            descripcion: null,
            contenido: $this->vistas->render('publico/mensaje', [
                'encabezado' => 'Encuesta no disponible',
                'mensaje' => 'Esta encuesta no existe o ya cerró. Consulta las encuestas abiertas.',
            ]),
            noindex: true,
            estado: Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * Huella anónima y no reversible. La sal viene del servidor, así que el
     * mismo navegador produce huellas distintas en instalaciones distintas y
     * el valor no sirve para cruzar datos con nada.
     */
    private function huella(Request $peticion): string
    {
        return hash('sha256', implode('|', [
            $peticion->getClientIp() ?? '',
            (string) $peticion->headers->get('User-Agent', ''),
            (string) ($_ENV['APP_SECRETO'] ?? 'hrpl-encuestas'),
        ]));
    }
}
