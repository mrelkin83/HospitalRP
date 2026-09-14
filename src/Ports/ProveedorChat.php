<?php

declare(strict_types=1);

namespace App\Ports;

/**
 * Puerto hacia el chat en vivo. Entró al alcance el 2026-09-14 (CLAUDE.md §7)
 * porque el Joomla ya lo tenía publicado y el hospital decidió conservarlo.
 *
 * Va detrás de un puerto por la misma razón que el correo y la IA: el
 * proveedor de chat es un tercero que carga JavaScript en el sitio de un
 * hospital. Cambiarlo, o apagarlo el día que incumpla, tiene que ser
 * configuración y no un despliegue.
 *
 * Dos límites que el adaptador debe respetar siempre:
 *
 * 1. **No es un canal clínico.** El chat orienta sobre trámites, horarios y
 *    ubicación. No da resultados, ni diagnósticos, ni agenda citas reales
 *    (CLAUDE.md §2.6 y §7).
 * 2. **No carga si el ciudadano no aceptó cookies de terceros.** El widget es
 *    un script externo; el banner de cookies manda sobre él.
 */
interface ProveedorChat
{
    /**
     * ¿Hay chat disponible ahora mismo? Falso apaga el widget entero sin
     * dejar rastro en el HTML.
     */
    public function estaActivo(): bool;

    /**
     * Fragmento HTML que monta el widget, ya listo para incrustar, o cadena
     * vacía si no hay nada que montar.
     *
     * El aviso de que el chat no atiende urgencias ni consultas clínicas lo
     * pone el sitio, no el proveedor: no se delega en un tercero un mensaje
     * del que responde el hospital.
     */
    public function widget(): string;

    /** Nombre del proveedor, para la auditoría y el panel. */
    public function nombre(): string;
}
