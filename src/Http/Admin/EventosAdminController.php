<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Eventos\Repository\EventoRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Calendario institucional desde el panel (ITA 1.12 y 6.5).
 */
final readonly class EventosAdminController
{
    private const string TITULO = 'Calendario de eventos';
    private const string RUTA = '/admin/eventos';

    private const array TIPOS = [
        'institucional', 'participacion_ciudadana', 'rendicion_cuentas',
        'capacitacion', 'jornada_salud', 'otro',
    ];

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private EventoRepository $eventos,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/eventos/listado', [
            'eventos' => $this->eventos->todos(),
            'tipos' => self::TIPOS,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido')
                ? 'El título y la fecha de inicio son obligatorios, y el fin no puede ser anterior al inicio.'
                : null,
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
        $inicio = $this->fecha($r->getString('inicio'));
        $fin = $this->fecha($r->getString('fin'));

        // Un evento que termina antes de empezar desaparecería del calendario
        // sin avisar, porque la consulta del mes filtra por rango. Se rechaza.
        if ($titulo === '' || $inicio === null || ($fin !== null && $fin < $inicio)) {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $descripcion = trim($r->getString('descripcion'));
        $lugar = trim($r->getString('lugar'));
        $url = trim($r->getString('url_externa'));

        $campos = [
            'titulo' => mb_substr($titulo, 0, 300),
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'tipo' => in_array($r->getString('tipo'), self::TIPOS, true) ? $r->getString('tipo') : 'institucional',
            'inicio' => $inicio,
            'fin' => $fin,
            'todo_el_dia' => $r->getBoolean('todo_el_dia') ? 1 : 0,
            'lugar' => $lugar !== '' ? mb_substr($lugar, 0, 300) : null,
            'url_externa' => $url !== '' ? mb_substr($url, 0, 500) : null,
            'publicado' => $r->getBoolean('publicado') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->eventos->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->eventos->actualizar($id, $campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'eventos', $id, sprintf('Editó el evento «%s»', $titulo));
        } else {
            $campos['creado_por'] = $this->pantalla->usuarioId();
            $id = $this->eventos->crear($campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'eventos', $id, sprintf('Creó el evento «%s»', $titulo));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $fila = $this->eventos->porId($id);

        if ($fila !== null) {
            $this->eventos->eliminar($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'eventos', $id, sprintf(
                'Eliminó el evento «%s»',
                (string) $fila['titulo'],
            ));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
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
