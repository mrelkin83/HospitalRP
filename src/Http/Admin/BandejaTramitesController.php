<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Repository\DependenciaRepository;
use App\Modules\Tramites\Repository\TipoTramiteRepository;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Modules\Tramites\Service\GestionTramiteService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bandeja de trámites: lo vencido y por vencer primero, filtros simples,
 * asignación y gestión con notas públicas o internas.
 */
final readonly class BandejaTramitesController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private TramiteRepository $tramites,
        private TipoTramiteRepository $tipos,
        private DependenciaRepository $dependencias,
        private GestionTramiteService $gestion,
        private Connection $conexion,
        private AuditoriaService $auditoria,
    ) {
    }

    public function bandeja(Request $peticion): Response
    {
        $q = $peticion->query;

        $filtros = [
            'tipo' => $q->getInt('tipo') > 0 ? $q->getInt('tipo') : null,
            'estado' => $q->getString('estado') !== '' ? $q->getString('estado') : null,
            'asignado' => $q->getInt('asignado') > 0 ? $q->getInt('asignado') : null,
            'vencimiento' => in_array($q->getString('vencimiento'), ['vencidos', 'proximos'], true)
                ? $q->getString('vencimiento')
                : null,
            'q' => $q->getString('q') !== '' ? $q->getString('q') : null,
        ];

        return $this->pagina('Bandeja de trámites', $this->vistas->render('admin/tramites/bandeja', [
            'tramites' => $this->tramites->bandeja($filtros),
            'tipos' => $this->tipos->listarParaPanel(),
            'filtros' => $filtros,
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
        ]));
    }

    public function detalle(Request $peticion): Response
    {
        $tramite = $this->tramites->porId($peticion->attributes->getInt('id'));

        if ($tramite === null) {
            return $this->pagina('No encontrado', $this->vistas->render('admin/mensaje', [
                'tipo' => 'error',
                'mensaje' => 'Ese trámite no existe.',
                'volverA' => '/admin/tramites',
            ]), Response::HTTP_NOT_FOUND);
        }

        $tipo = $this->tipos->porId((int) $tramite['tipo_tramite_id']);

        return $this->pagina('Trámite ' . (string) $tramite['radicado'], $this->vistas->render('admin/tramites/detalle', [
            'tramite' => $tramite,
            'tipo' => $tipo,
            'valores' => $this->tramites->valores((int) $tramite['id']),
            'adjuntos' => $this->tramites->adjuntos((int) $tramite['id']),
            'seguimientos' => $this->tramites->seguimientos((int) $tramite['id'], soloPublicos: false),
            'dependencias' => $this->dependencias->activas(),
            'usuarios' => $this->usuariosActivos(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function cambiarEstado(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $nota = trim($peticion->request->getString('nota'));

        $this->gestion->cambiarEstado(
            $id,
            $peticion->request->getInt('estado_id'),
            $nota !== '' ? $nota : null,
            $peticion->request->getBoolean('visible_ciudadano'),
            Actor::desde($this->sesion, $peticion),
        );

        return new RedirectResponse('/admin/tramites/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function asignar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');

        $this->gestion->asignar(
            $id,
            $peticion->request->getInt('asignado_a') > 0 ? $peticion->request->getInt('asignado_a') : null,
            $peticion->request->getInt('dependencia_id') > 0 ? $peticion->request->getInt('dependencia_id') : null,
            Actor::desde($this->sesion, $peticion),
        );

        return new RedirectResponse('/admin/tramites/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /**
     * Marca una solicitud como trasladada a otra entidad o con acceso negado
     * (ITA 6.10.b y 6.10.d).
     *
     * Sin esta pantalla el informe trimestral reportaría siempre cero negadas
     * y cero traslados, que es justo la cifra que el evaluador del índice
     * contrasta contra el resto del expediente.
     *
     * Negar el acceso sin causal no se guarda: la Ley 1712 (arts. 18, 19 y 26)
     * exige que toda negativa sea motivada por escrito, así que el formulario
     * no puede permitir lo que la ley prohíbe.
     */
    public function marcarAcceso(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $r = $peticion->request;

        $negado = $r->getBoolean('acceso_negado');
        $causal = trim($r->getString('causal_negacion'));
        $traslado = trim($r->getString('trasladado_a'));

        if ($negado && $causal === '') {
            return new RedirectResponse('/admin/tramites/' . $id . '?sin_causal=1', Response::HTTP_SEE_OTHER);
        }

        $this->conexion->update('tramites', [
            'acceso_negado' => $negado ? 1 : 0,
            'causal_negacion' => $negado ? mb_substr($causal, 0, 300) : null,
            'trasladado_a' => $traslado !== '' ? mb_substr($traslado, 0, 200) : null,
        ], ['id' => $id]);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Actualizacion,
            entidad: 'tramites',
            entidadId: $id,
            descripcion: $negado
                ? sprintf('Acceso negado a la información. Causal: %s', mb_substr($causal, 0, 200))
                : 'Marca de acceso a la información actualizada',
            usuarioId: $this->sesion->usuarioId(),
            usuarioNombre: $this->sesion->usuarioNombre(),
        ));

        return new RedirectResponse('/admin/tramites/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function agregarNota(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $nota = trim($peticion->request->getString('nota'));

        if ($nota !== '') {
            $this->gestion->agregarNota($id, $nota, $peticion->request->getBoolean('visible_ciudadano'), Actor::desde($this->sesion, $peticion));
        }

        return new RedirectResponse('/admin/tramites/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function usuariosActivos(): array
    {
        /** @var list<array{id: int|string, nombre: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, nombre FROM usuarios WHERE activo = 1 AND eliminado_en IS NULL ORDER BY nombre',
        );

        return array_map(
            static fn (array $f): array => ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre']],
            $filas,
        );
    }

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina('Sesión expirada', $this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/tramites',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $titulo, string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => $titulo,
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
