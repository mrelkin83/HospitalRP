<?php
/**
 * Home. Lenguaje visual premium pro max.
 * Héroe configurable desde panel, con animaciones y transiciones avanzadas.
 */

use App\Core\Support\Fechas;
use App\Modules\Contenido\Entity\Audiencia;

// Las tarjetas de acceso rápido ya no viven aquí: son el menú «accesos», que
// se edita en Panel → Sistema → Menús del sitio. El color se reparte en ciclo
// para que la rejilla conserve su ritmo aunque el hospital añada o quite
// tarjetas; el icono lo elige quien edita, de una lista cerrada.
$azulejosAcceso = [
    'bg-white text-primario-700 dark:bg-primario-900 dark:text-primario-100',
    'bg-error-100 text-error-600 dark:bg-error-900 dark:text-white',
    'bg-exito-100 text-exito-600 dark:bg-exito-900 dark:text-white',
    'bg-aviso-100 text-aviso-600 dark:bg-aviso-900 dark:text-white',
];

// Etiquetas de los tipos de evento: el enum de la base habla en snake_case y
// el ciudadano no tiene por qué leer «participacion_ciudadana».
$tiposEvento = [
    'institucional' => 'Institucional',
    'participacion_ciudadana' => 'Participación ciudadana',
    'rendicion_cuentas' => 'Rendición de cuentas',
    'capacitacion' => 'Capacitación',
    'jornada_salud' => 'Jornada de salud',
];

// El héroe son varias diapositivas y cada una es una fila de la tabla
// `heroes`: se crean en Panel → Héroe de la portada, con su imagen, su texto
// y sus botones. Llegan en `$heroes`, ya filtradas por visibilidad y vigencia.
//
// Lo que sigue en Ajustes es lo que NO se repite por diapositiva: el texto
// guía del buscador, la tarjeta ilustrada y los dos avisos flotantes. Los que
// nacen vacíos son adorno: vaciarlos en el panel significa «quítalo», y el
// bloque no se dibuja.
$heroBuscador = $config->texto('hero.buscador_leyenda', 'Buscar trámites, médicos, noticias...');

// La tarjeta ilustrada de la derecha.
$heroTarjetaRotulo = $config->texto('hero.tarjeta_rotulo');
$heroTarjetaVersion = $config->texto('hero.tarjeta_version');
$heroTarjetaTitulo = $config->texto('hero.tarjeta_titulo', 'Rosario Digital');
$heroTarjetaTexto = $config->texto('hero.tarjeta_texto', 'Tu portal clínico unificado e interactivo.');
$heroTarjetaPie = $config->texto('hero.tarjeta_pie');

// Los dos avisos flotantes dicen cómo está el servicio ahora mismo —«sin
// esperas», «agendar en 2 minutos»— y nada en el sistema lo mide: son texto
// fijo. Vienen apagados por la misma razón por la que se sacaron del héroe
// las cifras escritas a mano: afirmar sin respaldo.
$heroAvisos = $config->booleano('hero.avisos_visibles');
$heroAviso1Rotulo = $config->texto('hero.aviso1_rotulo', 'Atención en sede');
$heroAviso1Texto = $config->texto('hero.aviso1_texto', 'Normal (sin esperas)');
$heroAviso2Rotulo = $config->texto('hero.aviso2_rotulo', 'Próxima cita');
$heroAviso2Texto = $config->texto('hero.aviso2_texto', 'Agendar en 2 minutos');
?>

