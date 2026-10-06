<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Entity\ModalidadPeriodos;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\EvidenciaActividadRepository;
use App\Modules\CuentasCobro\Repository\SupervisorRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\CalculosCuenta;
use App\Modules\CuentasCobro\Service\FechasCuenta;
use App\Modules\CuentasCobro\Service\GeneradorDocumentosCuenta;
use App\Modules\CuentasCobro\Service\SupervisionCuentaService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La bandeja del supervisor (S1 a S4 del documento del módulo): revisar
 * las cuentas enviadas de SUS contratos, marcar cumplimiento, aprobar y
 * firmar —o devolver con motivo—, registrar actas de inicio y descargar
 * los paquetes.
 *
 * La identidad manda: cada acción verifica que el contrato de la cuenta
 * pertenezca al supervisor de la sesión. Un supervisor no ve ni una
 * cuenta ajena (§3, Ley 1581).
 */
final readonly class CuentasSupervisionController
{
    private const string TITULO = 'Cuentas de cobro — Supervisión';
    private const string RUTA = '/admin/cuentas-cobro/bandeja';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private CuentaRepository $cuentas,
        private EvidenciaActividadRepository $evidencias,
        private ContratoRepository $contratos,
        private SupervisorRepository $supervisores,
        private SupervisionCuentaService $supervision,
        private GeneradorDocumentosCuenta $generador,
        private ArchivosCuentas $archivos,
    ) {
    }

    // ------------------------------------------------------------------
    // S1 — Bandeja
    // ------------------------------------------------------------------

    public function bandeja(Request $peticion): Response
    {
        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $pestana = $peticion->query->getString('pestana', 'pendientes');
        $estados = match ($pestana) {
            'devueltas' => ['DEVUELTA'],
            'aprobadas' => ['APROBADA', 'ENTREGADA_FINANCIERA'],
            'todas' => null,
            // Pendientes de verdad: las que ya pasaron la revisión previa.
            // Las ENVIADA siguen donde los revisores.
            default => ['REVISADA'],
        };

        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/bandeja', [
            'supervisor' => $supervisor,
            'pestana' => in_array($pestana, ['pendientes', 'devueltas', 'aprobadas', 'todas'], true) ? $pestana : 'pendientes',
            'cuentas' => $this->cuentas->bandejaDelSupervisor((int) $supervisor['id'], $estados),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /** La firma del supervisor: sin ella no se aprueba nada. */
    public function guardarMiFirma(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $firma = $peticion->files->get('firma');

        if (!$firma instanceof UploadedFile) {
            return $this->volverConError(self::RUTA, 'Seleccione la imagen de su firma (JPG o PNG).');
        }

        try {
            $ruta = $this->archivos->guardar($firma, 'firmas');
        } catch (\InvalidArgumentException $e) {
            return $this->volverConError(self::RUTA, $e->getMessage());
        }

        $this->supervisores->actualizar((int) $supervisor['id'], ['firma_archivo' => $ruta]);
        $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_supervisor', (int) $supervisor['id'], 'Firma del supervisor actualizada');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Su firma quedó registrada.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // S2 — Revisión de una cuenta / S3 — Aprobada
    // ------------------------------------------------------------------

    public function revision(Request $peticion): Response
    {
        $contexto = $this->cuentaDelSupervisor($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$supervisor, $cuenta, $contrato] = $contexto;
        $ss = $this->cuentas->seguridadSocial((int) $cuenta['id']);
        $sugeridos = [];

        if ($ss !== null && is_string($ss['valores_sugeridos_json'] ?? null)) {
            $decodificado = json_decode((string) $ss['valores_sugeridos_json'], true);
            $sugeridos = is_array($decodificado) ? $decodificado : [];
        }

        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/revision', [
            'supervisor' => $supervisor,
            'cuenta' => $cuenta,
            'contrato' => $contrato,
            'actividades' => $this->cuentas->actividades((int) $cuenta['id']),
            'evidencias' => $this->evidencias->porCuenta((int) $cuenta['id']),
            'ss' => $ss,
            'sugeridos' => $sugeridos,
            'adjuntos' => $this->cuentas->adjuntos((int) $cuenta['id']),
            'periodoTexto' => FechasCuenta::periodo(
                FechasCuenta::desdeMysql((string) $cuenta['periodo_desde']),
                FechasCuenta::desdeMysql((string) $cuenta['periodo_hasta']),
            ),
            'tieneFirma' => is_string($supervisor['firma_archivo'] ?? null) && $supervisor['firma_archivo'] !== '',
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /** Vista previa del informe de supervisión que se generará (S2). */
    public function vistaPrevia(Request $peticion): Response
    {
        $contexto = $this->cuentaDelSupervisor($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, $cuenta] = $contexto;
        $documento = $this->generador->generar('INFORME_SUPERVISION', (int) $cuenta['id'], 'BORRADOR: sin valor legal hasta la aprobación.');

        return $this->responderPdf($this->archivos->rutaAbsoluta($documento['ruta']), 'borrador-supervision.pdf', true);
    }

    public function aprobar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $contexto = $this->cuentaDelSupervisor($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$supervisor, $cuenta] = $contexto;
        $marcas = $peticion->request->all()['cumplimiento'] ?? [];
        $marcas = is_array($marcas) ? array_map(strval(...), $marcas) : [];

        $resultado = $this->supervision->aprobar(
            $cuenta,
            $supervisor,
            array_combine(array_map(intval(...), array_keys($marcas)), array_values($marcas)),
            $peticion->request->getString('observacion'),
            trim($peticion->request->getString('fecha_informe')),
            (string) $peticion->getClientIp(),
            $peticion->headers->get('User-Agent'),
        );

        $volverA = '/admin/cuentas-cobro/bandeja/' . (int) $cuenta['id'];

        if (!$resultado['ok']) {
            return $this->volverConError($volverA, $resultado['mensaje']);
        }

        $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_cuenta', (int) $cuenta['id'], 'Cuenta ' . $cuenta['radicado'] . ' aprobada por el supervisor');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', $resultado['mensaje']);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    public function devolver(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $contexto = $this->cuentaDelSupervisor($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$supervisor, $cuenta] = $contexto;
        $resultado = $this->supervision->devolver(
            $cuenta,
            $supervisor,
            $peticion->request->getString('observacion'),
            (string) $peticion->getClientIp(),
        );

        if (!$resultado['ok']) {
            return $this->volverConError('/admin/cuentas-cobro/bandeja/' . (int) $cuenta['id'], $resultado['mensaje']);
        }

        $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_cuenta', (int) $cuenta['id'], 'Cuenta ' . $cuenta['radicado'] . ' devuelta al contratista');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', $resultado['mensaje']);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function marcarEntregada(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $contexto = $this->cuentaDelSupervisor($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$supervisor, $cuenta] = $contexto;
        $resultado = $this->supervision->marcarEntregada($cuenta, $supervisor, (string) $peticion->getClientIp());

        $this->pantalla->guardarEnLaSesion($resultado['ok'] ? 'cc_mensaje' : 'cc_error', $resultado['mensaje']);

        if ($resultado['ok']) {
            $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_cuenta', (int) $cuenta['id'], 'Cuenta ' . $cuenta['radicado'] . ' entregada a Financiera');
        }

        return new RedirectResponse('/admin/cuentas-cobro/bandeja/' . (int) $cuenta['id'], Response::HTTP_SEE_OTHER);
    }

    /** Descarga de un documento de la cuenta (S3). */
    public function descargar(Request $peticion): Response
    {
        $contexto = $this->cuentaDelSupervisor($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, $cuenta] = $contexto;
        $columna = match ((string) $peticion->attributes->get('documento')) {
            'informe' => 'pdf_informe_actividades',
            'supervision' => 'pdf_informe_supervision',
            'paquete' => 'pdf_paquete',
            default => 'pdf_cuenta_cobro',
        };
        $ruta = $cuenta[$columna] ?? null;

        if (!is_string($ruta) || $ruta === '' || !$this->archivos->existe($ruta)) {
            return $this->volverConError('/admin/cuentas-cobro/bandeja/' . (int) $cuenta['id'], 'Ese documento aún no está generado.');
        }

        return $this->responderPdf(
            $this->archivos->rutaAbsoluta($ruta),
            str_replace('pdf_', '', $columna) . '-' . $cuenta['radicado'] . '.pdf',
            false,
        );
    }

    /** Visor de anexos (la planilla PILA y demás) para el supervisor. */
    /**
     * Uno de los documentos del contratista, para quien certifica la
     * cuenta.
     *
     * Son los mismos archivos que administra Contratación; aquí se sirven
     * con el vínculo de esta bandeja, porque quien firma el cumplimiento
     * necesita poder comprobar contra qué firma.
     */
    public function documentoContratista(Request $peticion): Response
    {
        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $contexto = $this->cuentaDelDocumento($peticion);

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contrato, $columna] = $contexto;

        // El documento es de un contratista concreto: solo lo ve el
        // supervisor de ESE contrato, igual que los demás anexos.
        if ((int) ($contrato['supervisor_id'] ?? 0) !== (int) $supervisor['id']) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }
        $ruta = (string) ($contrato[$columna] ?? '');

        if ($ruta === '' || !$this->archivos->existe($ruta)) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta($ruta)), Response::HTTP_OK, [
            'Content-Type' => str_ends_with($ruta, '.pdf') ? 'application/pdf' : 'image/jpeg',
            'Content-Disposition' => 'inline; filename="' . $peticion->attributes->getString('documento') . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * El contrato de la cuenta pedida y la columna del documento, con el
     * vínculo ya comprobado.
     *
     * @return array{0: array<string, mixed>, 1: string}|Response
     */
    private function cuentaDelDocumento(Request $peticion): array|Response
    {
        $columnas = [
            'cedula' => 'cedula_archivo',
            'cedula-reverso' => 'cedula_reverso_archivo',
            'rut' => 'rut_archivo',
            'certificacion-bancaria' => 'certificacion_bancaria_archivo',
            'firma' => 'firma_archivo',
        ];
        $columna = $columnas[$peticion->attributes->getString('documento')] ?? null;
        $cuenta = $this->cuentas->porId($peticion->attributes->getInt('id'));

        if ($columna === null || $cuenta === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $contrato = $this->contratos->porId((int) $cuenta['contrato_id']);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return [$contrato, $columna];
    }

    public function adjunto(Request $peticion): Response
    {
        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $adjunto = $this->cuentas->adjuntoPorId($peticion->attributes->getInt('id'));

        if ($adjunto === null || (int) $adjunto['supervisor_id'] !== (int) $supervisor['id'] || !$this->archivos->existe((string) $adjunto['archivo'])) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta((string) $adjunto['archivo'])), Response::HTTP_OK, [
            'Content-Type' => (string) $adjunto['mime'],
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $adjunto['nombre_original']) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Sirve una evidencia de actividad al supervisor que la debe
     * certificar, y solo a él: la comprobación de que el contrato es suyo
     * va antes de leer el archivo, igual que con los demás anexos.
     */
    public function evidencia(Request $peticion): Response
    {
        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $evidencia = $this->evidencias->porIdConContexto($peticion->attributes->getInt('evidencia'));

        if ($evidencia === null
            || (int) $evidencia['supervisor_id'] !== (int) $supervisor['id']
            || (string) $evidencia['tipo'] !== 'ARCHIVO'
            || !$this->archivos->existe((string) $evidencia['archivo'])) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta((string) $evidencia['archivo'])), Response::HTTP_OK, [
            'Content-Type' => (string) ($evidencia['mime'] ?? 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($evidencia['nombre_original'] ?? 'evidencia')) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ------------------------------------------------------------------
    // S4 — Mis contratos y acta de inicio
    // ------------------------------------------------------------------

    public function misContratos(Request $peticion): Response
    {
        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/mis-contratos', [
            'supervisor' => $supervisor,
            'contratos' => $this->contratos->listarDelSupervisor((int) $supervisor['id']),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /** Registrar el acta de inicio de un contrato propio (S4, §8.1). */
    public function registrarActa(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $contrato = $this->contratos->porId($peticion->attributes->getInt('id'));
        $volverA = '/admin/cuentas-cobro/mis-contratos';

        if ($contrato === null || (int) $contrato['supervisor_id'] !== (int) $supervisor['id']) {
            return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
        }

        $fechaTexto = trim($peticion->request->getString('fecha_acta_inicio'));

        if ($fechaTexto === '') {
            return $this->volverConError($volverA, 'Indique la fecha del acta de inicio.');
        }

        if ($this->contratos->tieneCuentasEnFirme((int) $contrato['id'])) {
            return $this->volverConError($volverA, 'Ya hay cuentas presentadas con este cronograma; registre una novedad en vez de recalcularlo.');
        }

        try {
            $acta = FechasCuenta::desdeMysql($fechaTexto);
        } catch (\InvalidArgumentException) {
            return $this->volverConError($volverA, 'La fecha del acta no es válida.');
        }

        $cuotas = $this->contratos->cuotas((int) $contrato['id']);

        if ($cuotas === []) {
            return $this->volverConError($volverA, 'El contrato no tiene cronograma de pagos; pida a la administración completarlo.');
        }

        $periodos = CalculosCuenta::cronograma($cuotas, ModalidadPeriodos::from((string) $contrato['modalidad_periodos']), $acta);
        $this->contratos->aplicarActaInicio((int) $contrato['id'], $acta, $periodos, CalculosCuenta::fechaTerminacion($periodos));

        $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_contrato', (int) $contrato['id'], 'Acta de inicio del ' . $contrato['numero'] . ' registrada por el supervisor: ' . $fechaTexto);
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Acta registrada. El cronograma quedó con fechas; revíselo.');

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** @return ?array<string, mixed> El supervisor vinculado al usuario de la sesión. */
    private function supervisorActual(): ?array
    {
        $usuarioId = $this->pantalla->usuarioId();

        if ($usuarioId === null) {
            return null;
        }

        $supervisor = $this->supervisores->porUsuarioId($usuarioId);

        return ($supervisor !== null && (int) $supervisor['activo'] === 1) ? $supervisor : null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}|Response
     *         [supervisor, cuenta, contrato] o la salida segura.
     */
    private function cuentaDelSupervisor(int $cuentaId): array|Response
    {
        $supervisor = $this->supervisorActual();

        if ($supervisor === null) {
            return $this->sinVinculo();
        }

        $cuenta = $this->cuentas->porId($cuentaId);

        if ($cuenta === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $contrato = $this->contratos->porId((int) $cuenta['contrato_id']);

        if ($contrato === null || (int) $contrato['supervisor_id'] !== (int) $supervisor['id']) {
            // Cuenta ajena: ni un mensaje que confirme que existe.
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return [$supervisor, $cuenta, $contrato];
    }

    private function sinVinculo(): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/bandeja', [
            'supervisor' => null,
            'pestana' => 'pendientes',
            'cuentas' => [],
            'mensaje' => null,
            'error' => 'Su usuario no está vinculado como supervisor. Pida a la administración vincularlo en Cuentas de cobro → Supervisores.',
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    private function responderPdf(string $rutaAbsoluta, string $nombre, bool $enLinea): Response
    {
        return new Response((string) file_get_contents($rutaAbsoluta), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombre . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function volverConError(string $volverA, string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }
}
