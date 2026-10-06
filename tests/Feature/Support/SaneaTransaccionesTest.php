<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;
use Tests\Support\SaneaTransacciones;

/**
 * La red que impide que un fallo se convierta en una cascada.
 *
 * Toda la suite comparte una conexión estática. Si una prueba muere dentro de
 * un `transactional()`, la transacción queda abierta y cada prueba posterior
 * que use transacciones revienta con «SAVEPOINT DOCTRINE_NN does not exist».
 * Así es como un fallo real llegó a reportarse como 342.
 *
 * Estas dos pruebas van en pareja a propósito: la primera deja la conexión
 * sucia —exactamente como lo haría una que falla— y la segunda comprueba que
 * la extensión ya la limpió. `#[Depends]` garantiza el orden entre ellas sin
 * depender de cómo PHPUnit ordene el resto de la suite.
 */
#[CoversClass(SaneaTransacciones::class)]
final class SaneaTransaccionesTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    /** Simula lo que deja una prueba que muere a mitad de una transacción. */
    public function testUnaPruebaPuedeDejarLaConexionConUnaTransaccionAbierta(): string
    {
        self::$conexion->beginTransaction();
        self::$conexion->beginTransaction();

        self::assertSame(2, self::$conexion->getTransactionNestingLevel());

        return 'sucia';
    }

    #[Depends('testUnaPruebaPuedeDejarLaConexionConUnaTransaccionAbierta')]
    public function testLaSiguientePruebaRecibeLaConexionLimpia(string $estado): void
    {
        self::assertSame('sucia', $estado);

        self::assertSame(
            0,
            self::$conexion->getTransactionNestingLevel(),
            'La extensión SaneaTransacciones debía deshacer la transacción que quedó abierta.',
        );

        // Y la conexión sirve: una transacción nueva abre y cierra sin que
        // falte ningún savepoint.
        $uno = self::$conexion->transactional(
            static fn (Connection $c): int => (int) $c->fetchOne('SELECT 1'),
        );

        self::assertSame(1, $uno);
        self::assertSame(0, self::$conexion->getTransactionNestingLevel());
    }

    /** El accesor devuelve la conexión ya abierta, sin construir otra. */
    public function testElAccesorDevuelveLaMismaConexionCompartida(): void
    {
        self::assertSame(self::$conexion, BaseDatosDePruebas::conexionAbiertaONula());
    }
}
