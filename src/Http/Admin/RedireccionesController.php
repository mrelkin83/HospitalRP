<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\RedireccionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirecciones de las URL del sitio anterior.
 *
 * Las 79 que sembró la Fase 7 cubren lo que se rastreó del Joomla, pero
 * siempre aparece un enlace citado en un oficio o en un acto administrativo
 * que nadie había visto. Sin esta pantalla, cada uno de esos 404 obliga a
 * pedirle al desarrollador que corra un comando de consola.
 *
 * La columna de visitas es lo más útil de la pantalla: dice qué enlaces
 * antiguos sigue usando la gente de verdad.
 */
final readonly class RedireccionesController
{
    private const string TITULO = 'Redirecciones';
    private const string RUTA = '/admin/redirecciones';

    /** 301 permanente, 302 temporal: no hay más casos razonables aquí. */
    private const array CODIGOS = [301, 302];

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private RedireccionRepository $redirecciones,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $busqueda = trim($peticion->query->getString('q'));

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/redirecciones/listado', [
            'redirecciones' => $this->redirecciones->todas($busqueda),
            'resumen' => $this->redirecciones->resumen(),
            'busqueda' => $busqueda,
            'codigos' => self::CODIGOS,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $this->errorDe($peticion->query->getString('error')),
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $origen = $this->ruta($r->getString('origen'));
        $destino = $this->ruta($r->getString('destino'));
        $codigo = in_array($r->getInt('codigo'), self::CODIGOS, true) ? $r->getInt('codigo') : 301;
        $activa = $r->getBoolean('activa');

        if ($origen === '' || $destino === '') {
            return $this->volverCon('datos');
        }

        // Un origen igual al destino es un bucle infinito servido por el
        // Kernel: el navegador corta a los veinte saltos y el ciudadano ve un
        // error del navegador, no del sitio.
        if ($origen === $destino) {
            return $this->volverCon('bucle');
        }

        $id = $peticion->attributes->getInt('id');

        if ($this->redirecciones->origenOcupado($origen, $id > 0 ? $id : null)) {
            return $this->volverCon('repetido');
        }

        if ($id > 0) {
            if ($this->redirecciones->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->redirecciones->actualizar($id, $origen, $destino, $codigo, $activa);
            $accion = AccionAuditoria::Actualizacion;
            $verbo = 'Editó';
        } else {
            $id = $this->redirecciones->crear($origen, $destino, $codigo, $activa);
            $accion = AccionAuditoria::Creacion;
            $verbo = 'Creó';
        }

        $this->pantalla->auditar($peticion, $accion, 'redirecciones', $id, sprintf(
            '%s la redirección %s → %s (%d)',
            $verbo,
            $origen,
            $destino,
            $codigo,
        ));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $fila = $this->redirecciones->porId($id);

        if ($fila !== null) {
            $this->redirecciones->eliminar($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'redirecciones', $id, sprintf(
                'Eliminó la redirección %s → %s',
                (string) $fila['origen'],
                (string) $fila['destino'],
            ));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * Normaliza a una ruta que el Kernel pueda comparar: siempre con barra
     * inicial y sin barra final, salvo la raíz. Un destino externo (http…) se
     * deja tal cual, que también es un caso legítimo.
     */
    private function ruta(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return '';
        }

        if (str_starts_with($valor, 'http://') || str_starts_with($valor, 'https://')) {
            return mb_substr($valor, 0, 500);
        }

        if (!str_starts_with($valor, '/')) {
            $valor = '/' . $valor;
        }

        if ($valor !== '/' && str_ends_with($valor, '/')) {
            $valor = rtrim($valor, '/');
        }

        return mb_substr($valor, 0, 500);
    }

    private function volverCon(string $error): Response
    {
        return new RedirectResponse(self::RUTA . '?error=' . $error, Response::HTTP_SEE_OTHER);
    }

    private function errorDe(string $codigo): ?string
    {
        return match ($codigo) {
            'datos' => 'El origen y el destino son obligatorios.',
            'repetido' => 'Ya existe una redirección con ese origen.',
            'bucle' => 'El origen y el destino no pueden ser iguales: sería un bucle.',
            default => null,
        };
    }
}
