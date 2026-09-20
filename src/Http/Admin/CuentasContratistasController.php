<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contratistas del módulo de cuentas de cobro (pantalla A4).
 *
 * El administrador crea la ficha con lo que trae la minuta; el resto
 * (contacto, banco, firma) lo completa el propio contratista en su primer
 * ingreso al portal (fase 2). Nadie se borra: se desactiva.
 */
final readonly class CuentasContratistasController
{
    private const string TITULO = 'Cuentas de cobro — Contratistas';
    private const string RUTA = '/admin/cuentas-cobro/contratistas';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ContratistaRepository $contratistas,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/contratistas', [
            'contratistas' => $this->contratistas->listar(),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
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
        $existente = $id > 0 ? $this->contratistas->porId($id) : null;

        if ($id > 0 && $existente === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $nombres = trim($r->getString('nombres'));
        $apellidos = trim($r->getString('apellidos'));
        $documento = trim($r->getString('numero_documento'));

        if ($nombres === '' || $apellidos === '' || $documento === '') {
            return $this->volverConError('Nombres, apellidos y número de documento son obligatorios.');
        }

        if (preg_match('/^\d{5,20}$/', $documento) !== 1) {
            return $this->volverConError('El número de documento debe tener solo dígitos (entre 5 y 20).');
        }

        $duplicado = $this->contratistas->porDocumento($documento);

        if ($duplicado !== null && (int) $duplicado['id'] !== $id) {
            return $this->volverConError("Ya existe un contratista con el documento {$documento}: " . $duplicado['nombre_completo'] . '.');
        }

        $clase = $r->getInt('clase_riesgo_arl');
        $campos = [
            'tipo_documento' => in_array($r->getString('tipo_documento'), ['CC', 'CE', 'PPT', 'NIT'], true) ? $r->getString('tipo_documento') : 'CC',
            'numero_documento' => $documento,
            'lugar_expedicion' => $this->texto($r->getString('lugar_expedicion'), 80),
            'nombres' => mb_substr($nombres, 0, 120),
            'apellidos' => mb_substr($apellidos, 0, 120),
            'direccion' => $this->texto($r->getString('direccion'), 200),
            'telefono' => $this->texto($r->getString('telefono'), 30),
            'celular' => $this->texto($r->getString('celular'), 30),
            'correo' => $this->texto($r->getString('correo'), 150),
            'banco' => $this->texto($r->getString('banco'), 100),
            'tipo_cuenta' => in_array($r->getString('tipo_cuenta'), ['AHORROS', 'CORRIENTE'], true) ? $r->getString('tipo_cuenta') : null,
            'numero_cuenta' => $this->texto($r->getString('numero_cuenta'), 40),
            'clase_riesgo_arl' => ($clase >= 1 && $clase <= 5) ? $clase : null,
        ];

        if ($existente === null) {
            $id = $this->contratistas->crear($campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'cc_contratista', $id, "Contratista {$documento} creado");
        } else {
            $this->contratistas->actualizar($id, $campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_contratista', $id, "Contratista {$documento} actualizado");
        }

        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Contratista guardado.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function cambiarActivo(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $contratista = $this->contratistas->porId($id);

        if ($contratista === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $activar = !((bool) $contratista['activo']);
        $this->contratistas->cambiarActivo($id, $activar);
        $this->pantalla->auditar(
            $peticion,
            AccionAuditoria::CambioEstado,
            'cc_contratista',
            $id,
            'Contratista ' . $contratista['numero_documento'] . ($activar ? ' reactivado' : ' desactivado'),
        );
        $this->pantalla->guardarEnLaSesion('cc_mensaje', $activar ? 'Contratista reactivado.' : 'Contratista desactivado: ya no puede entrar al portal.');

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
