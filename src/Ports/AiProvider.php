<?php

declare(strict_types=1);

namespace App\Ports;

/**
 * Puerto hacia el proveedor de IA (CLAUDE.md §6, «IA»): el panel solo conoce
 * esta interfaz. Cambiar de proveedor o de modelo es configuración, nunca
 * código de los módulos.
 */
interface AiProvider
{
    /**
     * @throws ProveedorIaException si el proveedor falla o responde basura
     */
    public function sugerir(PeticionIa $peticion): RespuestaIa;
}
