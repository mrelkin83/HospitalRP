<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Ports\AiProvider;
use App\Ports\PeticionIa;
use App\Ports\ProveedorIaException;
use App\Ports\RespuestaIa;

/**
 * Adaptador para TODA API que hable chat/completions de OpenAI: la propia
 * OpenAI y la familia de compatibles del catálogo (DeepSeek, Kimi, GLM,
 * Gemini, OpenRouter, Groq, Mistral, xAI, personalizado). Cambiar de
 * proveedor es cambiar URL, clave y nombre: nunca el código (patrón del
 * módulo de IA del proyecto SEPUD).
 */
final readonly class OpenAiProvider implements AiProvider
{
    public const string URL_OFICIAL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private ClienteHttpJson $http,
        private string $claveApi,
        private string $modelo,
        private string $url = self::URL_OFICIAL,
        private string $nombre = 'OpenAI',
        /** OpenAI moderno exige max_completion_tokens; el resto, max_tokens. */
        private string $campoMax = 'max_completion_tokens',
    ) {
    }

    public function sugerir(PeticionIa $peticion): RespuestaIa
    {
        $contenido = [];

        foreach ($peticion->todasLasImagenes() as $imagen) {
            $contenido[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => sprintf('data:%s;base64,%s', $imagen->mime, $imagen->base64),
                ],
            ];
        }

        $contenido[] = ['type' => 'text', 'text' => $peticion->mensaje];

        $datos = $this->http->post($this->nombre, $this->url, [
            'Authorization: Bearer ' . $this->claveApi,
        ], [
            'model' => $this->modelo,
            $this->campoMax => $peticion->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $peticion->instrucciones],
                ['role' => 'user', 'content' => $contenido],
            ],
        ]);

        $texto = $datos['choices'][0]['message']['content'] ?? null;

        if (!is_string($texto) || trim($texto) === '') {
            throw ProveedorIaException::porRespuestaInvalida($this->nombre);
        }

        return new RespuestaIa(
            texto: trim($texto),
            tokensEntrada: (int) ($datos['usage']['prompt_tokens'] ?? 0),
            tokensSalida: (int) ($datos['usage']['completion_tokens'] ?? 0),
        );
    }
}
