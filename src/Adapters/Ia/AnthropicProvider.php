<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Ports\AiProvider;
use App\Ports\PeticionIa;
use App\Ports\ProveedorIaException;
use App\Ports\RespuestaIa;

/**
 * Adaptador para la API de Anthropic (Claude), incluida la lectura de
 * imágenes para el texto alternativo.
 */
final readonly class AnthropicProvider implements AiProvider
{
    private const string URL = 'https://api.anthropic.com/v1/messages';
    private const string VERSION = '2023-06-01';

    public function __construct(
        private ClienteHttpJson $http,
        private string $claveApi,
        private string $modelo,
    ) {
    }

    public function sugerir(PeticionIa $peticion): RespuestaIa
    {
        $contenido = [];

        if ($peticion->imagen !== null) {
            $contenido[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $peticion->imagen->mime,
                    'data' => $peticion->imagen->base64,
                ],
            ];
        }

        $contenido[] = ['type' => 'text', 'text' => $peticion->mensaje];

        $datos = $this->http->post('Anthropic', self::URL, [
            'x-api-key: ' . $this->claveApi,
            'anthropic-version: ' . self::VERSION,
        ], [
            'model' => $this->modelo,
            'max_tokens' => $peticion->maxTokens,
            'system' => $peticion->instrucciones,
            'messages' => [
                ['role' => 'user', 'content' => $contenido],
            ],
        ]);

        $texto = $datos['content'][0]['text'] ?? null;

        if (!is_string($texto) || trim($texto) === '') {
            throw ProveedorIaException::porRespuestaInvalida('Anthropic');
        }

        return new RespuestaIa(
            texto: trim($texto),
            tokensEntrada: (int) ($datos['usage']['input_tokens'] ?? 0),
            tokensSalida: (int) ($datos['usage']['output_tokens'] ?? 0),
        );
    }
}
