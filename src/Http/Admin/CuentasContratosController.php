<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Entity\EstadoContrato;
use App\Modules\CuentasCobro\Entity\ModalidadPeriodos;
use App\Modules\CuentasCobro\Entity\TipoCuota;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\SupervisorRepository;
use App\Modules\CuentasCobro\Service\CalculosCuenta;
use App\Modules\CuentasCobro\Service\FechasCuenta;
use App\Modules\CuentasCobro\Service\NumeroALetras;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contratos de prestación de servicios: la pantalla A1 del módulo de
 * cuentas de cobro, más el registro de CDP/RP (A3), el acta de inicio y
 * la activación.
 *
 * El contrato se captura UNA vez y de él beben los tres PDF de cada cuenta
 * mensual. Por eso activar exige que todo cuadre: contratista con cédula,
 * supervisor asignado, al menos un CDP y un cronograma que sume exactamente
 * el valor total. Un contrato activado ya no se edita a la ligera: las
 * cuentas en firme lo referencian.
 */
final readonly class CuentasContratosController
{
    private const string TITULO = 'Cuentas de cobro — Contratos';
    private const string RUTA = '/admin/cuentas-cobro/contratos';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ContratoRepository $contratos,
        private ContratistaRepository $contratistas,
        private SupervisorRepository $supervisores,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/contratos-listado', [
            'contratos' => $this->contratos->listar(
                $peticion->query->getString('estado') ?: null,
                $peticion->query->getString('q') ?: null,
            ),
            'estadoFiltro' => $peticion->query->getString('estado'),
            'busqueda' => $peticion->query->getString('q'),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
        ]));
    }

    public function formulario(Request $peticion): Response
    {
        $id = $peticion->attributes->getInt('id');
        $contrato = $id > 0 ? $this->contratos->porId($id) : null;

        if ($id > 0 && $contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        if ($contrato !== null && $contrato['estado'] !== EstadoContrato::EN_REVISION->value) {
            $this->pantalla->guardarEnLaSesion('cc_error', 'Un contrato activado ya no se edita desde aquí: las cuentas en firme dependen de él. Regístrelo como novedad o pida soporte.');

            return new RedirectResponse(self::RUTA . '/' . $id, Response::HTTP_SEE_OTHER);
        }

        $error = $this->pantalla->sacarDeLaSesion('cc_error');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/contrato-formulario', [
            'contrato' => $contrato,
            'obligaciones' => $contrato !== null ? $this->contratos->obligaciones($id) : [],
            'cuotas' => $contrato !== null ? $this->contratos->pagos($id) : [],
            'contratistas' => $this->contratistas->listar(),
            'supervisores' => $this->supervisores->activos(),
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $id = $peticion->attributes->getInt('id');
        $existente = $id > 0 ? $this->contratos->porId($id) : null;

        if ($id > 0 && $existente === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        if ($existente !== null && $existente['estado'] !== EstadoContrato::EN_REVISION->value) {
            return $this->volverConError('Este contrato ya no está en revisión y no se puede editar.', self::RUTA . '/' . $id);
        }

        $numero = trim($r->getString('numero'));
        $contratistaId = $r->getInt('contratista_id');
        $valorTotal = $r->getInt('valor_total');
        $fechaSuscripcion = trim($r->getString('fecha_suscripcion'));
        $objeto = trim($r->getString('objeto'));
        $volverA = $id > 0 ? self::RUTA . '/' . $id . '/editar' : self::RUTA . '/nuevo';

        if ($numero === '' || $fechaSuscripcion === '' || $objeto === '') {
            return $this->volverConError('El número, la fecha de suscripción y el objeto son obligatorios: así vienen en la minuta.', $volverA);
        }

        if ($this->contratistas->porId($contratistaId) === null) {
            return $this->volverConError('Seleccione el contratista. Si aún no existe, créelo primero en Contratistas.', $volverA);
        }

        if ($valorTotal <= 0) {
            return $this->volverConError('El valor total debe ser un número de pesos mayor que cero, sin puntos ni comas.', $volverA);
        }

        $duplicado = $this->contratos->porNumero($numero);

        if ($duplicado !== null && (int) $duplicado['id'] !== $id) {
            return $this->volverConError("Ya existe un contrato con el número {$numero}.", $volverA);
        }

        $cuotas = $this->cuotasDelFormulario($r->all());

        if (is_string($cuotas)) {
            return $this->volverConError($cuotas, $volverA);
        }

        $supervisorId = $r->getInt('supervisor_id');
        $letras = trim($r->getString('valor_total_letras'));
        $campos = [
            'numero' => mb_substr($numero, 0, 20),
            'contratista_id' => $contratistaId,
            'supervisor_id' => $supervisorId > 0 ? $supervisorId : null,
            'cargo_supervisor_minuta' => $this->texto($r->getString('cargo_supervisor_minuta'), 160),
            'tipo_contrato' => $this->texto($r->getString('tipo_contrato'), 120) ?? 'PRESTACIÓN DE SERVICIOS',
            'ciudad_suscripcion' => $this->texto($r->getString('ciudad_suscripcion'), 80) ?? 'Valledupar',
            'fecha_suscripcion' => $fechaSuscripcion,
            'objeto' => $objeto,
            'valor_total' => $valorTotal,
            'valor_total_letras' => $letras !== '' ? mb_substr($letras, 0, 400) : NumeroALetras::pesos($valorTotal),
            'plazo_texto' => $this->texto($r->getString('plazo_texto'), 120) ?? '',
            'plazo_meses' => max(0, min(255, $r->getInt('plazo_meses'))),
            'plazo_dias' => max(0, min(999, $r->getInt('plazo_dias'))),
            'modalidad_periodos' => $r->getString('modalidad_periodos') === 'ANIVERSARIO' ? 'ANIVERSARIO' : 'CALENDARIO',
            'garantias' => $r->getBoolean('garantias') ? 1 : 0,
            'seccion_ubicacion' => $this->texto($r->getString('seccion_ubicacion'), 160),
            'forma_pago_texto' => $this->texto($r->getString('forma_pago_texto'), 5000),
            'requisitos_pago_texto' => $this->texto($r->getString('requisitos_pago_texto'), 5000),
        ];

        if ($existente === null) {
            $id = $this->contratos->crear($campos);
            $accion = AccionAuditoria::Creacion;
        } else {
            $this->contratos->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        }

        $this->contratos->reemplazarPagos($id, $cuotas);
        $this->contratos->reemplazarObligaciones($id, $this->lineas($r->getString('obligaciones')), 'ESPECIFICA');
        $this->contratos->reemplazarObligaciones($id, $this->lineas($r->getString('obligaciones_sgic')), 'SGIC');

        $this->pantalla->auditar($peticion, $accion, 'cc_contrato', $id, "Contrato {$numero} guardado en revisión");
        $this->pantalla->guardarEnLaSesion('cc_mensaje', "Contrato {$numero} guardado. Revise el cronograma y actívelo cuando todo cuadre.");

        return new RedirectResponse(self::RUTA . '/' . $id, Response::HTTP_SEE_OTHER);
    }

    public function ficha(Request $peticion): Response
    {
        $id = $peticion->attributes->getInt('id');
        $contrato = $this->contratos->porId($id);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $cuotas = $this->contratos->cuotas($id);
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/contrato-ficha', [
            'contrato' => $contrato,
            'imputaciones' => $this->contratos->imputaciones($id),
            'obligaciones' => $this->contratos->obligaciones($id),
            'pagos' => $this->contratos->pagos($id),
            'diferencia' => CalculosCuenta::diferenciaConTotal(
                $cuotas,
                ContratoRepository::pesos((string) $contrato['valor_total']) + $this->contratos->totalAdiciones($id),
            ),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    /**
     * Activar es la compuerta de calidad: desde aquí el contratista puede
     * entrar al portal, así que nada puede quedar cojo (§7.3 A2).
     */
    public function activar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $contrato = $this->contratos->porId($id);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $volverA = self::RUTA . '/' . $id;

        if ($contrato['estado'] !== EstadoContrato::EN_REVISION->value) {
            return $this->volverConError('Solo un contrato en revisión se puede activar.', $volverA);
        }

        if ($contrato['supervisor_id'] === null) {
            return $this->volverConError('Asigne el supervisor antes de activar: sin él nadie puede aprobar cuentas.', $volverA);
        }

        if ($this->contratos->contarImputaciones($id, 'CDP') === 0) {
            return $this->volverConError('Registre al menos el CDP de la minuta antes de activar.', $volverA);
        }

        $diferencia = CalculosCuenta::diferenciaConTotal(
            $this->contratos->cuotas($id),
            ContratoRepository::pesos((string) $contrato['valor_total']) + $this->contratos->totalAdiciones($id),
        );

        if ($diferencia !== 0) {
            $signo = $diferencia > 0 ? 'sobran' : 'faltan';

            return $this->volverConError(
                'El cronograma no cuadra: ' . $signo . ' ' . NumeroALetras::moneda(abs($diferencia)) . ' frente al valor total. Corrija las cuotas.',
                $volverA,
            );
        }

        $this->contratos->activar($id, $this->pantalla->usuarioId() ?? 0);
        $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_contrato', $id, 'Contrato ' . $contrato['numero'] . ' activado');
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Contrato activado. Cuando el supervisor registre el acta de inicio, el cronograma quedará con fechas.');

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    /**
     * El acta de inicio aterriza el cronograma sobre el calendario: fija
     * los periodos de cada cuota y la fecha de terminación (§8.1).
     */
    public function registrarActa(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $contrato = $this->contratos->porId($id);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $volverA = self::RUTA . '/' . $id;
        $fechaTexto = trim($peticion->request->getString('fecha_acta_inicio'));

        if ($fechaTexto === '') {
            return $this->volverConError('Indique la fecha del acta de inicio.', $volverA);
        }

        if ($this->contratos->tieneCuentasEnFirme($id)) {
            return $this->volverConError('Ya hay cuentas presentadas con este cronograma; los periodos no se recalculan completos. Registre una novedad.', $volverA);
        }

        try {
            $acta = FechasCuenta::desdeMysql($fechaTexto);
        } catch (\InvalidArgumentException) {
            return $this->volverConError('La fecha del acta no es válida.', $volverA);
        }

        $cuotas = $this->contratos->cuotas($id);

        if ($cuotas === []) {
            return $this->volverConError('El contrato no tiene cronograma de pagos; edítelo y agregue las cuotas.', $volverA);
        }

        $periodos = CalculosCuenta::cronograma(
            $cuotas,
            ModalidadPeriodos::from((string) $contrato['modalidad_periodos']),
            $acta,
        );

        $this->contratos->aplicarActaInicio($id, $acta, $periodos, CalculosCuenta::fechaTerminacion($periodos));
        $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_contrato', $id, 'Acta de inicio del ' . $contrato['numero'] . ' registrada: ' . $fechaTexto);
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Acta registrada. Revise que los periodos del cronograma coincidan con la minuta.');

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    /** A3: registrar CDP o RP. El RP llega después de la firma. */
    public function agregarImputacion(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $contrato = $this->contratos->porId($id);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $r = $peticion->request;
        $volverA = self::RUTA . '/' . $id;
        $tipo = $r->getString('tipo') === 'RP' ? 'RP' : 'CDP';
        $numero = trim($r->getString('numero'));
        $fecha = trim($r->getString('fecha'));
        $codigoRubro = trim($r->getString('codigo_rubro'));
        $nombreRubro = trim($r->getString('nombre_rubro'));
        $valor = $r->getInt('valor');

        if ($numero === '' || $fecha === '' || $codigoRubro === '' || $nombreRubro === '' || $valor <= 0) {
            return $this->volverConError("Para registrar el {$tipo} se necesitan número, fecha, rubro y valor.", $volverA);
        }

        $imputacionId = $this->contratos->agregarImputacion($id, [
            'tipo' => $tipo,
            'numero' => mb_substr($numero, 0, 30),
            'fecha' => $fecha,
            'codigo_rubro' => mb_substr($codigoRubro, 0, 40),
            'nombre_rubro' => mb_substr($nombreRubro, 0, 200),
            'recurso' => $this->texto($r->getString('recurso'), 120),
            'valor' => $valor,
        ]);

        $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'cc_contrato_imputacion', $imputacionId, "{$tipo} {$numero} registrado al contrato " . $contrato['numero']);
        $this->pantalla->guardarEnLaSesion('cc_mensaje', "{$tipo} {$numero} registrado.");

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    /** Nada se borra: anular deja el contrato como constancia, con motivo. */
    public function anular(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $contrato = $this->contratos->porId($id);

        if ($contrato === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $motivo = trim($peticion->request->getString('motivo'));

        if ($motivo === '') {
            return $this->volverConError('Anular exige un motivo: quedará en la auditoría.', self::RUTA . '/' . $id);
        }

        $this->contratos->cambiarEstado($id, EstadoContrato::ANULADO);
        $this->pantalla->auditar($peticion, AccionAuditoria::CambioEstado, 'cc_contrato', $id, 'Contrato ' . $contrato['numero'] . ' anulado: ' . mb_substr($motivo, 0, 300));
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Contrato anulado.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // Ayudas privadas
    // ------------------------------------------------------------------

    /**
     * Las cuotas del formulario (arreglos paralelos) a filas listas para
     * guardar, o un mensaje de error en lenguaje del funcionario.
     *
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

            // Una fila vacía del formulario no es un error: se ignora.
            if ($descripcion === '' && $valor === 0) {
                continue;
            }

            if ($descripcion === '' || $valor <= 0) {
                return 'Cada cuota necesita descripción y valor en pesos mayor que cero (fila ' . ($i + 1) . ').';
            }

            $mes = trim((string) ($meses[$i] ?? ''));

            if ($mes !== '' && preg_match('/^\d{4}-\d{2}$/', $mes) !== 1) {
                return 'El mes de la cuota ' . ($i + 1) . ' debe tener formato AAAA-MM, por ejemplo 2026-08.';
            }

            $cuotas[] = [
                'numero_cuota' => ++$numero,
                'descripcion' => mb_substr($descripcion, 0, 120),
                'tipo' => ($tipos[$i] ?? '') === TipoCuota::PARCIAL->value ? 'PARCIAL' : 'COMPLETA',
                'mes' => $mes === '' ? null : $mes,
                'valor' => $valor,
            ];
        }

        return $cuotas === [] ? 'El contrato necesita al menos una cuota en el cronograma de pagos.' : $cuotas;
    }

    /** @return list<string> Una obligación por línea, sin numeración manual. */
    private function lineas(string $texto): array
    {
        $lineas = [];

        foreach (preg_split('/\R/', $texto) ?: [] as $linea) {
            $limpia = trim(preg_replace('/^\s*\d+[.)]\s*/', '', trim($linea)) ?? '');

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

    private function volverConError(string $mensaje, string $volverA): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }
}
