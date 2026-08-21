<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Core\Config\Env;
use App\Ports\AiProvider;
use App\Ports\FabricaAiProvider;
use App\Ports\ProveedorIaException;

/**
 * Construye el proveedor según la configuración del panel. Las claves de
 * API llegan del .env: son credenciales de infraestructura, no contenido,
 * y jamás se muestran ni se guardan en base de datos.
 */
final readonly class FabricaProveedorIa implements FabricaAiProvider
{
    public function __construct(
        private ClienteHttpJson $http,
        private string $claveAnthropic,
        private string $claveOpenai,
    ) {
    }

    public static function desde(Env $env): self
    {
        return new self(
            new ClienteHttpJson(),
            $env->texto('IA_CLAVE_ANTHROPIC', ''),
            $env->texto('IA_CLAVE_OPENAI', ''),
        );
    }

    public function tieneClave(string $proveedor): bool
    {
        return match ($proveedor) {
            'anthropic' => $this->claveAnthropic !== '',
            'openai' => $this->claveOpenai !== '',
            default => false,
        };
    }

    public function crear(string $proveedor, string $modelo): AiProvider
    {
        if (!$this->tieneClave($proveedor)) {
            throw new ProveedorIaException(sprintf(
                'No hay clave de API configurada para «%s» (variable de entorno del servidor).',
                $proveedor,
            ));
        }

        return match ($proveedor) {
            'anthropic' => new AnthropicProvider($this->http, $this->claveAnthropic, $modelo),
            'openai' => new OpenAiProvider($this->http, $this->claveOpenai, $modelo),
            default => throw new ProveedorIaException(sprintf('Proveedor de IA desconocido: «%s».', $proveedor)),
        };
    }
}
