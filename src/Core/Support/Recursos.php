<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Huella de caché de los estáticos compilados.
 *
 * Nace de un fallo real (2026-09-14): se cambió la paleta del sitio, se
 * recompiló `app.css` y el visitante seguía viendo el diseño viejo. El
 * service worker cachea `/build/` con estrategia «caché primero» y su
 * comentario daba por hecho que esos archivos «son versionados» — pero la
 * URL era siempre `/build/app.css`, sin versión. Cache-first sobre una URL
 * que nunca cambia es una hoja de estilos congelada para siempre.
 *
 * Aquí se hace cierta esa suposición: la URL cambia cuando cambia el archivo,
 * así que el navegador y el service worker piden una entrada nueva sin que
 * nadie tenga que acordarse de purgar nada.
 *
 * Se usa `filemtime` y no un hash del contenido a propósito: leer el archivo
 * entero en cada petición para hashearlo cuesta más de lo que aporta, y el
 * despliegue siempre reescribe el archivo.
 */
final class Recursos
{
    /** @var array<string, string> ruta => ruta con huella, resuelta una vez por petición */
    private static array $cache = [];

    private function __construct()
    {
    }

    /**
     * Devuelve la ruta pública con su huella: `/build/app.css?v=1a2b3c`.
     *
     * Si el archivo no existe —desarrollo sin compilar, por ejemplo— devuelve
     * la ruta tal cual en lugar de romper la página: una hoja sin versionar
     * es un problema menor que una plantilla que revienta.
     */
    public static function url(string $ruta): string
    {
        if (isset(self::$cache[$ruta])) {
            return self::$cache[$ruta];
        }

        $absoluta = dirname(__DIR__, 3) . '/public' . $ruta;
        $marca = is_file($absoluta) ? filemtime($absoluta) : false;

        return self::$cache[$ruta] = $marca === false
            ? $ruta
            : $ruta . '?v=' . dechex($marca);
    }

    /** Solo para las pruebas: la huella se memoriza por petición. */
    public static function olvidar(): void
    {
        self::$cache = [];
    }
}
