<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\AutorizacionService;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\AuditoriaRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portada del panel: qué hay pendiente y qué pasó hace poco.
 *
 * Hasta la Fase 9 mostraba un texto fijo que decía que el panel «todavía no
 * tiene módulos», cuatro fases después de tenerlos todos. Ahora muestra el
 * estado real: lo que espera respuesta, lo que está sin publicar y los últimos
 * movimientos del registro de auditoría.
 */
final readonly class PanelController
{
    public function __construct(
        private PantallaAdmin $pantalla,
        private Sesion $sesion,
        private AutorizacionService $autorizacion,
        private Vistas $vistas,
        private Connection $conexion,
        private AuditoriaRepository $auditoria,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        $usuarioId = $this->sesion->usuarioId();

        $contenido = $this->vistas->render('admin/panel', [
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'cifras' => $this->cifras(),
            'movimientos' => $this->auditoria->ultimos(8),
            // Las tarjetas son atajos: enseñarle a un redactor «2 usuarios
            // activos» que al pulsar responde 403 no es informar, es marear.
            'puede' => fn (string $permiso): bool => $this->autorizacion->puede($usuarioId, $permiso),
        ]);

        return $this->pantalla->pagina('Inicio', $contenido);
    }

    /**
     * Lo que de verdad reclama atención hoy.
     *
     * Consultas de conteo directas y no repositorios: cada cifra viene de un
     * módulo distinto e inyectar seis repositorios en la portada para contar
     * filas sería cargar medio sistema en cada entrada al panel.
     *
     * @return array<string, int>
     */
    private function cifras(): array
    {
        return [
            'tramitesAbiertos' => $this->contar(
                "SELECT COUNT(*) FROM tramites t
                   JOIN tramite_estados e ON e.id = t.estado_id
                  WHERE e.es_final = 0",
            ),
            'noticiasBorrador' => $this->contar(
                "SELECT COUNT(*) FROM noticias
                  WHERE estado_aprobacion <> 'publicado' AND eliminado_en IS NULL",
            ),
            'documentosSinPublicar' => $this->contar(
                "SELECT COUNT(*) FROM documentos WHERE estado = 'borrador' AND eliminado_en IS NULL",
            ),
            'usuariosActivos' => $this->contar('SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND eliminado_en IS NULL'),
            'passwordProvisional' => $this->contar(
                'SELECT COUNT(*) FROM usuarios WHERE debe_cambiar_pass = 1 AND activo = 1 AND eliminado_en IS NULL',
            ),
            'cuentasBloqueadas' => $this->contar('SELECT COUNT(*) FROM usuarios WHERE bloqueado_hasta > NOW()'),
        ];
    }

    /**
     * Un módulo que aún no exista en la base no debe tumbar la portada: el
     * conteo que falle vale cero y el resto del tablero se dibuja igual.
     */
    private function contar(string $sql): int
    {
        try {
            return (int) $this->conexion->fetchOne($sql);
        } catch (\Throwable) {
            return 0;
        }
    }
}
