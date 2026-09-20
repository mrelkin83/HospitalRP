<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use Doctrine\DBAL\Connection;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genera los PDF de una cuenta (fase 2: cuenta de cobro GF-FR-002 e
 * informe de actividades) reproduciendo los formatos institucionales
 * (§11 del documento del módulo).
 *
 * Todo dato calculable se calcula aquí, una sola vez y para ambos
 * documentos: el mismo periodo, el mismo valor en letras, las mismas
 * obligaciones. Que dos documentos de la misma cuenta digan cosas
 * distintas era EL problema que este módulo viene a matar.
 */
final readonly class GeneradorDocumentosCuenta
{
    public function __construct(
        private Connection $conexion,
        private ParametrosCuentasRepository $parametros,
        private ArchivosCuentas $archivos,
        private string $raizPlantillas,
        private string $rutaLogo,
    ) {
    }

    /**
     * Renderiza un documento y lo deja en el almacén privado.
     *
     * @param 'CUENTA_COBRO'|'INFORME_ACTIVIDADES' $clave
     *
     * @return array{ruta: string, hash: string}
     */
    public function generar(string $clave, int $cuentaId, string $renglonFirma = ''): array
    {
        $pdf = $this->renderizar($this->html($clave, $cuentaId, $renglonFirma));
        $ruta = $this->archivos->guardarGenerado($pdf, 'pdf', 'pdf');

        return ['ruta' => $ruta, 'hash' => hash('sha256', $pdf)];
    }

    /** Vista previa HTML para la pantalla «Revisar y enviar» (P5). */
    public function vistaPrevia(string $clave, int $cuentaId): string
    {
        return $this->html($clave, $cuentaId, '');
    }

    private function html(string $clave, int $cuentaId, string $renglonFirma): string
    {
        $plantilla = $this->plantilla($clave);
        $archivo = $this->raizPlantillas . '/' . basename((string) $plantilla['archivo_html']);

        return PlantillaPdf::rellenar(
            (string) file_get_contents($archivo),
            $this->datos($cuentaId)
                + $this->identidadSgc($plantilla)
                + ['renglon_verificacion' => $renglonFirma],
        );
    }

    /** @return array<string, mixed> */
    private function plantilla(string $clave): array
    {
        foreach ($this->parametros->plantillas() as $plantilla) {
            if ($plantilla['clave'] === $clave) {
                return $plantilla;
            }
        }

        throw new \RuntimeException("No existe la plantilla {$clave}.");
    }

    /** @param array<string, mixed> $plantilla @return array<string, string> */
    private function identidadSgc(array $plantilla): array
    {
        return [
            'sgc_codigo' => (string) ($plantilla['codigo_sgc'] ?? ''),
            'sgc_version' => (string) ($plantilla['version_sgc'] ?? ''),
            'sgc_fecha' => (string) ($plantilla['fecha_sgc'] ?? ''),
            'logo' => $this->logoBase64(),
        ];
    }

    /**
     * Todos los marcadores que usan las plantillas de §11.1 y §11.2,
     * calculados desde la base. Los montos van formateados («$ 2.350.000»)
     * y las fechas en el español de los formatos.
     *
     * @return array<string, mixed>
     */
    private function datos(int $cuentaId): array
    {
        $cuenta = $this->conexion->fetchAssociative(
            'SELECT c.*, k.numero AS numero_contrato, k.objeto, k.tipo_contrato, k.ciudad_suscripcion,
                    k.fecha_suscripcion, k.valor_total, k.valor_total_letras, k.plazo_texto, k.garantias,
                    k.fecha_acta_inicio, k.fecha_terminacion, k.seccion_ubicacion, k.id AS kid,
                    t.nombre_completo AS contratista_nombre, t.tipo_documento, t.numero_documento,
                    t.lugar_expedicion, t.direccion, t.telefono, t.celular, t.correo, t.banco,
                    t.tipo_cuenta AS banco_tipo_cuenta, t.numero_cuenta AS banco_numero_cuenta,
                    t.firma_archivo AS contratista_firma,
                    s.nombre_completo AS supervisor_nombre, s.cargo AS supervisor_cargo
               FROM cc_cuentas c
               JOIN cc_contratos k ON k.id = c.contrato_id
               JOIN cc_contratistas t ON t.id = k.contratista_id
               LEFT JOIN cc_supervisores s ON s.id = k.supervisor_id
              WHERE c.id = ?',
            [$cuentaId],
        );

        if ($cuenta === false) {
            throw new \RuntimeException('La cuenta ' . $cuentaId . ' no existe.');
        }

        $desde = FechasCuenta::desdeMysql((string) $cuenta['periodo_desde']);
        $hasta = FechasCuenta::desdeMysql((string) $cuenta['periodo_hasta']);
        $ss = $this->conexion->fetchAssociative('SELECT * FROM cc_cuenta_seguridad_social WHERE cuenta_id = ?', [$cuentaId]) ?: [];
        $moneda = static fn (mixed $v): string => ($v === null || $v === '') ? '' : NumeroALetras::moneda(ContratoRepository::pesos((string) $v));

        return [
            'consecutivo' => (int) $cuenta['consecutivo'],
            'radicado' => (string) ($cuenta['radicado'] ?? ''),
            'numero_contrato' => (string) $cuenta['numero_contrato'],
            'objeto' => (string) $cuenta['objeto'],
            'tipo_contrato' => (string) $cuenta['tipo_contrato'],
            'ciudad' => (string) $cuenta['ciudad_suscripcion'],
            'fecha_suscripcion_larga' => FechasCuenta::larga(FechasCuenta::desdeMysql((string) $cuenta['fecha_suscripcion'])),
            'valor_total' => $moneda($cuenta['valor_total']),
            'valor_total_letras' => (string) $cuenta['valor_total_letras'],
            'plazo_texto' => (string) $cuenta['plazo_texto'],
            'garantias' => ((int) $cuenta['garantias'] === 1) ? 'SÍ' : 'NO',
            'fecha_acta_inicio' => $cuenta['fecha_acta_inicio'] === null ? '' : FechasCuenta::larga(FechasCuenta::desdeMysql((string) $cuenta['fecha_acta_inicio'])),
            'fecha_terminacion' => $cuenta['fecha_terminacion'] === null ? '' : FechasCuenta::larga(FechasCuenta::desdeMysql((string) $cuenta['fecha_terminacion'])),
            'seccion_ubicacion' => (string) ($cuenta['seccion_ubicacion'] ?? ''),
            'periodo_texto' => FechasCuenta::periodo($desde, $hasta),
            'periodo_desde_larga_mayus' => mb_strtoupper(FechasCuenta::larga($desde), 'UTF-8'),
            'periodo_hasta_larga_mayus' => mb_strtoupper(FechasCuenta::larga($hasta), 'UTF-8'),
            'fecha_cobro_larga' => FechasCuenta::larga(FechasCuenta::desdeMysql((string) $cuenta['fecha_cobro'])),
            'valor' => $moneda($cuenta['valor']),
            'valor_letras' => (string) $cuenta['valor_letras'],
            'contratista_nombre' => (string) $cuenta['contratista_nombre'],
            'numero_documento' => (string) $cuenta['numero_documento'],
            'lugar_expedicion' => (string) ($cuenta['lugar_expedicion'] ?? ''),
            'direccion' => (string) ($cuenta['direccion'] ?? ''),
            'telefonos' => trim(((string) ($cuenta['telefono'] ?? '')) . ' ' . ((string) ($cuenta['celular'] ?? ''))),
            'correo' => (string) ($cuenta['correo'] ?? ''),
            'banco' => (string) ($cuenta['banco'] ?? ''),
            'tipo_cuenta' => (string) ($cuenta['banco_tipo_cuenta'] ?? ''),
            'numero_cuenta' => (string) ($cuenta['banco_numero_cuenta'] ?? ''),
            'supervisor_nombre' => (string) ($cuenta['supervisor_nombre'] ?? ''),
            'supervisor_cargo' => (string) ($cuenta['supervisor_cargo'] ?? ''),
            'firma_contratista' => $this->imagenBase64($cuenta['contratista_firma']),
            'obligaciones' => $this->obligaciones($cuentaId),
            'imputaciones' => $this->imputaciones((int) $cuenta['kid'], $moneda),
            'cdp_numero' => $this->numerosImputacion((int) $cuenta['kid'], 'CDP'),
            'rp_numero' => $this->numerosImputacion((int) $cuenta['kid'], 'RP'),
            'anexos' => $this->anexos($cuentaId),
            ...$this->datosSeguridadSocial($ss, $moneda),
        ];
    }

    /** @return list<array<string, string|int>> Una fila por obligación específica. */
    private function obligaciones(int $cuentaId): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT o.orden, o.texto, a.descripcion_ejecucion
               FROM cc_cuenta_actividades a
               JOIN cc_contrato_obligaciones o ON o.id = a.obligacion_id
              WHERE a.cuenta_id = ?
              ORDER BY o.orden',
            [$cuentaId],
        );

        return array_map(static fn (array $f): array => [
            'orden' => (int) $f['orden'],
            'texto' => (string) $f['texto'],
            'ejecucion' => (string) ($f['descripcion_ejecucion'] ?? ''),
        ], $filas);
    }

    /** @return list<array<string, string>> CDP y RP para la tabla de imputación. */
    private function imputaciones(int $contratoId, \Closure $moneda): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT tipo, numero, fecha, codigo_rubro, nombre_rubro, recurso, valor
               FROM cc_contrato_imputaciones WHERE contrato_id = ? ORDER BY tipo, fecha, id',
            [$contratoId],
        );

        return array_map(static fn (array $f): array => [
            'tipo' => (string) $f['tipo'],
            'numero' => (string) $f['numero'],
            'fecha' => (string) $f['fecha'],
            'codigo_rubro' => (string) $f['codigo_rubro'],
            'nombre_rubro' => (string) $f['nombre_rubro'],
            'recurso' => (string) ($f['recurso'] ?? ''),
            'valor' => $moneda($f['valor']),
        ], $filas);
    }

    private function numerosImputacion(int $contratoId, string $tipo): string
    {
        $numeros = $this->conexion->fetchFirstColumn(
            'SELECT numero FROM cc_contrato_imputaciones WHERE contrato_id = ? AND tipo = ? ORDER BY fecha, id',
            [$contratoId, $tipo],
        );

        return implode(', ', array_map(strval(...), $numeros));
    }

    /** @return list<array<string, string>> */
    private function anexos(int $cuentaId): array
    {
        $etiquetas = [
            'PLANILLA_PILA' => 'Planilla de pago aportes SGSS',
            'PAZ_Y_SALVO' => 'Paz y salvo',
            'IMPUESTOS_DEPARTAMENTALES' => 'Impuestos departamentales',
            'OTRO' => 'Otro anexo',
        ];
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT tipo, nombre_original FROM cc_cuenta_adjuntos WHERE cuenta_id = ? ORDER BY id',
            [$cuentaId],
        );

        return array_map(static fn (array $f): array => [
            'nombre' => $etiquetas[(string) $f['tipo']] ?? (string) $f['nombre_original'],
        ], $filas);
    }

    /**
     * @param array<string, mixed> $ss Fila de cc_cuenta_seguridad_social (o vacía).
     *
     * @return array<string, string>
     */
    private function datosSeguridadSocial(array $ss, \Closure $moneda): array
    {
        $mes = static function (mixed $v): string {
            if (!is_string($v) || preg_match('/^\d{4}-\d{2}$/', $v) !== 1) {
                return '';
            }

            return mb_strtoupper(FechasCuenta::mes(FechasCuenta::desdeMysql($v . '-01')), 'UTF-8') . ' DE ' . substr($v, 0, 4);
        };
        $totalSs = 0;

        foreach (['aporte_salud', 'aporte_pension', 'aporte_fsp', 'aporte_arl'] as $campo) {
            $totalSs += isset($ss[$campo]) ? ContratoRepository::pesos((string) $ss[$campo]) : 0;
        }

        return [
            'numero_planilla' => (string) ($ss['numero_planilla'] ?? ''),
            'mes_salud' => $mes($ss['mes_salud'] ?? null),
            'mes_pension' => $mes($ss['mes_pension'] ?? null),
            'mes_salud_texto' => is_string($ss['mes_salud'] ?? null) ? FechasCuenta::mes(FechasCuenta::desdeMysql($ss['mes_salud'] . '-01')) . ' de ' . substr((string) $ss['mes_salud'], 0, 4) : '',
            'ibc' => isset($ss['ibc']) ? NumeroALetras::moneda(ContratoRepository::pesos((string) $ss['ibc'])) : '',
            'aporte_salud' => $moneda($ss['aporte_salud'] ?? null),
            'aporte_pension' => $moneda($ss['aporte_pension'] ?? null),
            'aporte_fsp' => $moneda($ss['aporte_fsp'] ?? null),
            'aporte_arl' => $moneda($ss['aporte_arl'] ?? null),
            'tarifa_arl' => isset($ss['tarifa_arl']) ? rtrim(rtrim((string) $ss['tarifa_arl'], '0'), '.') : '',
            'aporte_sena' => $moneda($ss['aporte_sena'] ?? null),
            'aporte_icbf' => $moneda($ss['aporte_icbf'] ?? null),
            'aporte_ccf' => $moneda($ss['aporte_ccf'] ?? null),
            'total_aportes' => $moneda($ss['total_aportes'] ?? null),
            'total_ss' => $totalSs > 0 ? NumeroALetras::moneda($totalSs) : '',
            'total_parafiscales' => NumeroALetras::moneda(0),
        ];
    }

    /** La imagen de la firma, embebida (Dompdf no debe salir a la red). */
    private function imagenBase64(mixed $rutaRelativa): string
    {
        if (!is_string($rutaRelativa) || $rutaRelativa === '' || !$this->archivos->existe($rutaRelativa)) {
            return '';
        }

        $absoluta = $this->archivos->rutaAbsoluta($rutaRelativa);
        $mime = str_ends_with($absoluta, '.png') ? 'image/png' : 'image/jpeg';

        return '<img src="data:' . $mime . ';base64,'
            . base64_encode((string) file_get_contents($absoluta))
            . '" style="max-height: 60px; max-width: 220px;" alt="Firma">';
    }

    private function logoBase64(): string
    {
        if (!is_file($this->rutaLogo)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($this->rutaLogo));
    }

    private function renderizar(string $html): string
    {
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
