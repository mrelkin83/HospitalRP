<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\TransparenciaPublicoController;
use App\Modules\Transparencia\Entity\TipoInstrumento;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Instrumentos de gestión de la información (Ley 1712 art. 6 lit. d, ITA 9.1).
 *
 * Lo que se verifica es lo que mira el evaluador del ITA: que la tabla exista,
 * que traiga TODAS las columnas que nombra el criterio, que lo no publicado no
 * se filtre, y que el instrumento se descargue en formato abierto.
 */
#[CoversClass(TransparenciaPublicoController::class)]
#[CoversClass(TipoInstrumento::class)]
final class InstrumentosGestionTest extends TestCase
{
    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM instrumentos_informacion');

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabricaContenedor */
        $fabricaContenedor = require $raiz . '/config/servicios.php';
        $this->contenedor = $fabricaContenedor($env);

        /** @var ConfigAplicacion $config */
        $config = $this->contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($this->contenedor, $rutas, $config);
    }

    protected function tearDown(): void
    {
        self::$conexion->executeStatement('DELETE FROM instrumentos_informacion');
    }

    /**
     * El criterio 9.1 se verifica columna por columna. Si una sola falta en la
     * tabla publicada, el evaluador marca «No» para todo el literal, así que
     * la prueba exige las trece del instrumento más exigente.
     */
    public function testElIndiceDeInformacionClasificadaPublicaLasTreceColumnasDelIta(): void
    {
        $this->sembrar(TipoInstrumento::IndiceClasificada, [
            'categoria' => 'Historias clínicas',
            'nombre' => 'Historia clínica de los usuarios',
            'medio_conservacion' => 'Electrónico',
            'fecha_generacion' => '2026-01-15',
            'responsable_produccion' => 'Subgerencia Científica',
            'responsable_informacion' => 'Gestión Documental',
            'objetivo_excepcion' => 'Proteger la intimidad del paciente',
            'fundamento_legal' => 'Constitución Política, artículo 15',
            'fundamento_juridico' => 'Ley 1712 de 2014, artículo 18 literal a',
            'alcance_excepcion' => 'total',
            'plazo_reserva' => 'Indefinido mientras subsista la reserva legal',
            'url_datos_abiertos' => 'https://www.datos.gov.co/ejemplo',
        ]);

        $html = $this->contenido('/transparencia/instrumentos-de-gestion/indice-de-informacion-clasificada-y-reservada');

        foreach (TipoInstrumento::IndiceClasificada->columnas() as $encabezado) {
            self::assertStringContainsString(
                htmlspecialchars($encabezado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $html,
                sprintf('Falta la columna «%s», que el ITA 9.1 verifica.', $encabezado),
            );
        }

        self::assertStringContainsString('Historia clínica de los usuarios', $html);
        self::assertStringContainsString('Ley 1712 de 2014, artículo 18 literal a', $html);
    }

    /**
     * Un registro en borrador no puede verse. Los instrumentos describen qué
     * información reserva la entidad: filtrar aquí es tan importante como
     * publicar.
     */
    public function testElRegistroSinPublicarNoSeFiltraAlSitioPublico(): void
    {
        $this->sembrar(TipoInstrumento::RegistroActivos, [
            'categoria' => 'Categoría visible',
            'nombre' => 'Categoría visible',
            'medio_conservacion' => 'Electrónico',
        ], publicado: true);

        $this->sembrar(TipoInstrumento::RegistroActivos, [
            'categoria' => 'Borrador reservado',
            'nombre' => 'Borrador reservado',
            'medio_conservacion' => 'Físico',
        ], publicado: false);

        $html = $this->contenido('/transparencia/instrumentos-de-gestion/registro-de-activos-de-informacion');

        self::assertStringContainsString('Categoría visible', $html);
        self::assertStringNotContainsString('Borrador reservado', $html);
    }

    /**
     * La Ley 1712 art. 6 lit. d exige formato abierto y reutilizable: una
     * tabla HTML cumple la publicidad, no la reutilización.
     */
    public function testCadaInstrumentoSeDescargaEnCsvConSusEncabezados(): void
    {
        $this->sembrar(TipoInstrumento::EsquemaPublicacion, [
            'nombre' => 'Informes de gestión',
            'medio_conservacion' => 'Electrónico',
            'formato' => 'PDF',
            'frecuencia_actualizacion' => 'Anual',
            'lugar_consulta' => 'https://hrplopez.gov.co/transparencia',
        ]);

        $respuesta = $this->kernel->manejar(
            Request::create('/transparencia/instrumentos-de-gestion/esquema-de-publicacion-de-informacion.csv'),
        );

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        self::assertStringContainsString(
            'attachment; filename="esquema-de-publicacion-de-informacion.csv"',
            (string) $respuesta->headers->get('Content-Disposition'),
        );

        $csv = $respuesta->getContent();
        self::assertIsString($csv);
        // BOM, para que Excel en Windows no parta las tildes.
        self::assertStringStartsWith("\u{FEFF}", $csv);
        self::assertStringContainsString('Frecuencia de actualización', $csv);
        self::assertStringContainsString('Informes de gestión', $csv);
        self::assertStringContainsString('Anual', $csv);
    }

    /** Un slug inventado no debe dar 404 ni 500: vuelve al índice. */
    public function testUnInstrumentoDesconocidoRedirigeAlIndice(): void
    {
        $respuesta = $this->kernel->manejar(
            Request::create('/transparencia/instrumentos-de-gestion/inventado'),
        );

        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $respuesta->getStatusCode());
        self::assertSame(
            '/transparencia/instrumentos-de-gestion',
            $respuesta->headers->get('Location'),
        );
    }

    /**
     * El índice no puede anunciar como publicado un instrumento vacío: el ITA
     * puntúa contenido, no encabezados de tabla.
     */
    public function testElIndiceDistingueLosInstrumentosVaciosDeLosQueTienenRegistros(): void
    {
        $this->sembrar(TipoInstrumento::RegistroActivos, [
            'categoria' => 'Contratación',
            'nombre' => 'Contratación',
            'medio_conservacion' => 'Electrónico',
        ]);

        $html = $this->contenido('/transparencia/instrumentos-de-gestion');

        self::assertStringContainsString('Registro de Activos de Información', $html);
        self::assertStringContainsString('1 registro', $html);
        // Los otros dos siguen vacíos y deben decirlo en vez de esconderse.
        self::assertStringContainsString('En actualización', $html);
        self::assertStringContainsString('Esquema de Publicación de Información', $html);
    }

    // --- soporte ---

    /** @param array<string, string> $campos */
    private function sembrar(TipoInstrumento $tipo, array $campos, bool $publicado = true): void
    {
        self::$conexion->insert('instrumentos_informacion', [
            'tipo' => $tipo->value,
            'idioma' => 'Español',
            'publicado' => $publicado ? 1 : 0,
            ...$campos,
        ]);
    }

    private function contenido(string $ruta): string
    {
        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode(), 'La ruta ' . $ruta . ' no respondió 200.');

        $html = $respuesta->getContent();
        self::assertIsString($html);

        return $html;
    }
}
