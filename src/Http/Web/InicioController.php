<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigDinamica;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Entity\Audiencia;
use App\Modules\Contenido\Repository\CampanaRepository;
use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Directorio\Repository\SedeRepository;
use App\Modules\Directorio\Repository\ServicioRepository;
use App\Modules\Encuestas\Repository\EncuestaRepository;
use App\Modules\Eventos\Repository\EventoRepository;
use App\Modules\Indicadores\Repository\IndicadorRepository;
use App\Modules\Sistema\Repository\MenuRepository;
use App\Modules\Contenido\Repository\HeroRepository;
use App\Modules\Sistema\Service\CatalogoIconos;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Home del sitio público: héroe, buscador, ocho accesos, campañas, noticias,
 * encuesta abierta y sede. Máximo ocho acciones destacadas (CLAUDE.md §6).
 *
 * Todo lo que sale aquí viene de la base de datos. La versión anterior traía
 * las campañas escritas a mano en la plantilla y las cifras del héroe
 * inventadas; ninguna de las dos cosas cabe en la portada de una entidad
 * pública, porque el ciudadano no tiene cómo distinguir un dato institucional
 * de un texto de relleno.
 */
final readonly class InicioController
{
    /** El héroe tiene sitio para dos indicadores sin romper la maqueta. */
    private const int INDICADORES_EN_PORTADA = 2;

    /** Noticias por página en la tarjeta de la portada. */
    private const int NOTICIAS_POR_PAGINA = 6;

    /** Cuántos eventos caben en la tarjeta de agenda sin desbordar la rejilla. */
    private const int EVENTOS_EN_PORTADA = 3;

    /**
     * Valores por omisión del carrusel de campañas. Los de verdad los pone
     * Comunicaciones en Panel → Ajustes del sitio → Portada; estos son el
     * respaldo si la clave no existe todavía (instalación sin sembrar).
     */
    private const int CAMPANAS_POR_PESTANA = 6;
    private const int SEGUNDOS_POR_CAMPANA = 7;

    /** Respaldo si nadie ha tocado «Segundos por héroe» en Ajustes. */
    private const int SEGUNDOS_POR_HEROE = 8;

    /** Cifras por grupo en la portada; el resto está en /estadisticas. */
    private const int INDICADORES_POR_GRUPO = 3;

    /**
     * Los tres grupos que el hospital pidió ver en la portada, en este orden.
     * `otro` queda fuera a propósito: es el cajón de sastre del panel y sale
     * en /estadisticas, no en la primera pantalla del sitio.
     */
    private const array GRUPOS_EN_PORTADA = ['contratacion', 'ocupacion', 'vitales'];

    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ConfigDinamica $config,
        private NoticiaRepository $noticias,
        private SedeRepository $sedes,
        private AlmacenArchivos $almacen,
        private CampanaRepository $campanas,
        private EncuestaRepository $encuestas,
        private IndicadorRepository $indicadores,
        private EventoRepository $eventos,
        private MenuRepository $menus,
        private HeroRepository $heroes,
        private ServicioRepository $servicios,
        private CatalogoIconos $iconos,
    ) {
    }

    public function index(Request $peticion): Response
    {
        // La tarjeta de noticias se pagina en la propia portada con `?pagina`,
        // sin JavaScript y con ancla al bloque: quien llega desde el buscador o
        // con conexión mala sigue navegando con enlaces normales.
        $pagina = max(1, $peticion->query->getInt('pagina', 1));
        ['noticias' => $noticias, 'total' => $totalNoticias] = $this->noticias->publicasPaginadas(
            $pagina,
            self::NOTICIAS_POR_PAGINA,
        );

        $paginasNoticias = max(1, (int) ceil($totalNoticias / self::NOTICIAS_POR_PAGINA));

        // Una página inventada en la URL no puede dejar la portada sin noticias:
        // se vuelve a la primera en lugar de mostrar un hueco.
        if ($noticias === [] && $pagina > 1) {
            $pagina = 1;
            ['noticias' => $noticias] = $this->noticias->publicasPaginadas($pagina, self::NOTICIAS_POR_PAGINA);
        }
        $sedes = array_slice($this->sedes->activasPublicas(), 0, 3);

        // Publicadas y vigentes, sin respaldo que muestre borradores: si no
        // hay campañas, la sección simplemente no se pinta.
        //
        // Dos consultas y no una repartida en PHP: cada pestaña quiere sus
        // tres piezas más recientes, y un `LIMIT` conjunto dejaría la pestaña
        // de personal vacía el día que Comunicaciones publique cuatro
        // campañas de comunidad seguidas.
        // Cuántas, en qué orden y cada cuánto rotan: administrable, porque son
        // decisiones de Comunicaciones y no de ingeniería. Los límites viven en
        // `entero()`: un 0 o un 500 escritos por error no pueden romper la
        // portada.
        $campanasPorPestana = $this->config->entero('portada.campanas_cantidad', self::CAMPANAS_POR_PESTANA, 1, 12);
        $ordenCampanas = $this->config->texto('portada.campanas_orden', CampanaRepository::ORDEN_RECIENTES);

        $campanasComunidad = $this->campanas->publicasVigentes($campanasPorPestana, Audiencia::Comunidad, $ordenCampanas);
        $campanasPersonal = $this->campanas->publicasVigentes($campanasPorPestana, Audiencia::Personal, $ordenCampanas);

        $imagenes = $this->almacen->porIds(array_merge(
            array_values(array_filter(array_map(
                static fn ($n): ?int => $n->imagenId,
                $noticias,
            ))),
            array_values(array_filter(array_map(
                static fn ($c): ?int => $c->imagenId,
                [...$campanasComunidad, ...$campanasPersonal],
            ))),
        ));

        // Cada grupo con su último periodo publicado. Un grupo sin nada
        // publicado no aparece: la portada no anuncia secciones vacías.
        $gruposIndicadores = [];

        foreach (self::GRUPOS_EN_PORTADA as $codigo) {
            // Los tres grupos salen SIEMPRE, aunque estén sin cifras: son
            // secciones fijas de la portada que el hospital pidió por nombre, y
            // una sección que aparece y desaparece según el mes deja al
            // visitante sin saber si existe. Lo que no sale nunca es un dato
            // sin publicar: el grupo vacío lo dice con todas sus letras.
            $gruposIndicadores[$codigo] = [
                'titulo' => IndicadorRepository::GRUPOS[$codigo],
                'indicadores' => array_slice(
                    $this->indicadores->ultimosPorGrupo($codigo),
                    0,
                    self::INDICADORES_POR_GRUPO,
                ),
            ];
        }

        $encuestasAbiertas = $this->encuestas->abiertas();
        $encuestaDestacada = $encuestasAbiertas === [] ? null : (int) $encuestasAbiertas[0]['id'];

        return $this->layout->responder(
            titulo: 'Inicio',
            descripcion: sprintf(
                '%s: citas, urgencias, especialidades, trámites y PQRSDF en línea. %s',
                $this->config->texto('entidad.nombre'),
                $this->config->texto('entidad.eslogan'),
            ),
            contenido: $this->vistas->render('publico/inicio', [
                // Las ocho tarjetas salen del menú «accesos»: etiqueta, frase,
                // icono, orden y visibilidad se editan en Panel → Menús del
                // sitio. Antes vivían escritas a mano en esta misma plantilla.
                'accesos' => $this->menus->itemsPorUbicacion('accesos'),
                'iconos' => $this->iconos,
                // El héroe: una fila por diapositiva, con su imagen, su texto y
                // sus botones, desde Panel → Héroe de la portada. Antes era un
                // bloque fijo repartido en claves sueltas de Ajustes.
                // Portafolio de Servicios 2026: la portada enseña los
                // primeros con fotografía. Cuáles y en qué orden se decide en
                // Panel → Servicios, no aquí.
                'serviciosDestacados' => $this->servicios->conFotoParaLaPortada(
                    $this->config->entero('portada.servicios', 6, 3, 12),
                ),
                'heroes' => $this->heroes->paraLaPortada(),
                'rotacionHeroesMs' => $this->config->entero('portada.heroes_segundos', self::SEGUNDOS_POR_HEROE, 0, 120) * 1000,
                'noticias' => $noticias,
                'paginaNoticias' => $pagina,
                'paginasNoticias' => $paginasNoticias,
                'imagenes' => $imagenes,
                'sedes' => $sedes,
                'campanasComunidad' => $campanasComunidad,
                // En milisegundos, que es lo que espera el temporizador del
                // navegador. Cero apaga la rotación automática.
                'rotacionCampanasMs' => $this->config->entero('portada.campanas_segundos', self::SEGUNDOS_POR_CAMPANA, 0, 120) * 1000,
                'campanasPersonal' => $campanasPersonal,
                'encuestas' => $encuestasAbiertas,
                // La tarjeta de la portada responde y muestra resultados sin
                // salir de la página: necesita las preguntas y el agregado ya
                // calculado, para que «ver resultados» no dependa de JavaScript
                // ni de haber votado.
                'preguntasEncuesta' => $encuestaDestacada === null ? [] : $this->encuestas->preguntasDe($encuestaDestacada),
                'resultadosEncuesta' => $encuestaDestacada === null ? [] : $this->encuestas->resultadosDe($encuestaDestacada),
                'totalRespuestasEncuesta' => $encuestaDestacada === null ? 0 : $this->encuestas->totalRespuestas($encuestaDestacada),
                'indicadores' => $this->indicadores->destacados(self::INDICADORES_EN_PORTADA),
                'gruposIndicadores' => $gruposIndicadores,
                // Solo los que aún no han pasado: una agenda que anuncia el
                // evento de la semana pasada manda gente a un salón vacío.
                'proximosEventos' => $this->eventos->proximos(self::EVENTOS_EN_PORTADA),
                'config' => $this->config,
            ]),
            esPortada: true,
        );
    }
}
