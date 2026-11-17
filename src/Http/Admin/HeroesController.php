<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Enlaces;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Contenido\Repository\HeroRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El héroe de la portada, desde el panel y en una sola pantalla.
 *
 * Antes esto eran ocho campos de texto perdidos en Ajustes que, por mucho que
 * se editaran, seguían pintando un único bloque sin imagen. Aquí se crean las
 * diapositivas, se les sube la foto, se ordenan y se apagan.
 *
 * Crear y editar comparten método —como en el calendario de eventos— porque
 * son el mismo formulario: con `id` en la ruta actualiza, sin él crea. Dos
 * métodos gemelos serían dos sitios donde olvidarse de validar lo mismo.
 */
final readonly class HeroesController
{
    private const string TITULO = 'Héroe de la portada';
    private const string RUTA = '/admin/heroes';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private HeroRepository $heroes,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $problema = $this->pantalla->sacarDeLaSesion('heroes_error');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/heroes/listado', [
            'heroes' => $this->heroes->todas(),
            'siguienteOrden' => $this->heroes->siguienteOrden(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados. Ya se ven en la portada.' : null,
            'error' => is_string($problema) ? $problema : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $id = $peticion->attributes->getInt('id');
        $existente = $id > 0 ? $this->heroes->porId($id) : null;

        if ($id > 0 && $existente === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $titulo = trim($r->getString('titulo'));
        $inicio = $this->fecha($r->getString('inicio'));
        $fin = $this->fecha($r->getString('fin'));

        if ($titulo === '') {
            return $this->volverConError('El título es obligatorio: es lo primero que lee quien entra al sitio.');
        }

        // Una vigencia que termina antes de empezar deja la diapositiva
        // invisible para siempre sin decir por qué.
        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            return $this->volverConError('La fecha de fin no puede ser anterior a la de inicio.');
        }

        $botones = $this->botones($r);

        if (is_string($botones)) {
            return $this->volverConError($botones);
        }

        $imagen = $this->procesarImagen($peticion);

        if (is_string($imagen)) {
            return $this->volverConError($imagen);
        }

        $campos = [
            'pildora' => $this->texto($r->getString('pildora'), 120),
            'titulo' => mb_substr($titulo, 0, 160),
            'titulo_degradado' => $this->texto($r->getString('titulo_degradado'), 160),
            'eslogan' => $this->texto($r->getString('eslogan'), 500),
            'mostrar_tarjeta' => $r->getBoolean('mostrar_tarjeta') ? 1 : 0,
            'visible' => $r->getBoolean('visible') ? 1 : 0,
            'orden' => max(0, min(999, $r->getInt('orden'))),
            'inicio' => $inicio,
            'fin' => $fin,
            // Destino al pulsar la fotografía. Vacío = imagen sin enlace, que
            // es como se comportaban todas las diapositivas hasta hoy.
            'enlace_imagen' => $this->texto($r->getString('enlace_imagen'), 255),
            ...$botones,
        ];

        // Tres casos distintos con la imagen y solo dos de ellos la tocan:
        // subieron una nueva, pidieron quitar la que había, o no dijeron nada
        // —y entonces la fila conserva la suya—.
        if ($imagen !== null) {
            $campos['imagen_id'] = $imagen;
        } elseif ($r->getBoolean('quitar_imagen')) {
            $campos['imagen_id'] = null;
        }

        if ($existente !== null) {
            $campos['actualizado_por'] = $this->pantalla->usuarioId();
            $this->heroes->actualizar((int) $existente['id'], $campos);
            $guardadoId = (int) $existente['id'];
            $accion = AccionAuditoria::Actualizacion;
            $rastro = sprintf('Editó el héroe «%s»', $titulo);
        } else {
            $campos['creado_por'] = $this->pantalla->usuarioId();
            $campos['imagen_id'] ??= null;
            $guardadoId = $this->heroes->crear($campos);
            $accion = AccionAuditoria::Creacion;
            $rastro = sprintf('Creó el héroe «%s»', $titulo);
        }

        // La tarjeta «Rosario Digital» es una sola. Si esta diapositiva se la
        // queda, las demás la sueltan: verla repetida en dos héroes distintos
        // haría dudar de si el sitio está roto.
        if ($campos['mostrar_tarjeta'] === 1) {
            $this->heroes->tarjetaSoloEn($guardadoId);
        }

        $this->pantalla->auditar($peticion, $accion, 'heroes', $guardadoId, $rastro);

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $hero = $this->heroes->porId($id);

        if ($hero === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $this->heroes->eliminar($id);
        $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'heroes', $id, sprintf(
            'Eliminó el héroe «%s»',
            (string) $hero['titulo'],
        ));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * Texto y dirección de los dos botones, o el mensaje de por qué no valen.
     *
     * Un botón es texto + dirección: con uno solo de los dos no se puede
     * pintar nada útil, así que o van los dos o no va ninguno.
     *
     * @return array<string, ?string>|string
     */
    private function botones(InputBag $r): array|string
    {
        $campos = [];

        foreach ([1, 2] as $n) {
            $texto = trim($r->getString('boton' . $n . '_texto'));
            $url = trim($r->getString('boton' . $n . '_url'));

            if ($texto === '' && $url === '') {
                $campos['boton' . $n . '_texto'] = null;
                $campos['boton' . $n . '_url'] = null;

                continue;
            }

            if ($texto === '' || $url === '') {
                return sprintf('Al botón %d le falta %s.', $n, $texto === '' ? 'el texto' : 'la dirección');
            }

            if (!Enlaces::aceptable($url)) {
                return sprintf(
                    'La dirección del botón %d no se puede usar. Escribe una ruta del sitio («/citas»), una dirección completa («https://…»), un correo («mailto:…») o un teléfono («tel:…»).',
                    $n,
                );
            }

            $campos['boton' . $n . '_texto'] = mb_substr($texto, 0, 60);
            $campos['boton' . $n . '_url'] = $url;
        }

        return $campos;
    }

    /**
     * Devuelve el id de la imagen subida, null si no subieron ninguna, o el
     * mensaje de error si la subida no sirve.
     */
    private function procesarImagen(Request $peticion): int|string|null
    {
        $imagen = $peticion->files->get('imagen');

        if (!$imagen instanceof UploadedFile) {
            return null;
        }

        // La imagen del héroe es decorativa —el texto que cuenta está en el
        // HTML, encima de ella—, pero se pide igual su descripción: la
        // fotografía se guarda en la mediateca compartida, y allí sin
        // descripción no hay forma de saber cuál es cuál.
        $textoAlt = trim($peticion->request->getString('imagen_alt'));

        if ($textoAlt === '') {
            return 'Describe la imagen en una frase antes de subirla: es lo que identifica la foto en la mediateca.';
        }

        try {
            return $this->almacen->guardar($imagen, publico: true, creadoPor: $this->pantalla->usuarioId(), textoAlt: $textoAlt)->id;
        } catch (ArchivoInvalidoException $e) {
            return $e->getMessage();
        }
    }

    private function volverConError(string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('heroes_error', $mensaje);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    private function texto(string $valor, int $largo): ?string
    {
        $valor = trim($valor);

        return $valor === '' ? null : mb_substr($valor, 0, $largo);
    }

    private function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        $fecha = date_create_immutable($valor);

        return $fecha === false ? null : $fecha->format('Y-m-d');
    }
}
