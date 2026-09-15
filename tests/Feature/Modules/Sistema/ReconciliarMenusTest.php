<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Core\Audit\AuditoriaService;
use App\Modules\Sistema\Command\SembrarMenusCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\BaseDatosDePruebas;

/**
 * Reconciliación del menú.
 *
 * Sembrar solo AÑADE, y eso dejó una instalación con once raíces: las siete
 * del plano de la Fase 9 conviviendo con las viejas de la Fase 0, con
 * «Especialidades», «Directorio médico» y «Sedes» duplicados como raíz y como
 * hijos de «Servicios». Ese exceso era además lo que desbordaba la cabecera.
 *
 * `--reconciliar` limpia eso. Lo que estas pruebas fijan es hasta dónde puede
 * llegar: jamás debe tocar un ítem protegido de la Res. 1519.
 */
#[CoversClass(SembrarMenusCommand::class)]
final class ReconciliarMenusTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM menu_items');
        self::$conexion->executeStatement('DELETE FROM menus');
    }

    public function testSinLaOpcionNoSeRetiraNada(): void
    {
        $this->sembrar();
        $sobrante = $this->agregarSobrante('Sedes', '/sedes-viejo', protegido: false);

        $this->sembrar();

        self::assertTrue($this->existe($sobrante), 'Sembrar sin la opción nunca debe borrar.');
    }

    public function testConLaOpcionSeRetiraLoQueYaNoEstaEnElPlano(): void
    {
        $this->sembrar();
        $sobrante = $this->agregarSobrante('Sedes', '/sedes-viejo', protegido: false);

        $this->sembrar(reconciliar: true);

        self::assertFalse($this->existe($sobrante), 'El ítem fuera del plano debía retirarse.');
    }

    /** La red de seguridad: los ítems de la Res. 1519 no se pueden perder. */
    public function testNuncaSeRetiraUnItemProtegido(): void
    {
        $this->sembrar();
        $protegido = $this->agregarSobrante('Menú obligatorio', '/algo-protegido', protegido: true);

        $this->sembrar(reconciliar: true);

        self::assertTrue($this->existe($protegido), 'Un ítem protegido jamás debe retirarse.');
    }

    /**
     * El caso que motivó todo: «Sedes» en plural con una sola sede. La
     * etiqueta del plano tiene que llegar a una instalación ya sembrada.
     */
    public function testLaReconciliacionPoneLasEtiquetasAlDia(): void
    {
        $this->sembrar();
        self::$conexion->executeStatement(
            "UPDATE menu_items SET etiqueta = 'Sedes' WHERE url = '/sedes'",
        );

        $this->sembrar(reconciliar: true);

        $etiquetas = self::$conexion->fetchFirstColumn(
            "SELECT etiqueta FROM menu_items WHERE url = '/sedes'",
        );

        self::assertNotSame([], $etiquetas);
        self::assertSame(['Nuestra sede y horarios'], array_values(array_unique($etiquetas)));
    }

    public function testElMenuPrincipalNoSeLlenaDeRaices(): void
    {
        $this->sembrar(reconciliar: true);

        $raices = (int) self::$conexion->fetchOne(
            "SELECT COUNT(*) FROM menu_items i
               JOIN menus m ON m.id = i.menu_id
              WHERE m.slug = 'principal' AND i.padre_id IS NULL",
        );

        // Siete raíces en el plano. Más de nueve no caben en una fila de
        // 1280 px y la cabecera vuelve a partirse.
        self::assertLessThanOrEqual(9, $raices, 'Demasiadas raíces: la cabecera se parte.');
    }

    private function sembrar(bool $reconciliar = false): void
    {
        $comando = new SembrarMenusCommand(self::$conexion, new AuditoriaService(self::$conexion));
        (new CommandTester($comando))->execute($reconciliar ? ['--reconciliar' => true] : []);
    }

    private function agregarSobrante(string $etiqueta, string $url, bool $protegido): int
    {
        $menuId = (int) self::$conexion->fetchOne("SELECT id FROM menus WHERE slug = 'principal'");

        self::$conexion->insert('menu_items', [
            'menu_id' => $menuId,
            'etiqueta' => $etiqueta,
            'url' => $url,
            'orden' => 99,
            'protegido' => $protegido ? 1 : 0,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function existe(int $id): bool
    {
        return false !== self::$conexion->fetchOne('SELECT id FROM menu_items WHERE id = ?', [$id]);
    }
}
