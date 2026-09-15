<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Sistema;

use App\Modules\Sistema\Service\CatalogoPermisos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El catálogo de permisos del panel (Fase 10).
 *
 * La prueba clave es la última: todo permiso exigido por una ruta tiene que
 * estar en el catálogo. Si no, el administrador no puede concederlo desde el
 * panel y la sección queda inalcanzable para cualquiera que no sea el rol
 * con comodín, que es justo el reparto de permisos que este módulo evita.
 */
#[CoversClass(CatalogoPermisos::class)]
final class CatalogoPermisosTest extends TestCase
{
    private CatalogoPermisos $catalogo;

    protected function setUp(): void
    {
        $this->catalogo = new CatalogoPermisos();
    }

    public function testDepurarDescartaLosPermisosQueNoExisten(): void
    {
        $depurados = $this->catalogo->depurar(['contenido.ver', 'inventado.falso', 'contenido.editar']);

        self::assertSame(['contenido.ver', 'contenido.editar'], $depurados);
    }

    public function testDepurarConservaElComodinDelAdministrador(): void
    {
        self::assertSame(['*'], $this->catalogo->depurar(['*']));
    }

    public function testDepurarQuitaRepetidos(): void
    {
        self::assertSame(['contenido.ver'], $this->catalogo->depurar(['contenido.ver', 'contenido.ver']));
    }

    public function testCadaPermisoDelCatalogoTieneDescripcionPropia(): void
    {
        foreach ($this->catalogo->todos() as $permiso) {
            self::assertNotSame(
                $permiso,
                $this->catalogo->describir($permiso),
                sprintf('«%s» no tiene descripción: la casilla del panel saldría sin explicar.', $permiso),
            );
        }
    }

    public function testTodoPermisoExigidoPorUnaRutaSePuedeConcederDesdeElPanel(): void
    {
        $delCatalogo = $this->catalogo->todos();
        $deLasRutas = $this->permisosDeLasRutas();

        // Si la extracción dejara de encontrar permisos, la prueba pasaría
        // vacía y dejaría de proteger nada.
        self::assertGreaterThan(15, count($deLasRutas), 'La lectura de config/rutas.php no está encontrando los permisos.');

        $faltantes = [];

        foreach ($deLasRutas as $permiso) {
            if (!in_array($permiso, $delCatalogo, true)) {
                $faltantes[] = $permiso;
            }
        }

        self::assertSame([], $faltantes, sprintf(
            'Estos permisos los exige config/rutas.php pero no están en el catálogo, '
            . 'así que ningún rol puede recibirlos desde el panel: %s',
            implode(', ', $faltantes),
        ));
    }

    /**
     * Lee los `_permiso` declarados en las rutas sin cargar el contenedor:
     * basta el texto del archivo, y así la prueba no depende de la base.
     *
     * @return list<string>
     */
    private function permisosDeLasRutas(): array
    {
        $fuente = (string) file_get_contents(dirname(__DIR__, 4) . '/config/rutas.php');

        // Dos formas en el archivo: literal («'_permiso' => 'x.y'») y por
        // helper, donde el permiso llega como último argumento: $panel(...),
        // $sistema(...), $privada(...) o $permisoDoc(...).
        $comilla = chr(39);

        preg_match_all(
            '/' . $comilla . '_permiso' . $comilla . '\s*=>\s*' . $comilla . '([a-z0-9_.]+)' . $comilla . '/',
            $fuente,
            $literales,
        );

        preg_match_all(
            '/\$(?:panel|sistema|privada|permisoDoc|permisoTipos|permisoTransp|permisoDatos|permisoTram)\([^)]*?'
                . $comilla . '([a-z0-9]+\.[a-z0-9_.]+)' . $comilla . '\s*\)/',
            $fuente,
            $porHelper,
        );

        return array_values(array_unique([...$literales[1], ...$porHelper[1]]));
    }
}
