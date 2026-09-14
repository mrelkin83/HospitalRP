<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Busqueda\Entity\TipoIndice;
use App\Modules\Busqueda\Service\IndexadorService;
use App\Modules\Convocatorias\Repository\ConvocatoriaRepository;
use App\Modules\Sistema\Repository\DependenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convocatorias de empleo, contratación y proveedores desde el panel.
 *
 * El estado lo maneja un funcionario a mano y no el reloj: una convocatoria
 * sigue abierta hasta que alguien la cierra. Se hizo así a propósito porque
 * cerrar automáticamente por fecha deja al hospital sin margen cuando un
 * proceso se prorroga; al ciudadano se le muestran el estado y la fecha
 * juntos para que pueda reclamar si no coinciden.
 */
final readonly class ConvocatoriasAdminController
{
    private const string TITULO = 'Convocatorias';
    private const string RUTA = '/admin/convocatorias';

    private const array ESTADOS = [
        'proxima', 'abierta', 'en_evaluacion', 'cerrada', 'desierta', 'adjudicada',
    ];
    private const array TIPOS = ['empleo', 'contratacion', 'proveedores', 'otra'];

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ConvocatoriaRepository $convocatorias,
        private DependenciaRepository $dependencias,
        private IndexadorService $indexador,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/convocatorias/listado', [
            'convocatorias' => $this->convocatorias->todas(),
            'dependencias' => $this->dependencias->activas(),
            'estados' => self::ESTADOS,
            'tipos' => self::TIPOS,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido')
                ? 'El título y la descripción son obligatorios.'
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
        $titulo = trim($r->getString('titulo'));
        $descripcion = trim($r->getString('descripcion'));

        if ($titulo === '' || $descripcion === '') {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        // Estado y tipo se validan contra la lista blanca y no se confía al
        // ENUM: un valor fuera de rango debe caer en el valor seguro, no
        // reventar con un error de base de datos delante del funcionario.
        $urlExterna = trim($r->getString('url_externa'));

        $campos = [
            'titulo' => mb_substr($titulo, 0, 300),
            'descripcion' => $descripcion,
            'tipo' => in_array($r->getString('tipo'), self::TIPOS, true) ? $r->getString('tipo') : 'otra',
            'estado' => in_array($r->getString('estado'), self::ESTADOS, true) ? $r->getString('estado') : 'proxima',
            'dependencia_id' => $r->getString('dependencia_id') !== '' ? $r->getInt('dependencia_id') : null,
            'apertura' => $this->fecha($r->getString('apertura')),
            'cierre' => $this->fecha($r->getString('cierre')),
            'url_externa' => $urlExterna !== '' ? mb_substr($urlExterna, 0, 500) : null,
            'publicada' => $r->getBoolean('publicada') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->convocatorias->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->convocatorias->actualizar($id, $campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'convocatorias', $id, sprintf('Editó la convocatoria «%s»', $titulo));
        } else {
            $campos['creado_por'] = $this->pantalla->usuarioId();
            $id = $this->convocatorias->crear($campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'convocatorias', $id, sprintf('Creó la convocatoria «%s»', $titulo));
        }

        $this->indexador->reindexar(TipoIndice::Convocatoria, $id);

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $fila = $this->convocatorias->porId($id);

        if ($fila !== null) {
            $this->convocatorias->eliminar($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'convocatorias', $id, sprintf(
                'Eliminó la convocatoria «%s»',
                (string) $fila['titulo'],
            ));
            $this->indexador->reindexar(TipoIndice::Convocatoria, $id);
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /** `datetime-local` llega como «2026-05-01T09:00»; vacío es NULL, no «ahora». */
    private function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        $fecha = date_create_immutable(str_replace('T', ' ', $valor));

        return $fecha === false ? null : $fecha->format('Y-m-d H:i:s');
    }
}
