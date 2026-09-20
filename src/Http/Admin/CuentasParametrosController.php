<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parámetros del módulo (A6) y plantillas SGC (A7) en una pantalla:
 * SMLV por año, tarifas, redondeo PILA, clases ARL de consulta y la
 * identidad CÓDIGO/VERSIÓN/FECHA de cada formato PDF.
 *
 * Cada enero cambia el SMLV por decreto; eso se registra aquí y el módulo
 * entero calcula con el año nuevo sin tocar código.
 */
final readonly class CuentasParametrosController
{
    private const string TITULO = 'Cuentas de cobro — Parámetros';
    private const string RUTA = '/admin/cuentas-cobro/parametros';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ParametrosCuentasRepository $parametros,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/parametros', [
            'anuales' => $this->parametros->anuales(),
            'clasesArl' => $this->parametros->clasesArl(),
            'plantillas' => $this->parametros->plantillas(),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardarAnio(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $anio = $r->getInt('anio');
        $smlv = $r->getInt('smlv');

        if ($anio < 2020 || $anio > 2100 || $smlv <= 0) {
            return $this->volverConError('Revise el año y el SMLV: el salario va en pesos enteros, sin puntos.');
        }

        $tarifas = [];

        foreach (['porcentaje_ibc' => '0.4000', 'tarifa_salud' => '0.1250', 'tarifa_pension' => '0.1600'] as $campo => $porDefecto) {
            $valor = trim($r->getString($campo)) ?: $porDefecto;

            if (preg_match('/^0\.\d{1,4}$/', $valor) !== 1) {
                return $this->volverConError('Las tarifas se escriben como decimales entre 0 y 1, por ejemplo 0.1250.');
            }

            $tarifas[$campo] = $valor;
        }

        $redondeo = $r->getInt('redondeo_aportes');
        $this->parametros->guardarAnio($anio, $tarifas + [
            'smlv' => $smlv,
            'redondeo_aportes' => $redondeo > 0 ? $redondeo : 100,
        ]);

        $this->pantalla->auditar($peticion, AccionAuditoria::CambioConfiguracion, 'cc_parametros_anuales', $anio, "Parámetros del año {$anio} guardados (SMLV {$smlv})");
        $this->pantalla->guardarEnLaSesion('cc_mensaje', "Parámetros del año {$anio} guardados.");

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function guardarPlantilla(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $clave = (string) $peticion->attributes->get('clave');
        $conocidas = array_column($this->parametros->plantillas(), 'clave');

        if (!in_array($clave, $conocidas, true)) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $fecha = trim($r->getString('fecha_sgc'));
        $this->parametros->actualizarPlantilla($clave, [
            'codigo_sgc' => $this->texto($r->getString('codigo_sgc'), 30),
            'version_sgc' => $this->texto($r->getString('version_sgc'), 10),
            'fecha_sgc' => $fecha === '' ? null : $fecha,
            'proceso' => $this->texto($r->getString('proceso'), 80),
        ]);

        $this->pantalla->auditar($peticion, AccionAuditoria::CambioConfiguracion, 'cc_plantilla', 0, "Identidad SGC de la plantilla {$clave} actualizada");
        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Plantilla actualizada. El encabezado de los próximos PDF usará estos datos.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    private function texto(string $valor, int $maximo): ?string
    {
        $limpio = trim($valor);

        return $limpio === '' ? null : mb_substr($limpio, 0, $maximo);
    }

    private function volverConError(string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }
}
