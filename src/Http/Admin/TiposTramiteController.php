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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tipos de trámite configurables (criterio de la Fase 5: crear uno nuevo
 * sin tocar código). Un tipo nuevo nace con los cuatro estados estándar;
 * sus campos se agregan y quitan desde aquí.
 */
final readonly class TiposTramiteController
{
    private const array TIPOS_CAMPO = ['texto', 'texto_largo', 'numero', 'fecha', 'correo', 'telefono', 'select'];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private TipoTramiteRepository $tipos,
        private DependenciaRepository $dependencias,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $tipos = [];

        foreach ($this->tipos->listarParaPanel() as $tipo) {
            $tipos[] = $this->tipos->porId($tipo->id);
        }

        return $this->pagina($this->vistas->render('admin/tramites/tipos', [
            'tipos' => array_filter($tipos),
            'dependencias' => $this->dependencias->activas(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'El tipo necesita nombre y un término en días mayor que cero.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $nombre = trim($r->getString('nombre'));
        $dias = $r->getInt('dias_termino');

        if ($nombre === '' || $dias < 1) {
            return new RedirectResponse('/admin/tipos-tramite?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 200),
            'descripcion' => $r->getString('descripcion') !== '' ? trim($r->getString('descripcion')) : null,
            'dependencia_id' => $r->getString('dependencia_id') !== '' ? $r->getInt('dependencia_id') : null,
            'dias_termino' => $dias,
            'tipo_dias' => $r->getString('tipo_dias') === 'calendario' ? 'calendario' : 'habiles',
            'fundamento_legal' => $r->getString('fundamento_legal') !== '' ? trim($r->getString('fundamento_legal')) : null,
            'permite_anonimo' => $r->getBoolean('permite_anonimo') ? 1 : 0,
            'requiere_adjunto' => $r->getBoolean('requiere_adjunto') ? 1 : 0,
            'max_adjuntos' => max(1, min(10, $r->getInt('max_adjuntos', 3))),
            'notificar_correos' => $r->getString('notificar_correos') !== '' ? trim($r->getString('notificar_correos')) : null,
            'activo' => $r->getBoolean('activo') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->tipos->porId($id) === null) {
                return new RedirectResponse('/admin/tipos-tramite', Response::HTTP_SEE_OTHER);
            }

            $this->tipos->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->tipos->crear($campos);
            $accion = AccionAuditoria::Creacion;

            // Un tipo sin estados no puede radicar: nace con el flujo estándar.
            foreach ([
                ['recibido', 'Recibido', 'Tu solicitud fue radicada y está en fila para revisión.', '#0030C9', 1, 0, 1],
                ['en_gestion', 'En gestión', 'Un funcionario está gestionando tu solicitud.', '#92400E', 0, 0, 1],
                ['respondido', 'Respondido', 'Tu solicitud fue respondida por el medio que elegiste.', '#166B4C', 0, 0, 1],
                ['cerrado', 'Cerrado', 'La solicitud quedó cerrada.', '#4B5563', 0, 1, 0],
            ] as $orden => [$codigo, $nombreEstado, $descripcionPublica, $color, $inicial, $final, $notifica]) {
                $this->tipos->crearEstado([
                    'tipo_tramite_id' => $id,
                    'codigo' => $codigo,
                    'nombre' => $nombreEstado,
                    'descripcion_publica' => $descripcionPublica,
                    'color' => $color,
                    'orden' => $orden,
                    'es_inicial' => $inicial,
                    'es_final' => $final,
                    'notifica_ciudadano' => $notifica,
                ]);
            }
        }

        $this->auditar($peticion, $accion, $id, sprintf(
            '%s el tipo de trámite «%s» (%d días %s)',
            $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
            $nombre,
            $dias,
            $campos['tipo_dias'],
        ));

        return new RedirectResponse('/admin/tipos-tramite?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function crearCampo(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $tipoId = $peticion->attributes->getInt('id');
        $r = $peticion->request;
        $etiqueta = trim($r->getString('etiqueta'));
        $tipoCampo = $r->getString('tipo_campo');

        if ($this->tipos->porId($tipoId) === null || $etiqueta === '' || !in_array($tipoCampo, self::TIPOS_CAMPO, true)) {
            return new RedirectResponse('/admin/tipos-tramite?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $opciones = array_values(array_filter(array_map(trim(...), explode(',', $r->getString('opciones')))));

        $campoId = $this->tipos->crearCampo([
            'tipo_tramite_id' => $tipoId,
            'codigo' => 'c' . substr((string) hrtime(true), -10),
            'etiqueta' => mb_substr($etiqueta, 0, 200),
            'ayuda' => $r->getString('ayuda') !== '' ? trim($r->getString('ayuda')) : null,
            'tipo_campo' => $tipoCampo,
            'opciones' => $tipoCampo === 'select' && $opciones !== []
                ? json_encode($opciones, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null,
            'requerido' => $r->getBoolean('requerido') ? 1 : 0,
            'orden' => $r->getInt('orden', 99),
        ]);

        $this->auditar($peticion, AccionAuditoria::Creacion, $campoId, sprintf(
            'Añadió el campo «%s» al tipo de trámite %d',
            $etiqueta,
            $tipoId,
        ));

        return new RedirectResponse('/admin/tipos-tramite?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminarCampo(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $campoId = $peticion->attributes->getInt('id');
        $this->tipos->eliminarCampo($campoId);
        $this->auditar($peticion, AccionAuditoria::Eliminacion, $campoId, sprintf('Eliminó el campo %d de un tipo de trámite', $campoId));

        return new RedirectResponse('/admin/tipos-tramite?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'tipos_tramite',
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/tipos-tramite',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Tipos de trámite',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
