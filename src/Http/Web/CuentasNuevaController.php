<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\CalculosCuenta;
use App\Modules\CuentasCobro\Service\EnvioCuentaService;
use App\Modules\CuentasCobro\Service\FechasCuenta;
use App\Modules\CuentasCobro\Service\GeneradorDocumentosCuenta;
use App\Modules\CuentasCobro\Service\NumeroALetras;
use App\Modules\CuentasCobro\Service\SesionContratista;
use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los cuatro pasos de presentar una cuenta de cobro (P2 a P6 del
 * documento del módulo): confirmar datos, contar qué hizo, reportar la
 * planilla y revisar y enviar.
 *
 * El contratista escribe lo mínimo: el valor, el periodo, el consecutivo
 * y los aportes sugeridos los pone el sistema. El borrador se guarda solo
 * (autoguardado): cerrar el navegador a mitad de camino no pierde nada.
 */
final readonly class CuentasNuevaController
{
    public function __construct(
        private Vistas $vistas,
        private SesionContratista $sesion,
        private ContratistaRepository $contratistas,
        private CuentaRepository $cuentas,
        private ParametrosCuentasRepository $parametros,
        private ArchivosCuentas $archivos,
        private GeneradorDocumentosCuenta $generador,
        private EnvioCuentaService $envios,
        private NotificadorCorreo $correo,
        private Connection $conexion,
    ) {
    }

    // ------------------------------------------------------------------
    // P2 — Sus datos del contrato
    // ------------------------------------------------------------------

    public function paso1(Request $peticion): Response
    {
        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;
        $cuota = $this->cuentas->editableDelContrato((int) $contrato['id']) === null
            ? $this->cuentas->proximaCuotaDisponible((int) $contrato['id'])
            : null;
        $editable = $this->cuentas->editableDelContrato((int) $contrato['id']);

        if ($editable !== null) {
            // Ya hay un borrador: esta pantalla solo confirma datos, así
            // que se salta directo a donde iba.
            return new RedirectResponse('/cuentas/nueva/2', Response::HTTP_SEE_OTHER);
        }

        if ($cuota === null) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        return $this->pagina('publico/cuentas/paso1', [
            'contratista' => $contratista,
            'contrato' => $contrato,
            'cuota' => $cuota,
            'imputaciones' => $this->conexion->fetchAllAssociative(
                'SELECT tipo, numero FROM cc_contrato_imputaciones WHERE contrato_id = ? ORDER BY tipo, id',
                [(int) $contrato['id']],
            ),
            'error' => $this->flash('cc_error'),
        ]);
    }

    public function confirmarPaso1(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, $contrato] = $contexto;
        $contratoId = (int) $contrato['id'];

        if ($this->cuentas->editableDelContrato($contratoId) === null) {
            $cuota = $this->cuentas->proximaCuotaDisponible($contratoId);

            if ($cuota === null) {
                return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
            }

            $this->cuentas->crearBorrador(
                $contratoId,
                $cuota,
                NumeroALetras::pesos(ContratoRepository::pesos((string) $cuota['valor'])),
            );
        }

        return new RedirectResponse('/cuentas/nueva/2', Response::HTTP_SEE_OTHER);
    }

    /** «Algo está mal»: aviso al hospital sin bloquear al contratista. */
    public function reportarProblema(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;
        $detalle = mb_substr(trim($peticion->request->getString('detalle')), 0, 1000);

        if ($detalle !== '') {
            $destinatario = $this->conexion->fetchOne(
                'SELECT correo FROM usuarios WHERE activo = 1 AND eliminado_en IS NULL ORDER BY id LIMIT 1',
            );

            if (is_string($destinatario) && $destinatario !== '') {
                try {
                    $this->correo->enviar(
                        $destinatario,
                        'Un contratista reporta un dato errado en su contrato ' . $contrato['numero'],
                        'Contratista: ' . $contratista['nombre_completo'] . ' (' . $contratista['numero_documento'] . ")\n"
                        . 'Contrato: ' . $contrato['numero'] . "\n\nLo que reporta:\n" . $detalle,
                    );
                } catch (\Throwable) {
                    // El reporte no bloquea al contratista ni con el correo caído.
                }
            }
        }

        $this->guardarFlash('cc_mensaje', 'Gracias por avisar. La oficina revisará el dato; usted puede continuar con su cuenta.');

        return new RedirectResponse('/cuentas/nueva/1', Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // P3 — Qué hizo este mes
    // ------------------------------------------------------------------

    public function paso2(Request $peticion): Response
    {
        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, $contrato, $cuenta] = $contexto;
        $actividades = $this->cuentas->actividades((int) $cuenta['id']);

        return $this->pagina('publico/cuentas/paso2', [
            'contrato' => $contrato,
            'cuenta' => $cuenta,
            'actividades' => $actividades,
            // Sin obligaciones cargadas (minuta inexistente o ilegible para
            // el OCR), las actividades se escriben a mano.
            'modoManual' => $this->esModoManual($actividades),
            'periodoTexto' => $this->periodoTexto($cuenta),
            'error' => $this->flash('cc_error'),
            'mensaje' => $this->flash('cc_mensaje'),
        ]);
    }

    /** @param list<array<string, mixed>> $actividades */
    private function esModoManual(array $actividades): bool
    {
        foreach ($actividades as $actividad) {
            if ($actividad['obligacion_id'] !== null) {
                return false;
            }
        }

        return true;
    }

    public function guardarPaso2(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, , $cuenta] = $contexto;
        $cuentaId = (int) $cuenta['id'];

        if ($this->esModoManual($this->cuentas->actividades($cuentaId))) {
            return $this->guardarPaso2Manual($peticion, $cuentaId);
        }

        $textos = $peticion->request->all()['actividad'] ?? [];
        $vacias = [];

        foreach ($this->cuentas->actividades($cuentaId) as $actividad) {
            $texto = trim((string) ($textos[(int) $actividad['obligacion_id']] ?? ''));
            $this->cuentas->guardarActividad($cuentaId, (int) $actividad['obligacion_id'], $texto === '' ? null : $texto);

            if ($texto !== '' && mb_strlen($texto) < 20) {
                return $this->volverConError('/cuentas/nueva/2', 'La descripción de la obligación ' . $actividad['orden'] . ' es muy corta: cuente qué hizo, con al menos una frase completa.');
            }

            if ($texto === '') {
                $vacias[] = (int) $actividad['orden'];
            }
        }

        // Dejar obligaciones sin descripción se permite, pero a
        // conciencia: el supervisor lo verá (§7.1 P3).
        if ($vacias !== [] && !$peticion->request->getBoolean('continuar_con_vacias')) {
            $this->guardarFlash('cc_error', 'Dejó sin descripción la obligación ' . implode(', la ', $vacias) . '. Si de verdad no hizo nada frente a ella este periodo, marque la casilla de abajo y continúe.');
            $this->guardarFlash('cc_confirmar_vacias', '1');

            return new RedirectResponse('/cuentas/nueva/2', Response::HTTP_SEE_OTHER);
        }

        return new RedirectResponse('/cuentas/nueva/3', Response::HTTP_SEE_OTHER);
    }

    /**
     * El paso 2 sin obligaciones: las actividades las escribe la persona.
     * Fila con título vacío = quitarla; filas nuevas con título = crearlas.
     */
    private function guardarPaso2Manual(Request $peticion, int $cuentaId): Response
    {
        $r = $peticion->request->all();
        $existentes = is_array($r['actividad_manual'] ?? null) ? $r['actividad_manual'] : [];
        $nuevas = is_array($r['nueva_actividad'] ?? null) ? $r['nueva_actividad'] : [];

        foreach ($this->cuentas->actividades($cuentaId) as $actividad) {
            $id = (int) $actividad['id'];
            $fila = $existentes[$id] ?? null;

            if (!is_array($fila)) {
                continue;
            }

            $titulo = trim((string) ($fila['titulo'] ?? ''));

            if ($titulo === '') {
                $this->cuentas->eliminarActividadManual($cuentaId, $id);

                continue;
            }

            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $this->cuentas->actualizarActividadManual($cuentaId, $id, $titulo, $descripcion === '' ? null : mb_substr($descripcion, 0, 5000));
        }

        foreach ($nuevas as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $titulo = trim((string) ($fila['titulo'] ?? ''));

            if ($titulo === '') {
                continue;
            }

            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $this->cuentas->crearActividadManual($cuentaId, $titulo, $descripcion === '' ? null : mb_substr($descripcion, 0, 5000));
        }

        $quedan = $this->cuentas->actividades($cuentaId);

        if ($quedan === []) {
            return $this->volverConError('/cuentas/nueva/2', 'Agregue al menos una actividad. Este contrato no tiene obligaciones cargadas, así que aquí se escriben a mano: qué se comprometió a hacer y qué hizo este periodo.');
        }

        $vacias = [];

        foreach ($quedan as $i => $actividad) {
            $texto = trim((string) ($actividad['descripcion_ejecucion'] ?? ''));

            if ($texto !== '' && mb_strlen($texto) < 20) {
                return $this->volverConError('/cuentas/nueva/2', 'La descripción de la actividad ' . ($i + 1) . ' es muy corta: cuente qué hizo, con al menos una frase completa.');
            }

            if ($texto === '') {
                $vacias[] = $i + 1;
            }
        }

        if ($vacias !== [] && !$peticion->request->getBoolean('continuar_con_vacias')) {
            $this->guardarFlash('cc_error', 'Dejó sin descripción la actividad ' . implode(', la ', $vacias) . '. Si de verdad no hizo nada frente a ella este periodo, marque la casilla de abajo y continúe.');
            $this->guardarFlash('cc_confirmar_vacias', '1');

            return new RedirectResponse('/cuentas/nueva/2', Response::HTTP_SEE_OTHER);
        }

        return new RedirectResponse('/cuentas/nueva/3', Response::HTTP_SEE_OTHER);
    }

    /** Autoguardado silencioso cada pocos segundos (fetch desde el portal). */
    public function autoguardar(Request $peticion): Response
    {
        if (!$this->sesion->validarCsrf((string) $peticion->headers->get('X-Token-CSRF', ''))) {
            return new JsonResponse(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return new JsonResponse(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        [, , $cuenta] = $contexto;
        $datos = json_decode((string) $peticion->getContent(), true);

        if (!is_array($datos) || !isset($datos['obligacion_id'], $datos['texto'])) {
            return new JsonResponse(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $obligacionId = (int) $datos['obligacion_id'];
        $texto = trim((string) $datos['texto']);
        $conocidas = array_column($this->cuentas->actividades((int) $cuenta['id']), 'obligacion_id');

        if (!in_array($obligacionId, array_map(intval(...), $conocidas), true)) {
            return new JsonResponse(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $this->cuentas->guardarActividad((int) $cuenta['id'], $obligacionId, $texto === '' ? null : mb_substr($texto, 0, 5000));

        return new JsonResponse(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // P4 — Su planilla de seguridad social
    // ------------------------------------------------------------------

    public function paso3(Request $peticion): Response
    {
        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato, $cuenta] = $contexto;
        $guardada = $this->cuentas->seguridadSocial((int) $cuenta['id']);
        $sugeridos = $this->sugeridos($contratista, $contrato, $cuenta);

        return $this->pagina('publico/cuentas/paso3', [
            'contrato' => $contrato,
            'cuenta' => $cuenta,
            'guardada' => $guardada,
            'sugeridos' => $sugeridos,
            'mesSugerido' => CalculosCuenta::mesPlanillaSugerido(FechasCuenta::desdeMysql((string) $cuenta['periodo_hasta'])),
            'planilla' => $this->cuentas->adjuntos((int) $cuenta['id'], 'PLANILLA_PILA'),
            'error' => $this->flash('cc_error'),
        ]);
    }

    public function guardarPaso3(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato, $cuenta] = $contexto;
        $cuentaId = (int) $cuenta['id'];
        $r = $peticion->request;
        $volverA = '/cuentas/nueva/3';

        $numeroPlanilla = preg_replace('/\D/', '', $r->getString('numero_planilla')) ?? '';

        if ($numeroPlanilla === '') {
            return $this->volverConError($volverA, 'Escriba el número de su planilla PILA: aparece en el comprobante de pago.');
        }

        foreach (['mes_salud', 'mes_pension'] as $campo) {
            if (preg_match('/^\d{4}-\d{2}$/', $r->getString($campo)) !== 1) {
                return $this->volverConError($volverA, 'Elija el mes de salud y el mes de pensión de su planilla.');
            }
        }

        $valores = [];

        foreach (['ibc', 'aporte_salud', 'aporte_pension', 'aporte_arl'] as $campo) {
            $valor = $r->getInt($campo);

            if ($valor <= 0) {
                return $this->volverConError($volverA, 'Los valores de la planilla van en pesos, sin puntos ni comas, y no pueden ser cero.');
            }

            $valores[$campo] = $valor;
        }

        $sugeridos = $this->sugeridos($contratista, $contrato, $cuenta);

        $this->cuentas->guardarSeguridadSocial($cuentaId, $valores + [
            'numero_planilla' => mb_substr($numeroPlanilla, 0, 30),
            'mes_salud' => $r->getString('mes_salud'),
            'mes_pension' => $r->getString('mes_pension'),
            'aporte_fsp' => max(0, $r->getInt('aporte_fsp')),
            'tarifa_arl' => $sugeridos['tarifa'],
            'total_aportes' => $valores['aporte_salud'] + $valores['aporte_pension'] + max(0, $r->getInt('aporte_fsp')) + $valores['aporte_arl'],
            'valores_sugeridos_json' => json_encode($sugeridos, JSON_THROW_ON_ERROR),
        ]);

        $planilla = $peticion->files->get('planilla');

        if ($planilla instanceof UploadedFile) {
            try {
                $ruta = $this->archivos->guardar($planilla, 'adjuntos');
            } catch (\InvalidArgumentException $e) {
                return $this->volverConError($volverA, $e->getMessage());
            }

            $this->cuentas->agregarAdjunto($cuentaId, [
                'tipo' => 'PLANILLA_PILA',
                'nombre_original' => mb_substr((string) $planilla->getClientOriginalName(), 0, 255),
                'archivo' => $ruta,
                'mime' => (string) $planilla->getMimeType(),
                'tamano' => (int) $planilla->getSize(),
                'subido_por_contratista' => (int) $contratista['id'],
            ]);
        }

        if ($this->cuentas->adjuntos($cuentaId, 'PLANILLA_PILA') === []) {
            return $this->volverConError($volverA, 'Falta subir su planilla PILA (una foto clara o el PDF del comprobante).');
        }

        return new RedirectResponse('/cuentas/nueva/4', Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // P5 — Revisar y enviar / P6 — Enviada
    // ------------------------------------------------------------------

    public function paso4(Request $peticion): Response
    {
        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato, $cuenta] = $contexto;

        return $this->pagina('publico/cuentas/paso4', [
            'contratista' => $contratista,
            'contrato' => $contrato,
            'cuenta' => $cuenta,
            'periodoTexto' => $this->periodoTexto($cuenta),
            'tieneFirma' => is_string($contratista['firma_archivo'] ?? null) && $contratista['firma_archivo'] !== '',
            'error' => $this->flash('cc_error'),
        ]);
    }

    /** El PDF de borrador que alimenta la vista previa embebida de P5. */
    public function vistaPrevia(Request $peticion): Response
    {
        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, , $cuenta] = $contexto;
        $documento = $peticion->attributes->get('documento') === 'informe' ? 'INFORME_ACTIVIDADES' : 'CUENTA_COBRO';
        $resultado = $this->generador->generar($documento, (int) $cuenta['id'], 'BORRADOR: sin valor legal hasta el envío.');

        return $this->responderPdf($this->archivos->rutaAbsoluta($resultado['ruta']), 'borrador.pdf');
    }

    public function enviar(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contextoConCuenta();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, , $cuenta] = $contexto;

        if (!$peticion->request->getBoolean('confirmo')) {
            return $this->volverConError('/cuentas/nueva/4', 'Para enviar debe marcar la casilla que confirma que la información es verdadera.');
        }

        $resultado = $this->envios->enviar(
            $cuenta,
            $contratista,
            (string) $peticion->getClientIp(),
            $peticion->headers->get('User-Agent'),
        );

        if (!$resultado['ok']) {
            return $this->volverConError('/cuentas/nueva/4', $resultado['mensaje']);
        }

        return new RedirectResponse('/cuentas/enviada/' . (int) $cuenta['id'], Response::HTTP_SEE_OTHER);
    }

    public function enviada(Request $peticion): Response
    {
        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;
        $cuenta = $this->cuentas->porId($peticion->attributes->getInt('id'));

        if ($cuenta === null || (int) $cuenta['contrato_id'] !== (int) $contrato['id']) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        // El enlace de WhatsApp lleva un token de un solo uso a 7 días; el
        // mensaje no adjunta el PDF (§9).
        $token = $this->cuentas->crearTokenDescarga((int) $cuenta['id'], 'CUENTA_COBRO');
        $enlaceDescarga = $peticion->getSchemeAndHttpHost() . '/cuentas/descarga/' . $token;
        $textoWhatsapp = 'Cuenta de cobro N° ' . (int) $cuenta['consecutivo'] . ' del contrato '
            . $contrato['numero'] . '. Radicado ' . (string) $cuenta['radicado']
            . '. Descargar (enlace de un solo uso): ' . $enlaceDescarga;

        return $this->pagina('publico/cuentas/enviada', [
            'contratista' => $contratista,
            'contrato' => $contrato,
            'cuenta' => $cuenta,
            'enlaceWhatsapp' => 'https://wa.me/?text=' . rawurlencode($textoWhatsapp),
            'mensaje' => $this->flash('cc_mensaje'),
            'error' => $this->flash('cc_error'),
        ]);
    }

    /** «Enviar a mi correo»: los enlaces viajan con token, no el archivo. */
    public function enviarACorreo(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;
        $cuenta = $this->cuentas->porId($peticion->attributes->getInt('id'));

        if ($cuenta === null || (int) $cuenta['contrato_id'] !== (int) $contrato['id']) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        $correo = trim((string) ($contratista['correo'] ?? ''));

        if ($correo === '') {
            return $this->volverConError('/cuentas/enviada/' . (int) $cuenta['id'], 'No tenemos un correo suyo registrado. Agréguelo en «Mis datos».');
        }

        $base = $peticion->getSchemeAndHttpHost();
        $enlaces = [];

        foreach (['CUENTA_COBRO' => 'Cuenta de cobro', 'INFORME_ACTIVIDADES' => 'Informe de actividades'] as $doc => $nombre) {
            $enlaces[] = $nombre . ': ' . $base . '/cuentas/descarga/' . $this->cuentas->crearTokenDescarga((int) $cuenta['id'], $doc);
        }

        try {
            $this->correo->enviar(
                $correo,
                'Su cuenta de cobro ' . (string) $cuenta['radicado'],
                "Estos son sus documentos (cada enlace sirve UNA vez y vence en 7 días):\n\n"
                . implode("\n", $enlaces)
                . "\n\nE.S.E. Hospital Rosario Pumarejo de López",
            );
        } catch (\Throwable) {
            return $this->volverConError('/cuentas/enviada/' . (int) $cuenta['id'], 'No pudimos enviar el correo en este momento. Inténtelo más tarde.');
        }

        $this->guardarFlash('cc_mensaje', 'Le enviamos los enlaces de descarga a ' . $correo . '.');

        return new RedirectResponse('/cuentas/enviada/' . (int) $cuenta['id'], Response::HTTP_SEE_OTHER);
    }

    /** Descarga autenticada de un PDF propio. */
    public function descargarPdf(Request $peticion): Response
    {
        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [, $contrato] = $contexto;
        $cuenta = $this->cuentas->porId($peticion->attributes->getInt('id'));

        if ($cuenta === null || (int) $cuenta['contrato_id'] !== (int) $contrato['id']) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        $columna = $peticion->attributes->get('documento') === 'informe' ? 'pdf_informe_actividades' : 'pdf_cuenta_cobro';
        $ruta = $cuenta[$columna] ?? null;

        if (!is_string($ruta) || $ruta === '' || !$this->archivos->existe($ruta)) {
            return $this->volverConError('/cuentas/inicio', 'Ese documento aún no está generado.');
        }

        $nombre = ($columna === 'pdf_cuenta_cobro' ? 'cuenta-de-cobro-' : 'informe-actividades-')
            . (string) $cuenta['radicado'] . '.pdf';

        return $this->responderPdf($this->archivos->rutaAbsoluta($ruta), $nombre);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|Response
     */
    private function contexto(): array|Response
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
              WHERE k.id = ? AND k.contratista_id = ? AND k.estado = 'ACTIVO'",
            [$contratoId, $contratistaId],
        );

        if ($contratista === null || (int) $contratista['activo'] !== 1 || $contrato === false) {
            return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
        }

        if ((int) $contratista['datos_completos'] !== 1) {
            return new RedirectResponse('/cuentas/mis-datos', Response::HTTP_SEE_OTHER);
        }

        return [$contratista, $contrato];
    }

    /**
     * Contexto + la cuenta editable (borrador o devuelta). Sin cuenta
     * editable no hay pasos 2 a 5.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}|Response
     */
    private function contextoConCuenta(): array|Response
    {
        $contexto = $this->contexto();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;
        $cuenta = $this->cuentas->editableDelContrato((int) $contrato['id']);

        if ($cuenta === null) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        return [$contratista, $contrato, $cuenta];
    }

    /**
     * Los aportes sugeridos del periodo (§8.3), con la cuota COMPLETA del
     * contrato como base también para las cuotas parciales.
     *
     * @return array<string, int|string>
     */
    private function sugeridos(array $contratista, array $contrato, array $cuenta): array
    {
        $anio = (int) substr((string) $cuenta['periodo_hasta'], 0, 4);
        $parametros = $this->parametros->porAnio($anio) ?? $this->parametros->porAnio($anio - 1);

        if ($parametros === null) {
            throw new \RuntimeException("No hay parámetros del año {$anio}; regístrelos en el panel.");
        }

        $clase = (int) ($contratista['clase_riesgo_arl'] ?? 1) ?: 1;
        $tarifa = $this->parametros->tarifaClase($clase) ?? '0.00522';
        $cuotaCompleta = $this->conexion->fetchOne(
            "SELECT MAX(valor) FROM cc_contrato_pagos WHERE contrato_id = ? AND tipo = 'COMPLETA'",
            [(int) $contrato['id']],
        );
        $base = $cuotaCompleta === false || $cuotaCompleta === null
            ? ContratoRepository::pesos((string) $cuenta['valor'])
            : ContratoRepository::pesos((string) $cuotaCompleta);

        $aportes = CalculosCuenta::aportesSugeridos($base, $tarifa, $parametros);

        return [
            'ibc' => $aportes->ibc,
            'aporte_salud' => $aportes->salud,
            'aporte_pension' => $aportes->pension,
            'aporte_fsp' => $aportes->fsp,
            'aporte_arl' => $aportes->arl,
            'tarifa' => $tarifa,
            'clase' => $clase,
        ];
    }

    /** @param array<string, mixed> $cuenta */
    private function periodoTexto(array $cuenta): string
    {
        return FechasCuenta::periodo(
            FechasCuenta::desdeMysql((string) $cuenta['periodo_desde']),
            FechasCuenta::desdeMysql((string) $cuenta['periodo_hasta']),
        );
    }

    private function responderPdf(string $rutaAbsoluta, string $nombre): Response
    {
        return new Response((string) file_get_contents($rutaAbsoluta), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombre . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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

        return $this->volverConError('/cuentas/inicio', 'Su sesión expiró. Vuelva a intentarlo.');
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
