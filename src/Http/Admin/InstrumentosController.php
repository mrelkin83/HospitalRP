<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Repository\DependenciaRepository;
use App\Modules\Transparencia\Entity\TipoInstrumento;
use App\Modules\Transparencia\Repository\InstrumentoInformacionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel → Transparencia → Instrumentos de gestión de la información.
 *
 * Los tres instrumentos de la Ley 1712 art. 6 lit. d (ITA 9.1). El formulario
 * se construye a partir de `TipoInstrumento::columnas()`, así que un cambio en
 * el criterio ITA se refleja solo en el enum: aquí no hay lista de campos que
 * se pueda quedar desactualizada respecto de la vista pública o del CSV.
 *
 * Solo se aceptan las columnas del tipo que se está editando. Mandar por POST
 * un campo de otro instrumento (p. ej. `plazo_reserva` en el Esquema de
 * Publicación) no lo escribe: el filtro es la lista blanca del enum, no la
 * confianza en el formulario.
 */
final readonly class InstrumentosController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private InstrumentoInformacionRepository $instrumentos,
        private DependenciaRepository $dependencias,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $tipo = TipoInstrumento::porSlug((string) $peticion->attributes->get('tipo'))
            ?? TipoInstrumento::RegistroActivos;

        return $this->pagina($this->vistas->render('admin/instrumentos/listado', [
            'tipo' => $tipo,
            'tipos' => TipoInstrumento::todos(),
            'filas' => $this->instrumentos->listarParaPanel($tipo),
            'dependencias' => $this->dependencias->activas(),
            'mensaje' => match (true) {
                $peticion->query->has('guardado') => 'Cambios guardados.',
                $peticion->query->has('eliminado') => 'Registro eliminado.',
                default => null,
            },
            'error' => $peticion->query->has('invalido')
                ? 'El registro necesita al menos un nombre o título y el medio de conservación.'
                : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]), tituloTipo: $tipo);
    }

    public function guardar(Request $peticion): Response
    {
        $tipo = TipoInstrumento::porSlug((string) $peticion->attributes->get('tipo'));

        if ($tipo === null) {
            return new RedirectResponse('/admin/transparencia/instrumentos', Response::HTTP_SEE_OTHER);
        }

        if (($rechazo = $this->exigirCsrf($peticion, $tipo)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $destino = '/admin/transparencia/instrumentos/' . $tipo->slug();

        // Lista blanca: solo las columnas que el ITA pide para ESTE instrumento.
        $campos = [];

        foreach (array_keys($tipo->columnas()) as $columna) {
            $valor = trim($r->getString($columna));
            $campos[$columna] = $valor === '' ? null : $valor;
        }

        // `nombre` es NOT NULL. En el Registro de Activos el ITA no pide un
        // título aparte de la categoría, así que la categoría hace de nombre.
        $campos['nombre'] ??= $campos['categoria'] ?? null;

        $medio = trim($r->getString('medio_conservacion'));

        if (($campos['nombre'] ?? null) === null || $medio === '') {
            return new RedirectResponse($destino . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos['medio_conservacion'] = $medio;
        $campos['idioma'] = trim($r->getString('idioma')) !== '' ? trim($r->getString('idioma')) : 'Español';
        $campos['tipo'] = $tipo->value;
        $campos['orden'] = $r->getInt('orden');
        $campos['publicado'] = $r->getBoolean('publicado') ? 1 : 0;
        $campos['dependencia_id'] = $r->getString('dependencia_id') !== ''
            ? $r->getInt('dependencia_id')
            : null;

        if (array_key_exists('alcance_excepcion', $campos)
            && !in_array($campos['alcance_excepcion'], ['total', 'parcial'], true)) {
            $campos['alcance_excepcion'] = null;
        }

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->instrumentos->porId($id) === null) {
                return new RedirectResponse($destino, Response::HTTP_SEE_OTHER);
            }

            unset($campos['tipo']);
            $this->instrumentos->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->instrumentos->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->registrar($accion, $id, sprintf(
            '%s: «%s»',
            $tipo->nombre(),
            (string) $campos['nombre'],
        ));

        return new RedirectResponse($destino . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        $tipo = TipoInstrumento::porSlug((string) $peticion->attributes->get('tipo'));

        if ($tipo === null) {
            return new RedirectResponse('/admin/transparencia/instrumentos', Response::HTTP_SEE_OTHER);
        }

        if (($rechazo = $this->exigirCsrf($peticion, $tipo)) !== null) {
            return $rechazo;
        }

        $destino = '/admin/transparencia/instrumentos/' . $tipo->slug();
        $id = $peticion->attributes->getInt('id');
        $fila = $this->instrumentos->porId($id);

        if ($fila === null) {
            return new RedirectResponse($destino, Response::HTTP_SEE_OTHER);
        }

        $this->instrumentos->eliminar($id);
        $this->registrar(AccionAuditoria::Eliminacion, $id, sprintf(
            '%s: «%s»',
            $tipo->nombre(),
            (string) $fila['nombre'],
        ));

        return new RedirectResponse($destino . '?eliminado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function registrar(AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $this->sesion->iniciar();

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'instrumentos_informacion',
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $this->sesion->usuarioId(),
            usuarioNombre: $this->sesion->usuarioNombre(),
        ));
    }

    private function exigirCsrf(Request $peticion, TipoInstrumento $tipo): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/transparencia/instrumentos/' . $tipo->slug(),
        ]), Response::HTTP_FORBIDDEN, $tipo);
    }

    private function pagina(
        string $contenido,
        int $estado = Response::HTTP_OK,
        ?TipoInstrumento $tituloTipo = null,
    ): Response {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => $tituloTipo?->nombre() ?? 'Instrumentos de gestión de la información',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
