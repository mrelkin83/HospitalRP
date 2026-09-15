<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\AuditoriaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bandeja del registro de auditoría.
 *
 * El sistema lleva anotando cada acción desde la Fase 0, pero ese registro
 * solo se podía leer con un SELECT contra la base. Para una entidad pública
 * eso no basta: cuando un ente de control pregunta quién despublicó un
 * documento, la respuesta tiene que poder darla el administrador del sitio
 * desde el panel, no el desarrollador.
 *
 * Solo consulta y filtros. No hay borrar ni editar a propósito: un registro de
 * auditoría que la aplicación pueda alterar no prueba nada.
 */
final readonly class AuditoriaAdminController
{
    private const string TITULO = 'Auditoría';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private AuditoriaRepository $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $q = $peticion->query;

        $filtros = [
            'usuario' => trim($q->getString('usuario')),
            'accion' => trim($q->getString('accion')),
            'entidad' => trim($q->getString('entidad')),
            'desde' => $this->fecha($q->getString('desde')),
            'hasta' => $this->fecha($q->getString('hasta')),
        ];

        $resultado = $this->auditoria->buscar($filtros, max(1, $q->getInt('pagina', 1)));
        $valores = $this->auditoria->valoresDeFiltro();

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/auditoria/listado', [
            'resultado' => $resultado,
            'filtros' => $filtros,
            'acciones' => $valores['acciones'],
            'entidades' => $valores['entidades'],
            'etiquetas' => $this->etiquetas(),
        ]));
    }

    /**
     * Nombre legible de cada acción registrada.
     *
     * La columna guarda el valor del enum («cambio_permisos»); el funcionario
     * que revisa la bandeja lee «Cambio de permisos». Lo que no esté en el
     * enum se muestra tal cual: es dato histórico y no se oculta.
     *
     * @return array<string, string>
     */
    private function etiquetas(): array
    {
        return [
            AccionAuditoria::Creacion->value => 'Creación',
            AccionAuditoria::Actualizacion->value => 'Edición',
            AccionAuditoria::Eliminacion->value => 'Eliminación',
            AccionAuditoria::Restauracion->value => 'Restauración',
            AccionAuditoria::Publicacion->value => 'Publicación',
            AccionAuditoria::Despublicacion->value => 'Despublicación',
            AccionAuditoria::CambioEstado->value => 'Cambio de estado',
            AccionAuditoria::RevocacionAutorizacion->value => 'Revocación de autorización',
            AccionAuditoria::InicioSesion->value => 'Inicio de sesión',
            AccionAuditoria::CierreSesion->value => 'Cierre de sesión',
            AccionAuditoria::IntentoFallido->value => 'Intento fallido',
            AccionAuditoria::BloqueoCuenta->value => 'Bloqueo de cuenta',
            AccionAuditoria::CambioPassword->value => 'Cambio de contraseña',
            AccionAuditoria::RestablecerPassword->value => 'Restablecimiento de contraseña',
            AccionAuditoria::DesbloqueoCuenta->value => 'Desbloqueo de cuenta',
            AccionAuditoria::CambioPermisos->value => 'Cambio de permisos',
            AccionAuditoria::CambioConfiguracion->value => 'Cambio de configuración',
        ];
    }

    /**
     * Acepta solo AAAA-MM-DD. Una fecha malformada se descarta en vez de
     * llegar a la consulta: el filtro debe ignorarse, no reventar la pantalla.
     */
    private function fecha(string $valor): string
    {
        $valor = trim($valor);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : '';
    }
}
