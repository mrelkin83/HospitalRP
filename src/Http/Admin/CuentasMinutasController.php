<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\ExtraccionMinutaRepository;
use App\Modules\CuentasCobro\Repository\SupervisorRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\ExtractorMinutaService;
use App\Modules\CuentasCobro\Service\NumeroALetras;
use App\Modules\CuentasCobro\Service\OcrMinutaService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A2 — Cargar minuta (fase 5): subir el PDF escaneado, dejar que la IA
 * prellene el formulario y revisarlo a dos columnas —las páginas a la
 * izquierda, los campos a la derecha, las alertas en rojo arriba—.
 *
 * Nada se activa sin revisión humana (§10 paso 4), y la auditoría anota
 * qué campos corrigió el revisor frente a lo que la IA extrajo.
 */
final readonly class CuentasMinutasController
{
    private const string TITULO = 'Cuentas de cobro — Cargar minuta';
    private const string RUTA = '/admin/cuentas-cobro/minutas';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ExtraccionMinutaRepository $extracciones,
        private ExtractorMinutaService $extractor,
        private OcrMinutaService $ocr,
        private ArchivosCuentas $archivos,
        private ContratistaRepository $contratistas,
        private ContratoRepository $contratos,
        private SupervisorRepository $supervisores,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/minutas-listado', [
            'extracciones' => $this->extracciones->recientes(),
            'iaDisponible' => $this->extractor->iaDisponible(),
            'ocrDisponible' => $this->ocr->disponible(),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /** Subir el PDF crea la extracción y la procesa de una vez. */
    public function subir(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $minuta = $peticion->files->get('minuta');

        if (!$minuta instanceof UploadedFile) {
            return $this->volverConError(self::RUTA, 'Seleccione el PDF de la minuta.');
        }

        if ($minuta->getMimeType() !== 'application/pdf') {
            return $this->volverConError(self::RUTA, 'La minuta debe ser un PDF (el escaneado de la Oficina Jurídica).');
        }

        try {
            $ruta = $this->archivos->guardar($minuta, 'minutas');
        } catch (\InvalidArgumentException $e) {
            return $this->volverConError(self::RUTA, $e->getMessage());
        }

        $id = $this->extracciones->crear($ruta, $this->pantalla->usuarioId() ?? 0);
        $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'cc_extraccion', $id, 'Minuta subida para extracción');

        // La extracción puede tardar (5 imágenes al proveedor): se avisa
        // al PHP que no corte a mitad de camino.
        set_time_limit(300);
        $resultado = $this->extractor->procesar($id, $this->pantalla->usuarioId());
        $this->pantalla->guardarEnLaSesion($resultado['ok'] ? 'cc_mensaje' : 'cc_error', $resultado['mensaje']);

        return new RedirectResponse(self::RUTA . '/' . $id, Response::HTTP_SEE_OTHER);
    }

    /** Reintentar la extracción con la IA. */
    public function reprocesar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        set_time_limit(300);
        $resultado = $this->extractor->procesar($id, $this->pantalla->usuarioId());
        $this->pantalla->guardarEnLaSesion($resultado['ok'] ? 'cc_mensaje' : 'cc_error', $resultado['mensaje']);

        return new RedirectResponse(self::RUTA . '/' . $id, Response::HTTP_SEE_OTHER);
    }

    /** Respaldo OCR: rescata forma de pago, plazo y obligaciones (§10). */
    public function extraerOcr(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $extraccion = $this->extracciones->porId($id);

        if ($extraccion === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $imagenes = $this->imagenesIa($id);

        if ($imagenes === []) {
            return $this->volverConError(self::RUTA . '/' . $id, 'Primero procese la minuta para generar sus imágenes.');
        }

        try {
            set_time_limit(300);
            $parcial = $this->ocr->extraer($imagenes);
        } catch (\RuntimeException $e) {
            return $this->volverConError(self::RUTA . '/' . $id, $e->getMessage());
        }

        // El OCR complementa sin pisar: solo rellena lo que falte.
        $resultado = $parcial + ExtraccionMinutaRepository::resultado($extraccion);
        $this->extracciones->actualizar($id, [
            'estado' => 'LISTA',
            'proveedor' => 'ocr',
            'modelo' => 'tesseract-spa',
            'resultado_json' => json_encode($resultado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'terminado_en' => date('Y-m-d H:i:s'),
        ]);
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'OCR aplicado: rescató lo que pudo (forma de pago, plazo, obligaciones). El recuadro de la página 1 va a mano.');

        return new RedirectResponse(self::RUTA . '/' . $id, Response::HTTP_SEE_OTHER);
    }

    /** La pantalla de revisión a dos columnas. */
    public function revision(Request $peticion): Response
    {
        $id = $peticion->attributes->getInt('id');
        $extraccion = $this->extracciones->porId($id);

        if ($extraccion === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/minuta-revision', [
            'extraccion' => $extraccion,
            'resultado' => ExtraccionMinutaRepository::resultado($extraccion),
            'alertas' => ExtraccionMinutaRepository::alertas($extraccion),
            'paginas' => count($this->imagenesVisor($id)),
            'supervisores' => $this->supervisores->activos(),
            'iaDisponible' => $this->extractor->iaDisponible(),
            'ocrDisponible' => $this->ocr->disponible(),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /** Sirve una página escaneada del visor (archivo privado). */
    public function imagen(Request $peticion): Response
    {
        $id = $peticion->attributes->getInt('id');
        $n = $peticion->attributes->getInt('n');
        $imagenes = $this->imagenesVisor($id);
        $ruta = $imagenes[$n - 1] ?? null;

        if ($ruta === null) {
            return new Response('No existe esa página.', Response::HTTP_NOT_FOUND);
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta($ruta)), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Crear el contrato desde el formulario revisado. La auditoría anota
     * qué campos cambió el revisor frente a lo extraído (§10 paso 4).
     */
    public function aplicar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $extraccion = $this->extracciones->porId($id);

        if ($extraccion === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        if ($extraccion['contrato_id'] !== null) {
            return $this->volverConError(self::RUTA . '/' . $id, 'Esta extracción ya se aplicó a un contrato.');
        }

        $r = $peticion->request;
        $volverA = self::RUTA . '/' . $id;

        // El contratista: por cédula, o se crea con lo de la minuta.
        $cedula = preg_replace('/\D/', '', $r->getString('contratista_documento')) ?? '';
        $nombres = trim($r->getString('contratista_nombres'));
        $apellidos = trim($r->getString('contratista_apellidos'));

        if ($cedula === '' || ($nombres === '' && $this->contratistas->porDocumento($cedula) === null)) {
            return $this->volverConError($volverA, 'El contratista necesita cédula y, si es nuevo, nombres y apellidos.');
        }

        $contratista = $this->contratistas->porDocumento($cedula);
        $contratistaId = $contratista !== null
            ? (int) $contratista['id']
            : $this->contratistas->crear([
                'numero_documento' => $cedula,
                'nombres' => mb_substr($nombres, 0, 120),
                'apellidos' => mb_substr($apellidos !== '' ? $apellidos : '—', 0, 120),
                'lugar_expedicion' => 'Valledupar',
            ]);

        // El contrato, con las validaciones mínimas de la carga manual.
        $numero = trim($r->getString('numero'));
        $valorTotal = $r->getInt('valor_total');
        $fecha = trim($r->getString('fecha_suscripcion'));
        $objeto = trim($r->getString('objeto'));

        if ($numero === '' || $valorTotal <= 0 || $fecha === '' || $objeto === '') {
            return $this->volverConError($volverA, 'Faltan datos del contrato: número, fecha, objeto o valor.');
        }

        if ($this->contratos->porNumero($numero) !== null) {
            return $this->volverConError($volverA, "Ya existe un contrato con el número {$numero}.");
        }

        $cuotas = $this->cuotasDelFormulario($r->all());

        if (is_string($cuotas)) {
            return $this->volverConError($volverA, $cuotas);
        }

        $supervisorId = $r->getInt('supervisor_id');
        $letras = trim($r->getString('valor_total_letras'));
        $contratoId = $this->contratos->crear([
            'numero' => mb_substr($numero, 0, 20),
            'contratista_id' => $contratistaId,
            'supervisor_id' => $supervisorId > 0 ? $supervisorId : null,
            'cargo_supervisor_minuta' => $this->texto($r->getString('cargo_supervisor_minuta'), 160),
            'tipo_contrato' => $this->texto($r->getString('tipo_contrato'), 120) ?? 'PRESTACIÓN DE SERVICIOS',
            'fecha_suscripcion' => $fecha,
            'objeto' => $objeto,
            'valor_total' => $valorTotal,
            'valor_total_letras' => $letras !== '' ? mb_substr($letras, 0, 400) : NumeroALetras::pesos($valorTotal),
            'plazo_texto' => $this->texto($r->getString('plazo_texto'), 120) ?? '',
            'plazo_meses' => max(0, min(255, $r->getInt('plazo_meses'))),
            'plazo_dias' => max(0, min(999, $r->getInt('plazo_dias'))),
            'garantias' => $r->getBoolean('garantias') ? 1 : 0,
            'forma_pago_texto' => $this->texto($r->getString('forma_pago_texto'), 5000),
            'minuta_archivo' => (string) $extraccion['minuta_archivo'],
        ]);

        $this->contratos->reemplazarPagos($contratoId, $cuotas);
        $this->contratos->reemplazarObligaciones($contratoId, $this->lineas($r->getString('obligaciones')), 'ESPECIFICA');
        $this->contratos->reemplazarObligaciones($contratoId, $this->lineas($r->getString('obligaciones_sgic')), 'SGIC');
        $this->guardarCdps($contratoId, $r->all());

        $this->extracciones->actualizar($id, ['estado' => 'APLICADA', 'contrato_id' => $contratoId]);
        $this->pantalla->auditar(
            $peticion,
            AccionAuditoria::Creacion,
            'cc_contrato',
            $contratoId,
            'Contrato ' . $numero . ' creado desde la minuta extraída'
            . $this->camposCorregidos($extraccion, $r->all()),
        );
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Contrato creado en revisión desde la minuta. Verifique el cronograma, registre lo que falte y actívelo.');

        return new RedirectResponse('/admin/cuentas-cobro/contratos/' . $contratoId, Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** @return list<string> Rutas relativas de las imágenes del visor. */
    private function imagenesVisor(int $extraccionId): array
    {
        $subcarpeta = 'extracciones-' . $extraccionId;
        $absolutas = glob($this->archivos->rutaAbsoluta($subcarpeta) . '/pag-*.png') ?: [];
        natsort($absolutas);

        return array_values(array_map(
            static fn (string $abs): string => $subcarpeta . '/' . basename($abs),
            $absolutas,
        ));
    }

    /** @return list<string> Las 150 dpi que alimentan IA y OCR. */
    private function imagenesIa(int $extraccionId): array
    {
        $subcarpeta = 'extracciones-' . $extraccionId;
        $absolutas = glob($this->archivos->rutaAbsoluta($subcarpeta) . '/ia-*.png') ?: [];
        natsort($absolutas);

        return array_values(array_map(
            static fn (string $abs): string => $subcarpeta . '/' . basename($abs),
            $absolutas,
        ));
    }

    /**
     * Qué campos del formulario difieren de lo extraído, para la
     * auditoría (§10 paso 4).
     *
     * @param array<string, mixed> $extraccion
     * @param array<string, mixed> $formulario
     */
    private function camposCorregidos(array $extraccion, array $formulario): string
    {
        $resultado = ExtraccionMinutaRepository::resultado($extraccion);

        if ($resultado === []) {
            return ' (carga manual, sin extracción previa)';
        }

        $comparables = [
            'numero' => (string) ($resultado['numero_contrato'] ?? ''),
            'fecha_suscripcion' => (string) ($resultado['fecha_suscripcion'] ?? ''),
            'valor_total' => (string) (int) ($resultado['valor_total'] ?? 0),
            'objeto' => (string) ($resultado['objeto'] ?? ''),
            'plazo_texto' => (string) ($resultado['plazo_texto'] ?? ''),
        ];
        $corregidos = [];

        foreach ($comparables as $campo => $extraido) {
            if ($extraido !== '' && trim((string) ($formulario[$campo] ?? '')) !== trim($extraido)) {
                $corregidos[] = $campo;
            }
        }

        return $corregidos === [] ? ' (sin correcciones del revisor)' : ' (el revisor corrigió: ' . implode(', ', $corregidos) . ')';
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @return list<array{numero_cuota: int, descripcion: string, tipo: string, mes: ?string, valor: int}>|string
     */
    private function cuotasDelFormulario(array $datos): array|string
    {
        $descripciones = is_array($datos['cuota_descripcion'] ?? null) ? array_values($datos['cuota_descripcion']) : [];
        $valores = is_array($datos['cuota_valor'] ?? null) ? array_values($datos['cuota_valor']) : [];
        $tipos = is_array($datos['cuota_tipo'] ?? null) ? array_values($datos['cuota_tipo']) : [];
        $meses = is_array($datos['cuota_mes'] ?? null) ? array_values($datos['cuota_mes']) : [];
        $cuotas = [];
        $numero = 0;

        foreach ($descripciones as $i => $descripcion) {
            $descripcion = trim((string) $descripcion);
            $valor = (int) ($valores[$i] ?? 0);

            if ($descripcion === '' && $valor === 0) {
                continue;
            }

            if ($descripcion === '' || $valor <= 0) {
                return 'Cada cuota necesita descripción y valor en pesos mayor que cero (fila ' . ($i + 1) . ').';
            }

            $mes = trim((string) ($meses[$i] ?? ''));

            $cuotas[] = [
                'numero_cuota' => ++$numero,
                'descripcion' => mb_substr($descripcion, 0, 120),
                'tipo' => ($tipos[$i] ?? '') === 'PARCIAL' ? 'PARCIAL' : 'COMPLETA',
                'mes' => preg_match('/^\d{4}-\d{2}$/', $mes) === 1 ? $mes : null,
                'valor' => $valor,
            ];
        }

        return $cuotas === [] ? 'El contrato necesita al menos una cuota en el cronograma.' : $cuotas;
    }

    /** @param array<string, mixed> $datos Los CDP del formulario (filas paralelas). */
    private function guardarCdps(int $contratoId, array $datos): void
    {
        $numeros = is_array($datos['cdp_numero'] ?? null) ? array_values($datos['cdp_numero']) : [];
        $fechas = is_array($datos['cdp_fecha'] ?? null) ? array_values($datos['cdp_fecha']) : [];
        $rubros = is_array($datos['cdp_codigo_rubro'] ?? null) ? array_values($datos['cdp_codigo_rubro']) : [];
        $nombres = is_array($datos['cdp_nombre_rubro'] ?? null) ? array_values($datos['cdp_nombre_rubro']) : [];
        $valores = is_array($datos['cdp_valor'] ?? null) ? array_values($datos['cdp_valor']) : [];

        foreach ($numeros as $i => $numero) {
            $numero = trim((string) $numero);
            $fecha = trim((string) ($fechas[$i] ?? ''));

            if ($numero === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
                continue;
            }

            $this->contratos->agregarImputacion($contratoId, [
                'tipo' => 'CDP',
                'numero' => mb_substr($numero, 0, 30),
                'fecha' => $fecha,
                'codigo_rubro' => mb_substr(trim((string) ($rubros[$i] ?? '')), 0, 40),
                'nombre_rubro' => mb_substr(trim((string) ($nombres[$i] ?? '')), 0, 200),
                'recurso' => '1.2.3.2.09 VENTA DE BIENES Y SERVICIOS',
                'valor' => max(1, (int) ($valores[$i] ?? 0)),
            ]);
        }
    }

    /** @return list<string> */
    private function lineas(string $texto): array
    {
        $lineas = [];

        foreach (preg_split('/\R/', $texto) ?: [] as $linea) {
            $limpia = trim((string) preg_replace('/^\s*\d+[.)]\s*/', '', trim($linea)));

            if ($limpia !== '') {
                $lineas[] = $limpia;
            }
        }

        return $lineas;
    }

    private function texto(string $valor, int $maximo): ?string
    {
        $limpio = trim($valor);

        return $limpio === '' ? null : mb_substr($limpio, 0, $maximo);
    }

    private function volverConError(string $volverA, string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }
}
