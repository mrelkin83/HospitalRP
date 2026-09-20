<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\OtpService;
use App\Modules\CuentasCobro\Service\SesionContratista;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El portal del contratista: ingreso (P0), inicio (P1) y «Mis datos»
 * (P1a) del documento del módulo.
 *
 * Todo está diseñado para adultos mayores con poca experiencia
 * tecnológica (§2): una tarea por pantalla, un botón grande, lenguaje
 * cotidiano y nada de contraseñas. La identidad es cédula + número de
 * contrato + código de un solo uso.
 */
final readonly class CuentasPortalController
{
    public function __construct(
        private Vistas $vistas,
        private SesionContratista $sesion,
        private ContratistaRepository $contratistas,
        private CuentaRepository $cuentas,
        private OtpService $otp,
        private ArchivosCuentas $archivos,
        private Connection $conexion,
    ) {
    }

    // ------------------------------------------------------------------
    // P0 — Ingreso
    // ------------------------------------------------------------------

    public function ingreso(Request $peticion): Response
    {
        if ($this->sesion->contratoId() !== null) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        return $this->pagina('publico/cuentas/ingreso', [
            'error' => $this->flash('cc_error'),
            'primeraVez' => $this->flash('cc_primera_vez') === '1',
        ]);
    }

    public function solicitarCodigo(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $ip = (string) $peticion->getClientIp();

        if ($this->otp->ipBloqueada($ip)) {
            return $this->volverConError('/cuentas/ingreso', 'Demasiados intentos desde esta conexión. Espere una hora e inténtelo de nuevo.');
        }

        $par = $this->buscarContratoActivo($peticion);

        if ($par === null) {
            return $this->volverConError('/cuentas/ingreso', 'No encontramos un contrato activo con esos datos. Revise el número de contrato en su copia o comuníquese con la oficina de Contratación del hospital.');
        }

        [$contratista, $contrato] = $par;

        // Primer ingreso de un contrato recién activado: sin celular ni
        // correo no hay a dónde enviar el código. Se le permite entrar con
        // la fecha de suscripción, que está en su copia del contrato.
        if (trim((string) ($contratista['correo'] ?? '')) === '' && trim((string) ($contratista['celular'] ?? '')) === '') {
            $fecha = trim($peticion->request->getString('fecha_suscripcion'));

            if ($fecha === '') {
                $this->guardarFlash('cc_primera_vez', '1');

                return $this->volverConError('/cuentas/ingreso', 'Como es su primer ingreso, escriba también la fecha de suscripción que aparece en su contrato.');
            }

            if ($fecha !== (string) $contrato['fecha_suscripcion']) {
                $this->guardarFlash('cc_primera_vez', '1');

                return $this->volverConError('/cuentas/ingreso', 'La fecha no coincide con la del contrato. Revísela en su copia.');
            }

            $this->sesion->abrir((int) $contratista['id'], (int) $contrato['id']);

            return new RedirectResponse('/cuentas/mis-datos', Response::HTTP_SEE_OTHER);
        }

        $resultado = $this->otp->enviar($contratista, $ip);

        if (!$resultado['ok']) {
            return $this->volverConError('/cuentas/ingreso', $resultado['mensaje']);
        }

        $this->sesion->recordarPendiente((int) $contratista['id'], (int) $contrato['id']);

        return $this->pagina('publico/cuentas/codigo', [
            'mensaje' => $resultado['mensaje'],
            'error' => null,
        ]);
    }

    public function verificarCodigo(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $pendiente = $this->sesion->pendiente();

        if ($pendiente === null) {
            return $this->volverConError('/cuentas/ingreso', 'Su código venció. Pida uno nuevo.');
        }

        [$contratistaId, $contratoId] = $pendiente;

        if (!$this->otp->verificar($contratistaId, trim($peticion->request->getString('codigo')))) {
            return $this->pagina('publico/cuentas/codigo', [
                'mensaje' => null,
                'error' => 'El código no coincide o ya venció. Revíselo o pida uno nuevo.',
            ]);
        }

        $this->sesion->abrir($contratistaId, $contratoId);
        $contratista = $this->contratistas->porId($contratistaId);

        $destino = ($contratista !== null && (int) $contratista['datos_completos'] === 1)
            ? '/cuentas/inicio'
            : '/cuentas/mis-datos';

        return new RedirectResponse($destino, Response::HTTP_SEE_OTHER);
    }

    public function salir(Request $peticion): Response
    {
        $this->sesion->cerrar();

        return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // P1 — Inicio del contratista
    // ------------------------------------------------------------------

    public function inicio(Request $peticion): Response
    {
        $contexto = $this->contextoAutenticado();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;

        if ((int) $contratista['datos_completos'] !== 1) {
            return new RedirectResponse('/cuentas/mis-datos', Response::HTTP_SEE_OTHER);
        }

        $contratoId = (int) $contrato['id'];

        return $this->pagina('publico/cuentas/inicio', [
            'contratista' => $contratista,
            'contrato' => $contrato,
            'editable' => $this->cuentas->editableDelContrato($contratoId),
            'enRevision' => $this->cuentas->enRevisionDelContrato($contratoId),
            'cuotaDisponible' => $this->cuentas->proximaCuotaDisponible($contratoId),
            'cuotaFutura' => $this->cuentas->proximaCuotaFutura($contratoId),
            'anteriores' => $this->cuentas->delContrato($contratoId),
            'mensaje' => $this->flash('cc_mensaje'),
            'error' => $this->flash('cc_error'),
        ]);
    }

    // ------------------------------------------------------------------
    // P1a — Mis datos (obligatoria mientras datos_completos = 0)
    // ------------------------------------------------------------------

    public function misDatos(Request $peticion): Response
    {
        $contexto = $this->contextoAutenticado();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista] = $contexto;
        $paso = max(1, min(3, $peticion->query->getInt('paso', 1)));

        return $this->pagina('publico/cuentas/mis-datos', [
            'contratista' => $contratista,
            'paso' => $paso,
            'error' => $this->flash('cc_error'),
            'mensaje' => $this->flash('cc_mensaje'),
        ]);
    }

    public function guardarMisDatos(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contextoAutenticado();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista] = $contexto;
        $contratistaId = (int) $contratista['id'];
        $paso = max(1, min(3, $peticion->attributes->getInt('paso')));
        $r = $peticion->request;

        if ($paso === 1) {
            $celular = trim($r->getString('celular'));
            $correo = trim($r->getString('correo'));

            if (preg_match('/^3\d{9}$/', $celular) !== 1) {
                return $this->volverConError('/cuentas/mis-datos?paso=1', 'El celular debe tener 10 números y empezar por 3, por ejemplo 3001234567.');
            }

            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                return $this->volverConError('/cuentas/mis-datos?paso=1', 'Ese correo no parece completo. Revíselo, por ejemplo: nombre@correo.com.');
            }

            $this->contratistas->actualizar($contratistaId, [
                'celular' => $celular,
                'correo' => $correo !== '' ? $correo : null,
                'direccion' => trim($r->getString('direccion')) !== '' ? mb_substr(trim($r->getString('direccion')), 0, 200) : null,
            ]);

            return new RedirectResponse('/cuentas/mis-datos?paso=2', Response::HTTP_SEE_OTHER);
        }

        if ($paso === 2) {
            $banco = trim($r->getString('banco'));
            $numeroCuenta = trim($r->getString('numero_cuenta'));
            $tipoCuenta = $r->getString('tipo_cuenta');

            if ($banco === '' || $numeroCuenta === '' || !in_array($tipoCuenta, ['AHORROS', 'CORRIENTE'], true)) {
                return $this->volverConError('/cuentas/mis-datos?paso=2', 'Complete el banco, el tipo de cuenta y el número de cuenta: ahí le consignan su pago.');
            }

            $campos = [
                'banco' => mb_substr($banco, 0, 100),
                'tipo_cuenta' => $tipoCuenta,
                'numero_cuenta' => mb_substr($numeroCuenta, 0, 40),
            ];

            $certificacion = $peticion->files->get('certificacion_bancaria');

            if ($certificacion instanceof UploadedFile) {
                try {
                    $campos['certificacion_bancaria_archivo'] = $this->archivos->guardar($certificacion, 'contratistas');
                } catch (\InvalidArgumentException $e) {
                    return $this->volverConError('/cuentas/mis-datos?paso=2', $e->getMessage());
                }
            }

            $this->contratistas->actualizar($contratistaId, $campos);

            return new RedirectResponse('/cuentas/mis-datos?paso=3', Response::HTTP_SEE_OTHER);
        }

        return $this->guardarDocumentosYFirma($peticion, $contratista);
    }

    /**
     * Paso 3 de «Mis datos»: cédula, RUT, firma (subida o dibujada) y la
     * autorización de tratamiento de datos con fecha e IP (Ley 1581).
     *
     * @param array<string, mixed> $contratista
     */
    private function guardarDocumentosYFirma(Request $peticion, array $contratista): Response
    {
        $contratistaId = (int) $contratista['id'];
        $volverA = '/cuentas/mis-datos?paso=3';
        $campos = [];

        foreach (['cedula_archivo' => 'cedula', 'rut_archivo' => 'rut'] as $columna => $campo) {
            $archivo = $peticion->files->get($campo);

            if ($archivo instanceof UploadedFile) {
                try {
                    $campos[$columna] = $this->archivos->guardar($archivo, 'contratistas');
                } catch (\InvalidArgumentException $e) {
                    return $this->volverConError($volverA, $e->getMessage());
                }
            }
        }

        $firmaSubida = $peticion->files->get('firma');
        $firmaDibujada = trim($peticion->request->getString('firma_dibujada'));

        try {
            if ($firmaSubida instanceof UploadedFile) {
                $campos['firma_archivo'] = $this->archivos->guardar($firmaSubida, 'firmas');
                $campos['firma_cargada_en'] = date('Y-m-d H:i:s');
            } elseif ($firmaDibujada !== '') {
                $campos['firma_archivo'] = $this->archivos->guardarFirmaDibujada($firmaDibujada, 'firmas');
                $campos['firma_cargada_en'] = date('Y-m-d H:i:s');
            }
        } catch (\InvalidArgumentException $e) {
            return $this->volverConError($volverA, $e->getMessage());
        }

        // La autorización de datos personales: casilla NO premarcada, con
        // evidencia de fecha e IP (CLAUDE.md §6 y §3 del módulo).
        if ($contratista['acepto_tratamiento_datos_en'] === null) {
            if (!$peticion->request->getBoolean('acepto_datos')) {
                return $this->volverConError($volverA, 'Para continuar debe leer y aceptar la autorización de tratamiento de datos personales.');
            }

            $campos['acepto_tratamiento_datos_en'] = date('Y-m-d H:i:s');
            $campos['acepto_tratamiento_datos_ip'] = (string) $peticion->getClientIp();
        }

        if ($campos !== []) {
            $this->contratistas->actualizar($contratistaId, $campos);
        }

        // ¿Ya está todo? Contacto, banco, firma y los anexos fijos.
        $al_dia = $this->contratistas->porId($contratistaId);

        if ($al_dia !== null
            && $al_dia['celular'] !== null
            && $al_dia['banco'] !== null
            && $al_dia['firma_archivo'] !== null
            && $al_dia['cedula_archivo'] !== null
            && $al_dia['rut_archivo'] !== null
            && $al_dia['acepto_tratamiento_datos_en'] !== null) {
            $this->contratistas->actualizar($contratistaId, ['datos_completos' => 1]);
            $this->guardarFlash('cc_mensaje', 'Sus datos quedaron completos. Ya puede presentar su cuenta de cobro.');

            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        return $this->volverConError($volverA, 'Falta algo: revise que estén la foto de su cédula, el RUT y su firma.');
    }

    // ------------------------------------------------------------------
    // Ayudas compartidas del portal
    // ------------------------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|Response
     *         [contratista, contrato] o la redirección al ingreso.
     */
    private function contextoAutenticado(): array|Response
    {
        $contratistaId = $this->sesion->contratistaId();
        $contratoId = $this->sesion->contratoId();

        if ($contratistaId === null || $contratoId === null) {
            return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
        }

        $contratista = $this->contratistas->porId($contratistaId);
        $contrato = $this->conexion->fetchAssociative(
            "SELECT k.*, s.nombre_completo AS supervisor_nombre
               FROM cc_contratos k
               LEFT JOIN cc_supervisores s ON s.id = k.supervisor_id
              WHERE k.id = ? AND k.contratista_id = ? AND k.estado IN ('ACTIVO', 'SUSPENDIDO')",
            [$contratoId, $contratistaId],
        );

        if ($contratista === null || (int) $contratista['activo'] !== 1 || $contrato === false) {
            $this->sesion->cerrar();

            return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
        }

        return [$contratista, $contrato];
    }

    /** @return ?array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function buscarContratoActivo(Request $peticion): ?array
    {
        $cedula = preg_replace('/\D/', '', $peticion->request->getString('cedula')) ?? '';
        $numeroContrato = trim($peticion->request->getString('contrato'));

        if ($cedula === '' || $numeroContrato === '') {
            return null;
        }

        $contratista = $this->contratistas->porDocumento($cedula);

        if ($contratista === null || (int) $contratista['activo'] !== 1) {
            return null;
        }

        $contrato = $this->conexion->fetchAssociative(
            "SELECT * FROM cc_contratos WHERE numero = ? AND contratista_id = ? AND estado = 'ACTIVO'",
            [$numeroContrato, (int) $contratista['id']],
        );

        return $contrato === false ? null : [$contratista, $contrato];
    }

    /** @param array<string, mixed> $datos */
    private function pagina(string $vista, array $datos): Response
    {
        return new Response($this->vistas->render($vista, $datos + [
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    private function rechazoCsrf(Request $peticion): ?Response
    {
        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->volverConError('/cuentas/ingreso', 'Su sesión expiró. Vuelva a empezar.');
    }

    private function volverConError(string $volverA, string $mensaje): RedirectResponse
    {
        $this->guardarFlash('cc_error', $mensaje);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    private function guardarFlash(string $clave, string $valor): void
    {
        $_SESSION['_flash'][$clave] = $valor;
    }

    private function flash(string $clave): ?string
    {
        $valor = $_SESSION['_flash'][$clave] ?? null;
        unset($_SESSION['_flash'][$clave]);

        return is_string($valor) ? $valor : null;
    }
}
