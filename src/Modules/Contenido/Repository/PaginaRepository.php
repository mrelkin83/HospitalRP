<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Modules\Contenido\Entity\Pagina;
use Doctrine\DBAL\Connection;

final readonly class PaginaRepository
{
    private const string VISIBLE_PUBLICO = "estado_aprobacion = 'publicado'
        AND eliminado_en IS NULL
        AND (publicar_en IS NULL OR publicar_en <= NOW())
        AND (despublicar_en IS NULL OR despublicar_en > NOW())";

    public function __construct(private Connection $conexion)
    {
    }

    public function publicaPorRuta(string $ruta): ?Pagina
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM paginas WHERE ruta = ? AND ' . self::VISIBLE_PUBLICO,
            ['/' . ltrim($ruta, '/')],
        );

        return $fila === false ? null : Pagina::desdeFila($fila);
    }

    public function contarVista(int $id): void
    {
        $this->conexion->executeStatement('UPDATE paginas SET vistas = vistas + 1 WHERE id = ?', [$id]);
    }
}
