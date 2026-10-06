<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Throwable;

/**
 * Corta la cascada de errores `SAVEPOINT DOCTRINE_NN does not exist`.
 *
 * Toda la suite comparte UNA conexión estática (`BaseDatosDePruebas`). Cuando
 * una prueba falla dentro de un `transactional()`, la conexión queda con el
 * nivel de anidamiento en alto: la transacción nunca se cerró. A partir de ahí
 * **toda** prueba que abra una transacción intenta crear un savepoint sobre una
 * transacción que el servidor ya descartó, y falla. Así es como un solo fallo
 * real se convertía en decenas: llegamos a ver 342 errores de los que uno solo
 * era verdadero.
 *
 * Esta extensión deshace cualquier transacción que quede abierta al terminar
 * cada prueba. No esconde el fallo original —ese se sigue reportando— sino que
 * impide que contamine a las que vienen detrás, que es lo que hacía la suite
 * ilegible.
 *
 * Se engancha a `Test\Finished`, que PHPUnit emite tanto si la prueba pasa como
 * si falla o lanza una excepción; `tearDown()` no sirve, porque una excepción
 * en `setUp()` lo salta.
 */
final class SaneaTransacciones implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $facade->registerSubscriber(new class implements FinishedSubscriber {
            public function notify(Finished $evento): void
            {
                $conexion = BaseDatosDePruebas::conexionAbiertaONula();

                if ($conexion === null) {
                    return;
                }

                // `rollBack()` baja un nivel por llamada, así que un anidamiento
                // de tres necesita tres. El tope evita un bucle infinito si el
                // driver dejara de bajar el contador.
                for ($i = 0; $i < 16 && $conexion->getTransactionNestingLevel() > 0; $i++) {
                    try {
                        $conexion->rollBack();
                    } catch (Throwable) {
                        // La transacción ya no existe del lado del servidor
                        // (MySQL hace commit implícito con cualquier DDL). No
                        // hay nada que deshacer y tampoco nada que reportar:
                        // el fallo de verdad ya lo contó PHPUnit.
                        break;
                    }
                }
            }
        });
    }
}
