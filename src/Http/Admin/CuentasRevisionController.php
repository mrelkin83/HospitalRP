<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\EvidenciaActividadRepository;
use App\Modules\CuentasCobro\Repository\RevisorRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\FechasCuenta;
use App\Modules\CuentasCobro\Service\RevisionCuentaService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La bandeja de los revisores: el paso previo al supervisor. Los cuatro
 * revisores comparten UNA cola con todas las cuentas ENVIADAS —de
 * cualquier contrato—; verifican la información, la firman (REVISADA) o
 * la devuelven al contratista con el motivo.
 *
 * Solo pide el permiso cuentas.revisar: quien únicamente sea revisor no
 * ve ninguna otra pantalla del panel (el menú se filtra solo).
 */
final readonly class CuentasRevisionController
{
    private const string TITULO = 'Cuentas de cobro — Revisión';
    private const string RUTA = '/admin/cuentas-cobro/revision';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private CuentaRepository $cuentas,
        private EvidenciaActividadRepository $evidencias,
        private ContratoRepository $contratos,
        private RevisorRepository $revisores,
        private RevisionCuentaService $revision,
        private ArchivosCuentas $archivos,
    ) {
    }

    public function bandeja(Request $peticion): Response
    {
        $revisor = $this->revisorActual();

        if ($revisor === null) {
            return $this->sinVinculo();
        }

        $pestana = $peticion->query->getString('pestana', 'pendientes');
        $estados = match ($pestana) {
            'revisadas' => ['REVISADA'],
            'devueltas' => ['DEVUELTA'],
            'todas' => null,
            default => ['ENVIADA'],
        };

        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/revision-bandeja', [
            'revisor' => $revisor,
            'pestana' => in_array($pestana, ['pendientes', 'revisadas', 'devueltas', 'todas'], true) ? $pestana : 'pendientes',
            'cuentas' => $this->cuentas->bandejaDeRevision($estados),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /** La firma del revisor: sin ella no se marca nada como revisado. */
    public function guardarMiFirma(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $revisor = $this->revisorActual();

        if ($revisor === null) {
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

        $this->revisores->actualizar((int) $revisor['id'], ['firma_archivo' => $ruta]);
        $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_revisor', (int) $revisor['id'], 'Firma del revisor actualizada');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Su firma quedó registrada.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function detalle(Request $peticion): Response
    {
        $contexto = $this->cuentaParaRevisar($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$revisor, $cuenta, $contrato] = $contexto;
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/revision-detalle', [
            'revisor' => $revisor,
            'cuenta' => $cuenta,
            'contrato' => $contrato,
            'actividades' => $this->cuentas->actividades((int) $cuenta['id']),
            'evidencias' => $this->evidencias->porCuenta((int) $cuenta['id']),
            'ss' => $this->cuentas->seguridadSocial((int) $cuenta['id']),
            'adjuntos' => $this->cuentas->adjuntos((int) $cuenta['id']),
            'periodoTexto' => FechasCuenta::periodo(
                FechasCuenta::desdeMysql((string) $cuenta['periodo_desde']),
                FechasCuenta::desdeMysql((string) $cuenta['periodo_hasta']),
            ),
            'tieneFirma' => is_string($revisor['firma_archivo'] ?? null) && $revisor['firma_archivo'] !== '',
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function marcarRevisada(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $contexto = $this->cuentaParaRevisar($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$revisor, $cuenta] = $contexto;
        $resultado = $this->revision->marcarRevisada(
            $cuenta,
            $revisor,
            $peticion->request->getString('observacion'),
            (string) $peticion->getClientIp(),
            $peticion->headers->get('User-Agent'),
        );

        if (!$resultado['ok']) {
            return $this->volverConError(self::RUTA . '/' . (int) $cuenta['id'], $resultado['mensaje']);
        }

        $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_cuenta', (int) $cuenta['id'], 'Cuenta ' . $cuenta['radicado'] . ' revisada y firmada');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', $resultado['mensaje']);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function devolver(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $contexto = $this->cuentaParaRevisar($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$revisor, $cuenta] = $contexto;
        $resultado = $this->revision->devolver(
            $cuenta,
            $revisor,
            $peticion->request->getString('observacion'),
            (string) $peticion->getClientIp(),
        );

        if (!$resultado['ok']) {
            return $this->volverConError(self::RUTA . '/' . (int) $cuenta['id'], $resultado['mensaje']);
        }

        $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_cuenta', (int) $cuenta['id'], 'Cuenta ' . $cuenta['radicado'] . ' devuelta desde la revisión');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', $resultado['mensaje']);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    /** Los PDF que firmó el contratista, para verificarlos. */
    public function descargar(Request $peticion): Response
    {
        $contexto = $this->cuentaParaRevisar($peticion->attributes->getInt('id'));

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, $cuenta] = $contexto;
        $columna = (string) $peticion->attributes->get('documento') === 'informe'
            ? 'pdf_informe_actividades'
            : 'pdf_cuenta_cobro';
        $ruta = $cuenta[$columna] ?? null;

        if (!is_string($ruta) || $ruta === '' || !$this->archivos->existe($ruta)) {
            return $this->volverConError(self::RUTA . '/' . (int) $cuenta['id'], 'Ese documento aún no está generado.');
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta($ruta)), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . str_replace('pdf_', '', $columna) . '-' . $cuenta['radicado'] . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Visor de anexos (la planilla PILA y demás) para el revisor. */
    /**
     * La evidencia de una actividad, para el revisor que la va a
     * certificar. Mismo control que los demás anexos: sin vínculo de
     * revisor no se sirve el archivo.
     */
    public function evidencia(Request $peticion): Response
    {
        if ($this->revisorActual() === null) {
            return $this->sinVinculo();
        }

        $evidencia = $this->evidencias->porIdConContexto($peticion->attributes->getInt('evidencia'));

        if ($evidencia === null
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
        if ($this->revisorActual() === null) {
            return $this->sinVinculo();
        }

        $contexto = $this->cuentaDelDocumento($peticion);

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contrato, $columna] = $contexto;
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
        $revisor = $this->revisorActual();

        if ($revisor === null) {
            return $this->sinVinculo();
        }

        $adjunto = $this->cuentas->adjuntoPorId($peticion->attributes->getInt('id'));

        if ($adjunto === null || !$this->archivos->existe((string) $adjunto['archivo'])) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta((string) $adjunto['archivo'])), Response::HTTP_OK, [
            'Content-Type' => (string) $adjunto['mime'],
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $adjunto['nombre_original']) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** @return ?array<string, mixed> */
    private function revisorActual(): ?array
    {
        $usuarioId = $this->pantalla->usuarioId();

        if ($usuarioId === null) {
            return null;
        }

        $revisor = $this->revisores->porUsuarioId($usuarioId);

        return ($revisor !== null && (int) $revisor['activo'] === 1) ? $revisor : null;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}|Response */
    private function cuentaParaRevisar(int $cuentaId): array|Response
    {
        $revisor = $this->revisorActual();

        if ($revisor === null) {
            return $this->sinVinculo();
        }

        $cuenta = $this->cuentas->porId($cuentaId);

        if ($cuenta === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $contrato = $this->contratos->porId((int) $cuenta['contrato_id']);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        return [$revisor, $cuenta, $contrato];
    }

    private function sinVinculo(): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/revision-bandeja', [
            'revisor' => null,
            'pestana' => 'pendientes',
            'cuentas' => [],
            'mensaje' => null,
            'error' => null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    private function volverConError(string $volverA, string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }
}
