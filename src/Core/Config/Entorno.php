<?php

declare(strict_types=1);

namespace App\Core\Config;

/**
 * El entorno de ejecución. Nunca un string suelto: de esto dependen la
 * exposición de errores, las cookies seguras y el envío real de correo.
 */
enum Entorno: string
{
    case Local = 'local';
    case Pruebas = 'pruebas';
    case Produccion = 'produccion';

    public function esProduccion(): bool
    {
        return $this === self::Produccion;
    }

    /**
     * En pruebas no se envía correo, no se escribe en disco real y no se
     * exige HTTPS. Se consulta lo bastante como para merecer un método.
     */
    public function esPruebas(): bool
    {
        return $this === self::Pruebas;
    }

    /**
     * Fuera de producción se pueden mostrar trazas. Dentro, jamás:
     * una traza de PHP expone rutas, credenciales y estructura de tablas.
     */
    public function permiteMostrarErrores(): bool
    {
        return $this !== self::Produccion;
    }

    /**
     * Cookies `Secure` y HSTS solo tienen sentido sobre HTTPS, y en local
     * Laragon sirve por HTTP. En producción no es negociable.
     */
    public function exigeHttps(): bool
    {
        return $this === self::Produccion;
    }
}
