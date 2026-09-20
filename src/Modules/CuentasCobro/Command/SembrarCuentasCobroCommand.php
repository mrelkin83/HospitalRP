<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Command;

use App\Modules\CuentasCobro\Entity\ModalidadPeriodos;
use App\Modules\CuentasCobro\Service\CalculosCuenta;
use App\Modules\CuentasCobro\Service\FechasCuenta;
use App\Modules\CuentasCobro\Service\NumeroALetras;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Siembra los contratos de muestra de la sección 13 del documento del
 * módulo, ya ACTIVOS y con su CDP, para desarrollo y demostración.
 *
 * Los datos personales son FICTICIOS a propósito (cédulas 11000000xx,
 * celulares 3000000xxx): los reales solo se cargan en producción por el
 * administrador. Idempotente por número de contrato.
 */
final class SembrarCuentasCobroCommand extends Command
{
    /** Las 14 obligaciones reales del 1407-2026, para el informe de prueba. */
    private const array OBLIGACIONES_1407 = [
        'Cubrir el servicio de mantenimiento preventivo y correctivo hospitalario durante los turnos asignados, de lunes a domingo incluido los días festivos, conforme al plan de mantenimiento de la E.S.E. y las necesidades.',
        'Asegurar el funcionamiento continuo y eficaz de los equipos, minimizando las posibles paradas como consecuencia de averías. El objetivo de disponibilidad total ha de entenderse como alcanzable.',
        'Presentar una relación mensual y físico de repuestos y/o accesorios utilizados en el desarrollo contractual.',
        'Apoyar en el proceso de levantamiento de inventario de activos fijos de equipos de cómputo y redes, además actualización de las hojas de vida de los equipos de cómputo (hardware y software), también se cubrirán las visitas de inspección, vigilancia y control, cuando ejerzan funciones de inspección, vigilancia y control sobre el mantenimiento hospitalario y demás procesos desarrollados de Gestión Tecnológica en Salud, en la Institución.',
        'Apoyar el proceso de bajas administrativas de equipos de cómputo y componentes de redes de datos.',
        'Elaborar, actualizar, custodiar y centralizar el historial de mantenimiento de equipos de cómputo.',
        'Apoyo en la realización y actualización del Inventario Técnico de los bienes de la Institución en relación a equipos de cómputo y comunicación.',
        'Apoyo en la realización y actualización del diseño lógico y físico de la red LAN de los bienes de la institución en relación a equipos de comunicación.',
        'Apoyo en la realización y actualización en el rotulado e identificación de los tendidos de cableado horizontal ANSI/UL 696.',
        'Asesorar y acompañar en el diseño, elaboración y actualización del Plan de Mantenimiento de cómputos.',
        'Acompañamiento para la recepción, inventariado y puesta en marcha de los nuevos equipos e instalaciones adquiridos o introducidos por la entidad.',
        'Realizar seguimiento al estado actual de los equipos de cómputo y redes de la ESE.',
        'Apoyar en la ejecución de los planes de mejoramiento que se deriven del proceso de mantenimiento hospitalario.',
        'Realizar el mantenimiento preventivo y correctivo de las impresoras que actualmente tiene el hospital.',
    ];

    private const string RUBRO = '2.1.2.02.02.008-83118';
    private const string NOMBRE_RUBRO = 'SERVICIOS DE GESTIÓN Y ADMINISTRACIÓN EMPRESARIAL (OFICINA CENTRAL)';
    private const string RECURSO = '1.2.3.2.09 VENTA DE BIENES Y SERVICIOS';

    public function __construct(private readonly Connection $conexion)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('semillas:cuentas-cobro')
            ->setDescription('Siembra los contratos de muestra del módulo de cuentas de cobro (datos ficticios)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $usuarioId = $this->conexion->fetchOne(
            'SELECT id FROM usuarios WHERE activo = 1 AND eliminado_en IS NULL ORDER BY id LIMIT 1',
        );

        if ($usuarioId === false) {
            $io->error('No hay usuarios en la plataforma. Cree el administrador primero (admin:crear).');

            return Command::FAILURE;
        }

        $supervisorId = $this->supervisorDemo((int) $usuarioId);
        $creados = 0;

        foreach ($this->contratos() as $contrato) {
            $creados += $this->sembrarContrato($contrato, $supervisorId, (int) $usuarioId, $io);
        }

        $io->success($creados . ' contratos sembrados (los existentes se dejaron como estaban).');

