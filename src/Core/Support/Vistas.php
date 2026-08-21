<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Support\Exception\VistaNoEncontradaException;
use Closure;

/**
 * Renderizador de plantillas PHP planas de `resources/views`.
 *
 * No es un motor: es `require` con búfer y ámbito aislado. Cada plantilla
 * recibe sus datos como variables, `$e(...)` para escapar y `$vistas` para
 * parciales. La regla es una sola: TODO dato dinámico pasa por `$e()` salvo
 * HTML ya saneado a conciencia (y eso se comenta en la plantilla).
 */
final readonly class Vistas
{
    public function __construct(private string $directorio)
    {
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function render(string $plantilla, array $datos = []): string
    {
        $ruta = $this->directorio . '/' . $plantilla . '.php';

        if (!is_file($ruta)) {
            throw VistaNoEncontradaException::para($plantilla, $ruta);
        }

        $e = static fn (string $texto): string => htmlspecialchars(
            $texto,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        // Closure sin $this ni ámbito de esta clase: la plantilla solo ve sus
        // datos, $e y $vistas. Los nombres internos van con __ para que
        // extract() no los pise.
        $incluir = Closure::bind(
            static function (string $__ruta, array $__datos, Closure $e, Vistas $vistas): string {
                extract($__datos, EXTR_SKIP);
                ob_start();

                try {
                    require $__ruta;
                } catch (\Throwable $__error) {
                    ob_end_clean();

                    throw $__error;
                }

                return (string) ob_get_clean();
            },
            null,
            null,
        );

        return $incluir($ruta, $datos, $e, $this);
    }
}