<?php if ($heroes !== []) { ?>
<section class="relative" aria-roledescription="carrusel" aria-label="Destacados del hospital"
         data-heroes data-rotacion-ms="<?= (int) $rotacionHeroesMs ?>">
    <?php
    // La tarjeta «Rosario Digital» es una y solo una. El panel ya la apaga en
    // las demás al marcarla en una, pero eso vale para lo que pasa por el
    // panel: una semilla, una migración o un arreglo a mano en la base podrían
    // dejar dos marcadas, y entonces se vería repetida. Aquí no puede pasar.
    $tarjetaYaPuesta = false;

    foreach ($heroes as $indiceHeroe => $heroe) {
        $conImagen = $heroe['imagen_id'] !== null;
        // La tarjeta ocupa el lado derecho; donde hay fotografía no cabe, así
        // que la fotografía manda aunque la casilla esté marcada.
        $conTarjeta = $heroe['mostrar_tarjeta'] && !$conImagen && !$tarjetaYaPuesta;
        $tarjetaYaPuesta = $tarjetaYaPuesta || $conTarjeta;

        // Sobre fotografía con velo oscuro el texto va en claro; sobre el
        // degradado de marca, en los colores de siempre.
        $claseTitulo = $conImagen ? 'text-white' : '';
        $claseParrafo = $conImagen ? 'text-primario-100' : 'texto-suave';
        $clasePildora = $conImagen
            ? 'border-white/30 bg-white/10 text-white'
            : 'border-primario-200 bg-[color:var(--fondo)]/80 text-primario-700 dark:border-primario-700 dark:text-primario-100';
        $claseBoton2 = $conImagen ? 'border-white/50 bg-white/10 text-white hover:bg-white/20' : 'hover:bg-primario-50 dark:hover:bg-primario-800';

        // La continuación del título va en degradado de marca sobre el fondo
        // claro, pero NO sobre fotografía: ese azul (#0997D5) sobre el velo
        // oscuro se queda en 1,8:1, muy por debajo del 3:1 que la WCAG 2.2
        // (1.4.3) pide al texto grande. Sobre foto se distingue por opacidad,
        // que no depende del color de la imagen que haya debajo.
        $claseDegradado = $conImagen ? 'text-white/85' : 'texto-degradado';

        // El primero es `h1` y los demás `h2`: la portada necesita un único
        // encabezado de primer nivel, y quien lo tiene es el que se ve al
        // entrar. Por eso la etiqueta es una variable y no hay `<h1>` literal
        // en esta plantilla.
        $etiquetaTitulo = $indiceHeroe === 0 ? 'h1' : 'h2';
        ?>
        <article class="heroe-diapositiva relative overflow-hidden <?= $conImagen ? 'bg-primario-950' : 'bg-gradiente-premium' ?>"
                 data-activa="<?= $indiceHeroe === 0 ? 'true' : 'false' ?>"
                 aria-roledescription="diapositiva"
                 aria-label="<?= $e(sprintf('%d de %d', $indiceHeroe + 1, count($heroes))) ?>">

            <?php if ($conImagen) { ?>
                <?php /* `alt` vacío a propósito: la imagen es el fondo y todo lo
                         que dice está escrito encima, en el HTML. Describirla
                         otra vez obligaría a oírlo dos veces. Las dimensiones
                         reservan el hueco y evitan el salto de maquetación en
                         lo primero que se pinta. */ ?>
                <img class="absolute inset-0 h-full w-full object-cover" src="/archivos/<?= (int) $heroe['imagen_id'] ?>" alt=""
                     <?php if ($heroe['imagen_ancho'] !== null && $heroe['imagen_alto'] !== null) { ?>
                         width="<?= (int) $heroe['imagen_ancho'] ?>" height="<?= (int) $heroe['imagen_alto'] ?>"
                     <?php } ?>
                     <?= $indiceHeroe === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                <?php /* Velo en dos capas, y no es adorno: el hospital sube la
                         fotografía que quiera, y sobre una foto clara un velo
                         de un solo degradado dejaba el párrafo en 3,4:1, por
                         debajo del 4,5:1 que pide la WCAG 2.2 (1.4.3).

                         Una capa pareja del 40 % pone un suelo mínimo de
                         oscuridad pase lo que pase, y encima el degradado que
                         carga la izquierda, que es donde va el texto. En la
                         zona de lectura la opacidad combinada no baja del 88 %:
                         incluso sobre blanco puro, el texto blanco queda en
                         5,9:1. A la derecha el velo se abre y la fotografía se
                         ve, porque ahí no hay nada que leer. */ ?>
                <div class="absolute inset-0 bg-primario-950/40" aria-hidden="true"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-primario-950/95 via-primario-950/80 to-transparent" aria-hidden="true"></div>
            <?php } else { ?>
                <div class="pointer-events-none absolute inset-0 hidden lg:block" aria-hidden="true">
                    <span class="mancha h-[500px] w-[500px] -top-20 -left-20 bg-[color:var(--color-marca-cian)] opacity-20"></span>
                    <span class="mancha h-[600px] w-[600px] top-20 -right-10 bg-primario-500 opacity-15"></span>
                </div>
            <?php } ?>

            <div class="relative mx-auto max-w-7xl px-4 py-16 lg:py-24 grid gap-10 <?= $conTarjeta ? 'lg:grid-cols-2 lg:items-center' : '' ?>">
                <div class="animar-fade-in-up [animation-delay:0.1s] <?= $conImagen ? 'max-w-2xl' : '' ?>">
                    <?php if ($heroe['pildora'] !== '') { ?>
                        <p class="inline-flex items-center gap-2 rounded-full border backdrop-blur-md px-4 py-1.5 text-sm font-semibold shadow-sm <?= $clasePildora ?>">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[color:var(--color-marca-cian)] opacity-75" aria-hidden="true"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-[color:var(--color-marca-cian)]" aria-hidden="true"></span>
                            </span>
                            <?= $e($heroe['pildora']) ?>
                        </p>
                    <?php } ?>

                    <<?= $etiquetaTitulo ?> <?= $indiceHeroe === 0 ? 'id="heroe-titulo"' : '' ?> class="<?= $heroe['pildora'] === '' ? '' : 'mt-6 ' ?>font-extrabold tracking-tight <?= $claseTitulo ?>">
                        <?= $e($heroe['titulo']) ?>
                        <?php if ($heroe['titulo_degradado'] !== '') { ?>
                            <span class="<?= $claseDegradado ?> block sm:inline"><?= $e($heroe['titulo_degradado']) ?></span>
                        <?php } ?>
                    </<?= $etiquetaTitulo ?>>

                    <?php if ($heroe['eslogan'] !== '') { ?>
                        <p class="mt-6 text-xl max-w-prose leading-relaxed <?= $claseParrafo ?>">
                            <?= $e($heroe['eslogan']) ?>
                        </p>
                    <?php } ?>

                    <?php if ($heroe['boton1_texto'] !== '' || $heroe['boton2_texto'] !== '') { ?>
                        <p class="mt-8 flex flex-wrap gap-4">
                            <?php if ($heroe['boton1_texto'] !== '') { ?>
                                <a class="boton boton-primario text-lg px-8 py-3 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all"
                                   href="<?= $e($heroe['boton1_url']) ?>"><?= $e($heroe['boton1_texto']) ?></a>
                            <?php } ?>
                            <?php if ($heroe['boton2_texto'] !== '') { ?>
                                <a class="boton boton-secundario text-lg px-8 py-3 rounded-xl transition-all <?= $claseBoton2 ?>"
                                   href="<?= $e($heroe['boton2_url']) ?>"><?= $e($heroe['boton2_texto']) ?></a>
                            <?php } ?>
                        </p>
                    <?php } ?>
                </div>

                <?php if ($conTarjeta) { ?>
                    <div class="relative animar-fade-in-up mt-10 lg:mt-0 [animation-delay:0.3s]">
                        <div class="relative mx-auto w-full max-w-xl min-h-[380px] sm:min-h-[440px] md:h-[480px] flex items-center justify-center">
                            <!-- Capa 1 (grid externa): degradado de marca #0065AE (primario-600)
                                 a #0997D5 (primario-500). -->
                            <div class="absolute inset-0 rounded-[2.5rem] bg-gradient-to-tr from-primario-600 to-primario-500 shadow-3xl overflow-hidden border border-white/10" aria-hidden="true">
                                <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:24px_24px] opacity-40"></div>
                                <div class="absolute -top-20 -left-20 w-64 h-64 bg-[color:var(--color-marca-cian)]/10 rounded-full blur-3xl"></div>
                                <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-primario-500/20 rounded-full blur-3xl"></div>
                            </div>

                            <!-- Capa 2: tarjeta del portal. Degradado de marca #1D5BA7 (primario-700)
                                 a #35BDE9 (primario-400). El texto blanco va holgado sobre el
                                 arranque oscuro, pero en el extremo claro queda en 2,19:1: si
                                 ahí llega a haber texto pequeño, hace falta velo. -->
                            <div class="absolute w-[90%] sm:w-[85%] h-[80%] sm:h-[75%] top-[10%] left-[5%] sm:left-[7.5%] rounded-3xl p-5 sm:p-6 shadow-2xl bg-gradient-to-br from-primario-700 to-primario-400 border border-white/15 flex flex-col justify-between backdrop-blur-xl">
                                <?php if ($heroTarjetaRotulo !== '' || $heroTarjetaVersion !== '') { ?>
                                    <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                                        <?php if ($heroTarjetaRotulo !== '') { ?>
                                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-primario-200"><?= $e($heroTarjetaRotulo) ?></span>
                                        <?php } ?>
                                        <?php if ($heroTarjetaVersion !== '') { ?>
                                            <span class="text-[9px] sm:text-[10px] bg-white/10 px-2 py-1 rounded-md text-white/80 font-mono ms-auto"><?= $e($heroTarjetaVersion) ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>

                                <div class="my-3 sm:my-4 flex-1 flex flex-col justify-center">
                                    <span class="text-2xl sm:text-3xl font-extrabold text-white leading-tight"><?= $e($heroTarjetaTitulo) ?></span>
                                    <span class="text-xs sm:text-sm text-primario-200 mt-1 block"><?= $e($heroTarjetaTexto) ?></span>

                                    <?php if ($indicadores !== []) { ?>
                                        <?php /* Indicadores reales: los marca el hospital en
                                                 Panel → Indicadores y aquí sale el último
                                                 periodo publicado de cada uno, con su fecha
                                                 y su fuente. Antes estas dos barras tenían
                                                 cifras escritas a mano —«98.4 %
                                                 Satisfacción del Paciente»— que nadie
                                                 respaldaba. */ ?>
                                        <div class="mt-4 sm:mt-6 space-y-3">
                                            <?php foreach ($indicadores as $indicador) {
                                                $valorIndicador = (float) $indicador['valor'];
                                                $unidadIndicador = (string) ($indicador['unidad'] ?? '');
                                                $metaIndicador = $indicador['meta'] === null ? null : (float) $indicador['meta'];

                                                // Referencia de la barra: la meta si existe; si el dato ya
                                                // es un porcentaje, 100. Sin ninguna de las dos no se
                                                // dibuja barra, porque un ancho inventado afirmaría algo
                                                // que el dato no dice.
                                                $referencia = $metaIndicador ?? ($unidadIndicador === '%' ? 100.0 : null);

                                                $cifraIndicador = $valorIndicador === (float) (int) $valorIndicador
                                                    ? number_format($valorIndicador, 0, ',', '.')
                                                    : number_format($valorIndicador, 1, ',', '.');
                                                ?>
                                                <div>
                                                    <div class="flex justify-between gap-3 text-[11px] sm:text-xs text-primario-100 mb-1 font-medium">
                                                        <span><?= $e((string) $indicador['nombre']) ?></span>
                                                        <span class="tabular-nums whitespace-nowrap"><?= $e($cifraIndicador) ?><?= $unidadIndicador !== '' ? ' ' . $e($unidadIndicador) : '' ?></span>
                                                    </div>
                                                    <?php if ($referencia !== null && $referencia > 0.0) { ?>
                                                        <?php /* Pista oscura, relleno blanco.

                                                                 El comentario de antes decía «relleno blanco» y el
                                                                 relleno estaba pintado de `marca-cian → primario-400`,
                                                                 que son el MISMO #35BDE9 con el que TERMINA el
                                                                 degradado de la tarjeta: en el extremo derecho la
                                                                 barra se disolvía en el fondo. Y el blanco sobre ese
                                                                 celeste tampoco bastaba —2,19:1, por debajo del 3:1
                                                                 que la WCAG 2.2 (1.4.11) pide a un elemento gráfico.

                                                                 Lo que arregla las dos cosas es oscurecer la PISTA:
                                                                 `white/10` pasa a `primario-950/30`, y sobre ella el
                                                                 relleno claro queda entre 3,1:1 y 3,7:1 en ambos
                                                                 extremos del degradado de la tarjeta. */ ?>
                                                        <div class="w-full bg-primario-950/30 h-2 rounded-full overflow-hidden">
                                                            <?php /* El ancho va en pasos de 5 % (clases .barra-N de
                                                                     app.css): la CSP del sitio público prohíbe el
                                                                     estilo en línea. La cifra exacta está arriba. */ ?>
                                                            <div class="bg-gradient-to-r from-white to-primario-100 h-full rounded-full barra-<?= (int) (round(min(100.0, max(0.0, $valorIndicador / $referencia * 100)) / 5) * 5) ?>"></div>
                                                        </div>
                                                    <?php } ?>
                                                    <p class="text-[10px] text-primario-300 mt-1">
                                                        <?= $e(Fechas::mesYAnio((string) $indicador['periodo'])) ?><?php if (($indicador['fuente'] ?? '') !== '') { ?> · <?= $e((string) $indicador['fuente']) ?><?php } ?>
                                                    </p>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>

                                <?php if ($heroTarjetaPie !== '') { ?>
                                    <div class="flex items-center gap-2 sm:gap-3 text-[10px] sm:text-xs text-primario-100 bg-white/5 p-2 sm:p-3 rounded-xl border border-white/5">
                                        <svg class="h-4 w-4 sm:h-5 sm:w-5 text-[color:var(--color-marca-cian)] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        </svg>
                                        <span><?= $e($heroTarjetaPie) ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <?php if ($heroAvisos) { ?>
                                <?php /* Los dos avisos flotantes. Texto fijo que habla del
                                         estado del servicio, así que se encienden desde
                                         Ajustes y vienen apagados. No llevan `cursor-pointer`:
                                         no son enlaces y prometían un clic que no existe. */ ?>
                                <div class="absolute -bottom-2 sm:-bottom-4 -right-2 sm:-right-4 bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 rounded-2xl p-3 sm:p-4 shadow-3xl flex items-center gap-2 sm:gap-3 animar-escala-hover z-20">
                                    <span class="relative flex h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0" aria-hidden="true">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-exito-500 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 sm:h-3 sm:w-3 bg-exito-500"></span>
                                    </span>
                                    <div>
                                        <span class="block text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-exito-600 dark:text-exito-400"><?= $e($heroAviso1Rotulo) ?></span>
                                        <span class="block text-xs sm:text-sm font-extrabold text-primario-900 dark:text-white"><?= $e($heroAviso1Texto) ?></span>
                                    </div>
                                </div>

                                <div class="absolute -top-4 sm:-top-6 -right-2 sm:-right-6 bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 rounded-2xl p-3 sm:p-4 shadow-3xl flex items-center gap-2 sm:gap-3 animar-escala-hover z-20">
                                    <span class="p-1.5 sm:p-2 bg-primario-50 dark:bg-primario-800 rounded-xl text-primario-600 dark:text-primario-200" aria-hidden="true">
                                        <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                    </span>
                                    <div>
                                        <span class="block text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-primario-500"><?= $e($heroAviso2Rotulo) ?></span>
                                        <span class="block text-xs sm:text-sm font-extrabold text-primario-900 dark:text-white"><?= $e($heroAviso2Texto) ?></span>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </article>
    <?php } ?>

    <?php if (count($heroes) > 1) { ?>
        <?php /* Los mandos solo existen con JavaScript: sin él las diapositivas
                 quedan una debajo de otra, que es contenido legible, y unos
                 botones que no hacen nada serían peor que no tenerlos.
                 `app.css` los esconde mientras `<html>` no lleve `data-js`. */ ?>
        <div class="heroe-navegacion absolute inset-x-0 bottom-4 z-20 flex items-center justify-center gap-3">
            <button type="button" class="heroe-anterior grid h-10 w-10 place-items-center rounded-full border border-white/40 bg-primario-950/50 text-white backdrop-blur-md hover:bg-primario-950/70"
                    aria-label="Destacado anterior">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <ul class="heroe-puntos flex items-center gap-2">
                <?php foreach ($heroes as $indicePunto => $heroePunto) { ?>
                    <li>
                        <button type="button" class="heroe-punto h-2.5 w-2.5 rounded-full border border-white/60 <?= $indicePunto === 0 ? 'bg-white' : 'bg-white/30' ?>"
                                data-indice="<?= $indicePunto ?>"
                                aria-label="<?= $e(sprintf('Ir al destacado %d: %s', $indicePunto + 1, $heroePunto['titulo'])) ?>"></button>
                    </li>
                <?php } ?>
            </ul>
            <button type="button" class="heroe-siguiente grid h-10 w-10 place-items-center rounded-full border border-white/40 bg-primario-950/50 text-white backdrop-blur-md hover:bg-primario-950/70"
                    aria-label="Destacado siguiente">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>
    <?php } ?>
</section>
<?php } ?>

<?php /* El buscador va fuera de las diapositivas a propósito: si rotara con
         ellas, desaparecería en mitad de lo que alguien está escribiendo. */ ?>
<div class="relative z-10 mx-auto max-w-7xl px-4 <?= $heroes === [] ? 'pt-10' : '-mt-8' ?>">
    <form class="flex max-w-md bg-[color:var(--fondo)] rounded-2xl shadow-lg border border-primario-100 dark:border-primario-800 p-1 focus-within:ring-2 focus-within:ring-primario-500 transition-shadow" action="/buscar" method="get" role="search">
        <label class="sr-only" for="buscar-q">¿Qué estás buscando?</label>
        <div class="flex-1 flex items-center px-4">
            <svg class="h-5 w-5 text-primario-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
            <input class="w-full bg-transparent border-0 focus:ring-0 py-2.5 px-3 text-primario-900 dark:text-primario-100 placeholder-primario-400" type="search" id="buscar-q" name="q" placeholder="<?= $e($heroBuscador) ?>">
        </div>
        <button type="submit" class="boton boton-primario rounded-xl px-6 py-2.5 m-0.5">Buscar</button>
    </form>
</div>

<?php if ($accesos !== []) { ?>
<?php /* Ya no monta sobre el héroe: entre los dos está el buscador, que es
         quien ahora hace ese solape. */ ?>
<section class="relative z-10 mx-auto mt-10 max-w-7xl px-4 pb-12" aria-labelledby="accesos-titulo">
    <h2 id="accesos-titulo" class="sr-only">Accesos rápidos</h2>
    <ul class="grid gap-5 grid-cols-2 md:grid-cols-4 animar-fade-in [animation-delay:0.5s]">
        <?php foreach ($accesos as $indiceAcceso => $acceso) {
            $azulejo = $azulejosAcceso[$indiceAcceso % count($azulejosAcceso)];
            ?>
            <li>
                <a class="tarjeta-acceso group flex h-full flex-col gap-4 rounded-3xl p-6 no-underline transition-all duration-300 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(10,25,47,0.15)] dark:hover:shadow-[0_20px_40px_-15px_rgba(0,0,0,0.5)] border border-primario-200/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm" href="<?= $e($acceso['url']) ?>"<?= $acceso['nueva_pestana'] ? ' target="_blank" rel="noopener"' : '' ?>>
                    <span class="grid h-14 w-14 place-items-center rounded-2xl <?= $e($azulejo) ?> shadow-sm group-hover:scale-110 transition-transform duration-300" aria-hidden="true">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="<?= $e($iconos->trazo($acceso['icono'])) ?>"/>
                        </svg>
                    </span>
                    <div>
                        <span class="block font-bold text-lg texto-titular group-hover:text-primario-600 dark:group-hover:text-primario-400 transition-colors"><?= $e($acceso['etiqueta']) ?></span>
                        <?php if ($acceso['descripcion'] !== '') { ?>
                            <span class="block mt-1 text-sm texto-suave leading-snug"><?= $e($acceso['descripcion']) ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>
        <?php } ?>
    </ul>
</section>
<?php } ?>

<?php
    /* Carrusel de Campañas y Comunicación Interna: las dos pestañas del
       diseño —«Salud y Prevención» para la comunidad y «Portal de Empleados»
       para el personal— servidas desde la tabla `campanas`.

       La versión anterior traía las cuatro piezas escritas a mano dentro de
       un `x-data`, con una tarjeta que invitaba a «Ingresar a Intranet» —la
       intranet que el hospital mandó retirar el 2026-09-14— y salía vacía,
       porque Alpine no estaba cargado. Ahora el contenido lo publica
       Comunicaciones en Panel → Campañas, eligiendo a qué audiencia le habla,
       y Alpine solo alterna qué se ve. */
    $pestanasCampanas = array_values(array_filter(
        [
            ['audiencia' => Audiencia::Comunidad, 'referencia' => 'Comunidad', 'campanas' => $campanasComunidad],
            ['audiencia' => Audiencia::Personal, 'referencia' => 'Personal', 'campanas' => $campanasPersonal],
        ],
        static fn (array $pestana): bool => $pestana['campanas'] !== [],
    ));
?>

<?php if ($pestanasCampanas !== []) { ?>
    <section class="mx-auto max-w-7xl px-4 pb-16 animar-fade-in [animation-delay:0.6s]" aria-labelledby="campanas-titulo" x-data="comunicaciones"
             data-rotacion-ms="<?= (int) $rotacionCampanasMs ?>">
        <div class="tarjeta p-8 border-white/20 bg-[color:var(--superficie)]/80 backdrop-blur-md shadow-2xl relative overflow-hidden">
            <div class="flex items-center justify-between border-b border-primario-100/50 dark:border-primario-800 pb-5 mb-8 flex-wrap gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-primario-500">Comunicaciones E.S.E.</span>
                    <h2 id="campanas-titulo" class="text-2xl font-extrabold tracking-tight texto-titular mt-1">Campañas y comunicados</h2>
                </div>

                <?php /* Las pestañas solo tienen sentido si hay dos paneles que
                         alternar; con uno solo serían un adorno que no hace nada. */ ?>
                <?php if (count($pestanasCampanas) === 2) { ?>
                    <div class="comunicaciones-pestanas flex bg-primario-50 dark:bg-primario-900/50 p-1 rounded-2xl border border-primario-100/20" role="tablist" aria-label="Audiencia de las comunicaciones">
                        <button type="button" role="tab" id="pestana-comunidad" aria-controls="panel-comunidad"
                                x-bind:aria-selected="seleccionComunidad" x-bind:class="claseComunidad" x-on:click="verComunidad"
                                class="px-5 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer bg-gradient-to-tr from-primario-700 to-primario-500 text-white shadow-md">
                            <?= $e(Audiencia::Comunidad->pestana()) ?>
                        </button>
                        <button type="button" role="tab" id="pestana-personal" aria-controls="panel-personal"
                                x-bind:aria-selected="seleccionPersonal" x-bind:class="clasePersonal" x-on:click="verPersonal"
                                class="px-5 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer text-primario-700 dark:text-primario-300 hover:bg-white/10">
                            <?= $e(Audiencia::Personal->pestana()) ?>
                        </button>
                    </div>
                <?php } ?>
            </div>

            <?php foreach ($pestanasCampanas as $indicePestana => $pestana) {
                $audiencia = $pestana['audiencia'];
                $esPersonal = $audiencia === Audiencia::Personal;
                $clave = strtolower($pestana['referencia']);
                ?>
                <div id="panel-<?= $e($clave) ?>" class="comunicaciones-panel" data-activa="<?= $indicePestana === 0 ? 'true' : 'false' ?>"
                     role="tabpanel" aria-labelledby="pestana-<?= $e($clave) ?>" x-ref="panel<?= $e($pestana['referencia']) ?>">
                    <h3 class="comunicaciones-titulo-panel text-lg font-extrabold texto-titular mb-4"><?= $e($audiencia->pestana()) ?></h3>

                    <?php /* -mx-8 saca la lista del padding de la tarjeta para que el
                             héroe llegue a los bordes. El encabezado y las pestañas se
                             quedan dentro, que es justo lo que no debe taparse. */ ?>
                    <ul class="-mx-8" x-ref="lista<?= $e($pestana['referencia']) ?>">
                        <?php foreach ($pestana['campanas'] as $indiceCampana => $campana) { ?>
                            <li class="comunicaciones-diapositiva"
                                data-activa="<?= $indiceCampana === 0 ? 'true' : 'false' ?>">
                                <?php /* Héroe a sangre: la imagen ocupa todo el ancho de la
                                         tarjeta y el texto de la campaña va ENCIMA. El velo
                                         oscuro no es decoración: sobre una foto cualquiera
                                         el texto blanco puede quedar en 1,5:1, y aquí va el
                                         titular de una campaña de salud. Con el degradado
                                         de abajo a arriba el texto se apoya siempre en la
                                         zona más oscura. */ ?>
                                <article class="relative isolate overflow-hidden rounded-[1.25rem] min-h-[420px] sm:min-h-[520px] flex items-end">
                                    <?php if ($campana->imagenId !== null) { ?>
                                        <img class="absolute inset-0 -z-10 h-full w-full object-cover" src="/archivos/<?= $campana->imagenId ?>"
                                             alt="<?= $e($imagenes[$campana->imagenId]->textoAlt ?? '') ?>"
                                             loading="lazy" width="1280" height="720">
                                    <?php } else { ?>
                                        <?php /* Sin foto no se mete un logo estirado: el degradado
                                                 de marca sostiene el texto igual de bien. */ ?>
                                        <span class="absolute inset-0 -z-10 <?= $esPersonal
                                            ? 'bg-gradient-to-br from-primario-800 via-primario-900 to-primario-950'
                                            : 'bg-gradient-to-br from-primario-700 via-primario-800 to-primario-900' ?>" aria-hidden="true"></span>
                                    <?php } ?>

                                    <span class="absolute inset-0 -z-10 bg-gradient-to-t from-primario-950/95 via-primario-950/70 to-primario-950/25" aria-hidden="true"></span>

                                    <div class="relative w-full p-7 sm:p-9 space-y-3 max-w-3xl">
                                        <?php if ($campana->poblacionObjetivo !== null && $campana->poblacionObjetivo !== '') { ?>
                                            <span class="inline-block text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full border <?= $esPersonal
                                                ? 'bg-aviso-400/20 text-aviso-100 border-aviso-200/40'
                                                : 'bg-[color:var(--color-marca-cian)]/20 text-white border-white/30' ?>">
                                                <?= $e($campana->poblacionObjetivo) ?>
                                            </span>
                                        <?php } ?>

                                        <h4 class="text-3xl sm:text-4xl font-extrabold leading-tight text-white drop-shadow-sm"><?= $e($campana->titulo) ?></h4>

                                        <?php if ($campana->resumen !== null && $campana->resumen !== '') { ?>
                                            <p class="text-base text-primario-100 leading-relaxed max-w-prose"><?= $e($campana->resumen) ?></p>
                                        <?php } ?>

                                        <?php if ($campana->fin !== null) { ?>
                                            <p class="text-xs text-primario-200">Vigente hasta el <?= $e(Fechas::larga($campana->fin)) ?></p>
                                        <?php } ?>

                                        <div class="pt-2">
                                            <a class="boton <?= $esPersonal
                                                ? 'bg-aviso-100 text-aviso-900 border border-aviso-200 hover:bg-aviso-200'
                                                : 'bg-white text-primario-800 hover:bg-primario-50' ?> font-bold text-sm py-2.5 px-6 rounded-xl shadow-lg no-underline"
                                               href="/campanas/<?= $e($campana->slug) ?>">Conoce los detalles</a>
                                        </div>
                                    </div>
                                </article>
                            </li>
                        <?php } ?>
                    </ul>

                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <?php if (count($pestana['campanas']) > 1) { ?>
                            <div class="comunicaciones-navegacion flex gap-2 items-center">
                                <button type="button" x-on:click="anterior" aria-label="Comunicación anterior"
                                        class="p-2 rounded-xl bg-primario-50 dark:bg-primario-800 text-primario-600 dark:text-primario-200 hover:bg-primario-100 cursor-pointer">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                </button>
                                <button type="button" x-on:click="siguiente" aria-label="Comunicación siguiente"
                                        class="p-2 rounded-xl bg-primario-50 dark:bg-primario-800 text-primario-600 dark:text-primario-200 hover:bg-primario-100 cursor-pointer">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </button>
                                <span class="text-xs texto-suave tabular-nums" x-text="posicion" aria-live="polite"></span>
                            </div>
                        <?php } ?>

                        <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center"
                           href="<?= $e($audiencia->ruta()) ?>">
                            <?= $esPersonal ? 'Todo para trabajadores y contratistas' : 'Ver todas las campañas' ?>
                            <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>
<?php } ?>

<?php
    /* Información estadística en la portada: los tres grupos que pidió el
       hospital —contratación, ocupación hospitalaria y estadísticas vitales—
       con el último periodo publicado de cada indicador.

       Cada cifra sale con su periodo y su fuente. No es adorno: un número de
       ocupación sin decir a qué mes corresponde y de dónde salió no informa,
       invita a interpretarlo mal. Por eso tampoco hay aquí ninguna cifra
       escrita en la plantilla: si el hospital no lo publicó en Panel →
       Indicadores, la sección entera no se pinta. */
    $cifraPortada = static fn (float $valor): string => $valor === (float) (int) $valor
        ? number_format($valor, 0, ',', '.')
        : number_format($valor, 1, ',', '.');
?>

<section class="mx-auto max-w-7xl px-4 pb-16" aria-labelledby="indicadores-titulo">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
            <div>
                <span class="text-xs font-bold uppercase tracking-widest text-primario-500">Datos abiertos</span>
                <h2 id="indicadores-titulo" class="text-2xl font-extrabold tracking-tight texto-titular mt-1">Así va la entidad</h2>
                <p class="mt-2 text-sm texto-suave max-w-2xl">
                    Cifras publicadas por la E.S.E., cada una con el periodo al que corresponde y su fuente.
                </p>
            </div>
            <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/estadisticas">
                Ver toda la información estadística
                <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>

        <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($gruposIndicadores as $codigoGrupo => $grupo) { ?>
                <li>
                    <section class="tarjeta h-full p-7 rounded-[2rem] bg-[color:var(--superficie)] border border-primario-100/50 dark:border-primario-700/50 shadow-lg hover:shadow-xl transition-shadow flex flex-col"
                             aria-labelledby="grupo-<?= $e($codigoGrupo) ?>">
                        <h3 id="grupo-<?= $e($codigoGrupo) ?>" class="text-lg font-extrabold texto-titular border-b border-primario-100/50 dark:border-primario-800 pb-4 mb-5">
                            <?= $e($grupo['titulo']) ?>
                        </h3>

                        <?php if ($grupo['indicadores'] === []) { ?>
                            <?php /* Ni un cero ni un guion suelto: el cero es una
                                     afirmación («cero contratos») y aquí lo que pasa
                                     es que nadie ha publicado el dato todavía. */ ?>
                            <p class="text-sm texto-suave leading-relaxed">
                                Aún no hay cifras publicadas de este periodo.
                            </p>
                        <?php } ?>

                        <ul class="flex flex-col gap-5">
                            <?php foreach ($grupo['indicadores'] as $indicador) {
                                $valorIndicador = (float) $indicador['valor'];
                                $unidadIndicador = (string) ($indicador['unidad'] ?? '');
                                $metaIndicador = $indicador['meta'] === null ? null : (float) $indicador['meta'];

                                // Barra solo cuando hay referencia real: la meta, o 100 si el
                                // dato ya es un porcentaje. Un ancho inventado afirmaría algo
                                // que la cifra no dice. Va en pasos de 5 % (clases .barra-N)
                                // porque la CSP del sitio público prohíbe el estilo en línea.
                                $referenciaIndicador = $metaIndicador ?? ($unidadIndicador === '%' ? 100.0 : null);
                                ?>
                                <li>
                                    <p class="text-sm texto-suave leading-snug"><?= $e((string) $indicador['nombre']) ?></p>

                                    <p class="mt-1 flex items-baseline gap-2">
                                        <span class="text-3xl font-extrabold texto-titular tabular-nums"><?= $e($cifraPortada($valorIndicador)) ?></span>
                                        <?php if ($unidadIndicador !== '') { ?>
                                            <span class="text-sm texto-suave"><?= $e($unidadIndicador) ?></span>
                                        <?php } ?>
                                    </p>

                                    <?php if ($referenciaIndicador !== null && $referenciaIndicador > 0.0) { ?>
                                        <div class="mt-2 barra-fondo" role="presentation">
                                            <span class="barra barra-<?= (int) (round(min(100.0, max(0.0, $valorIndicador / $referenciaIndicador * 100)) / 5) * 5) ?>"></span>
                                        </div>
                                    <?php } ?>

                                    <p class="mt-1.5 text-xs texto-suave">
                                        <?= $e(Fechas::mesYAnio((string) $indicador['periodo'])) ?><?php if (($indicador['fuente'] ?? '') !== '') { ?> · <?= $e((string) $indicador['fuente']) ?><?php } ?>
                                    </p>
                                </li>
                            <?php } ?>
                        </ul>

                        <a class="mt-auto pt-6 text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center"
                           href="/estadisticas#<?= $e($codigoGrupo) ?>">
                            Detalle y serie histórica
                            <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </section>
                </li>
            <?php } ?>
    </ul>
</section>

<section class="mx-auto max-w-7xl px-4 py-16" aria-labelledby="bento-titulo">
    <h2 id="bento-titulo" class="sr-only">Urgencias, noticias y sedes</h2>

    <div class="grid gap-8 lg:grid-cols-3">
        <?php /* Columna izquierda: urgencias arriba y participación debajo.
                 Las dos son llamadas a la acción del ciudadano —una para cuando
                 pasa algo, otra para cuando quiere opinar—, así que viven juntas
                 y dejan las dos columnas de la derecha para el contenido. */ ?>
        <div class="flex flex-col gap-8">
        <?php /* Gradiente oficial de tres paradas: #1D5BA7 → #35BDE9 → #1D5BA7,
                     o sea primario-700 → primario-400 → primario-700 en los tokens.
                     Se escribe con las variables y no con el hexadecimal suelto
                     porque la paleta vive en un solo sitio (app.css): cambiarla ahí
                     tiene que cambiarla aquí. */ ?>
            <article class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-primario-700 via-primario-400 to-primario-700 p-10 text-white shadow-2xl animar-escala-hover group">
                <span class="mancha h-72 w-72 -right-10 -top-10 bg-[color:var(--color-marca-cian)] opacity-30 group-hover:opacity-40 transition-opacity duration-500 blur-3xl" aria-hidden="true"></span>
                <div class="relative z-10 flex flex-col h-full justify-between">
                    <div>
                        <span class="inline-flex items-center justify-center p-3 bg-white/10 rounded-2xl backdrop-blur-md mb-6 shadow-inner">
                            <svg class="h-10 w-10 text-[color:var(--color-marca-cian)]" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v5M12 16.5v.01M12 3.5 21 20H3l9-16.5Z"/>
                            </svg>
                        </span>
                        <h3 class="text-white text-3xl font-extrabold tracking-tight">Urgencias 24h</h3>
                        <p class="mt-4 text-primario-100 text-lg leading-relaxed">
                            Atención prioritaria y especializada sin esperas. Llámanos o acércate a nuestra sede principal.
                        </p>
                    </div>
                    <div class="mt-10 flex flex-col sm:flex-row gap-4">
                        <a class="boton bg-white text-primario-800 font-bold shadow-lg hover:shadow-xl hover:bg-primario-50 transition-all text-lg py-3 flex-1 justify-center"
                           href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.emergencias'))) ?>">
                            <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            <?= $e($config->texto('contacto.emergencias')) ?>
                        </a>
                        <a class="boton border-2 border-white/30 hover:border-white text-white no-underline transition-all bg-white/5 hover:bg-white/10 text-lg py-3 flex-1 justify-center" href="/urgencias">Ruta de acceso</a>
                    </div>
                </div>
            </article>

            <?php if ($encuestas !== []) { ?>
                <?php
                /* Participación ciudadana, respondible SIN salir de la portada.
                   Dos estados en la misma tarjeta, que alterna Alpine:
                   el cuestionario —de una pregunta en una— y los resultados.

                   El envío va por `fetch` al MISMO endpoint que el formulario
                   de /encuestas (`POST /encuestas/{slug}`), que responde JSON
                   cuando se lo piden por `Accept`. Un solo camino de guardado y
                   una sola validación: dos acabarían divergiendo.

                   Sin JavaScript la tarjeta sigue sirviendo: el formulario se
                   envía completo y el navegador aterriza en los resultados, y
                   «Ver resultados» es un enlace normal. Por eso los resultados
                   vienen ya renderizados desde el servidor —el ciudadano puede
                   mirarlos sin haber votado, que es lo que se pidió— y Alpine
                   solo los muestra u oculta. */
                $encuesta = $encuestas[0];
                $pasoEncuesta = static fn (int $porcentaje): int => (int) (round(max(0, min(100, $porcentaje)) / 5) * 5);
                ?>
                <div class="panel-vidrio rounded-[2.5rem] p-8 shadow-lg hover:shadow-xl transition-shadow border-white/40 dark:border-primario-700/40"
                     x-data="encuestaPortada">
                    <div class="flex items-center justify-between border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                        <h3 id="encuesta-titulo" class="text-xl font-bold flex items-center gap-2">
                            <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            Participación ciudadana
                        </h3>
                        <span class="text-xs font-bold uppercase tracking-wider text-primario-500">Abierta</span>
                    </div>

                    <h4 class="text-base font-extrabold text-primario-900 dark:text-white mb-3 leading-snug">
                        <?= $e((string) $encuesta['titulo']) ?>
                    </h4>

                    <?php if (($encuesta['descripcion'] ?? '') !== '') { ?>
                        <p class="text-sm texto-suave leading-relaxed mb-5">
                            <?= $e((string) $encuesta['descripcion']) ?>
                        </p>
                    <?php } ?>

                    <?php /* --- Estado 1: el cuestionario --- */ ?>
                    <form class="encuesta-cuestionario" method="post" data-activa="true"
                          action="/encuestas/<?= $e((string) $encuesta['slug']) ?>"
                          x-ref="formulario" x-on:submit="enviar">
                        <?php /* Trampa para robots: ningún humano la ve ni la llena. */ ?>
                        <div class="campo-trampa" aria-hidden="true">
                            <label for="sitio_web_portada">No llenar</label>
                            <input type="text" id="sitio_web_portada" name="sitio_web" tabindex="-1" autocomplete="off">
                        </div>

                        <ul x-ref="preguntas">
                            <?php foreach ($preguntasEncuesta as $indicePregunta => $pregunta) { ?>
                                <li class="encuesta-pregunta" data-activa="<?= $indicePregunta === 0 ? 'true' : 'false' ?>"
                                    data-obligatoria="<?= $pregunta['obligatoria'] ? 'true' : 'false' ?>">
                                    <fieldset>
                                        <legend class="text-sm font-bold texto-titular mb-3">
                                            <?= $e($pregunta['enunciado']) ?><?= $pregunta['obligatoria'] ? '' : ' (opcional)' ?>
                                        </legend>

                                        <?php if ($pregunta['tipo'] === 'texto') { ?>
                                            <textarea class="campo" name="p<?= $pregunta['id'] ?>[]" rows="3"
                                                      placeholder="Escribe tu respuesta"></textarea>
                                            <p class="mt-2 text-xs texto-suave">
                                                No escribas datos que permitan identificarte.
                                            </p>
                                        <?php } elseif ($pregunta['tipo'] === 'escala') { ?>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach (range(1, 5) as $punto) { ?>
                                                    <label class="cursor-pointer">
                                                        <input class="sr-only encuesta-opcion" type="radio" name="p<?= $pregunta['id'] ?>[]" value="<?= $punto ?>">
                                                        <span class="grid h-11 w-11 place-items-center rounded-xl border border-primario-200 dark:border-primario-700 font-bold tabular-nums transition-colors hover:bg-primario-50 dark:hover:bg-primario-800"><?= $punto ?></span>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                            <p class="mt-2 text-xs texto-suave">1 es «muy mala» y 5 es «excelente».</p>
                                        <?php } else { ?>
                                            <div class="flex flex-col gap-2">
                                                <?php foreach ($pregunta['opciones'] as $opcion) { ?>
                                                    <label class="flex items-center gap-2.5 text-sm cursor-pointer">
                                                        <input class="encuesta-opcion" type="<?= $pregunta['tipo'] === 'opcion_multiple' ? 'checkbox' : 'radio' ?>"
                                                               name="p<?= $pregunta['id'] ?>[]" value="<?= $e($opcion) ?>">
                                                        <span><?= $e($opcion) ?></span>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </fieldset>
                                </li>
                            <?php } ?>
                        </ul>

                        <p class="encuesta-aviso mt-4 text-sm text-error-600 dark:text-error-300" data-activa="false" x-ref="aviso" x-text="aviso" role="alert"></p>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <?php /* Sin JS los botones de paso no tienen sentido: se esconden
                                     y queda el envío completo de siempre. */ ?>
                            <button type="button" class="encuesta-paso boton boton-secundario text-sm py-2 px-5 rounded-xl"
                                    x-on:click="anterior" x-bind:disabled="esPrimera">Anterior</button>
                            <button type="button" class="encuesta-paso boton boton-primario text-sm py-2 px-5 rounded-xl"
                                    x-on:click="siguiente" x-show="!esUltima">Siguiente</button>
                            <button type="submit" class="boton boton-primario text-sm py-2 px-6 rounded-xl shadow-md"
                                    x-show="esUltima">Enviar respuestas</button>
                            <span class="encuesta-paso text-xs texto-suave tabular-nums" x-text="posicion"></span>
                        </div>
                    </form>

                    <?php /* --- Estado 2: los resultados, visibles sin haber votado --- */ ?>
                    <div class="encuesta-resultados" data-activa="false" x-ref="resultados">
                        <p class="text-sm texto-suave mb-4" x-ref="totalRespuestas">
                            <?= $totalRespuestasEncuesta === 1
                                ? '1 persona ha respondido'
                                : $e(number_format($totalRespuestasEncuesta, 0, ',', '.')) . ' personas han respondido' ?>.
                        </p>

                        <?php if ($totalRespuestasEncuesta === 0) { ?>
                            <?php /* Se oculta en cuanto llega el primer voto sin recargar:
                                     si no, quedaría «1 persona ha respondido» encima de
                                     «todavía nadie ha respondido». */ ?>
                            <p class="text-sm texto-suave" x-ref="sinRespuestas">Todavía nadie ha respondido. Sé la primera persona en opinar.</p>
                        <?php } ?>

                        <div class="flex flex-col gap-5">
                            <?php foreach ($resultadosEncuesta as $preguntaResultado) { ?>
                                <?php if ($preguntaResultado['tipo'] === 'texto' || $preguntaResultado['opciones'] === []) { continue; } ?>
                                <section>
                                    <h5 class="text-sm font-bold texto-titular mb-2"><?= $e($preguntaResultado['enunciado']) ?></h5>
                                    <ul class="flex flex-col gap-3">
                                        <?php foreach ($preguntaResultado['opciones'] as $opcionResultado) { ?>
                                            <li>
                                                <div class="flex justify-between gap-3 text-xs mb-1">
                                                    <span class="texto-titular"><?= $e($opcionResultado['texto']) ?></span>
                                                    <span class="texto-suave tabular-nums"><?= $e((string) $opcionResultado['porcentaje']) ?> %</span>
                                                </div>
                                                <div class="barra-fondo" role="presentation">
                                                    <span class="barra barra-<?= $pasoEncuesta($opcionResultado['porcentaje']) ?>"></span>
                                                </div>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </section>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-primario-100 dark:border-primario-800 pt-4">
                        <?php /* Con JS alterna cuestionario y resultados sin recargar; sin JS
                                 es un enlace a la página de resultados. */ ?>
                        <button type="button" class="encuesta-paso text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline"
                                x-on:click="alternarResultados" x-text="etiquetaResultados"></button>
                        <a class="encuesta-enlace-resultados text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline"
                           href="/encuestas/<?= $e((string) $encuesta['slug']) ?>/resultados">Ver resultados</a>

                        <a class="ms-auto text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/encuestas">
                            Otras encuestas
                            <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </div>

                    <p class="text-xs texto-suave mt-4">
                        Es anónima: no se guarda ningún dato que permita identificarte.
                        <?php if (count($encuestas) > 1) { ?>
                            Hay <?= count($encuestas) ?> encuestas abiertas.
                        <?php } ?>
                    </p>
                </div>
            <?php } ?>
        </div>

        <?php /* Columnas derechas: noticias ocupando las dos (con el hueco
                 del medio incluido), y debajo agenda y sede. */ ?>
        <div class="lg:col-span-2 grid gap-8 sm:grid-cols-2">
            <div class="sm:col-span-2 panel-vidrio rounded-[2.5rem] p-8 shadow-lg hover:shadow-xl transition-shadow border-white/40 dark:border-primario-700/40">
                <div class="flex items-center justify-between border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                    <h3 id="noticias-titulo" class="text-xl font-bold flex items-center gap-2">
                        <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2M18 14h-8M15 18h-5"/></svg>
                        Noticias recientes
                    </h3>
                    <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/noticias">Ver todas <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>

                <?php if ($noticias === []) { ?>
                    <p class="mt-4 text-sm texto-suave">Aún no hay noticias publicadas.</p>
                <?php } else { ?>
                    <?php /* Seis piezas: dos columnas en pantallas medianas y tres en las
                                 anchas, para que quepan en dos filas en vez de seis. */ ?>
                    <ul class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" aria-labelledby="noticias-titulo">
                        <?php foreach ($noticias as $noticia) { ?>
                            <li class="group">
                                <article class="flex flex-col gap-3 h-full">
                                    <?php if ($noticia->imagenId !== null) { ?>
                                        <div class="overflow-hidden rounded-2xl relative aspect-[16/9]">
                                            <img class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" src="/archivos/<?= $noticia->imagenId ?>"
                                                 alt="<?= $e($imagenes[$noticia->imagenId]->textoAlt ?? '') ?>"
                                                 loading="lazy" width="600" height="337">
                                            <div class="absolute inset-0 ring-1 ring-inset ring-black/10 rounded-2xl"></div>
                                        </div>
                                    <?php } ?>
                                    <div>
                                        <p class="text-xs font-semibold text-primario-500 uppercase tracking-wider mb-1"><?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?></p>
                                        <h4 class="text-lg font-bold leading-tight">
                                            <a class="texto-titular no-underline group-hover:text-primario-600 transition-colors" href="/noticias/<?= $e($noticia->slug) ?>">
                                                <?= $e($noticia->titulo) ?>
                                            </a>
                                        </h4>
                                    </div>
                                </article>
                            </li>
                        <?php } ?>
                    </ul>

                    <?php if ($paginasNoticias > 1) { ?>
                        <?php /* Paginador de enlaces, sin JavaScript, con ancla al
                                 propio bloque: al cambiar de página el navegador
                                 aterriza en las noticias y no al principio de la
                                 portada. `aria-current` marca dónde está uno. */ ?>
                        <nav class="mt-8 flex flex-wrap items-center justify-center gap-2" aria-label="Páginas de noticias">
                            <?php if ($paginaNoticias > 1) { ?>
                                <a class="boton boton-secundario text-sm py-1.5 px-4 rounded-xl no-underline"
                                   href="/?pagina=<?= $paginaNoticias - 1 ?>#noticias-titulo" rel="prev">Anteriores</a>
                            <?php } ?>

                            <?php for ($n = 1; $n <= $paginasNoticias; $n++) { ?>
                                <?php if ($n === $paginaNoticias) { ?>
                                    <span class="boton boton-primario text-sm py-1.5 px-4 rounded-xl tabular-nums" aria-current="page"><?= $n ?></span>
                                <?php } else { ?>
                                    <a class="boton boton-secundario text-sm py-1.5 px-4 rounded-xl no-underline tabular-nums"
                                       href="/?pagina=<?= $n ?>#noticias-titulo"><?= $n ?></a>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($paginaNoticias < $paginasNoticias) { ?>
                                <a class="boton boton-secundario text-sm py-1.5 px-4 rounded-xl no-underline"
                                   href="/?pagina=<?= $paginaNoticias + 1 ?>#noticias-titulo" rel="next">Siguientes</a>
                            <?php } ?>
                        </nav>
                    <?php } ?>
                <?php } ?>
            </div>

            <?php /* Agenda institucional (ITA 1.12 y 6.5). La tarjeta es fija:
                     aparece siempre, con datos o con la línea que dice que no
                     hay nada programado. Una sección que va y viene según el
                     mes deja al visitante sin saber si existe.

                     `proximos()` ya descarta lo que pasó: anunciar en la
                     portada el evento de la semana pasada manda gente a un
                     salón vacío. */ ?>
            <div class="panel-vidrio rounded-[2.5rem] p-8 shadow-lg hover:shadow-xl transition-shadow border-white/40 dark:border-primario-700/40">
                <div class="flex items-center justify-between border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                    <h3 id="eventos-titulo" class="text-xl font-bold flex items-center gap-2">
                        <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v3M16 2v3M3.5 9h17M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z"/></svg>
                        Próximos eventos
                    </h3>
                    <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/eventos">
                        Ver la agenda
                        <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <?php if ($proximosEventos === []) { ?>
                    <p class="text-sm texto-suave leading-relaxed">
                        No hay eventos programados por ahora. La agenda completa, mes a mes,
                        está en <a class="font-semibold hover:underline" href="/eventos">el calendario</a>.
                    </p>
                <?php } else { ?>
                    <ul class="flex flex-col gap-5" aria-labelledby="eventos-titulo">
                        <?php foreach ($proximosEventos as $evento) {
                            $inicioEvento = (string) $evento['inicio'];
                            $tipoEvento = (string) $evento['tipo'];
                            ?>
                            <li class="flex gap-4">
                                <?php /* Fecha en bloque: el día y el mes se leen de un
                                         vistazo sin tener que descifrar la frase. */ ?>
                                <span class="shrink-0 grid place-items-center h-14 w-14 rounded-2xl bg-primario-50 dark:bg-primario-900/60 border border-primario-100/60 dark:border-primario-700/50 leading-none">
                                    <span class="text-xl font-extrabold texto-titular tabular-nums"><?= $e(Fechas::dia($inicioEvento)) ?></span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider texto-suave mt-0.5"><?= $e(Fechas::mesCorto($inicioEvento)) ?></span>
                                </span>

                                <div class="min-w-0">
                                    <h4 class="font-bold leading-snug texto-titular">
                                        <a class="no-underline hover:underline" href="/eventos/<?= $e((string) $evento['slug']) ?>"><?= $e((string) $evento['titulo']) ?></a>
                                    </h4>
                                    <p class="mt-1 text-xs texto-suave">
                                        <?= $e((bool) $evento['todo_el_dia'] ? 'Todo el día' : Fechas::hora($inicioEvento)) ?><?php if (($evento['lugar'] ?? '') !== '') { ?> · <?= $e((string) $evento['lugar']) ?><?php } ?>
                                    </p>
                                    <?php if (isset($tiposEvento[$tipoEvento])) { ?>
                                        <span class="mt-2 inline-block text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-0.5 rounded-full bg-[color:var(--color-marca-cian)]/10 text-primario-800 dark:text-white border border-[color:var(--color-marca-cian)]/20">
                                            <?= $e($tiposEvento[$tipoEvento]) ?>
                                        </span>
                                    <?php } ?>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>

            <div class="panel-vidrio rounded-[2.5rem] p-8 shadow-lg hover:shadow-xl transition-shadow border-white/40 dark:border-primario-700/40">
                <div class="flex items-center justify-between border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                    <h3 id="sedes-titulo" class="text-xl font-bold flex items-center gap-2">
                        <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?= count($sedes) === 1 ? 'Nuestra sede' : 'Nuestras sedes' ?>
                    </h3>
                    <?php if (count($sedes) > 1) { ?>
                        <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/sedes">Ver todas <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    <?php } ?>
                </div>
                <?php if ($sedes === []) { ?>
                    <p class="mt-4 text-sm texto-suave">
                        Consulta nuestra dirección y horarios en <a href="/contacto" class="text-primario-600 font-semibold hover:underline">contacto</a>.
                    </p>
                <?php } else { ?>
                    <ul class="flex flex-col gap-4" aria-labelledby="sedes-titulo">
                        <?php foreach ($sedes as $sede) { ?>
                            <li class="transform transition-transform hover:-translate-y-1">
                                <?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
