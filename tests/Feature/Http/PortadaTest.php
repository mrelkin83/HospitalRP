<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\InicioController;
use App\Modules\Contenido\Repository\CampanaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Qué muestra —y qué NO muestra— la portada.
 *
 * Las reglas que se vigilan aquí salieron de fallos reales encontrados el
 * 2026-09-14: la portada llamaba a `listarParaPanel()`, que devuelve también
 * borradores, y caía en mostrarlos cuando no había nada publicado; y el héroe
 * traía cifras escritas a mano en la plantilla.
 */
#[CoversClass(InicioController::class)]
#[CoversClass(CampanaRepository::class)]
final class PortadaTest extends TestCase
{
    private static Connection $conexion;
    private Kernel $kernel;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM campanas');
        self::$conexion->executeStatement('DELETE FROM indicadores');
        self::$conexion->executeStatement('DELETE FROM eventos');
        self::$conexion->executeStatement("DELETE FROM noticias WHERE slug LIKE 'noticia-de-prueba-%'");
        // Lo que rodea al héroe lee `configuracion`; cada héroe es una fila de
        // `heroes`. Cada caso parte de cero y crea solo lo que está probando.
        self::$conexion->executeStatement("DELETE FROM configuracion WHERE clave LIKE 'hero.%'");
        self::$conexion->executeStatement('DELETE FROM heroes');
        self::$conexion->executeStatement("DELETE FROM archivos WHERE nombre_original = 'heroe-de-prueba.jpg'");

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabrica */
        $fabrica = require $raiz . '/config/servicios.php';
        $contenedor = $fabrica($env);

