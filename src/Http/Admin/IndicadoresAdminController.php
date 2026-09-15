<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Indicadores\Repository\IndicadorRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Carga de indicadores estadísticos desde el panel.
 *
 * Cada fila es la lectura de un indicador en un mes. Publicar el dato de marzo
 * es añadir una fila, no editar la de febrero: así la serie histórica sale
 * sola y el ciudadano puede comparar.
 *
 * Nada se publica al crearse. Una cifra a medio cargar en la página de
 * estadísticas de un hospital público es peor que no tener la página.
 */
final readonly class IndicadoresAdminController
{
    private const string TITULO = 'Indicadores';
    private const string RUTA = '/admin/indicadores';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private IndicadorRepository $indicadores,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/indicadores/listado', [
            'indicadores' => $this->indicadores->todos(),
            'grupos' => IndicadorRepository::GRUPOS,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido')
                ? 'El nombre, el periodo y el valor son obligatorios, y el valor debe ser un número.'
                : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $nombre = trim($r->getString('nombre'));
        $periodo = $this->periodo($r->getString('periodo'));

        // El valor se valida como número explícitamente: un casteo silencioso
        // convertiría «pendiente» en un 0, y un 0 publicado no es un campo
        // vacío, es una cifra falsa.
        $valorCrudo = trim($r->getString('valor'));

        if ($nombre === '' || $periodo === null || !is_numeric($valorCrudo)) {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $metaCruda = trim($r->getString('meta'));
        $unidad = trim($r->getString('unidad'));
        $fuente = trim($r->getString('fuente'));
        $nota = trim($r->getString('nota'));
        $grupo = $r->getString('grupo');

        $campos = [
            'grupo' => array_key_exists($grupo, IndicadorRepository::GRUPOS) ? $grupo : 'otro',
            'nombre' => mb_substr($nombre, 0, 200),
            'periodo' => $periodo,
            'valor' => (float) $valorCrudo,
            'unidad' => $unidad !== '' ? mb_substr($unidad, 0, 40) : null,
            'meta' => is_numeric($metaCruda) ? (float) $metaCruda : null,
            'fuente' => $fuente !== '' ? mb_substr($fuente, 0, 200) : null,
            'nota' => $nota !== '' ? mb_substr($nota, 0, 500) : null,
            'orden' => $r->getInt('orden'),
            'publicado' => $r->getBoolean('publicado') ? 1 : 0,
            'destacado' => $r->getBoolean('destacado') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->indicadores->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->indicadores->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
            $descripcion = sprintf('Editó el indicador «%s» de %s', $nombre, $periodo);
        } else {
            $campos['creado_por'] = $this->pantalla->usuarioId();
            $id = $this->indicadores->guardar($campos);
            $accion = AccionAuditoria::Creacion;
            $descripcion = sprintf('Cargó el indicador «%s» de %s', $nombre, $periodo);
        }

        $this->pantalla->auditar($peticion, $accion, 'indicadores', $id, $descripcion);

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $fila = $this->indicadores->porId($id);

        if ($fila !== null) {
            $this->indicadores->eliminar($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'indicadores', $id, sprintf(
                'Eliminó el indicador «%s» de %s',
                (string) $fila['nombre'],
                (string) $fila['periodo'],
            ));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /**
     * `<input type="month">` llega como «2026-03»; se guarda el día 1 porque
     * el periodo es el mes completo, no una fecha concreta.
     */
    private function periodo(string $valor): ?string
    {
        $valor = trim($valor);

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor) !== 1) {
            return null;
        }

        return $valor . '-01';
    }
}
