<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use Doctrine\DBAL\Connection;

/**
 * Las diapositivas del héroe de la portada.
 *
 * Una fila es una diapositiva: imagen a sangre, texto y hasta dos botones.
 * Antes el héroe era un bloque fijo repartido en claves sueltas de Ajustes.
 *
 * La consulta pública y la del panel son distintas a propósito, por el mismo
 * motivo por el que `CampanaRepository::publicasVigentes()` existe separada de
 * `listarParaPanel()`: la portada no puede acabar mostrando una diapositiva
 * apagada o caducada porque alguien reutilizó la consulta cómoda.
 */
final readonly class HeroRepository
{
    /**
     * Columnas de la diapositiva más lo que hace falta de la imagen.
     *
     * `texto_alt`, `ancho` y `alto` vienen de `archivos` para poder pintar el
     * `<img>` con sus dimensiones: sin ellas el navegador no reserva el hueco
     * y la portada da el salto de maquetación justo en lo primero que se ve.
     */
    private const string COLUMNAS = 'h.id, h.pildora, h.titulo, h.titulo_degradado, h.eslogan,
                h.boton1_texto, h.boton1_url, h.boton2_texto, h.boton2_url,
                h.imagen_id, h.mostrar_tarjeta, h.orden, h.visible, h.inicio, h.fin,
                a.texto_alt AS imagen_alt, a.ancho AS imagen_ancho, a.alto AS imagen_alto';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Las que se ven hoy en el sitio: encendidas y dentro de su vigencia.
     *
     * Una diapositiva sin fechas está siempre vigente; es el caso normal. Las
     * fechas son para el héroe de una jornada concreta, que debe apagarse solo.
     *
     * @return list<array<string, mixed>>
     */
    public function paraLaPortada(): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT ' . self::COLUMNAS . '
               FROM heroes h
               LEFT JOIN archivos a ON a.id = h.imagen_id
              WHERE h.visible = 1
                AND (h.inicio IS NULL OR h.inicio <= CURDATE())
                AND (h.fin    IS NULL OR h.fin    >= CURDATE())
              ORDER BY h.orden, h.id',
        );

        return array_map($this->normalizar(...), $filas);
    }

    /**
     * Todas, encendidas o no, para la pantalla del panel.
     *
     * @return list<array<string, mixed>>
     */
    public function todas(): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT ' . self::COLUMNAS . '
               FROM heroes h
               LEFT JOIN archivos a ON a.id = h.imagen_id
              ORDER BY h.orden, h.id',
        );

        return array_map($this->normalizar(...), $filas);
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT ' . self::COLUMNAS . '
               FROM heroes h
               LEFT JOIN archivos a ON a.id = h.imagen_id
              WHERE h.id = ?',
            [$id],
        );

        return $fila === false ? null : $this->normalizar($fila);
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $this->conexion->insert('heroes', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('heroes', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): bool
    {
        return $this->conexion->delete('heroes', ['id' => $id]) > 0;
    }

    /**
     * Deja la tarjeta «Rosario Digital» solo en esta diapositiva.
     *
     * La tarjeta es una y ocupa el lado derecho; repetida en dos héroes
     * distintos parecería un fallo. Se apaga en las demás al vuelo en vez de
     * validarlo en el formulario: quien marca la casilla está diciendo «la
     * quiero aquí», y eso implica «ya no allá».
     */
    public function tarjetaSoloEn(int $id): void
    {
        $this->conexion->executeStatement(
            'UPDATE heroes SET mostrar_tarjeta = 0 WHERE id <> ?',
            [$id],
        );
    }

    /**
     * El siguiente hueco de la lista, para que una diapositiva nueva entre al
     * final y no empatada con otra: dos `orden` iguales dejan el desempate a
     * `id`, y entonces reordenar deja de ser predecible para quien lo hace.
     */
    public function siguienteOrden(): int
    {
        return ((int) $this->conexion->fetchOne('SELECT COALESCE(MAX(orden), -1) FROM heroes')) + 1;
    }

    /**
     * Deja los tipos como los espera la plantilla: cadenas vacías en vez de
     * NULL para el texto, y booleanos de verdad para los interruptores. Así la
     * vista pregunta `!== ''` y no tiene que repetir `?? ''` en cada campo.
     *
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    private function normalizar(array $fila): array
    {
        foreach (['pildora', 'titulo', 'titulo_degradado', 'eslogan', 'boton1_texto', 'boton1_url', 'boton2_texto', 'boton2_url', 'imagen_alt'] as $texto) {
            $fila[$texto] = $fila[$texto] === null ? '' : (string) $fila[$texto];
        }

        $fila['id'] = (int) $fila['id'];
        $fila['orden'] = (int) $fila['orden'];
        $fila['imagen_id'] = $fila['imagen_id'] === null ? null : (int) $fila['imagen_id'];
        $fila['imagen_ancho'] = $fila['imagen_ancho'] === null ? null : (int) $fila['imagen_ancho'];
        $fila['imagen_alto'] = $fila['imagen_alto'] === null ? null : (int) $fila['imagen_alto'];
        $fila['visible'] = (int) $fila['visible'] === 1;
        $fila['mostrar_tarjeta'] = (int) $fila['mostrar_tarjeta'] === 1;

        return $fila;
    }
}
