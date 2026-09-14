<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Slugs;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Encuestas\Repository\EncuestaRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encuestas de satisfacción desde el panel.
 *
 * La pantalla de resultados muestra **conteos agregados**, nunca respuestas
 * individuales de preguntas cerradas: el ciudadano respondió creyendo que era
 * anónimo y el panel no puede desmentirlo. El texto libre sí se lee, porque
 * no hay forma de agregarlo y son las sugerencias que el hospital debe
 * atender; por eso el formulario público avisa de no escribir datos
 * personales ahí.
 */
final readonly class EncuestasAdminController
{
    private const string TITULO = 'Encuestas de satisfacción';
    private const string RUTA = '/admin/encuestas';

    private const array ESTADOS = ['borrador', 'abierta', 'cerrada'];
    private const array TIPOS_PREGUNTA = ['escala', 'opcion_unica', 'opcion_multiple', 'texto'];

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private EncuestaRepository $encuestas,
        private Connection $conexion,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/encuestas/listado', [
            'encuestas' => $this->encuestas->todas(),
            'estados' => self::ESTADOS,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'El título es obligatorio.' : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $titulo = trim($r->getString('titulo'));

        if ($titulo === '') {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $descripcion = trim($r->getString('descripcion'));

        $campos = [
            'titulo' => mb_substr($titulo, 0, 300),
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado' => in_array($r->getString('estado'), self::ESTADOS, true) ? $r->getString('estado') : 'borrador',
            'cierre' => $this->fecha($r->getString('cierre')),
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->encuestas->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->conexion->update('encuestas', $campos, ['id' => $id]);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'encuestas', $id, sprintf('Editó la encuesta «%s»', $titulo));
        } else {
            $campos['slug'] = $this->slugLibre($titulo);
            $campos['creado_por'] = $this->pantalla->usuarioId();
            $this->conexion->insert('encuestas', $campos);
            $id = (int) $this->conexion->lastInsertId();
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'encuestas', $id, sprintf('Creó la encuesta «%s»', $titulo));
        }

        return new RedirectResponse(self::RUTA . '/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /** Detalle: preguntas del cuestionario y resultados agregados. */
    public function detalle(Request $peticion): Response
    {
        $id = $peticion->attributes->getInt('id');
        $encuesta = $this->encuestas->porId($id);

        if ($encuesta === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $preguntas = $this->encuestas->preguntasDe($id);
        $resultados = [];

        foreach ($preguntas as $pregunta) {
            $resultados[$pregunta['id']] = $pregunta['tipo'] === 'texto'
                ? ['tipo' => 'texto', 'valores' => $this->encuestas->textosDe($pregunta['id'])]
                : ['tipo' => 'conteo', 'valores' => $this->encuestas->conteoDe($pregunta['id'])];
        }

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/encuestas/detalle', [
            'encuesta' => $encuesta,
            'preguntas' => $preguntas,
            'resultados' => $resultados,
            'total' => $this->encuestas->totalRespuestas($id),
            'tiposPregunta' => self::TIPOS_PREGUNTA,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function crearPregunta(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $encuesta = $this->encuestas->porId($id);
        $enunciado = trim($peticion->request->getString('enunciado'));

        if ($encuesta === null || $enunciado === '') {
            return new RedirectResponse(self::RUTA . '/' . $id, Response::HTTP_SEE_OTHER);
        }

        $tipo = $peticion->request->getString('tipo');
        $tipo = in_array($tipo, self::TIPOS_PREGUNTA, true) ? $tipo : 'escala';

        // Una opción por línea. Si el tipo no las usa se guarda NULL: así el
        // repositorio no tiene que adivinar y el formulario público no pinta
        // casillas fantasma.
        $opciones = array_values(array_filter(
            array_map(
                static fn (string $o): string => trim($o),
                preg_split('/\r?\n/', $peticion->request->getString('opciones')) ?: [],
            ),
            static fn (string $o): bool => $o !== '',
        ));

        $this->conexion->insert('encuesta_preguntas', [
            'encuesta_id' => $id,
            'enunciado' => mb_substr($enunciado, 0, 500),
            'tipo' => $tipo,
            'opciones' => in_array($tipo, ['opcion_unica', 'opcion_multiple'], true) && $opciones !== []
                ? json_encode($opciones, JSON_UNESCAPED_UNICODE)
                : null,
            'obligatoria' => $peticion->request->getBoolean('obligatoria') ? 1 : 0,
            'orden' => $peticion->request->getInt('orden'),
        ]);

        $this->pantalla->auditar(
            $peticion,
            AccionAuditoria::Creacion,
            'encuesta_preguntas',
            (int) $this->conexion->lastInsertId(),
            sprintf('Añadió una pregunta a la encuesta «%s»', (string) $encuesta['titulo']),
        );

        return new RedirectResponse(self::RUTA . '/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /**
     * Borrar una pregunta borra en cascada sus respuestas. Se avisa en la
     * vista: no es lo mismo corregir una errata que tirar el histórico.
     */
    public function eliminarPregunta(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $preguntaId = $peticion->attributes->getInt('id');
        $fila = $this->conexion->fetchAssociative(
            'SELECT id, encuesta_id, enunciado FROM encuesta_preguntas WHERE id = ?',
            [$preguntaId],
        );

        if ($fila === false) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $this->conexion->delete('encuesta_preguntas', ['id' => $preguntaId]);
        $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'encuesta_preguntas', $preguntaId, sprintf(
            'Eliminó la pregunta «%s» y sus respuestas',
            (string) $fila['enunciado'],
        ));

        return new RedirectResponse(self::RUTA . '/' . (int) $fila['encuesta_id'] . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    private function slugLibre(string $titulo): string
    {
        $base = Slugs::desde($titulo, 300);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM encuestas WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }

    private function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        $fecha = date_create_immutable(str_replace('T', ' ', $valor));

        return $fecha === false ? null : $fecha->format('Y-m-d H:i:s');
    }
}
