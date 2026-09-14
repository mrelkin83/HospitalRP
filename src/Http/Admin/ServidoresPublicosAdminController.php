<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Directorio\Repository\ServidorPublicoRepository;
use App\Modules\Sistema\Repository\DependenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directorio de servidores públicos desde el panel (ITA 1.6 y 1.6.1).
 *
 * El formulario solo pide datos institucionales. No hay campo de documento,
 * dirección ni teléfono personal, y no es un olvido: la Ley 1712 obliga a
 * publicar el cargo y la remuneración, no la vida privada de quien ocupa el
 * cargo. La hoja de vida se enlaza al SIGEP, que es la fuente oficial.
 */
final readonly class ServidoresPublicosAdminController
{
    private const string TITULO = 'Servidores públicos';
    private const string RUTA = '/admin/servidores-publicos';

    private const array VINCULACIONES = [
        'planta', 'libre_nombramiento', 'provisional', 'contrato', 'otro',
    ];

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ServidorPublicoRepository $servidores,
        private DependenciaRepository $dependencias,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/servidores-publicos/listado', [
            'servidores' => $this->servidores->todos(),
            'dependencias' => $this->dependencias->activas(),
            'vinculaciones' => self::VINCULACIONES,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido')
                ? 'Los nombres, apellidos y el cargo son obligatorios.'
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
        $nombres = trim($r->getString('nombres'));
        $apellidos = trim($r->getString('apellidos'));
        $cargo = trim($r->getString('cargo'));

        if ($nombres === '' || $apellidos === '' || $cargo === '') {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombres' => mb_substr($nombres, 0, 120),
            'apellidos' => mb_substr($apellidos, 0, 120),
            'cargo' => mb_substr($cargo, 0, 200),
            'dependencia_id' => $r->getString('dependencia_id') !== '' ? $r->getInt('dependencia_id') : null,
            'oficina' => $this->opcional($r->getString('oficina'), 200),
            'tipo_vinculacion' => in_array($r->getString('tipo_vinculacion'), self::VINCULACIONES, true)
                ? $r->getString('tipo_vinculacion')
                : 'planta',
            'formacion_academica' => $this->opcional($r->getString('formacion_academica'), 300),
            'correo_institucional' => $this->opcional($r->getString('correo_institucional'), 160),
            'telefono_institucional' => $this->opcional($r->getString('telefono_institucional'), 60),
            'url_hoja_vida' => $this->opcional($r->getString('url_hoja_vida'), 500),
            'escala_salarial' => $this->opcional($r->getString('escala_salarial'), 160),
            'url_escala_salarial' => $this->opcional($r->getString('url_escala_salarial'), 500),
            'orden' => $r->getInt('orden'),
            'publicado' => $r->getBoolean('publicado') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');
        $nombreCompleto = $apellidos . ' ' . $nombres;

        if ($id > 0) {
            if ($this->servidores->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->servidores->actualizar($id, $campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'servidores_publicos', $id, sprintf('Editó la ficha de %s', $nombreCompleto));
        } else {
            $id = $this->servidores->crear($campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'servidores_publicos', $id, sprintf('Creó la ficha de %s', $nombreCompleto));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $fila = $this->servidores->porId($id);

        if ($fila !== null) {
            $this->servidores->eliminar($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'servidores_publicos', $id, sprintf(
                'Eliminó la ficha de %s %s',
                (string) $fila['apellidos'],
                (string) $fila['nombres'],
            ));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    private function opcional(string $valor, int $largo): ?string
    {
        $valor = trim($valor);

        return $valor === '' ? null : mb_substr($valor, 0, $largo);
    }
}
