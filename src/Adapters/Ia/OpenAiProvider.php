<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Ports\AiProvider;
use App\Ports\PeticionIa;
use App\Ports\ProveedorIaException;
use App\Ports\RespuestaIa;

/**
 * Adaptador para la API de OpenAI (chat completions), segundo proveedor
 * exigido por el plan: cambiar de uno a otro es solo configuración.
 */
final readonly class OpenAiProvider implements AiProvider
{
    private const string URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private ClienteHttpJson $http,
        private string $claveApi,
        private string $modelo,
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

        $datos = $this->http->post('OpenAI', self::URL, [
            'Authorization: Bearer ' . $this->claveApi,
        ], [
            'model' => $this->modelo,
            'max_completion_tokens' => $peticion->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $peticion->instrucciones],
                ['role' => 'user', 'content' => $contenido],
            ],
        ]);

        $texto = $datos['choices'][0]['message']['content'] ?? null;

        if (!is_string($texto) || trim($texto) === '') {
            throw ProveedorIaException::porRespuestaInvalida('OpenAI');
        }

        return new RespuestaIa(
            texto: trim($texto),
            tokensEntrada: (int) ($datos['usage']['prompt_tokens'] ?? 0),
            tokensSalida: (int) ($datos['usage']['completion_tokens'] ?? 0),
        );
    }
}
