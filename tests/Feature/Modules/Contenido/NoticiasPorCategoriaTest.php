<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Modules\Contenido\Repository\NoticiaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Listado de noticias filtrado por categoría.
 *
 * Existe por los boletines de prensa: en el Joomla eran una sección propia y
 * aquí son una categoría de noticia. El módulo guardaba `categoria_id` desde
 * la Fase 1, pero el listado público no filtraba, así que el ítem de menú
 * «Boletines de prensa» habría llevado al listado completo.
 */
#[CoversClass(NoticiaRepository::class)]
final class NoticiasPorCategoriaTest extends TestCase
{
    private static Connection $conexion;
    private NoticiaRepository $noticias;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM noticias');
        self::$conexion->executeStatement("DELETE FROM categorias WHERE tipo = 'noticia'");
        $this->noticias = new NoticiaRepository(self::$conexion);
    }

    public function testSinCategoriaDevuelveTodoLoPublicado(): void
    {
        $boletines = $this->crearCategoria('Boletines de prensa', 'boletines-de-prensa');
        $this->crearNoticia('Boletín uno', 'boletin-uno', $boletines);
        $this->crearNoticia('Noticia suelta', 'noticia-suelta', null);

        self::assertSame(2, $this->noticias->publicasPaginadas(1, 12)['total']);
    }

    public function testConCategoriaSoloDevuelveEsaCategoria(): void
    {
        $boletines = $this->crearCategoria('Boletines de prensa', 'boletines-de-prensa');
        $this->crearNoticia('Boletín uno', 'boletin-uno', $boletines);
        $this->crearNoticia('Noticia suelta', 'noticia-suelta', null);

        $resultado = $this->noticias->publicasPaginadas(1, 12, $boletines);

        self::assertSame(1, $resultado['total']);
        self::assertSame('Boletín uno', $resultado['noticias'][0]->titulo);
    }

    /** El filtro no puede colarse la ventana de publicación. */
    public function testElFiltroSigueRespetandoLoQueNoEstaPublicado(): void
    {
        $boletines = $this->crearCategoria('Boletines de prensa', 'boletines-de-prensa');
        $this->crearNoticia('Boletín visible', 'boletin-visible', $boletines);
        $this->crearNoticia('Boletín en borrador', 'boletin-borrador', $boletines, publicado: false);

        $resultado = $this->noticias->publicasPaginadas(1, 12, $boletines);

        self::assertSame(1, $resultado['total']);
        self::assertSame('Boletín visible', $resultado['noticias'][0]->titulo);
    }

    /** Ofrecer un filtro sin contenido manda al ciudadano a una página vacía. */
    public function testLasCategoriasSinNadaPublicadoNoSeOfrecen(): void
    {
        $this->crearCategoria('Boletines de prensa', 'boletines-de-prensa');
        $conNoticias = $this->crearCategoria('Comunicados oficiales', 'comunicados');
        $this->crearNoticia('Comunicado', 'comunicado', $conNoticias);

        $ofrecidas = array_column($this->noticias->categoriasConNoticias(), 'slug');

        self::assertSame(['comunicados'], $ofrecidas);
    }

    public function testUnaCategoriaInventadaNoSeResuelve(): void
    {
        self::assertNull($this->noticias->categoriaPorSlug('no-existe'));
    }

    private function crearCategoria(string $nombre, string $slug): int
    {
        self::$conexion->insert('categorias', [
            'tipo' => 'noticia',
            'nombre' => $nombre,
            'slug' => $slug,
            'orden' => 0,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function crearNoticia(string $titulo, string $slug, ?int $categoriaId, bool $publicado = true): void
    {
        self::$conexion->insert('noticias', [
            'slug' => $slug,
            'titulo' => $titulo,
            'resumen' => 'Resumen de prueba.',
            'contenido' => '<p>Cuerpo de prueba.</p>',
            'categoria_id' => $categoriaId,
            'estado_aprobacion' => $publicado ? 'publicado' : 'borrador',
            'publicar_en' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);
    }
}
