<?php

declare(strict_types=1);

namespace App\Ports;

/**
 * Frontera de construcción del proveedor: el panel pide «el proveedor
 * configurado» sin saber cómo se fabrica ni dónde viven las claves.
 */
interface FabricaAiProvider
{
    /**
     * Todos los proveedores seleccionables desde el panel (catálogo traído
     * del módulo de IA del proyecto SEPUD): Anthropic habla su protocolo
     * nativo (y una URL base opcional le enchufa pasarelas compatibles,
     * como OneProvider); el resto hablan chat/completions de OpenAI.
     *
     * @var array<string, string> proveedor => etiqueta para el panel
     */
    public const array PROVEEDORES = [
        'anthropic' => 'Anthropic (Claude) — oficial o pasarela compatible',
        'openai' => 'OpenAI',
        'deepseek' => 'DeepSeek',
        'kimi' => 'Kimi (Moonshot AI)',
        'glm' => 'GLM (Z.ai / Zhipu)',
        'gemini' => 'Google Gemini',
        'openrouter' => 'OpenRouter',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'xai' => 'xAI (Grok)',
        'personalizado' => 'Personalizado (endpoint compatible OpenAI)',
    ];

    public function tieneClave(string $proveedor): bool;

    /**
     * @throws ProveedorIaException si el proveedor es desconocido o no tiene clave
     */
    public function crear(string $proveedor, string $modelo): AiProvider;

    /**
     * Los modelos disponibles del proveedor: en vivo desde su GET /models
     * cuando hay clave, o el catálogo estático de referencia si no.
     *
     * @return array{origen: string, modelos: list<string>, nota: ?string}
     */
    public function listarModelos(string $proveedor): array;
}
