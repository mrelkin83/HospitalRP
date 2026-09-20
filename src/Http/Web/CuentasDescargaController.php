<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga externa de un PDF de cuenta de cobro con token de UN SOLO USO
 * y vencimiento (§9): es el enlace que viaja por WhatsApp o correo. Sin
 * sesión, sin adivinanzas: 64 caracteres aleatorios hasheados en base.
 */
final readonly class CuentasDescargaController
{
    private const array COLUMNAS = [
        'CUENTA_COBRO' => 'pdf_cuenta_cobro',
        'INFORME_ACTIVIDADES' => 'pdf_informe_actividades',
        'INFORME_SUPERVISION' => 'pdf_informe_supervision',
        'PAQUETE' => 'pdf_paquete',
    ];

    public function __construct(
        private CuentaRepository $cuentas,
        private ArchivosCuentas $archivos,
    ) {
    }

    public function descargar(Request $peticion): Response
    {
        $token = (string) $peticion->attributes->get('token');
        $fila = $this->cuentas->consumirTokenDescarga($token);

        if ($fila === null) {
            return new Response(
                '<!doctype html><html lang="es"><meta charset="utf-8"><title>Enlace vencido</title>'
                . '<body style="font-family: system-ui; max-width: 40rem; margin: 4rem auto; font-size: 1.2rem;">'
                . '<h1>Este enlace ya no sirve</h1>'
                . '<p>Los enlaces de descarga sirven una sola vez y vencen a los 7 días. '
                . 'Entre de nuevo al portal de cuentas de cobro y pida otro.</p>'
                . '<p><a href="/cuentas/ingreso">Ir al portal</a></p></body></html>',
                Response::HTTP_GONE,
            );
        }

        $cuenta = $this->cuentas->porId((int) $fila['cuenta_id']);
        $columna = self::COLUMNAS[(string) $fila['documento']] ?? 'pdf_cuenta_cobro';
        $ruta = $cuenta[$columna] ?? null;

        if ($cuenta === null || !is_string($ruta) || $ruta === '' || !$this->archivos->existe($ruta)) {
            return new Response('El documento no está disponible.', Response::HTTP_NOT_FOUND);
        }

        return new Response((string) file_get_contents($this->archivos->rutaAbsoluta($ruta)), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . strtolower((string) $fila['documento']) . '-' . $cuenta['radicado'] . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
