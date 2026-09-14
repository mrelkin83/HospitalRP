<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Core\Audit\AuditoriaService;
use App\Modules\Contenido\Command\SembrarPaginasCommand;
use App\Modules\Sistema\Repository\MenuRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\BaseDatosDePruebas;

/**
 * Bloque A de la Fase 9 (docs/15). La auditoría del 2026-09-14 encontró tres
 * ítems de menú protegidos apuntando a rutas que respondían 404; estas
 * pruebas fijan que dejen de hacerlo y que el mapa del sitio se arme solo.
 */
#[CoversClass(MenuRepository::class)]
#[CoversClass(SembrarPaginasCommand::class)]
final class MapaSitioTest extends TestCase
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
        self::$conexion->executeStatement(
            "DELETE FROM paginas WHERE ruta LIKE '/el-hospital%' OR ruta IN ('/participa', '/notificaciones-judiciales')",
        );
    }

    public function testElArbolPublicoAnidaLosHijosBajoSuPadre(): void
    {
        $menuId = $this->crearMenu('Principal', 'principal');
        $padre = $this->crearItem($menuId, null, 'El Hospital', '/el-hospital', 0);
        $this->crearItem($menuId, $padre, 'Historia', '/el-hospital/historia', 0);
        $this->crearItem($menuId, null, 'Noticias', '/noticias', 1);

        $arbol = (new MenuRepository(self::$conexion))->arbolPublico();

        self::assertCount(1, $arbol);
        self::assertSame('Principal', $arbol[0]['nombre']);
        self::assertSame(
            [['El Hospital', 0], ['Historia', 1], ['Noticias', 0]],
            array_map(static fn (array $i): array => [$i['etiqueta'], $i['nivel']], $arbol[0]['items']),
        );
    }

    public function testElArbolPublicoOmiteLosItemsOcultos(): void
    {
        $menuId = $this->crearMenu('Pie', 'pie_1');
        $this->crearItem($menuId, null, 'Visible', '/visible', 0);
        $this->crearItem($menuId, null, 'Oculto', '/oculto', 1, visible: false);

        $arbol = (new MenuRepository(self::$conexion))->arbolPublico();

        self::assertSame(['Visible'], array_column($arbol[0]['items'], 'etiqueta'));
    }

    /**
     * El defecto concreto de docs/14 §5.1: el menú sembraba `/participa` y
     * `/notificaciones-judiciales` sin página que los sirviera.
     */
    public function testLasPaginasProtegidasDelMenuQuedanPublicadas(): void
    {
        $this->sembrarPaginas();

        foreach (['/participa', '/notificaciones-judiciales', '/el-hospital'] as $ruta) {
            $estado = self::$conexion->fetchOne('SELECT estado_aprobacion FROM paginas WHERE ruta = ?', [$ruta]);

            self::assertSame('publicado', $estado, sprintf('La ruta %s debe quedar publicada', $ruta));
        }
    }

    /** Una ficha institucional sin texto no puede salir al aire. */
    public function testLasPaginasSinContenidoQuedanEnBorrador(): void
    {
        $this->sembrarPaginas();

        $estado = self::$conexion->fetchOne(
            'SELECT estado_aprobacion FROM paginas WHERE ruta = ?',
            ['/el-hospital/historia'],
        );

        self::assertSame('borrador', $estado);
    }

    public function testSembrarDosVecesNoDuplicaNingunaPagina(): void
    {
        $this->sembrarPaginas();
        $this->sembrarPaginas();

        $total = self::$conexion->fetchOne(
            "SELECT COUNT(*) FROM paginas WHERE ruta LIKE '/el-hospital%' OR ruta IN ('/participa', '/notificaciones-judiciales')",
        );

        self::assertSame(14, (int) $total);
    }

    private function sembrarPaginas(): void
    {
        $comando = new SembrarPaginasCommand(self::$conexion, new AuditoriaService(self::$conexion));
        (new CommandTester($comando))->execute([]);
    }

    private function crearMenu(string $nombre, string $slug): int
    {
        self::$conexion->insert('menus', [
            'nombre' => $nombre,
            'slug' => $slug,
            'ubicacion' => $slug,
            'protegido' => 0,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function crearItem(int $menuId, ?int $padreId, string $etiqueta, string $url, int $orden, bool $visible = true): int
    {
        self::$conexion->insert('menu_items', [
            'menu_id' => $menuId,
            'padre_id' => $padreId,
            'etiqueta' => $etiqueta,
            'url' => $url,
            'orden' => $orden,
            'visible' => $visible ? 1 : 0,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}
