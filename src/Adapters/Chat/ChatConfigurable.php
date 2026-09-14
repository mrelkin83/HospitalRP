<?php

declare(strict_types=1);

namespace App\Adapters\Chat;

use App\Core\Config\ConfigDinamica;
use App\Ports\ProveedorChat;

/**
 * Adaptador de chat gobernado por la configuración del panel.
 *
 * Nace **apagado**: `chat_activo` viene en falso y sin proveedor, así que el
 * sitio no carga nada de terceros hasta que un funcionario lo encienda a
 * conciencia. Es el mismo criterio con el que se sembró la IA en la Fase 8.
 *
 * No incrusta el script del proveedor a ciegas: solo acepta proveedores
 * conocidos e identificadores con forma conocida. Un campo de configuración
 * que termina dentro de un `<script>` es una inyección esperando ocurrir, y
 * esto es el sitio de un hospital.
 */
final readonly class ChatConfigurable implements ProveedorChat
{
    /** proveedor => plantilla de la URL del script, con %s por el identificador */
    private const array PROVEEDORES = [
        'chatroll' => 'https://static.chatroll.com/js/embed/%s.js',
        'tawk' => 'https://embed.tawk.to/%s/default',
    ];

    public function __construct(private ConfigDinamica $config)
    {
    }

    public function estaActivo(): bool
    {
        return $this->config->booleano('chat_activo', false)
            && isset(self::PROVEEDORES[$this->nombre()])
            && $this->identificador() !== '';
    }

    public function widget(): string
    {
        if (!$this->estaActivo()) {
            return '';
        }

        $url = sprintf(self::PROVEEDORES[$this->nombre()], $this->identificador());

        // El aviso lo pone el hospital, no el proveedor: de este mensaje
        // responde la entidad, y tiene que verse aunque el script falle.
        return '<aside class="chat-widget" aria-label="Chat de orientación">'
            . '<p class="chat-aviso">Este chat orienta sobre trámites, horarios y ubicación. '
            . '<strong>No atiende urgencias ni consultas médicas.</strong> '
            . 'Si es una urgencia, llama a los teléfonos de <a href="/urgencias">urgencias</a>.</p>'
            . '<script defer src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>'
            . '</aside>';
    }

    public function nombre(): string
    {
        return strtolower(trim($this->config->texto('chat_proveedor', '')));
    }

    /**
     * Solo letras, dígitos, guiones y barras. Cualquier otra cosa se descarta
     * entera en vez de intentar sanearla: si el identificador no tiene la
     * forma esperada, es que algo está mal configurado.
     */
    private function identificador(): string
    {
        $id = trim($this->config->texto('chat_id_widget', ''));

        return preg_match('/^[A-Za-z0-9_\-\/]{1,64}$/', $id) === 1 ? $id : '';
    }
}
