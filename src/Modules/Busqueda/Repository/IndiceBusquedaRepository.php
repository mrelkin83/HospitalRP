<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\Repository;

use App\Modules\Busqueda\Entity\TipoIndice;
use Doctrine\DBAL\Connection;

final readonly class IndiceBusquedaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Inserta o refresca una entrada. La clave natural es (tipo, entidad_id).
     */
    public function guardar(
        TipoIndice $tipo,
        int $entidadId,
        string $titulo,
        ?string $resumen,
        ?string $contenido,
        string $url,
    ): void {
        $this->conexion->executeStatement(
            'INSERT INTO indice_busqueda (tipo, entidad_id, titulo, resumen, contenido, url, peso)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                titulo = VALUES(titulo), resumen = VALUES(resumen),
                contenido = VALUES(contenido), url = VALUES(url), peso = VALUES(peso)',
            [$tipo->value, $entidadId, $titulo, $resumen, $contenido, $url, $tipo->peso()],
        );
    }

    public function eliminar(TipoIndice $tipo, int $entidadId): void
    {
        $this->conexion->delete('indice_busqueda', [
            'tipo' => $tipo->value,
            'entidad_id' => $entidadId,
        ]);
    }

    public function vaciarTipo(TipoIndice $tipo): void
    {
        $this->conexion->delete('indice_busqueda', ['tipo' => $tipo->value]);
    }

    /**
     * Búsqueda FULLTEXT en modo booleano con cada palabra como prefijo
     * (`certificado*` casa con «certificados»): FULLTEXT no hace stemming en
     * español y el ciudadano no tiene por qué acertar singular o plural. La
     * relevancia textual se multiplica por el peso editorial del tipo, así lo
     * accionable (trámites, servicios) sale antes que una noticia que repita
     * más veces el término.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $termino, int $limite = 60): array
    {
        $palabras = preg_split('/\s+/u', trim($termino)) ?: [];
        $expresion = implode(' ', array_map(
            // Los operadores booleanos del usuario se descartan: es una
            // caja de búsqueda ciudadana, no una consola.
            static fn (string $p): string => preg_replace('/[+\-<>~*"()@]/u', '', $p) . '*',
            array_filter($palabras, static fn (string $p): bool => mb_strlen($p) >= 3),
        ));

        if (trim($expresion, '* ') === '') {
            return [];
        }

        // El ORDER BY usa el alias: repetir el MATCH dentro de una
        // multiplicación dispara el error 1690 (DOUBLE out of range) en
        // MySQL 8 cuando el optimizador reutiliza la pista FTS.
        return $this->conexion->fetchAllAssociative(
            'SELECT tipo, entidad_id, titulo, resumen, url,
                    MATCH (titulo, resumen, contenido) AGAINST (? IN BOOLEAN MODE) AS relevancia
               FROM indice_busqueda
              WHERE MATCH (titulo, resumen, contenido) AGAINST (? IN BOOLEAN MODE)
              ORDER BY relevancia * (1 + peso / 100) DESC
              LIMIT ' . $limite,
            [$expresion, $expresion],
        );
    }

    /**
     * URLs públicas para el sitemap, la más reciente primero por si dos
     * entradas comparten URL (FAQ, datasets).
     *
     * @return list<array<string, mixed>>
     */
    public function urlsParaSitemap(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT url, MAX(actualizado_en) AS actualizado_en
               FROM indice_busqueda
              GROUP BY url
              ORDER BY url',
        );
    }

    /**
     * El registro tiene un tope global por minuto: sin él, un script podría
     * llenar el disco a punta de términos aleatorios (rate limiting de
     * búsqueda, CLAUDE.md §6). Pasado el tope la búsqueda sigue
     * funcionando; solo deja de registrarse.
     */
    public function registrarBusqueda(string $termino, int $resultados): void
    {
        $recientes = (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM busquedas_log WHERE creado_en >= NOW() - INTERVAL 1 MINUTE',
        );

        if ($recientes >= 60) {
            return;
        }

        $this->conexion->insert('busquedas_log', [
            'termino' => mb_substr($termino, 0, 255),
            'resultados' => $resultados,
        ]);
    }

    /**
     * Términos buscados sin resultado en los últimos 90 días, para que
     * comunicaciones cree la FAQ o el contenido que falta.
     *
     * @return list<array<string, mixed>>
     */
    public function terminosSinResultado(int $limite = 20): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT termino, COUNT(*) AS veces, MAX(creado_en) AS ultima_vez
               FROM busquedas_log
              WHERE resultados = 0 AND creado_en >= NOW() - INTERVAL 90 DAY
              GROUP BY termino
              ORDER BY veces DESC, ultima_vez DESC
              LIMIT ' . $limite,
        );
    }
}
