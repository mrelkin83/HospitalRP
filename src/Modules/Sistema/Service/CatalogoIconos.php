<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Service;

/**
 * Iconos que el panel puede elegir para una tarjeta de acceso rápido.
 *
 * Una clave por icono y el trazo SVG aquí, en el servidor. El panel guarda
 * solo la clave: si dejáramos pegar SVG desde un formulario estaríamos
 * metiendo marcado ajeno en la portada —un `<svg>` admite `<script>` y
 * `onload`— y la CSP no debería ser la única línea de defensa.
 *
 * Los trazos son los que ya usaba la portada cuando las ocho tarjetas estaban
 * escritas a mano; se movieron aquí tal cual.
 */
final readonly class CatalogoIconos
{
    /** clave => [etiqueta para el panel, trazo del path] */
    private const array ICONOS = [
        'calendario' => ['Calendario (citas)', 'M8 2v3M16 2v3M3.5 9h17M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z'],
        'cruz' => ['Cruz (urgencias)', 'M12 4v16M4 12h16'],
        'estetoscopio' => ['Estetoscopio (especialidades)', 'M6 3v6a6 6 0 0 0 12 0V3M12 15v3a3.5 3.5 0 0 0 7 0v-2'],
        'persona' => ['Persona (directorio)', 'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM5 20.5a7 7 0 0 1 14 0'],
        'documento' => ['Documento (trámites)', 'M7 3.5h7L18.5 8v12a1 1 0 0 1-1 1h-10.5a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1ZM14 3.5V8h4.5M8.5 12.5h7M8.5 16h7'],
        'lupa' => ['Lupa (consultas)', 'M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13ZM15.5 15.5 21 21'],
        'mensaje' => ['Mensaje (PQRSDF)', 'M4 5.5h16v11H9l-5 4v-15ZM8 9.5h8M8 12.5h5'],
        'escudo' => ['Escudo (transparencia)', 'M12 3 5 6v5c0 4.5 3 8.5 7 9.5 4-1 7-5 7-9.5V6l-7-3ZM9 12l2 2 4-4'],
        'corazon' => ['Corazón (programas)', 'M12 20s-7-4.3-7-9.2A4 4 0 0 1 12 8a4 4 0 0 1 7 2.8C19 15.7 12 20 12 20Z'],
        'hospital' => ['Hospital (sedes)', 'M4 21h16M6 21V7l6-4 6 4v14M10 11h4M12 9v4'],
        'telefono' => ['Teléfono (contacto)', 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.1 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z'],
        'grafico' => ['Gráfico (estadísticas)', 'M4 20V10M10 20V4M16 20v-7M22 20H2'],
    ];

    /** Con el que se pinta una tarjeta cuyo icono no existe o está vacío. */
    public const string POR_OMISION = 'documento';

    /**
     * @return array<string, string> clave => etiqueta legible, para el selector del panel
     */
    public function etiquetas(): array
    {
        return array_map(static fn (array $icono): string => $icono[0], self::ICONOS);
    }

    /**
     * Trazo del icono. Una clave desconocida —icono retirado del catálogo,
     * dato viejo— cae en el de por omisión en vez de dejar un hueco.
     */
    public function trazo(?string $clave): string
    {
        return (self::ICONOS[$clave] ?? self::ICONOS[self::POR_OMISION])[1];
    }

    public function existe(?string $clave): bool
    {
        return $clave !== null && isset(self::ICONOS[$clave]);
    }
}
