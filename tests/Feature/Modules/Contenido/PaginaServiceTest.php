<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Core\Audit\AuditoriaService;
use App\Core\Auth\Actor;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;
use App\Modules\Busqueda\Service\IndexadorService;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Repository\PaginaRepository;
use App\Modules\Contenido\Request\PaginaDatos;
use App\Modules\Contenido\Service\PaginaService;
use App\Modules\Contenido\Service\SanitizadorHtml;
use App\Modules\Contenido\Service\VersionadoService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(PaginaService::class)]
#[CoversClass(PaginaRepository::class)]
final class PaginaServiceTest extends TestCase
{
    private static Connection $conexion;
    private PaginaService $servicio;
    private PaginaRepository $paginas;
    private Actor $actor;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM contenido_versiones');
        self::$conexion->executeStatement('DELETE FROM paginas');

        $this->paginas = new PaginaRepository(self::$conexion);
        $this->servicio = new PaginaService(
            $this->paginas,
            new SanitizadorHtml(),
            new VersionadoService(self::$conexion),
            new AuditoriaService(self::$conexion),
            new IndexadorService(self::$conexion, new IndiceBusquedaRepository(self::$conexion)),
        );
        $this->actor = new Actor(7, 'María Pérez', '203.0.113.7', 'PHPUnit');
    }

    public function testElCicloCompletoDejaLaPaginaVisibleAlPublico(): void
    {
        $id = $this->servicio->crear($this->datos(), null, $this->actor);

        $this->servicio->cambiarEstado($id, EstadoAprobacion::EnRevision, $this->actor);
        $this->servicio->cambiarEstado($id, EstadoAprobacion::Aprobado, $this->actor);
        $this->servicio->cambiarEstado($id, EstadoAprobacion::Publicado, $this->actor);

        self::assertNotNull($this->paginas->publicaPorRuta('/institucional/quienes-somos'));
    }

    public function testRestaurarRecuperaElContenidoSinTocarLaRuta(): void
    {
        $id = $this->servicio->crear($this->datos(titulo: 'Quiénes somos'), null, $this->actor);
        $this->servicio->actualizar($id, $this->datos(titulo: 'Título dañado', ruta: '/institucional/otra-ruta'), null, $this->actor);

        $this->servicio->restaurar($id, 1, $this->actor);

        $pagina = $this->paginas->porId($id);
        self::assertSame('Quiénes somos', $pagina?->titulo);
        // La ruta no viaja en el snapshot: restaurar contenido no rompe URLs.
        self::assertSame('/institucional/otra-ruta', $pagina?->ruta);
    }

    public function testUnaPaginaProtegidaNoSePuedeEliminar(): void
    {
        $id = $this->servicio->crear($this->datos(), null, $this->actor);
        self::$conexion->executeStatement('UPDATE paginas SET protegida = 1 WHERE id = ?', [$id]);

        self::assertFalse($this->servicio->eliminar($id, $this->actor));
        self::assertNotNull($this->paginas->porId($id));
    }

    public function testLaRutaDuplicadaSeDetecta(): void
    {
        $this->servicio->crear($this->datos(), null, $this->actor);

        self::assertTrue($this->paginas->rutaOcupada('/institucional/quienes-somos'));
        self::assertFalse($this->paginas->rutaOcupada('/institucional/historia'));
    }

    // --- soporte ---

    private function datos(
        string $titulo = 'Quiénes somos',
        string $ruta = '/institucional/quienes-somos',
    ): PaginaDatos {
        return new PaginaDatos(
            titulo: $titulo,
            ruta: $ruta,
            resumen: null,
            contenido: '<p>Contenido institucional</p>',
            noindex: false,
            metaTitulo: null,
            metaDescripcion: null,
            publicarEn: null,
            despublicarEn: null,
        );
    }
}
