<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Core\Audit\AuditoriaService;
use App\Core\Auth\Actor;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Exception\TransicionEstadoInvalidaException;
use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Contenido\Request\NoticiaDatos;
use App\Modules\Contenido\Service\NoticiaService;
use App\Modules\Contenido\Service\SanitizadorHtml;
use App\Modules\Contenido\Service\VersionadoService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(NoticiaService::class)]
#[CoversClass(NoticiaRepository::class)]
final class NoticiaServiceTest extends TestCase
{
    private static Connection $conexion;
    private NoticiaService $servicio;
    private NoticiaRepository $noticias;
    private Actor $actor;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM contenido_versiones');
        self::$conexion->executeStatement('DELETE FROM noticias');

        $this->noticias = new NoticiaRepository(self::$conexion);
        $this->servicio = new NoticiaService(
            $this->noticias,
            new SanitizadorHtml(),
            new VersionadoService(self::$conexion),
            new AuditoriaService(self::$conexion),
        );
        $this->actor = new Actor(7, 'María Pérez', '203.0.113.7', 'PHPUnit');
    }

    public function testCrearSaneaElHtmlVersionaYAudita(): void
    {
        $id = $this->servicio->crear($this->datos(
            contenido: '<p>Válido</p><script>alert("xss")</script>',
        ), null, $this->actor);

        $noticia = $this->noticias->porId($id);
        self::assertNotNull($noticia);
        self::assertStringNotContainsString('script', $noticia->contenido);
        self::assertStringContainsString('<p>Válido</p>', $noticia->contenido);
        self::assertSame(EstadoAprobacion::Borrador, $noticia->estado);
        self::assertSame('jornada-de-vacunacion', $noticia->slug);

        self::assertSame(1, $this->ultimaVersion($id));
        self::assertSame('crear', $this->ultimaAuditoria()['accion']);
    }

    public function testActualizarCreaLaSiguienteVersion(): void
    {
        $id = $this->servicio->crear($this->datos(), null, $this->actor);
        $this->servicio->actualizar($id, $this->datos(titulo: 'Título corregido'), null, $this->actor);

        self::assertSame('Título corregido', $this->noticias->porId($id)?->titulo);
        self::assertSame(2, $this->ultimaVersion($id));
        // El slug no cambia al editar: las URLs publicadas no se rompen.
        self::assertSame('jornada-de-vacunacion', $this->noticias->porId($id)?->slug);
    }

    public function testElFlujoDeAprobacionCompletoQuedaAuditado(): void
    {
        $id = $this->servicio->crear($this->datos(), null, $this->actor);

        $this->servicio->cambiarEstado($id, EstadoAprobacion::EnRevision, $this->actor);
        $this->servicio->cambiarEstado($id, EstadoAprobacion::Aprobado, $this->actor);
        $this->servicio->cambiarEstado($id, EstadoAprobacion::Publicado, $this->actor);

        self::assertSame(EstadoAprobacion::Publicado, $this->noticias->porId($id)?->estado);
        self::assertSame('publicar', $this->ultimaAuditoria()['accion']);

        $fila = self::$conexion->fetchAssociative('SELECT aprobado_por, aprobado_en FROM noticias WHERE id = ?', [$id]);
        self::assertNotFalse($fila);
        self::assertSame(7, (int) $fila['aprobado_por']);
        self::assertNotNull($fila['aprobado_en']);
    }

    public function testNoSePuedePublicarUnBorradorDirectamente(): void
    {
        $id = $this->servicio->crear($this->datos(), null, $this->actor);

        $this->expectException(TransicionEstadoInvalidaException::class);

        $this->servicio->cambiarEstado($id, EstadoAprobacion::Publicado, $this->actor);
    }

    public function testRestaurarVuelveAlContenidoViejoSinReescribirElHistorial(): void
    {
        $id = $this->servicio->crear($this->datos(titulo: 'Título original'), null, $this->actor);
        $this->servicio->actualizar($id, $this->datos(titulo: 'Título dañado'), null, $this->actor);

        $this->servicio->restaurar($id, 1, $this->actor);

        // El contenido volvió…
        self::assertSame('Título original', $this->noticias->porId($id)?->titulo);
        // …como versión nueva (3), con la 2 intacta en el historial…
        self::assertSame(3, $this->ultimaVersion($id));
        // …y auditado.
        $auditoria = $this->ultimaAuditoria();
        self::assertSame('restaurar', $auditoria['accion']);
        self::assertSame('María Pérez', $auditoria['usuario_nombre']);
    }

    public function testEliminarEsLogicoYLaNoticiaDejaDeExistirParaElPanel(): void
    {
        $id = $this->servicio->crear($this->datos(), null, $this->actor);

        $this->servicio->eliminar($id, $this->actor);

        self::assertNull($this->noticias->porId($id));
        self::assertSame('eliminar', $this->ultimaAuditoria()['accion']);
        // Sigue en la tabla: borrado lógico, nunca DELETE físico.
        self::assertNotFalse(self::$conexion->fetchOne('SELECT id FROM noticias WHERE id = ?', [$id]));
    }

    public function testDosTitulosIgualesObtienenSlugsDistintos(): void
    {
        $a = $this->servicio->crear($this->datos(), null, $this->actor);
        $b = $this->servicio->crear($this->datos(), null, $this->actor);

        self::assertSame('jornada-de-vacunacion', $this->noticias->porId($a)?->slug);
        self::assertSame('jornada-de-vacunacion-2', $this->noticias->porId($b)?->slug);
    }

    // --- soporte ---

    private function datos(
        string $titulo = 'Jornada de vacunación',
        string $contenido = '<p>Contenido</p>',
    ): NoticiaDatos {
        return new NoticiaDatos(
            titulo: $titulo,
            resumen: 'Resumen de la noticia',
            contenido: $contenido,
            categoriaId: null,
            destacada: false,
            metaTitulo: null,
            metaDescripcion: null,
            publicarEn: null,
            despublicarEn: null,
        );
    }

    private function ultimaVersion(int $id): int
    {
        return (int) self::$conexion->fetchOne(
            "SELECT MAX(version) FROM contenido_versiones WHERE entidad = 'noticias' AND entidad_id = ?",
            [$id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ultimaAuditoria(): array
    {
        $fila = self::$conexion->fetchAssociative('SELECT * FROM auditoria ORDER BY id DESC LIMIT 1');
        self::assertNotFalse($fila);

        return $fila;
    }
}
