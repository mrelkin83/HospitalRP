<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Core\Support\Cifrador;
use App\Ports\AiProvider;
use App\Ports\FabricaAiProvider;
use App\Ports\ProveedorIaException;
use Doctrine\DBAL\Connection;

/**
 * Construye el proveedor de IA según lo configurado DESDE EL PANEL
 * (patrón del módulo de proveedores del proyecto SEPUD): las claves de
 * API y la URL base se guardan cifradas en la tabla `configuracion` y se
 * administran en Sistema → Asistente de IA, sin tocar el .env. Las
 * variables IA_CLAVE_* del .env quedan como respaldo de arranque: si el
 * panel no tiene clave para un proveedor, se usa la del entorno.
 *
 * Anthropic habla su protocolo nativo y admite una URL base alternativa
 * (pasarelas compatibles como OneProvider); el resto del catálogo habla
 * chat/completions de OpenAI.
 */
final readonly class FabricaProveedorIa implements FabricaAiProvider
{
    /**
     * Proveedores compatibles con OpenAI (catálogo del SEPUD).
     *
     * @var array<string, array{nombre: string, url: string, modelo: string, campo_max: string}>
     */
    public const array COMPATIBLES = [
        'openai' => ['nombre' => 'OpenAI', 'url' => 'https://api.openai.com/v1/chat/completions', 'modelo' => 'gpt-4o-mini', 'campo_max' => 'max_completion_tokens'],
        'deepseek' => ['nombre' => 'DeepSeek', 'url' => 'https://api.deepseek.com/chat/completions', 'modelo' => 'deepseek-chat', 'campo_max' => 'max_tokens'],
        'kimi' => ['nombre' => 'Kimi (Moonshot AI)', 'url' => 'https://api.moonshot.ai/v1/chat/completions', 'modelo' => 'kimi-k2-0905-preview', 'campo_max' => 'max_tokens'],
        'glm' => ['nombre' => 'GLM (Z.ai / Zhipu)', 'url' => 'https://api.z.ai/api/paas/v4/chat/completions', 'modelo' => 'glm-4.6', 'campo_max' => 'max_tokens'],
        'gemini' => ['nombre' => 'Google Gemini', 'url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'modelo' => 'gemini-2.5-flash', 'campo_max' => 'max_tokens'],
        'openrouter' => ['nombre' => 'OpenRouter', 'url' => 'https://openrouter.ai/api/v1/chat/completions', 'modelo' => 'openai/gpt-4o-mini', 'campo_max' => 'max_tokens'],
        'groq' => ['nombre' => 'Groq', 'url' => 'https://api.groq.com/openai/v1/chat/completions', 'modelo' => 'llama-3.3-70b-versatile', 'campo_max' => 'max_tokens'],
        'mistral' => ['nombre' => 'Mistral', 'url' => 'https://api.mistral.ai/v1/chat/completions', 'modelo' => 'mistral-large-latest', 'campo_max' => 'max_tokens'],
        'xai' => ['nombre' => 'xAI (Grok)', 'url' => 'https://api.x.ai/v1/chat/completions', 'modelo' => 'grok-3-mini', 'campo_max' => 'max_tokens'],
    ];

    /**
     * Catálogo estático de respaldo cuando el GET /models no es posible.
     *
     * @var array<string, list<string>>
     */
    public const array MODELOS_CONOCIDOS = [
        'anthropic' => ['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5'],
        'openai' => ['gpt-5', 'gpt-5-mini', 'gpt-4.1', 'gpt-4o', 'gpt-4o-mini'],
        'deepseek' => ['deepseek-chat', 'deepseek-reasoner'],
        'kimi' => ['kimi-k2-0905-preview', 'kimi-k2-turbo-preview'],
        'glm' => ['glm-4.6', 'glm-4.5', 'glm-4.5-air'],
        'gemini' => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite'],
        'openrouter' => ['openai/gpt-4o-mini', 'anthropic/claude-sonnet-4.6', 'google/gemini-2.5-flash'],
        'groq' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
        'mistral' => ['mistral-large-latest', 'mistral-small-latest'],
        'xai' => ['grok-4', 'grok-3', 'grok-3-mini'],
        'personalizado' => [],
    ];

    public function __construct(
        private ClienteHttpJson $http,
        private Connection $conexion,
        private Cifrador $cifrador,
        private string $claveEnvAnthropic,
        private string $claveEnvOpenai,
    ) {
    }

    public function tieneClave(string $proveedor): bool
    {
        return isset(FabricaAiProvider::PROVEEDORES[$proveedor]) && $this->claveDe($proveedor) !== '';
    }

    public function crear(string $proveedor, string $modelo): AiProvider
    {
        $clave = $this->claveDe($proveedor);

        if (!isset(FabricaAiProvider::PROVEEDORES[$proveedor])) {
            throw new ProveedorIaException(sprintf('Proveedor de IA desconocido: «%s».', $proveedor));
        }

        if ($clave === '') {
            throw new ProveedorIaException(sprintf(
                'No hay clave de API para «%s»: guárdela en Sistema → Asistente de IA.',
                FabricaAiProvider::PROVEEDORES[$proveedor],
            ));
        }

        $modelo = trim($modelo) !== '' ? trim($modelo) : $this->modeloPredeterminado($proveedor);

        if ($proveedor === 'anthropic') {
            $urlBase = $this->urlBase();

            return new AnthropicProvider(
                $this->http,
                $clave,
                $modelo,
                $urlBase !== '' ? $urlBase : AnthropicProvider::URL_OFICIAL,
                $urlBase !== '' ? 'Anthropic (pasarela)' : 'Anthropic',
            );
        }

        if ($proveedor === 'personalizado') {
            $urlBase = rtrim($this->urlBase(), '/');

            if (preg_match('#^https?://#i', $urlBase) !== 1) {
                throw new ProveedorIaException('El proveedor «Personalizado» necesita una URL base válida (https://…/v1) en Sistema → Asistente de IA.');
            }

            return new OpenAiProvider($this->http, $clave, $modelo, $urlBase . '/chat/completions', 'Personalizado', 'max_tokens');
        }

        $registro = self::COMPATIBLES[$proveedor];

        return new OpenAiProvider($this->http, $clave, $modelo, $registro['url'], $registro['nombre'], $registro['campo_max']);
    }

    /**
     * Los modelos del proveedor: en vivo desde su GET /models cuando hay
     * clave, o el catálogo estático de referencia si no la hay o la
     * consulta falla (patrón del SEPUD).
     *
     * @return array{origen: string, modelos: list<string>, nota: ?string}
     */
    public function listarModelos(string $proveedor): array
    {
        if (!isset(FabricaAiProvider::PROVEEDORES[$proveedor])) {
            return ['origen' => 'estatico', 'modelos' => [], 'nota' => 'Proveedor no reconocido.'];
        }

        $respaldo = self::MODELOS_CONOCIDOS[$proveedor] ?? [];
        $clave = $this->claveDe($proveedor);

        if ($clave === '') {
            return ['origen' => 'estatico', 'modelos' => $respaldo, 'nota' => 'Sin clave guardada: lista de referencia.'];
        }

        try {
            $modelos = $this->modelosEnVivo($proveedor, $clave);
        } catch (\Throwable $e) {
            return ['origen' => 'estatico', 'modelos' => $respaldo, 'nota' => 'No se pudo consultar la API (' . $e->getMessage() . '): lista de referencia.'];
        }

        return $modelos === []
            ? ['origen' => 'estatico', 'modelos' => $respaldo, 'nota' => 'La API no devolvió modelos: lista de referencia.']
            : ['origen' => 'api', 'modelos' => $modelos, 'nota' => null];
    }

    public static function desde(\App\Core\Config\Env $env, Connection $conexion, Cifrador $cifrador): self
    {
        return new self(
            new ClienteHttpJson(),
            $conexion,
            $cifrador,
            $env->texto('IA_CLAVE_ANTHROPIC', ''),
            $env->texto('IA_CLAVE_OPENAI', ''),
        );
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * La clave del proveedor: primero la guardada (cifrada) desde el
     * panel; si no hay, la del .env como respaldo de arranque.
     */
    private function claveDe(string $proveedor): string
    {
        $guardada = $this->valorConfig('ia.clave_' . $proveedor);

        if ($guardada !== '') {
            $descifrada = $this->cifrador->descifrar($guardada);

            if (is_string($descifrada) && $descifrada !== '') {
                return $descifrada;
            }
        }

        return match ($proveedor) {
            'anthropic' => $this->claveEnvAnthropic,
            'openai' => $this->claveEnvOpenai,
            default => '',
        };
    }

    /** La URL base alternativa (pasarela Anthropic o endpoint personalizado). */
    private function urlBase(): string
    {
        return trim($this->valorConfig('ia.url_base'));
    }

    private function valorConfig(string $clave): string
    {
        $valor = $this->conexion->fetchOne('SELECT valor FROM configuracion WHERE clave = ?', [$clave]);

        return is_string($valor) ? $valor : '';
    }

    private function modeloPredeterminado(string $proveedor): string
    {
        return match (true) {
            $proveedor === 'anthropic' => 'claude-sonnet-5',
            isset(self::COMPATIBLES[$proveedor]) => self::COMPATIBLES[$proveedor]['modelo'],
            default => '',
        };
    }

    /** @return list<string> */
    private function modelosEnVivo(string $proveedor, string $clave): array
    {
        if ($proveedor === 'anthropic') {
            $urlBase = $this->urlBase();
            $datos = $this->http->get(
                'Anthropic',
                rtrim($urlBase !== '' ? $urlBase : AnthropicProvider::URL_OFICIAL, '/') . '/v1/models?limit=100',
                ['x-api-key: ' . $clave, 'anthropic-version: 2023-06-01'],
            );
        } else {
            // Los compatibles derivan su /models del propio endpoint de chat.
            $urlChat = $proveedor === 'personalizado'
                ? rtrim($this->urlBase(), '/') . '/chat/completions'
                : self::COMPATIBLES[$proveedor]['url'];
            $urlModelos = (string) preg_replace('#/chat/completions$#', '/models', $urlChat);
            $datos = $this->http->get(
                FabricaAiProvider::PROVEEDORES[$proveedor],
                $urlModelos,
                ['Authorization: Bearer ' . $clave],
            );
        }

        $modelos = [];

        foreach (is_array($datos['data'] ?? null) ? $datos['data'] : [] as $modelo) {
            $id = is_array($modelo) ? (string) ($modelo['id'] ?? '') : '';

            if ($id !== '') {
                $modelos[] = $id;
            }
        }

        return $modelos;
    }
}