        return Command::SUCCESS;
    }

    /**
     * La sección 13 del documento, tal cual: 7 contratos de sistemas de la
     * vigencia 2026 más el 1060, el de referencia de los formatos.
     *
     * @return list<array<string, mixed>>
     */
    private function contratos(): array
    {
        $meses = static function (int $valor, string ...$mesesLista): array {
            $cuotas = [];

            foreach ($mesesLista as $mes) {
                $nombre = ['08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'][substr($mes, 5)] ?? $mes;
                $cuotas[] = ['descripcion' => $nombre . ' de 2026', 'tipo' => 'COMPLETA', 'mes' => $mes, 'valor' => $valor];
            }

            return $cuotas;
        };
        $agoDic = static fn (int $valor): array => $meses($valor, '2026-08', '2026-09', '2026-10', '2026-11', '2026-12');

        return [
            ['numero' => '1305-2026', 'fecha' => '2026-07-07', 'objeto' => 'Prestación de servicios profesionales de apoyo al área de sistemas en seguridad de la información y tecnologías de la información.', 'valor' => 23333333, 'plazo' => '25 DÍAS Y 5 MESES', 'meses' => 5, 'dias' => 25, 'cedula' => '1100000001', 'nombres' => 'Andrés', 'apellidos' => 'Prueba Uno', 'cdp' => ['1495', '2026-07-06'],
             'cuotas' => [['descripcion' => '25 días de julio de 2026', 'tipo' => 'PARCIAL', 'mes' => '2026-07', 'valor' => 3333333], ...$agoDic(4000000)]],
            ['numero' => '1407-2026', 'fecha' => '2026-08-01', 'objeto' => 'Prestación de servicios de apoyo a la gestión como tecnólogo en sistemas para el mantenimiento preventivo y correctivo de los equipos de cómputo y redes de la E.S.E.', 'valor' => 14250000, 'plazo' => 'CINCO (5) MESES', 'meses' => 5, 'dias' => 0, 'cedula' => '1100000002', 'nombres' => 'Bernardo', 'apellidos' => 'Prueba Dos', 'cdp' => ['1611', '2026-07-24'],
             'cuotas' => $agoDic(2850000), 'obligaciones' => self::OBLIGACIONES_1407],
            ['numero' => '1408-2026', 'fecha' => '2026-08-01', 'objeto' => 'Prestación de servicios de apoyo a la gestión como técnico en sistemas para el mantenimiento de los equipos de cómputo de la E.S.E.', 'valor' => 13750000, 'plazo' => 'CINCO (5) MESES', 'meses' => 5, 'dias' => 0, 'cedula' => '1100000003', 'nombres' => 'Carmen', 'apellidos' => 'Prueba Tres', 'cdp' => ['1612', '2026-07-24'],
             'cuotas' => $agoDic(2750000)],
            ['numero' => '1409-2026', 'fecha' => '2026-07-24', 'objeto' => 'Prestación de servicios profesionales de apoyo al área de sistemas en seguridad de la información.', 'valor' => 29303733, 'plazo' => '8 DÍAS Y 5 MESES', 'meses' => 5, 'dias' => 8, 'cedula' => '1100000004', 'nombres' => 'Diana', 'apellidos' => 'Prueba Cuatro', 'cdp' => ['1613', '2026-07-24'],
             'cuotas' => [['descripcion' => '8 días de julio de 2026', 'tipo' => 'PARCIAL', 'mes' => '2026-07', 'valor' => 1483733], ...$agoDic(5564000)]],
            ['numero' => '1414-2026', 'fecha' => '2026-07-27', 'objeto' => 'Prestación de servicios profesionales como ingeniero de sistemas.', 'valor' => 18083333, 'plazo' => '5 DÍAS Y 5 MESES', 'meses' => 5, 'dias' => 5, 'cedula' => '1100000005', 'nombres' => 'Esteban', 'apellidos' => 'Prueba Cinco', 'cdp' => ['1622', '2026-07-27'],
             'cuotas' => [['descripcion' => 'Días de julio de 2026', 'tipo' => 'PARCIAL', 'mes' => '2026-07', 'valor' => 583333], ...$agoDic(3500000)]],
            ['numero' => '1415-2026', 'fecha' => '2026-07-27', 'objeto' => 'Prestación de servicios de apoyo a la gestión como tecnólogo en sistemas para el mantenimiento de equipos de cómputo.', 'valor' => 14250000, 'plazo' => 'CINCO (5) MESES', 'meses' => 5, 'dias' => 0, 'cedula' => '1100000006', 'nombres' => 'Fabiola', 'apellidos' => 'Prueba Seis', 'cdp' => ['1623', '2026-07-27'],
             'cuotas' => $agoDic(2850000)],
            // El 1473 trae la inconsistencia real: recuadro 4 meses,
            // cláusula 5 dice 2. Se guarda la del recuadro y la alerta la
            // pondrá la extracción (fase 5).
            ['numero' => '1473-2026', 'fecha' => '2026-08-01', 'objeto' => 'Prestación de servicios como técnico en sistemas para el mantenimiento de equipos de cómputo.', 'valor' => 10600000, 'plazo' => 'CUATRO (4) MESES', 'meses' => 4, 'dias' => 0, 'cedula' => '1100000007', 'nombres' => 'Gustavo', 'apellidos' => 'Prueba Siete', 'cdp' => ['1669', '2026-07-30'],
             'cuotas' => $meses(2650000, '2026-08', '2026-09', '2026-10', '2026-11')],
            // El contrato de referencia de los formatos: ANIVERSARIO, con
            // acta, RP y sección, para previsualizar los PDF de la fase 2.
            ['numero' => '1060-2026', 'fecha' => '2026-01-28', 'objeto' => 'Prestación de servicios de apoyo a la gestión como técnico administrativo (facturador) en la E.S.E. Hospital Rosario Pumarejo de López.', 'valor' => 14100000, 'plazo' => 'SEIS (6) MESES', 'meses' => 6, 'dias' => 0, 'cedula' => '1100000008', 'nombres' => 'Helena', 'apellidos' => 'Prueba Ocho', 'cdp' => ['271', '2026-01-28', 282000000],
             'modalidad' => 'ANIVERSARIO', 'seccion' => 'Autorizadora urgencias', 'rp' => ['2055', '2026-01-28', 14100000], 'acta' => '2026-01-28', 'arl' => 3,
             'cuotas' => array_map(static fn (int $n): array => ['descripcion' => 'Mensualidad ' . $n, 'tipo' => 'COMPLETA', 'mes' => null, 'valor' => 2350000], range(1, 6))],
        ];
    }

    /** Un supervisor demo vinculado al primer usuario del panel. */
    private function supervisorDemo(int $usuarioId): int
    {
        $existente = $this->conexion->fetchOne('SELECT id FROM cc_supervisores WHERE usuario_id = ?', [$usuarioId]);

        if ($existente !== false) {
            return (int) $existente;
        }

        $this->conexion->insert('cc_supervisores', [
            'usuario_id' => $usuarioId,
            'nombre_completo' => 'SUPERVISOR DE PRUEBA',
            'cargo' => 'PROFESIONAL UNIVERSITARIO (FACTURACIÓN)',
            'dependencia' => 'Subgerencia administrativa',
            'activo' => 1,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $c */
    private function sembrarContrato(array $c, int $supervisorId, int $usuarioId, SymfonyStyle $io): int
    {
        if ($this->conexion->fetchOne('SELECT id FROM cc_contratos WHERE numero = ?', [$c['numero']]) !== false) {
            return 0;
        }

        $contratistaId = $this->contratista($c);

        $this->conexion->insert('cc_contratos', [
            'numero' => $c['numero'],
            'contratista_id' => $contratistaId,
            'supervisor_id' => $supervisorId,
            'cargo_supervisor_minuta' => 'PROFESIONAL UNIVERSITARIO',
            'fecha_suscripcion' => $c['fecha'],
            'objeto' => $c['objeto'],
            'valor_total' => $c['valor'],
            'valor_total_letras' => NumeroALetras::pesos((int) $c['valor']),
            'plazo_texto' => $c['plazo'],
            'plazo_meses' => $c['meses'],
            'plazo_dias' => $c['dias'],
            'modalidad_periodos' => $c['modalidad'] ?? 'CALENDARIO',
            'seccion_ubicacion' => $c['seccion'] ?? null,
            'estado' => 'ACTIVO',
            'activado_en' => date('Y-m-d H:i:s'),
            'activado_por' => $usuarioId,
        ]);
        $contratoId = (int) $this->conexion->lastInsertId();

        foreach (array_values($c['cuotas']) as $i => $cuota) {
            $this->conexion->insert('cc_contrato_pagos', [
                'contrato_id' => $contratoId,
                'numero_cuota' => $i + 1,
            ] + $cuota);
        }

        $cdp = $c['cdp'];
        $this->conexion->insert('cc_contrato_imputaciones', [
            'contrato_id' => $contratoId,
            'tipo' => 'CDP',
            'numero' => $cdp[0],
            'fecha' => $cdp[1],
            'codigo_rubro' => self::RUBRO,
            'nombre_rubro' => self::NOMBRE_RUBRO,
            'recurso' => self::RECURSO,
            'valor' => $cdp[2] ?? $c['valor'],
        ]);

        if (isset($c['rp'])) {
            $this->conexion->insert('cc_contrato_imputaciones', [
                'contrato_id' => $contratoId,
                'tipo' => 'RP',
                'numero' => $c['rp'][0],
                'fecha' => $c['rp'][1],
                'codigo_rubro' => self::RUBRO,
                'nombre_rubro' => self::NOMBRE_RUBRO,
                'recurso' => self::RECURSO,
                'valor' => $c['rp'][2],
            ]);
        }

        $obligaciones = $c['obligaciones'] ?? [
            'Prestar el servicio contratado conforme al objeto y a las condiciones de la minuta.',
            'Presentar informe mensual de actividades como requisito de pago.',
            'Acreditar el pago de aportes al Sistema de Seguridad Social Integral.',
        ];

        foreach (array_values($obligaciones) as $i => $texto) {
            $this->conexion->insert('cc_contrato_obligaciones', [
                'contrato_id' => $contratoId,
                'orden' => $i + 1,
                'grupo' => 'ESPECIFICA',
                'texto' => $texto,
            ]);
        }

        // El contrato de referencia llega con acta: se calculan sus
        // periodos con la misma clase que usa el panel.
        if (isset($c['acta'])) {
            $acta = FechasCuenta::desdeMysql((string) $c['acta']);
            $cuotas = array_map(
                static fn (int $i, array $cuota): \App\Modules\CuentasCobro\Entity\Cuota => new \App\Modules\CuentasCobro\Entity\Cuota(
                    numero: $i + 1,
                    descripcion: (string) $cuota['descripcion'],
                    valor: (int) $cuota['valor'],
                    tipo: \App\Modules\CuentasCobro\Entity\TipoCuota::from((string) $cuota['tipo']),
                    mes: $cuota['mes'],
                ),
                array_keys(array_values($c['cuotas'])),
                array_values($c['cuotas']),
            );
            $periodos = CalculosCuenta::cronograma($cuotas, ModalidadPeriodos::from($c['modalidad'] ?? 'CALENDARIO'), $acta);

            foreach ($periodos as $periodo) {
                $this->conexion->update('cc_contrato_pagos', [
                    'periodo_desde' => $periodo->desde->format('Y-m-d'),
                    'periodo_hasta' => $periodo->hasta->format('Y-m-d'),
                    'dias' => $periodo->dias,
                ], ['contrato_id' => $contratoId, 'numero_cuota' => $periodo->numeroCuota]);
            }

            $this->conexion->update('cc_contratos', [
                'fecha_acta_inicio' => $acta->format('Y-m-d'),
                'fecha_terminacion' => CalculosCuenta::fechaTerminacion($periodos)->format('Y-m-d'),
            ], ['id' => $contratoId]);
        }

        if (isset($c['arl'])) {
            $this->conexion->update('cc_contratistas', ['clase_riesgo_arl' => $c['arl']], ['id' => $contratistaId]);
        }

        $io->text('Contrato ' . $c['numero'] . ' sembrado y activado.');

        return 1;
    }

    /** @param array<string, mixed> $c */
    private function contratista(array $c): int
    {
        $existente = $this->conexion->fetchOne('SELECT id FROM cc_contratistas WHERE numero_documento = ?', [$c['cedula']]);

        if ($existente !== false) {
            return (int) $existente;
        }

        $this->conexion->insert('cc_contratistas', [
            'numero_documento' => $c['cedula'],
            'lugar_expedicion' => 'Valledupar',
            'nombres' => $c['nombres'],
            'apellidos' => $c['apellidos'],
            'celular' => '300000' . substr((string) $c['cedula'], -4),
            'correo' => 'contratista' . substr((string) $c['cedula'], -2) . '@ejemplo.test',
            // Banco FICTICIO: sin él, los PDF de muestra salen con la
            // autorización de consignación en blanco.
            'banco' => 'Banco de Prueba',
            'tipo_cuenta' => 'AHORROS',
            'numero_cuenta' => '4500' . substr((string) $c['cedula'], -6),
            'clase_riesgo_arl' => 1,
        ]);

        return (int) $this->conexion->lastInsertId();
    }
}
