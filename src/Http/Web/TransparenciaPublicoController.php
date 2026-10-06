<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigDinamica;
use App\Core\Support\Vistas;
use App\Modules\CuentasCobro\Service\EjecucionContractualPublica;
use App\Modules\Tramites\Service\InformeAccesoInformacion;
use App\Modules\Transparencia\Entity\TipoInstrumento;
use App\Modules\Transparencia\Repository\DatasetRepository;
use App\Modules\Transparencia\Repository\EnteVigilanciaRepository;
use App\Modules\Transparencia\Repository\InstrumentoInformacionRepository;
use App\Modules\Transparencia\Repository\TransparenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menú público de transparencia (Ley 1712, Res. 1519) y datos abiertos.
 * Las secciones vacías también se muestran, con la leyenda «En
 * actualización»: ocultarlas sería fingir cumplimiento.
 */
final readonly class TransparenciaPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private TransparenciaRepository $transparencia,
        private DatasetRepository $datasets,
        private InstrumentoInformacionRepository $instrumentos,
        private EnteVigilanciaRepository $entes,
        private EjecucionContractualPublica $ejecucion,
        private InformeAccesoInformacion $informeAcceso,
        private ConfigDinamica $config,
    ) {
    }

    /**
     * Informe trimestral de acceso a la información, quejas y reclamos
     * (ITA 6.10). Se calcula sobre las solicitudes radicadas, así que está
     * siempre al día sin que nadie tenga que redactarlo cada trimestre.
     */
    public function informeAcceso(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Informe de acceso a la información, quejas y reclamos',
            descripcion: 'Solicitudes recibidas, tiempos de respuesta, traslados y negaciones de '
                . 'acceso de la E.S.E. Hospital Rosario Pumarejo de López, trimestre a trimestre.',
            contenido: $this->vistas->render('publico/transparencia/informe-acceso', [
                'trimestres' => $this->informeAcceso->porTrimestre(),
                'causales' => $this->informeAcceso->causalesDeNegacion(),
            ]),
        );
    }

    /** El mismo informe como dato abierto. */
    public function informeAccesoCsv(Request $peticion): Response
    {
        return $this->csv('informe-acceso-a-la-informacion', [
            'periodo' => 'Periodo',
            'recibidas' => 'Solicitudes recibidas',
            'respondidas' => 'Solicitudes respondidas',
            'trasladadas' => 'Trasladadas a otra entidad',
            'negadas' => 'Solicitudes con acceso negado',
            'dias_promedio' => 'Tiempo promedio de respuesta (días)',
        ], $this->informeAcceso->porTrimestre());
    }

    /**
     * Estado de ejecución de los contratos (ITA 5.3), una de las pérdidas de
     * 2026. Los seis literales del criterio los calcula el servicio público
     * del módulo de cuentas de cobro, que es donde viven los contratos.
     */
    public function ejecucionContractual(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Ejecución de contratos',
            descripcion: 'Estado de ejecución de los contratos de la E.S.E. Hospital Rosario '
                . 'Pumarejo de López: valor, porcentaje ejecutado, recursos desembolsados y adiciones.',
            contenido: $this->vistas->render('publico/transparencia/ejecucion-contractual', [
                'contratos' => $this->ejecucion->contratos(),
                'resumen' => $this->ejecucion->resumen(),
            ]),
        );
    }

    /** El mismo estado de ejecución como dato abierto. */
    public function ejecucionContractualCsv(Request $peticion): Response
    {
        $encabezados = [
            'numero' => 'Número de contrato',
            'tipo_contrato' => 'Tipo de contrato',
            'seccion_ubicacion' => 'Dependencia',
            'fecha_acta_inicio' => 'Fecha de inicio',
            'fecha_terminacion' => 'Fecha de finalización',
            'valor_total' => 'Valor del contrato',
            'porcentaje' => 'Porcentaje de ejecución',
            'pagado' => 'Recursos desembolsados',
            'pendiente' => 'Recursos pendientes de ejecutar',
            'novedades' => 'Cantidad de otrosíes y adiciones',
            'valor_adiciones' => 'Monto de las adiciones',
            'estado' => 'Estado del contrato',
        ];

        return $this->csv('ejecucion-contractual', $encabezados, $this->ejecucion->contratos());
    }

    /**
     * Entes y autoridades que vigilan a la entidad (ITA 3.13).
     *
     * Los siete literales del criterio viven en `entes_vigilancia`, uno por
     * columna, porque el evaluador los verifica uno por uno.
     */
    public function entesDeControl(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Entes y autoridades que nos vigilan',
            descripcion: 'Autoridades de inspección, vigilancia y control de la E.S.E. Hospital '
                . 'Rosario Pumarejo de López, con sus datos de contacto y el tipo de control que ejercen.',
            contenido: $this->vistas->render('publico/transparencia/entes-de-control', [
                'entes' => $this->entes->publicados(),
            ]),
        );
    }

    public function index(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Transparencia y acceso a la información pública',
            descripcion: 'Información pública de la E.S.E. Hospital Rosario Pumarejo de López según la Ley 1712 de 2014 y la Resolución 1519 de 2020.',
            contenido: $this->vistas->render('publico/transparencia/index', [
                'arbol' => $this->transparencia->arbol(soloPublico: true),
            ]),
        );
    }

    public function datosAbiertos(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Datos abiertos',
            descripcion: 'Conjuntos de datos abiertos de la E.S.E. Hospital Rosario Pumarejo de López, descargables en CSV, JSON y otros formatos abiertos.',
            contenido: $this->vistas->render('publico/transparencia/datos-abiertos', [
                'datasets' => $this->datasets->publicados(),
                'portalUrl' => $this->config->texto('datos_abiertos.portal_url'),
                'conteoInstrumentos' => $this->instrumentos->conteoPublicado(),
            ]),
        );
    }

    /**
     * Índice de los tres instrumentos de gestión de la información
     * (Ley 1712 art. 6 lit. d, ITA 9.1).
     */
    public function instrumentos(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Instrumentos de gestión de la información',
            descripcion: 'Registro de Activos de Información, Índice de Información Clasificada y '
                . 'Reservada y Esquema de Publicación de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/transparencia/instrumentos', [
                'tipos' => TipoInstrumento::todos(),
                'conteo' => $this->instrumentos->conteoPublicado(),
                'portalUrl' => $this->config->texto('datos_abiertos.portal_url'),
            ]),
        );
    }

    /**
     * Un instrumento, como tabla. El ITA 9.1 verifica columna por columna, así
     * que la tabla se pinta completa aunque una celda esté vacía: ocultar la
     * columna haría ilegible qué falta por diligenciar.
     */
    public function instrumento(Request $peticion): Response
    {
        $tipo = TipoInstrumento::porSlug((string) $peticion->attributes->get('slug'));

        if ($tipo === null) {
            return new RedirectResponse('/transparencia/instrumentos-de-gestion', Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->layout->responder(
            titulo: $tipo->nombre(),
            descripcion: $tipo->descripcion(),
            contenido: $this->vistas->render('publico/transparencia/instrumento', [
                'tipo' => $tipo,
                'filas' => $this->instrumentos->publicados($tipo),
            ]),
        );
    }

    /**
     * El mismo instrumento en CSV. La Ley 1712 art. 6 lit. d exige formato
     * abierto y reutilizable: una tabla HTML cumple la publicidad pero no la
     * reutilización, así que cada instrumento se descarga además como dato.
     */
    public function instrumentoCsv(Request $peticion): Response
    {
        $tipo = TipoInstrumento::porSlug((string) $peticion->attributes->get('slug'));

        if ($tipo === null) {
            return new Response('No encontrado', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $this->csv($tipo->slug(), $tipo->columnas(), $this->instrumentos->publicados($tipo));
    }

    /**
     * Vuelca filas a CSV descargable.
     *
     * Separador `;` porque Excel en español lo espera; con `,` abre todo en una
     * sola columna. El BOM es por la misma razón: sin él Excel en Windows lee
     * el archivo en ANSI y parte las tildes.
     *
     * @param array<string, string>            $columnas campo => encabezado
     * @param list<array<string, mixed>>       $filas
     */
    private function csv(string $nombre, array $columnas, array $filas): Response
    {
        $salida = fopen('php://temp', 'r+');
        assert($salida !== false);

        fwrite($salida, "\u{FEFF}");
        fputcsv($salida, array_values($columnas), ';');

        foreach ($filas as $fila) {
            fputcsv($salida, array_map(
                static fn (string $campo): string => (string) ($fila[$campo] ?? ''),
                array_keys($columnas),
            ), ';');
        }

        rewind($salida);
        $csv = (string) stream_get_contents($salida);
        fclose($salida);

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function descargarRecurso(Request $peticion): Response
    {
        $recurso = $this->datasets->recursoPublico($peticion->attributes->getInt('id'));

        if ($recurso === null) {
            return new Response('No encontrado', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $this->datasets->contarDescarga((int) $recurso['id']);

        if ($recurso['archivo_id'] !== null) {
            return new RedirectResponse('/archivos/' . (int) $recurso['archivo_id'], Response::HTTP_FOUND);
        }

        return new RedirectResponse((string) $recurso['url_externa'], Response::HTTP_FOUND);
    }

    /**
     * Sub-URL con nombre. Las redirecciones del Joomla apuntan aquí (docs/14
     * §5), así que resolver el ancla de la sección evita la cadena
     * 301 → 302 → `/transparencia` sobre las ~2.000 URL migradas: un solo
     * salto, y el ciudadano cae en la sección que pidió y no al principio
     * del menú.
     *
     * El ancla `seccion-{codigo}` la emite la vista del índice; los códigos
     * son los que siembra SembrarTransparenciaCommand.
     */
    private const array ANCLAS = [
        'informacion-de-la-entidad' => '1',
        'normatividad' => '2',
        'contratacion' => '3',
        'planeacion-presupuesto-informes' => '4',
        'tramites' => '5',
        'participa' => '6',
        'datos-abiertos' => '7',
        'grupos-de-interes' => '8',
        'reportes-sector-salud' => '9',
        'gestion-del-riesgo' => '11',

        // Subsecciones con enlace propio. El oficio de Supersalud
        // 20264200002708501 cita por escrito la URL del Joomla
        // `/informes/reportes-de-control-interno`, así que esa dirección tiene
        // que seguir llevando al sitio exacto —no al índice— cuando entre el
        // sitio nuevo: de lo contrario el «enlace directo y funcional» que
        // exige la orden 9 queda roto en un expediente.
        'informes-de-control-interno' => '4.16',
        'instrumentos-de-gestion-de-la-informacion' => '7.3',
    ];

    public function subseccion(Request $peticion): Response
    {
        $resto = trim((string) $peticion->attributes->get('resto'), '/');
        $codigo = self::ANCLAS[$resto] ?? null;

        return new RedirectResponse(
            $codigo === null ? '/transparencia' : '/transparencia#seccion-' . $codigo,
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
