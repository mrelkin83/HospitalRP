<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Qué direcciones acepta el panel en un campo de enlace.
 *
 * Lo que se escribe en esos campos acaba en un `href` del sitio público
 * —tarjetas de accesos rápidos, botones del héroe, menús— y `javascript:` en
 * un `href` ejecuta código en el navegador de cualquier visitante. Escapar el
 * valor no protege: `$e()` deja el esquema intacto porque no hay caracteres
 * que escapar. Se filtra al guardar, que además es donde queda auditado.
 *
 * Lista de permitidos, no de prohibidos: `data:`, `vbscript:` y lo que venga
 * mañana se quedan fuera sin que nadie tenga que acordarse de añadirlos.
 *
 * Vive en `Core\Support` y no dentro de un controlador porque la regla es una
 * sola: el día que haya que admitir otro esquema, se cambia aquí y vale para
 * todos los formularios a la vez.
 */
final readonly class Enlaces
{
    private const int LARGO_MAXIMO = 255;

    public static function aceptable(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > self::LARGO_MAXIMO) {
            return false;
        }

        // Ruta del propio sitio («/citas»), pero no «//otrositio.com», que el
        // navegador lee como dirección absoluta a otro dominio.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        return (bool) preg_match('#^(https?://|mailto:|tel:)#i', $url);
    }
}
