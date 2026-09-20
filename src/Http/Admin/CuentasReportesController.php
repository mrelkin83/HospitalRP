<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\ReportesCuentasRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A8 — Reportes del módulo de cuentas de cobro, con exportación CSV.
 * El CSV sale en UTF-8 con BOM: sin él, Excel en Windows muestra los
 * acentos rotos y el reporte pierde credibilidad ante Gerencia.
 */
final readonly class CuentasReportesController
{
    private const string TITULO = 'Cuentas de cobro — Reportes';

    private const array REPORTES = [
        'tiempos' => 'tiemposPorSupervisor',
        'estados' => 'cuentasPorEstadoYMes',
        'sin-acta' => 'contratosSinActa',
        'vencidas' => 'cuotasVencidasSinCuenta',
    ];

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ReportesCuentasRepository $reportes,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $csv = $peticion->query->getString('csv');

        if (isset(self::REPORTES[$csv])) {
            return $this->csv($csv, $this->reportes->{self::REPORTES[$csv]}());
        }

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/reportes', [
            'tiempos' => $this->reportes->tiemposPorSupervisor(),
            'estados' => $this->reportes->cuentasPorEstadoYMes(),
            'sinActa' => $this->reportes->contratosSinActa(),
            'vencidas' => $this->reportes->cuotasVencidasSinCuenta(),
        ]));
    }

    /** @param list<array<string, mixed>> $filas */
    private function csv(string $nombre, array $filas): Response
    {
        $lineas = [];

        if ($filas !== []) {
            $lineas[] = self::lineaCsv(array_keys($filas[0]));

            foreach ($filas as $fila) {
                $lineas[] = self::lineaCsv(array_map(
                    static fn (mixed $v): string => $v === null ? '' : (string) $v,
                    array_values($fila),
                ));
            }
        }

        return new Response("\u{FEFF}" . implode("\r\n", $lineas) . "\r\n", Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cuentas-cobro-' . $nombre . '-' . date('Y-m-d') . '.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param list<string> $campos */
    private static function lineaCsv(array $campos): string
    {
        return implode(';', array_map(
            static fn (string $campo): string => '"' . str_replace('"', '""', $campo) . '"',
            $campos,
        ));
    }
}
