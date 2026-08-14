<?php

declare(strict_types=1);

namespace App\Core\Config;

/**
 * Configuración de infraestructura de la aplicación.
 *
 * Ojo con la frontera: aquí vive solo lo que cambia entre servidores (entorno,
 * URL, zona horaria). Todo lo que un funcionario deba poder cambiar —teléfonos,
 * horarios, colores, textos— va en la tabla `configuracion`, nunca aquí.
 * Es la regla de oro #2 de CLAUDE.md §2.
 */
final readonly class ConfigAplicacion
{
    public function __construct(
        public Entorno $entorno,
        public bool $debug,
        public string $url,
        public string $zonaHoraria,
    ) {
    }

    public static function desde(Env $env): self
    {
        $entorno = Entorno::from(
            $env->opcion(
                'APP_ENTORNO',
                array_map(static fn (Entorno $e): string => $e->value, Entorno::cases()),
                Entorno::Produccion->value,
            ),
        );

        return new self(
            entorno: $entorno,
            // Un `APP_DEBUG=true` olvidado en producción filtra trazas con rutas y
            // credenciales. El entorno manda sobre la variable, no al revés.
            debug: $entorno->permiteMostrarErrores() && $env->booleano('APP_DEBUG', false),
            url: rtrim($env->texto('APP_URL'), '/'),
            zonaHoraria: $env->texto('APP_ZONA_HORARIA', 'America/Bogota'),
        );
    }
}
