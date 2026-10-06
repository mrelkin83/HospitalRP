<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Entity;

/**
 * Definición configurable de un trámite (motor, no BPM): término legal,
 * campos y estados viven en base de datos, no en código.
 *
 * @phpstan-type Campo array{id: int, codigo: string, etiqueta: string, ayuda: ?string, tipo_campo: string, opciones: list<string>, requerido: bool}
 * @phpstan-type Estado array{id: int, codigo: string, nombre: string, descripcion_publica: ?string, color: ?string, es_inicial: bool, es_final: bool, notifica_ciudadano: bool, detiene_termino: bool}
 */
final readonly class TipoTramite
{
    /**
     * @param list<array{id: int, codigo: string, etiqueta: string, ayuda: ?string, tipo_campo: string, opciones: list<string>, requerido: bool}> $campos
     * @param list<array{id: int, codigo: string, nombre: string, descripcion_publica: ?string, color: ?string, es_inicial: bool, es_final: bool, notifica_ciudadano: bool, detiene_termino: bool}> $estados
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $slug,
        public ?string $descripcion,
        public ?string $instrucciones,
        public string $prefijoRadicado,
        public ?int $dependenciaId,
        public int $diasTermino,
        public bool $diasHabiles,
        public ?string $fundamentoLegal,
        public bool $permiteAnonimo,
        public bool $requiereAdjunto,
        public int $maxAdjuntos,
        public ?string $notificarCorreos,
        public bool $activo,
        public array $campos = [],
        public array $estados = [],
        // Costos asociados al trámite (ITA 7.1). Fue el único «No» de 2025:
        // la sección sacó 75/100 porque los costos los publicaba un contratista
        // y al terminar su contrato nadie los volvió a publicar. Como campo del
        // tipo de trámite, dejan de depender de un tercero.
        public bool $gratuito = true,
        public ?string $costoDetalle = null,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<array<string, mixed>> $campos
     * @param list<array<string, mixed>> $estados
     */
    public static function desdeFila(array $fila, array $campos = [], array $estados = []): self
    {
        return new self(
            id: (int) $fila['id'],
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
            descripcion: isset($fila['descripcion']) ? (string) $fila['descripcion'] : null,
            instrucciones: isset($fila['instrucciones']) ? (string) $fila['instrucciones'] : null,
            prefijoRadicado: (string) $fila['prefijo_radicado'],
            dependenciaId: isset($fila['dependencia_id']) ? (int) $fila['dependencia_id'] : null,
            diasTermino: (int) $fila['dias_termino'],
            diasHabiles: ((string) $fila['tipo_dias']) === 'habiles',
            fundamentoLegal: isset($fila['fundamento_legal']) ? (string) $fila['fundamento_legal'] : null,
            permiteAnonimo: (bool) $fila['permite_anonimo'],
            requiereAdjunto: (bool) $fila['requiere_adjunto'],
            maxAdjuntos: (int) $fila['max_adjuntos'],
            notificarCorreos: isset($fila['notificar_correos']) ? (string) $fila['notificar_correos'] : null,
            activo: (bool) $fila['activo'],
            campos: array_map(self::campoDesdeFila(...), $campos),
            estados: array_map(self::estadoDesdeFila(...), $estados),
            // Sin la columna (una fila construida a mano en pruebas) se asume
            // gratuito: es el caso de casi todos los trámites del hospital y
            // el error seguro, porque nunca anuncia un cobro inexistente.
            gratuito: (bool) ($fila['gratuito'] ?? true),
            costoDetalle: isset($fila['costo_detalle']) && (string) $fila['costo_detalle'] !== ''
                ? (string) $fila['costo_detalle']
                : null,
        );
    }

    /**
     * @return ?array{id: int, codigo: string, nombre: string, descripcion_publica: ?string, color: ?string, es_inicial: bool, es_final: bool, notifica_ciudadano: bool, detiene_termino: bool}
     */
    public function estadoInicial(): ?array
    {
        foreach ($this->estados as $estado) {
            if ($estado['es_inicial']) {
                return $estado;
            }
        }

        return $this->estados[0] ?? null;
    }

    /**
     * @param array<string, mixed> $f
     * @return array{id: int, codigo: string, etiqueta: string, ayuda: ?string, tipo_campo: string, opciones: list<string>, requerido: bool}
     */
    private static function campoDesdeFila(array $f): array
    {
        $opciones = [];

        if (isset($f['opciones']) && is_string($f['opciones'])) {
            $decodificado = json_decode($f['opciones'], true);

            if (is_array($decodificado)) {
                $opciones = array_values(array_filter($decodificado, is_string(...)));
            }
        }

        return [
            'id' => (int) $f['id'],
            'codigo' => (string) $f['codigo'],
            'etiqueta' => (string) $f['etiqueta'],
            'ayuda' => isset($f['ayuda']) ? (string) $f['ayuda'] : null,
            'tipo_campo' => (string) $f['tipo_campo'],
            'opciones' => $opciones,
            'requerido' => (bool) $f['requerido'],
        ];
    }

    /**
     * @param array<string, mixed> $f
     * @return array{id: int, codigo: string, nombre: string, descripcion_publica: ?string, color: ?string, es_inicial: bool, es_final: bool, notifica_ciudadano: bool, detiene_termino: bool}
     */
    private static function estadoDesdeFila(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'codigo' => (string) $f['codigo'],
            'nombre' => (string) $f['nombre'],
            'descripcion_publica' => isset($f['descripcion_publica']) ? (string) $f['descripcion_publica'] : null,
            'color' => isset($f['color']) ? (string) $f['color'] : null,
            'es_inicial' => (bool) $f['es_inicial'],
            'es_final' => (bool) $f['es_final'],
            'notifica_ciudadano' => (bool) $f['notifica_ciudadano'],
            'detiene_termino' => (bool) $f['detiene_termino'],
        ];
    }
}
