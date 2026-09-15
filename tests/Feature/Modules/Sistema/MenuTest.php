<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Modules\Sistema\Repository\MenuRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Menús de dos niveles y su interruptor.
 *
 * La consulta pública filtraba con `padre_id IS NULL`, así que los 48 enlaces
 * que el hospital había organizado bajo sus siete raíces no salían en ninguna
 * parte: el menú se veía completo en el panel y a medias en el sitio. Estas
 * pruebas existen para que no vuelva a pasar en silencio.
 */
#[CoversClass(MenuRepository::class)]
final class MenuTest extends TestCase
{
    /**
     * `ubicacion` es un ENUM cerrado —principal, superior, pie_1, pie_2,
     * pie_3—, así que la prueba no puede inventarse una ubicación: usa la del
     * pie institucional y la deja vacía al entrar y al salir.
     */
    private const string UBICACION = 'pie_3';

    private static Connection $conexion;
    private MenuRepository $menus;
    private int $menuId;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        $this->limpiar();
        $this->menus = new MenuRepository(self::$conexion);

        self::$conexion->insert('menus', [
            'nombre' => 'Menú de prueba',
            'slug' => 'menu-de-prueba',
            'ubicacion' => self::UBICACION,
        ]);

        $this->menuId = (int) self::$conexion->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
    }

    public function testUnaRaizLlegaConSusHijos(): void
    {
        $padre = $this->crearItem('El Hospital', '/el-hospital');
        $this->crearItem('Historia', '/el-hospital/historia', $padre);
        $this->crearItem('Organigrama', '/el-hospital/organigrama', $padre);

        $items = $this->menus->itemsPorUbicacion(self::UBICACION);

        self::assertCount(1, $items, 'Los hijos no pueden salir también como raíces.');
        self::assertSame('El Hospital', $items[0]['etiqueta']);
        self::assertSame(['Historia', 'Organigrama'], array_column($items[0]['hijos'], 'etiqueta'));
    }

    /** La casilla «Visible» del panel es lo que decide, también en los hijos. */
    public function testUnHijoOcultoNoLlegaAlSitio(): void
    {
        $padre = $this->crearItem('El Hospital', '/el-hospital');
        $this->crearItem('Historia', '/el-hospital/historia', $padre);
        $this->crearItem('Borrador interno', '/interno', $padre, visible: false);

        $items = $this->menus->itemsPorUbicacion(self::UBICACION);

        self::assertSame(['Historia'], array_column($items[0]['hijos'], 'etiqueta'));
    }

    /**
     * Si se apaga la raíz, sus hijos no se quedan sueltos por el menú: el
     * desplegable entero desaparece.
     */
    public function testAlOcultarLaRaizDesaparecenTambienSusHijos(): void
    {
        $padre = $this->crearItem('El Hospital', '/el-hospital', visible: false);
        $this->crearItem('Historia', '/el-hospital/historia', $padre);

        self::assertSame([], $this->menus->itemsPorUbicacion(self::UBICACION));
    }

    /** Un enlace no puede colgar de sí mismo: sería un menú que no se pinta. */
    public function testUnEnlaceNoPuedeSerSuPropioPadre(): void
    {
        $id = $this->crearItem('El Hospital', '/el-hospital');

        $this->menus->actualizarItem($id, 'El Hospital', '/el-hospital', 0, true, $id);

        self::assertNull(self::$conexion->fetchOne('SELECT padre_id FROM menu_items WHERE id = ?', [$id]));
    }

    // --- soporte ---

    private function crearItem(string $etiqueta, string $url, ?int $padre = null, bool $visible = true): int
    {
        self::$conexion->insert('menu_items', [
            'menu_id' => $this->menuId,
            'padre_id' => $padre,
            'etiqueta' => $etiqueta,
            'url' => $url,
            'destino' => 'mismo',
            'orden' => 0,
            'visible' => $visible ? 1 : 0,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function limpiar(): void
    {
        self::$conexion->executeStatement(
            'DELETE FROM menu_items WHERE menu_id IN (SELECT id FROM menus WHERE ubicacion = ?)',
            [self::UBICACION],
        );
        self::$conexion->executeStatement('DELETE FROM menus WHERE ubicacion = ?', [self::UBICACION]);
    }
}
