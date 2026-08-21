<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Contenido\Repository\PaginaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio del paso 10 (Fase 1): el ciudadano solo ve lo publicado y dentro
 * de su ventana. Todo lo demás —borradores, en revisión, vencido, eliminado—
 * no existe para el sitio público.
 */
#[CoversClass(NoticiaRepository::class)]
#[CoversClass(PaginaRepository::class)]
final class VisibilidadPublicaTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM noticias');
        self::$conexion->executeStatement('DELETE FROM paginas');
    }

    public function testSoloLaNoticiaPublicadaYVigenteSeVe(): void
    {
        $this->crearNoticia('visible', 'publicado');
        $this->crearNoticia('en-revision', 'en_revision');
        $this->crearNoticia('vencida', 'publicado', despublicarEnMinutos: -10);
        $this->crearNoticia('programada', 'publicado', publicarEnMinutos: 60);
        $this->crearNoticia('eliminada', 'publicado', eliminada: true);

        $repositorio = new NoticiaRepository(self::$conexion);

        self::assertNotNull($repositorio->publicaPorSlug('visible'));
        self::assertNull($repositorio->publicaPorSlug('en-revision'));
        self::assertNull($repositorio->publicaPorSlug('vencida'));
        self::assertNull($repositorio->publicaPorSlug('programada'));
        self::assertNull($repositorio->publicaPorSlug('eliminada'));

        $recientes = $repositorio->publicasRecientes(10);
        self::assertCount(1, $recientes);
        self::assertSame('visible', $recientes[0]->slug);
    }

    public function testUnaPaginaEnRevisionNoExisteParaElPublico(): void
    {
        $this->crearPagina('/institucional/quienes-somos', 'publicado');
        $this->crearPagina('/institucional/historia', 'en_revision');

        $repositorio = new PaginaRepository(self::$conexion);

        self::assertNotNull($repositorio->publicaPorRuta('/institucional/quienes-somos'));
        self::assertNull($repositorio->publicaPorRuta('/institucional/historia'));
    }

    // --- soporte ---

    private function crearNoticia(
        string $slug,
        string $estado,
        ?int $publicarEnMinutos = null,
        ?int $despublicarEnMinutos = null,
        bool $eliminada = false,
    ): void {
        self::$conexion->executeStatement(
            'INSERT INTO noticias (slug, titulo, resumen, contenido, estado_aprobacion, publicar_en, despublicar_en, eliminado_en)
             VALUES (?, ?, ?, ?, ?,
                     IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE),
                     IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE),
                     IF(?, NOW(), NULL))',
            [
                $slug, 'Título', 'Resumen', '<p>Contenido</p>', $estado,
                $publicarEnMinutos, $publicarEnMinutos ?? 0,
                $despublicarEnMinutos, $despublicarEnMinutos ?? 0,
                (int) $eliminada,
            ],
        );
    }

    private function crearPagina(string $ruta, string $estado): void
    {
        self::$conexion->insert('paginas', [
            'slug' => trim(str_replace('/', '-', $ruta), '-'),
            'ruta' => $ruta,
            'titulo' => 'Título',
            'contenido' => '<p>Contenido</p>',
            'estado_aprobacion' => $estado,
        ]);
    }
}
