<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * Lectura del registro de auditoría para la bandeja del panel.
 *
 * Solo lectura, y a propósito: AuditoriaService escribe, esto consulta.
 * Un registro de auditoría que la aplicación pueda editar o borrar no sirve
 * de prueba ante un ente de control, así que aquí no hay ni update ni delete.
 */
final readonly class AuditoriaRepository
{
    public const int POR_PAGINA = 50;

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @param array{usuario?: string, accion?: string, entidad?: string, desde?: string, hasta?: string} $filtros
     * @return array{registros: list<array<string, mixed>>, total: int, paginas: int, pagina: int}
     */
    public function buscar(array $filtros, int $pagina = 1): array
    {
        [$where, $parametros] = $this->condiciones($filtros);

        $total = (int) $this->conexion->fetchOne(
            "SELECT COUNT(*) FROM auditoria a {$where}",
            $parametros,
        );

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = max(1, min($pagina, $paginas));

        /** @var list<array<string, mixed>> $registros */
        $registros = $this->conexion->fetchAllAssociative(
            "SELECT a.*, INET6_NTOA(a.ip) AS ip_texto
               FROM auditoria a
               {$where}
           ORDER BY a.id DESC
              LIMIT " . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA),
            $parametros,
        );

        return [
            'registros' => $registros,
            'total' => $total,
            'paginas' => $paginas,
            'pagina' => $pagina,
        ];
    }

    /**
     * Valores presentes en el histórico, para armar los desplegables del
     * filtro sin inventar opciones que no existen en los datos.
     *
     * @return array{acciones: list<string>, entidades: list<string>}
     */
    public function valoresDeFiltro(): array
    {
        /** @var list<string> $acciones */
        $acciones = $this->conexion->fetchFirstColumn('SELECT DISTINCT accion FROM auditoria ORDER BY accion');
        /** @var list<string> $entidades */
        $entidades = $this->conexion->fetchFirstColumn('SELECT DISTINCT entidad FROM auditoria ORDER BY entidad');

        return ['acciones' => $acciones, 'entidades' => $entidades];
    }

    /**
     * Últimos movimientos, para el tablero de inicio del panel.
     *
     * @return list<array<string, mixed>>
     */
    public function ultimos(int $limite = 8): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT usuario_nombre, accion, entidad, descripcion, creado_en
               FROM auditoria
           ORDER BY id DESC
              LIMIT ' . max(1, min($limite, 50)),
        );

        return $filas;
    }

    /**
     * @param array<string, string> $filtros
     * @return array{0: string, 1: array<string, string>}
     */
    private function condiciones(array $filtros): array
    {
        $condiciones = [];
        $parametros = [];

        if (($filtros['usuario'] ?? '') !== '') {
            $condiciones[] = 'a.usuario_nombre LIKE :usuario';
            $parametros['usuario'] = '%' . $filtros['usuario'] . '%';
        }

        if (($filtros['accion'] ?? '') !== '') {
            $condiciones[] = 'a.accion = :accion';
            $parametros['accion'] = $filtros['accion'];
        }

        if (($filtros['entidad'] ?? '') !== '') {
            $condiciones[] = 'a.entidad = :entidad';
            $parametros['entidad'] = $filtros['entidad'];
        }

        if (($filtros['desde'] ?? '') !== '') {
            $condiciones[] = 'a.creado_en >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }

        if (($filtros['hasta'] ?? '') !== '') {
            // Hasta el final del día elegido: quien filtra «hasta el 30» espera
            // ver lo ocurrido el 30, no todo lo anterior a su medianoche.
            $condiciones[] = 'a.creado_en <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $parametros];
    }
}
