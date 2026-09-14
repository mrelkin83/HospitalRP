<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Eventos\Repository\EventoRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Calendario institucional (ITA 1.12 y 6.5).
 *
 * La agenda se navega por mes con enlaces normales, no con JavaScript: en una
 * conexión mala del Cesar un calendario que solo existe si carga un script es
 * un calendario que no existe. El mes viene renderizado desde el servidor.
 */
final readonly class EventosPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private EventoRepository $eventos,
    ) {
    }

    public function agenda(Request $peticion): Response
    {
        $hoy = new \DateTimeImmutable('today');
        $anio = (int) $peticion->query->get('anio', $hoy->format('Y'));
        $mes = (int) $peticion->query->get('mes', $hoy->format('n'));

        // Un mes fuera de rango se corrige en vez de reventar: la URL la puede
        // escribir cualquiera.
        if ($mes < 1 || $mes > 12) {
            $mes = (int) $hoy->format('n');
        }

        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) $hoy->format('Y');
        }

        $primero = new \DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));

        return $this->layout->responder(
            titulo: 'Calendario de eventos',
            descripcion: 'Agenda institucional de la E.S.E. Hospital Rosario Pumarejo de López: '
                . 'rendición de cuentas, jornadas de salud y espacios de participación ciudadana.',
            contenido: $this->vistas->render('publico/eventos/agenda', [
                'eventos' => $this->eventos->delMes($anio, $mes),
                'proximos' => $this->eventos->proximos(5),
                'mesActual' => $primero,
                'mesAnterior' => $primero->modify('-1 month'),
                'mesSiguiente' => $primero->modify('+1 month'),
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $evento = $this->eventos->publicoPorSlug((string) $peticion->attributes->get('slug'));

        if ($evento === null) {
            return $this->layout->responder(
                titulo: 'Evento no encontrado',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Evento no encontrado',
                    'mensaje' => 'Este evento no existe o dejó de publicarse. Consulta la agenda completa.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: (string) $evento['titulo'],
            descripcion: mb_substr(strip_tags((string) ($evento['descripcion'] ?? '')), 0, 300) ?: null,
            contenido: $this->vistas->render('publico/eventos/detalle', ['evento' => $evento]),
        );
    }
}
