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
 *
 * La base de la URL es configurable porque el MISMO protocolo lo hablan
 * pasarelas compatibles (OneProvider, por ejemplo): cambiar de proveedor
 * es cambiar la base y la clave, nunca el código.
 */
final readonly class AnthropicProvider implements AiProvider
{
    public const string URL_OFICIAL = 'https://api.anthropic.com';
    private const string VERSION = '2023-06-01';

    public function __construct(
        private ClienteHttpJson $http,
        private string $claveApi,
        private string $modelo,
        private string $urlBase = self::URL_OFICIAL,
        private string $nombre = 'Anthropic',
    ) {
    }

    public function sugerir(PeticionIa $peticion): RespuestaIa
    {
        $contenido = [];

        foreach ($peticion->todasLasImagenes() as $imagen) {
            $contenido[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $imagen->mime,
                    'data' => $imagen->base64,
                ],
            ];
        }

        $contenido[] = ['type' => 'text', 'text' => $peticion->mensaje];

        $datos = $this->http->post($this->nombre, rtrim($this->urlBase, '/') . '/v1/messages', [
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
            throw ProveedorIaException::porRespuestaInvalida($this->nombre);
        }

        return new RespuestaIa(
            texto: trim($texto),
            tokensEntrada: (int) ($datos['usage']['input_tokens'] ?? 0),
            tokensSalida: (int) ($datos['usage']['output_tokens'] ?? 0),
        );
    }
}
