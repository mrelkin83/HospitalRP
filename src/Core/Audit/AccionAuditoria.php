<?php

declare(strict_types=1);

namespace App\Core\Audit;

/**
 * Acciones auditables del sistema.
 *
 * Enum y no VARCHAR libre en el código (CLAUDE.md §5): la columna `accion`
 * admite 60 caracteres, pero cada valor nuevo se declara aquí para que la
 * bandeja de auditoría del panel pueda filtrar y traducir sin sorpresas.
 * Se añaden casos a medida que las fases los necesiten; no se inventan antes.
 */
enum AccionAuditoria: string
{
    case Creacion = 'crear';
    case Actualizacion = 'actualizar';
    case Eliminacion = 'eliminar';
    case Restauracion = 'restaurar';

    // Publicación de contenido (Fase 1)
    case Publicacion = 'publicar';
    case Despublicacion = 'despublicar';
    case CambioEstado = 'cambio_estado';

    // Autenticación (Fase 0, paso 7)
    case InicioSesion = 'inicio_sesion';
    case CierreSesion = 'cierre_sesion';
    case IntentoFallido = 'intento_fallido';
    case BloqueoCuenta = 'bloqueo_cuenta';
}