        /** @var ConfigAplicacion $config */
        $config = $contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($contenedor, $rutas, $config);
    }

    /**
     * El caso que de verdad dolía: sin campañas publicadas, la portada
     * mostraba los borradores en lugar de no mostrar nada.
     */
    public function testUnaCampanaEnBorradorNoSaleAunqueSeaLaUnica(): void
    {
        $this->crearCampana('Campaña a medio redactar', 'borrador');

        self::assertStringNotContainsString('Campaña a medio redactar', $this->portada());
    }

    public function testUnaCampanaPublicadaSiSale(): void
    {
        $this->crearCampana('Jornada de vacunación de septiembre', 'publicado');

        self::assertStringContainsString('Jornada de vacunación de septiembre', $this->portada());
    }

    /** Una campaña cuya vigencia ya pasó manda gente al hospital en balde. */
    public function testUnaCampanaVencidaNoSale(): void
    {
        $this->crearCampana('Jornada que ya terminó', 'publicado', fin: '2026-01-31');

        self::assertStringNotContainsString('Jornada que ya terminó', $this->portada());
    }

    /**
     * Sin marcar como destacado no entra AL HÉROE. Ojo con el matiz: desde
     * que la portada tiene la sección «Así va la entidad», ese mismo
     * indicador sí sale más abajo, dentro de su grupo. Por eso la
     * comprobación se acota al héroe y no al documento entero.
     */
    public function testElHeroeNoMuestraIndicadoresSinMarcarComoDestacados(): void
    {
        $this->crearIndicador('Porcentaje de ocupación', destacado: 0);

        self::assertStringNotContainsString('Porcentaje de ocupación', $this->heroe());
    }

    public function testUnIndicadorDestacadoSinPublicarTampocoSale(): void
    {
        $this->crearIndicador('Porcentaje de ocupación', destacado: 1, publicado: 0);

        self::assertStringNotContainsString('Porcentaje de ocupación', $this->portada());
    }

    /** Publicado y destacado: sale, y con el periodo al que corresponde. */
    public function testUnIndicadorDestacadoYPublicadoSaleConSuPeriodo(): void
    {
        $this->crearIndicador('Porcentaje de ocupación', destacado: 1);

        $cuerpo = $this->portada();

        self::assertStringContainsString('Porcentaje de ocupación', $cuerpo);
        self::assertStringContainsString('85,8', $cuerpo);
        self::assertStringContainsString('agosto de 2026', $cuerpo);
    }

    /**
     * Los tres grupos que pidió el hospital —contratación, ocupación y
     * vitales— viven en la portada, no solo en /estadisticas, y cada cifra
     * sale con su periodo y su fuente.
     */
    public function testLaPortadaMuestraLosGruposDeIndicadoresPublicados(): void
    {
        $this->crearIndicador('Contratos suscritos en el periodo', destacado: 0, grupo: 'contratacion', slug: 'contratos-suscritos');
        $this->crearIndicador('Porcentaje de ocupación de camas', destacado: 0, grupo: 'ocupacion', slug: 'ocupacion-camas');
        $this->crearIndicador('Egresos hospitalarios', destacado: 0, grupo: 'vitales', slug: 'egresos');

        $cuerpo = $this->portada();

        self::assertStringContainsString('Así va la contratación', $cuerpo);
        self::assertStringContainsString('Ocupación hospitalaria', $cuerpo);
        self::assertStringContainsString('Estadísticas vitales', $cuerpo);
        self::assertStringContainsString('Contratos suscritos en el periodo', $cuerpo);
        self::assertStringContainsString('agosto de 2026', $cuerpo);
        self::assertStringContainsString('Oficina de Planeación', $cuerpo);
    }

    /**
     * Los tres grupos son secciones fijas de la portada: salen aunque no
     * tengan cifras, porque una sección que aparece y desaparece según el mes
     * deja al visitante sin saber si existe. Lo que el grupo vacío NO hace es
     * rellenar con un cero, que sería afirmar «cero contratos».
     */
    public function testUnGrupoSinCifrasPublicadasSaleConSuEstadoVacio(): void
    {
        $this->crearIndicador('Egresos hospitalarios', destacado: 0, grupo: 'vitales', slug: 'egresos');

        $cuerpo = $this->portada();

        self::assertStringContainsString('Estadísticas vitales', $cuerpo);
        self::assertStringContainsString('Así va la contratación', $cuerpo);
        self::assertStringContainsString('Aún no hay cifras publicadas de este periodo.', $cuerpo);
    }

    public function testUnIndicadorSinPublicarNoLlegaALaPortada(): void
    {
        $this->crearIndicador('Giro cama', destacado: 0, grupo: 'ocupacion', slug: 'giro-cama', publicado: 0);

        self::assertStringNotContainsString('Giro cama', $this->portada());
    }

    /**
     * La agenda institucional (ITA 1.12 y 6.5) tiene tarjeta fija en la
     * portada: sale con eventos o con la línea de que no hay nada programado.
     */
    public function testLaPortadaMuestraLosProximosEventos(): void
    {
        $this->crearEvento('Rendición de cuentas de la vigencia', '+10 days');

        $cuerpo = $this->portada();

        self::assertStringContainsString('Próximos eventos', $cuerpo);
        self::assertStringContainsString('Rendición de cuentas de la vigencia', $cuerpo);
        self::assertStringContainsString('href="/eventos"', $cuerpo);
    }

    /** Un evento que ya pasó mandaría gente a un salón vacío. */
    public function testUnEventoQueYaPasoNoSaleEnLaPortada(): void
    {
        $this->crearEvento('Jornada del mes pasado', '-20 days');

        self::assertStringNotContainsString('Jornada del mes pasado', $this->portada());
    }

    public function testUnEventoSinPublicarNoSaleEnLaPortada(): void
    {
        $this->crearEvento('Borrador de evento', '+10 days', publicado: 0);

        self::assertStringNotContainsString('Borrador de evento', $this->portada());
    }

    public function testSinEventosLaTarjetaSigueEnLaPortadaConSuEstadoVacio(): void
    {
        $cuerpo = $this->portada();

        self::assertStringContainsString('Próximos eventos', $cuerpo);
        self::assertStringContainsString('No hay eventos programados por ahora.', $cuerpo);
    }

    /**
     * La tarjeta de noticias trae seis y su paginador. No se prueba «hay
     * noticias» sino el número exacto: el fallo que importa es que la portada
     * crezca sin control cuando el hospital publique cincuenta.
     */
    public function testLaPortadaMuestraSeisNoticiasYSuPaginador(): void
    {
        $this->crearNoticiasRecientes(9);

        $cuerpo = $this->portada();

        // Se cuentan TODAS las noticias de la tarjeta y no solo las de esta
        // prueba: lo que se vigila es cuántas pinta la portada, y la base de
        // pruebas puede traer otras publicadas por otro caso.
        self::assertCount(6, $this->slugsDeNoticias($cuerpo));
        self::assertStringContainsString('Páginas de noticias', $cuerpo);
    }

    public function testLaSegundaPaginaDeNoticiasTraeOtrasDistintas(): void
    {
        $this->crearNoticiasRecientes(9);

        $primera = $this->slugsDeNoticias($this->portada());
        $segunda = $this->slugsDeNoticias($this->portada('/?pagina=2'));

        self::assertNotSame([], $segunda);
        self::assertSame([], array_intersect($primera, $segunda), 'Una noticia no puede salir en dos páginas.');
    }

    /** Una página inventada en la URL no puede dejar la portada sin noticias. */
    public function testUnaPaginaDeNoticiasInventadaVuelveALaPrimera(): void
    {
        $this->crearNoticiasRecientes(9);

        self::assertSame($this->slugsDeNoticias($this->portada()), $this->slugsDeNoticias($this->portada('/?pagina=999')));
    }

    /**
     * El héroe decía ser «configurable desde el panel» y no lo era: eran ocho
     * campos sueltos de Ajustes que pintaban un bloque fijo, sin imagen. Ahora
     * cada héroe es una fila de `heroes`, creada en Panel → Héroe de la
     * portada. Lo que se comprueba es que lo escrito allí llegue al sitio.
     */
    public function testElHeroeSaleConLoQueSeEscribioEnElPanel(): void
    {
        $this->crearHeroe([
            'titulo' => 'Estamos contigo,',
            'titulo_degradado' => 'cuando más importa',
            'boton1_texto' => 'Pedir cita ya',
            'boton1_url' => '/citas-nuevas',
        ]);
        $this->ajuste('hero.buscador_leyenda', 'Busca tu especialista');

        $heroe = $this->heroe();

        self::assertStringContainsString('Estamos contigo,', $heroe);
        self::assertStringContainsString('cuando más importa', $heroe);
        self::assertStringContainsString('Pedir cita ya', $heroe);
        self::assertStringContainsString('href="/citas-nuevas"', $heroe);
        self::assertStringContainsString('Busca tu especialista', $heroe);
    }

    public function testLaPildoraSaleConElTextoDelPanel(): void
    {
        $this->crearHeroe(['pildora' => 'Atención humana y segura']);

        self::assertStringContainsString('Atención humana y segura', $this->heroe());
    }

    /**
     * Vaciar la píldora en el panel tiene que quitarla entera, no dejar el
     * marco con el punto parpadeando y sin texto dentro.
     */
    public function testUnaPildoraVaciaEnElPanelNoDejaRastro(): void
    {
        $this->crearHeroe(['pildora' => null]);

        $heroe = $this->heroe();

        self::assertStringNotContainsString('Atención humana y segura', $heroe);
        self::assertStringNotContainsString('animate-ping', $heroe, 'Quedó el punto de la píldora sin su frase.');
    }

    /**
     * Lo que pidió el hospital con todas las letras: la tarjeta «Rosario
     * Digital» se ve en el primer héroe y en ninguno más. Se comprueba
     * contando, no con un `contains`: el fallo que importa es verla repetida.
     */
    public function testLaTarjetaRosarioDigitalSaleUnaSolaVez(): void
    {
        $this->crearHeroe(['titulo' => 'El primero', 'mostrar_tarjeta' => 1, 'orden' => 0]);
        $this->crearHeroe(['titulo' => 'El segundo', 'mostrar_tarjeta' => 1, 'orden' => 1]);
        $this->crearHeroe(['titulo' => 'El tercero', 'orden' => 2]);

        self::assertSame(1, substr_count($this->heroe(), 'Rosario Digital'));
    }

    /**
     * Donde hay fotografía no cabe la tarjeta: ocupan el mismo sitio. Manda la
     * fotografía aunque la casilla del panel esté marcada, porque un héroe con
     * imagen es a sangre y la tarjeta se le montaría encima.
     */
    public function testUnHeroeConImagenNoDibujaLaTarjetaAunqueEsteMarcada(): void
    {
        $this->crearHeroe([
            'titulo' => 'Con fotografía',
            'mostrar_tarjeta' => 1,
            'imagen_id' => $this->crearArchivoDeImagen(),
        ]);

        $heroe = $this->heroe();

        self::assertStringNotContainsString('Rosario Digital', $heroe);
        self::assertStringContainsString('object-cover', $heroe, 'La fotografía de fondo no se pintó.');
    }

    /** Un héroe apagado en el panel no puede seguir viéndose en el sitio. */
    public function testUnHeroeOcultoNoSaleEnLaPortada(): void
    {
        $this->crearHeroe(['titulo' => 'Borrador de héroe', 'visible' => 0]);

        self::assertStringNotContainsString('Borrador de héroe', $this->portada());
    }

    /**
     * La vigencia existe para que un héroe de jornada se apague solo. Si no se
     * respetara, el sitio seguiría anunciando algo que ya pasó.
     */
    public function testUnHeroeConVigenciaVencidaNoSaleEnLaPortada(): void
    {
        $this->crearHeroe([
            'titulo' => 'Jornada que ya terminó',
            'inicio' => date('Y-m-d', (int) strtotime('-20 days')),
            'fin' => date('Y-m-d', (int) strtotime('-5 days')),
        ]);

        self::assertStringNotContainsString('Jornada que ya terminó', $this->portada());
    }

    public function testUnHeroeQueAunNoEmpiezaTampocoSale(): void
    {
        $this->crearHeroe([
            'titulo' => 'Jornada de la semana que viene',
            'inicio' => date('Y-m-d', (int) strtotime('+7 days')),
        ]);

        self::assertStringNotContainsString('Jornada de la semana que viene', $this->portada());
    }

    /**
     * Un solo héroe no es un carrusel: los mandos sobran y anunciar
     * «diapositiva 1 de 1» es ruido. Con dos, aparecen.
     */
    public function testLosMandosDelCarruselSoloSalenConMasDeUnHeroe(): void
    {
        $this->crearHeroe(['titulo' => 'Único']);

        self::assertStringNotContainsString('heroe-navegacion', $this->heroe());
    }

    public function testConDosHeroesSalenLosMandosYUnSoloH1(): void
    {
        $this->crearHeroe(['titulo' => 'El primero', 'orden' => 0]);
        $this->crearHeroe(['titulo' => 'El segundo', 'orden' => 1]);

        $cuerpo = $this->portada();

        self::assertStringContainsString('heroe-navegacion', $cuerpo);
        // El segundo héroe no puede traer otro <h1>: el esquema de encabezados
        // de la página se rompe y el buscador deja de saber cuál manda.
        self::assertSame(1, preg_match_all('/<h1[\s>]/i', $cuerpo));
    }

    /**
     * El buscador vive FUERA de las diapositivas: si rotara con ellas,
     * desaparecería en mitad de lo que alguien está escribiendo.
     */
    public function testElBuscadorNoViveDentroDeUnaDiapositiva(): void
    {
        $this->crearHeroe(['titulo' => 'El primero', 'orden' => 0]);
        $this->crearHeroe(['titulo' => 'El segundo', 'orden' => 1]);

        $cuerpo = $this->portada();

        self::assertSame(1, substr_count($cuerpo, 'id="buscar-q"'), 'El buscador se duplicó por diapositiva.');
        self::assertStringNotContainsString(
            'id="buscar-q"',
            $this->carruselDeHeroes($cuerpo),
            'El buscador quedó dentro del carrusel y desaparecerá al rotar.',
        );
    }

    /** Sin héroes la portada sigue en pie: empieza por el buscador. */
    public function testSinHeroesLaPortadaSigueRespondiendo(): void
    {
        $cuerpo = $this->portada();

        self::assertStringNotContainsString('data-heroes', $cuerpo);
        self::assertStringContainsString('id="buscar-q"', $cuerpo);
    }

    /**
     * Los dos avisos flotantes —«sin esperas», «agendar en 2 minutos»— dicen
     * cómo está el servicio ahora mismo y nada en el sistema lo mide. Vienen
     * apagados: es la misma regla que sacó del héroe las cifras inventadas.
     */
    public function testLosAvisosFlotantesNoSalenSiNadieLosEnciende(): void
    {
        $this->ajuste('hero.aviso1_texto', 'Normal (sin esperas)');

        self::assertStringNotContainsString('Normal (sin esperas)', $this->heroe());
    }

    public function testLosAvisosFlotantesSalenCuandoElPanelLosEnciende(): void
    {
        // Los avisos cuelgan de la tarjeta ilustrada, así que hace falta un
        // héroe que la lleve: sin tarjeta no hay dónde colgarlos.
        $this->crearHeroe(['mostrar_tarjeta' => 1]);
        $this->ajuste('hero.avisos_visibles', '1', 'booleano');
        $this->ajuste('hero.aviso1_texto', 'Normal (sin esperas)');
        $this->ajuste('hero.aviso2_texto', 'Agendar en 2 minutos');

        $heroe = $this->heroe();

        self::assertStringContainsString('Normal (sin esperas)', $heroe);
        self::assertStringContainsString('Agendar en 2 minutos', $heroe);
    }

    /**
     * El distintivo de versión de la tarjeta nace vacío a propósito: un
     * «PORTAL V2.6» en la portada de un hospital no le dice nada a nadie.
     */
    public function testElDistintivoDeVersionNoSaleSiNadieLoEscribe(): void
    {
        self::assertStringNotContainsString('PORTAL V', $this->heroe());
    }

    public function testElDistintivoDeVersionSaleSiLoEscribenEnElPanel(): void
    {
        $this->crearHeroe(['mostrar_tarjeta' => 1]);
        $this->ajuste('hero.tarjeta_version', 'PORTAL V3');

        self::assertStringContainsString('PORTAL V3', $this->heroe());
    }

    /**
     * Las cifras que estaban escritas a mano en la plantilla no pueden volver
     * por la vía de copiar y pegar.
     */
    public function testLaPortadaNoTraeCifrasEscritasAMano(): void
    {
        $cuerpo = $this->portada();

        self::assertStringNotContainsString('98.4', $cuerpo);
        self::assertStringNotContainsString('Satisfacción del Paciente', $cuerpo);
        self::assertStringNotContainsString('Ingresar a Intranet', $cuerpo);
    }

    // --- soporte ---

    private function portada(string $ruta = '/'): string
    {
        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());

        return (string) $respuesta->getContent();
    }

    /**
     * Solo el héroe: desde el inicio del documento hasta la rejilla de accesos
     * rápidos, que es lo primero que viene después.
     */
    private function heroe(): string
    {
        $cuerpo = $this->portada();

        // Se corta en lo primero que venga después del héroe. La rejilla de
        // accesos ya no siempre está —desde que es un menú administrable,
        // puede quedar vacía—, así que hay más de un candidato.
        foreach (['id="accesos-titulo"', 'id="campanas-titulo"', 'id="indicadores-titulo"'] as $marca) {
            $corte = strpos($cuerpo, $marca);

            if ($corte !== false) {
                return substr($cuerpo, 0, $corte);
            }
        }

        self::fail('No se encontró dónde termina el héroe: revisa este recorte.');
    }

    /**
     * Pone una clave de Ajustes como la dejaría alguien desde el panel. Tiene
     * que llamarse ANTES del primer `portada()` del caso: `ConfigDinamica`
     * carga la tabla entera en la primera lectura y no vuelve a mirarla.
     */
    private function ajuste(string $clave, string $valor, string $tipo = 'texto'): void
    {
        self::$conexion->insert('configuracion', [
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => $tipo,
            'grupo' => 'hero',
            'etiqueta' => 'Ajuste de prueba',
            'orden' => 0,
            'editable' => 1,
        ]);
    }

    /**
     * Solo el carrusel: desde `data-heroes` hasta el `</section>` que lo
     * cierra. Sirve para comprobar qué está DENTRO de lo que rota.
     */
    private function carruselDeHeroes(string $html): string
    {
        $inicio = strpos($html, 'data-heroes');

        if ($inicio === false) {
            return '';
        }

        $fin = strpos($html, '</section>', $inicio);

        return $fin === false ? substr($html, $inicio) : substr($html, $inicio, $fin - $inicio);
    }

    /**
     * Una diapositiva del héroe, como la dejaría alguien desde el panel.
     *
     * @param array<string, mixed> $campos
     */
    private function crearHeroe(array $campos = []): int
    {
        self::$conexion->insert('heroes', [
            'titulo' => 'Héroe de prueba',
            'visible' => 1,
            'orden' => 0,
            ...$campos,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    /**
     * Fila de `archivos` para poder poner imagen a un héroe. No hace falta que
     * exista el archivo en disco: la portada solo pinta `/archivos/{id}`.
     */
    private function crearArchivoDeImagen(): int
    {
        self::$conexion->insert('archivos', [
            'nombre_original' => 'heroe-de-prueba.jpg',
            'ruta' => 'pruebas/heroe-de-prueba.jpg',
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano' => 1024,
            'hash_sha256' => str_repeat('a', 64),
            'ancho' => 1600,
            'alto' => 900,
            'texto_alt' => 'Fachada del hospital',
            'publico' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function crearCampana(string $titulo, string $estado, ?string $fin = null): void
    {
        self::$conexion->insert('campanas', [
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'titulo' => $titulo,
            'resumen' => 'Resumen de prueba.',
            'contenido' => '<p>Contenido de prueba.</p>',
            'estado_aprobacion' => $estado,
            'fin' => $fin,
        ]);
    }

    /**
     * Los slugs de las noticias que pinta la tarjeta. El enlace «Ver todas»
     * apunta a `/noticias` sin barra final, así que no se cuela aquí.
     *
     * @return list<string>
     */
    private function slugsDeNoticias(string $html): array
    {
        preg_match_all('#href="/noticias/([a-z0-9-]+)"#', $html, $coincidencias);

        return $coincidencias[1];
    }

    /** Las más recientes del sitio, para que sean las que pinte la portada. */
    private function crearNoticiasRecientes(int $cuantas): void
    {
        for ($i = 1; $i <= $cuantas; $i++) {
            self::$conexion->insert('noticias', [
                'slug' => 'noticia-de-prueba-' . $i,
                'titulo' => 'Noticia de prueba ' . $i,
                'resumen' => 'Resumen de prueba.',
                'contenido' => '<p>Contenido de prueba.</p>',
                'estado_aprobacion' => 'publicado',
                'aprobado_en' => date('Y-m-d H:i:s'),
                'publicar_en' => date('Y-m-d H:i:s', (int) strtotime('-' . $i . ' minutes')),
            ]);
        }
    }

    private function crearEvento(string $titulo, string $cuando, int $publicado = 1): void
    {
        $inicio = new \DateTimeImmutable($cuando);

        self::$conexion->insert('eventos', [
            'titulo' => $titulo,
            'slug' => 'evento-' . bin2hex(random_bytes(6)),
            'descripcion' => '<p>Evento de prueba.</p>',
            'tipo' => 'institucional',
            'inicio' => $inicio->format('Y-m-d H:i:s'),
            'lugar' => 'Auditorio',
            'publicado' => $publicado,
        ]);
    }

    private function crearIndicador(
        string $nombre,
        int $destacado,
        int $publicado = 1,
        string $grupo = 'ocupacion',
        string $slug = 'indicador-de-prueba',
    ): void {
        self::$conexion->insert('indicadores', [
            'grupo' => $grupo,
            'nombre' => $nombre,
            'slug' => $slug,
            'periodo' => '2026-08-01',
            'valor' => 85.8,
            'unidad' => '%',
            'meta' => 100,
            'fuente' => 'Oficina de Planeación',
            'publicado' => $publicado,
            'destacado' => $destacado,
        ]);
    }
}
