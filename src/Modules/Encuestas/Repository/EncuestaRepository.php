<?php

declare(strict_types=1);

namespace App\Modules\Encuestas\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Encuestas de satisfacción. Entraron al alcance el 2026-09-14 (CLAUDE.md §7).
 *
 * Tres reglas que no se negocian:
 *
 * 1. **Anónimas.** No se guarda quién respondió. `huella` es un hash con sal
 *    para frenar el reenvío desde el mismo navegador y no permite volver a la
 *    persona.
 * 2. **Sin dato clínico** (CLAUDE.md §2.6). El panel puede redactar la
 *    pregunta que quiera, pero el módulo no ofrece campo de diagnóstico,
 *    documento ni número de historia.
 * 3. **Resultados agregados.** Nunca se listan respuestas individuales de
 *    preguntas de escala u opción: solo conteos. El texto libre se lee en el
 *    panel para atender sugerencias, y por eso el formulario avisa de que no
 *    se escriban datos personales ahí.
 */
final readonly class EncuestaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function abiertas(): array
    {
        return $this->conexion->fetchAllAssociative(
            "SELECT id, titulo, slug, descripcion
               FROM encuestas
              WHERE estado = 'abierta'
                AND eliminado_en IS NULL
                AND (apertura IS NULL OR apertura <= NOW())
                AND (cierre IS NULL OR cierre > NOW())
              ORDER BY creado_en DESC",
        );
    }

    /** @return ?array<string, mixed> */
    public function abiertaPorSlug(string $slug): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT * FROM encuestas
              WHERE slug = ? AND estado = 'abierta' AND eliminado_en IS NULL
                AND (apertura IS NULL OR apertura <= NOW())
                AND (cierre IS NULL OR cierre > NOW())",
            [$slug],
        );

        return $fila === false ? null : $fila;
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM encuestas WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> */
    public function todas(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT e.id, e.titulo, e.slug, e.estado, e.cierre,
                    (SELECT COUNT(*) FROM encuesta_respuestas r WHERE r.encuesta_id = e.id) AS respuestas
               FROM encuestas e
              WHERE e.eliminado_en IS NULL
              ORDER BY e.creado_en DESC',
        );
    }

    /**
     * @return list<array{id: int, enunciado: string, tipo: string, opciones: list<string>, obligatoria: bool}>
     */
    public function preguntasDe(int $encuestaId): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, enunciado, tipo, opciones, obligatoria
               FROM encuesta_preguntas WHERE encuesta_id = ? ORDER BY orden, id',
            [$encuestaId],
        );

        return array_map(static function (array $f): array {
            /** @var list<mixed> $opciones */
            $opciones = $f['opciones'] === null ? [] : (array) json_decode((string) $f['opciones'], true);

            return [
                'id' => (int) $f['id'],
                'enunciado' => (string) $f['enunciado'],
                'tipo' => (string) $f['tipo'],
                'opciones' => array_values(array_map(static fn (mixed $o): string => (string) $o, $opciones)),
                'obligatoria' => (bool) $f['obligatoria'],
            ];
        }, $filas);
    }

    /**
     * Guarda una respuesta completa en una transacción.
     *
     * Devuelve false si esa huella ya respondió: la clave única
     * (encuesta_id, huella) lo resuelve en la base y no en PHP, así que dos
     * envíos simultáneos del mismo navegador no crean dos respuestas.
     *
     * @param array<int, list<string>> $valores pregunta_id => valores
     */
    public function guardarRespuesta(int $encuestaId, ?string $huella, array $valores): bool
    {
        try {
            return (bool) $this->conexion->transactional(
                function (Connection $conexion) use ($encuestaId, $huella, $valores): bool {
                    $conexion->insert('encuesta_respuestas', [
                        'encuesta_id' => $encuestaId,
                        'huella' => $huella,
                    ]);
                    $respuestaId = (int) $conexion->lastInsertId();

                    foreach ($valores as $preguntaId => $lista) {
                        foreach ($lista as $valor) {
                            $conexion->insert('encuesta_valores', [
                                'respuesta_id' => $respuestaId,
                                'pregunta_id' => $preguntaId,
                                'valor' => mb_substr($valor, 0, 2000),
                            ]);
                        }
                    }

                    return true;
                },
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    public function yaRespondio(int $encuestaId, string $huella): bool
    {
        return false !== $this->conexion->fetchOne(
            'SELECT id FROM encuesta_respuestas WHERE encuesta_id = ? AND huella = ?',
            [$encuestaId, $huella],
        );
    }

    public function totalRespuestas(int $encuestaId): int
    {
        return (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM encuesta_respuestas WHERE encuesta_id = ?',
            [$encuestaId],
        );
    }

    /**
     * Resultados AGREGADOS de una pregunta cerrada: valor => conteo. Nunca
     * devuelve respuestas individuales.
     *
     * @return array<string, int>
     */
    public function conteoDe(int $preguntaId): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT valor, COUNT(*) AS total
               FROM encuesta_valores WHERE pregunta_id = ?
              GROUP BY valor ORDER BY total DESC, valor',
            [$preguntaId],
        );

        $conteo = [];

        foreach ($filas as $fila) {
            $conteo[(string) $fila['valor']] = (int) $fila['total'];
        }

        return $conteo;
    }

    /**
     * Texto libre de una pregunta abierta. Se lee en el panel para atender
     * sugerencias; no se publica en el sitio.
     *
     * @return list<string>
     */
    public function textosDe(int $preguntaId, int $limite = 200): array
    {
        return array_map(
            static fn (mixed $v): string => (string) $v,
            $this->conexion->fetchFirstColumn(
                'SELECT valor FROM encuesta_valores WHERE pregunta_id = ?
                  ORDER BY id DESC LIMIT ' . max(1, min($limite, 500)),
                [$preguntaId],
            ),
        );
    }
}
