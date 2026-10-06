<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Anti-SPAM del formulario público de trámites (ITA 13.3.c).
 *
 * Dos mecanismos, los dos invisibles para quien radica de verdad:
 *
 * 1. **Campo trampa.** Un campo de texto que el CSS esconde y que ninguna
 *    persona ve. Los robots rellenan todo lo que encuentran; si llega con
 *    contenido, no es una persona. Va con `autocomplete="off"` y
 *    `tabindex="-1"` para que ni el gestor de contraseñas ni el tabulador lo
 *    toquen, y su etiqueta dice «déjalo vacío» para quien use lector de
 *    pantalla.
 *
 * 2. **Límite por IP.** Un máximo de radicaciones por ventana de tiempo.
 *
 * Se descartó el captcha de terceros a propósito: rompería la CSP del sitio
 * (`script-src 'self'`), mandaría la IP del ciudadano a otra empresa sin su
 * consentimiento, y pondría una barrera de accesibilidad justo en el canal
 * por donde se ejerce el derecho de petición.
 *
 * El límite es deliberadamente holgado. Equivocarse por el lado estricto aquí
 * significa impedirle a alguien radicar una queja, que es peor que recibir
 * algo de basura: por eso 10 por hora y no 3.
 */
final readonly class ProteccionSpam
{
    /** Nombre del campo trampa. Suena a campo real para que el robot lo llene. */
    public const string CAMPO_TRAMPA = 'confirmar_correo';

    private const int MAX_POR_VENTANA = 10;
    private const int MINUTOS_VENTANA = 60;

    public function __construct(private Connection $conexion)
    {
    }

    /** El campo trampa llegó con contenido: no lo llenó una persona. */
    public function pareceRobot(Request $peticion): bool
    {
        return trim($peticion->request->getString(self::CAMPO_TRAMPA)) !== '';
    }

    /**
     * ¿Esta IP ya pasó del máximo en la última hora?
     *
     * Se cuenta ANTES de registrar el intento actual, así que el intento
     * número 11 es el primero que se rechaza.
     */
    public function excedeLimite(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        $intentos = $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM tramite_intentos
              WHERE ip = ? AND creado_en > (NOW() - INTERVAL ? MINUTE)',
            [$ip, self::MINUTOS_VENTANA],
        );

        return (int) $intentos >= self::MAX_POR_VENTANA;
    }

    public function registrarIntento(?string $ip): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $this->conexion->insert('tramite_intentos', ['ip' => $ip]);
    }

    /**
     * Borra los registros fuera de la ventana. Lo llama la tarea programada:
     * la tabla solo necesita la última hora, y guardar la IP más tiempo del
     * necesario sería retener un dato personal sin razón (Ley 1581).
     */
    public function purgar(): int
    {
        return (int) $this->conexion->executeStatement(
            'DELETE FROM tramite_intentos WHERE creado_en < (NOW() - INTERVAL ? MINUTE)',
            [self::MINUTOS_VENTANA],
        );
    }

    public function mensajeDeLimite(): string
    {
        return sprintf(
            'Recibimos varias solicitudes desde esta conexión en la última hora. '
            . 'Espera un momento e intenta de nuevo, o radica presencialmente en la '
            . 'ventanilla del hospital. Si necesitas ayuda, llámanos: tu derecho de '
            . 'petición no se pierde. (Máximo %d solicitudes por hora.)',
            self::MAX_POR_VENTANA,
        );
    }
}
