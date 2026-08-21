<?php

declare(strict_types=1);

namespace App\Ports;

/**
 * Frontera de construcción del proveedor: el panel pide «el proveedor
 * configurado» sin saber cómo se fabrica ni dónde viven las claves.
 */
interface FabricaAiProvider
{
    /** @var array<string, string> proveedor => etiqueta para el panel */
    public const array PROVEEDORES = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI',
    ];

    public function tieneClave(string $proveedor): bool;

    /**
     * @throws ProveedorIaException si el proveedor es desconocido o no tiene clave
     */
    public function crear(string $proveedor, string $modelo): AiProvider;
}
