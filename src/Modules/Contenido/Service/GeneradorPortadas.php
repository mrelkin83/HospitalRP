<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use GdImage;
use InvalidArgumentException;

/**
 * Portadas de relleno para noticias, campañas y comunicados.
 *
 * Por qué dibujadas y no fotos de banco: una foto de archivo con personas
 * dentro de un sitio de salud pública se lee como «este es el hospital, esta
 * es su gente, estos son sus pacientes», y no lo es. Un degradado de marca con
 * un pictograma del tema no afirma nada falso, no depende de la red y se
 * reemplaza desde Panel → Medios el día que Comunicaciones tenga fotos
 * propias. Es relleno y se nota que es relleno: esa es la gracia.
 *
 * El resultado es determinista —el mismo motivo da siempre la misma imagen—,
 * así que volver a sembrar no ensucia la biblioteca de medios con variantes.
 */
final class GeneradorPortadas
{
    /** Proporción 1200×630: la que piden Open Graph y las tarjetas del sitio. */
    private const int ANCHO = 1200;
    private const int ALTO = 630;

    /** El pictograma se dibuja al doble y se reduce: GD no antialiasa polígonos. */
    private const int LIENZO_ICONO = 1024;

    /** Los cuatro azules oficiales de la identidad del hospital. */
    private const string AZUL_OSCURO = '#1D5BA7';
    private const string AZUL_MEDIO = '#0065AE';
    private const string AZUL_CELESTE = '#0997D5';
    private const string AZUL_CLARO = '#35BDE9';

    /**
     * Motivo => [azul inicial, azul final] del degradado.
     *
     * Los temas institucionales (seguridad del paciente, comunicados al
     * personal) tiran a los azules profundos; los de promoción y prevención, a
     * los claros. No es capricho: en el listado, el tono separa lo
     * administrativo de lo asistencial antes de que se lea el titular.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array MOTIVOS = [
        'vacuna' => [self::AZUL_MEDIO, self::AZUL_CLARO],
        'corazon' => [self::AZUL_OSCURO, self::AZUL_CELESTE],
        'lactancia' => [self::AZUL_CELESTE, self::AZUL_CLARO],
        'sangre' => [self::AZUL_OSCURO, self::AZUL_MEDIO],
        'quirofano' => [self::AZUL_MEDIO, self::AZUL_CELESTE],
        'reloj' => [self::AZUL_CELESTE, self::AZUL_CLARO],
        'capacitacion' => [self::AZUL_OSCURO, self::AZUL_CELESTE],
        'empleo' => [self::AZUL_OSCURO, self::AZUL_MEDIO],
        'seguridad' => [self::AZUL_OSCURO, self::AZUL_CELESTE],
        'encuesta' => [self::AZUL_MEDIO, self::AZUL_CLARO],
    ];

    /** @return list<string> */
    public static function motivos(): array
    {
        return array_keys(self::MOTIVOS);
    }

    public static function existeMotivo(string $motivo): bool
    {
        return isset(self::MOTIVOS[$motivo]);
    }

    /**
     * Dibuja la portada del motivo y la escribe en `$destino` como JPEG.
     *
     * @throws InvalidArgumentException si el motivo no existe
     */
    public function generar(string $motivo, string $destino): void
    {
        if (!isset(self::MOTIVOS[$motivo])) {
            throw new InvalidArgumentException(sprintf(
                'Motivo de portada desconocido: «%s». Disponibles: %s.',
                $motivo,
                implode(', ', self::motivos()),
            ));
        }

        [$desde, $hasta] = self::MOTIVOS[$motivo];

        $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagealphablending($lienzo, true);

        $this->degradado($lienzo, $desde, $hasta);
        $this->cuadricula($lienzo);
        $this->pictograma($lienzo, $motivo);
        $this->franjaDeMarca($lienzo);

        imagejpeg($lienzo, $destino, 82);
        imagedestroy($lienzo);
    }

    /**
     * Degradado en diagonal, de la esquina superior izquierda a la inferior
     * derecha (el mismo 135° del resto del sitio).
     *
     * Se pinta con líneas a −45° y no píxel a píxel: 1.830 llamadas a GD en
     * vez de 756.000 iteraciones de PHP.
     */
    private function degradado(GdImage $lienzo, string $desde, string $hasta): void
    {
        [$r1, $g1, $b1] = $this->rgb($desde);
        [$r2, $g2, $b2] = $this->rgb($hasta);

        $pasos = self::ANCHO + self::ALTO;

        for ($t = 0; $t < $pasos; $t++) {
            $avance = $t / ($pasos - 1);
            $color = imagecolorallocate(
                $lienzo,
                (int) round($r1 + ($r2 - $r1) * $avance),
                (int) round($g1 + ($g2 - $g1) * $avance),
                (int) round($b1 + ($b2 - $b1) * $avance),
            );
            imageline($lienzo, $t, 0, 0, $t, $color);
        }
    }

    /** Trama técnica muy tenue, la misma del héroe de la portada. */
    private function cuadricula(GdImage $lienzo): void
    {
        $linea = imagecolorallocatealpha($lienzo, 255, 255, 255, 115);

        for ($x = 0; $x < self::ANCHO; $x += 48) {
            imageline($lienzo, $x, 0, $x, self::ALTO, $linea);
        }

        for ($y = 0; $y < self::ALTO; $y += 48) {
            imageline($lienzo, 0, $y, self::ANCHO, $y, $linea);
        }
    }

    /**
     * El pictograma, blanco y translúcido, descentrado a la derecha.
     *
     * Se dibuja opaco en una capa aparte y la translucidez se aplica de golpe
     * al final. Si se dibujara forma a forma con alfa, cada solape se sumaría
     * y el corazón —dos círculos y un triángulo— saldría con una mancha más
     * oscura justo en el cruce.
     */
    private function pictograma(GdImage $lienzo, string $motivo): void
    {
        $capa = $this->capaTransparente();
        $blanco = imagecolorallocate($capa, 255, 255, 255);

        $this->dibujarMotivo($capa, $motivo, $blanco);

        // Los tres ceros no cambian el color; el cuarto argumento sube el alfa.
        imagefilter($capa, IMG_FILTER_COLORIZE, 0, 0, 0, 98);

        // Reducción a menos de la mitad: el suavizado que GD no da en los
        // polígonos lo pone aquí el remuestreo.
        $lado = 420;
        imagecopyresampled(
            $lienzo,
            $capa,
            self::ANCHO - $lado - 90,
            intdiv(self::ALTO - $lado, 2),
            0,
            0,
            $lado,
            $lado,
            self::LIENZO_ICONO,
            self::LIENZO_ICONO,
        );

        imagedestroy($capa);
    }

    /** Los cuatro azules oficiales, en una franja al pie. */
    private function franjaDeMarca(GdImage $lienzo): void
    {
        $azules = [self::AZUL_OSCURO, self::AZUL_MEDIO, self::AZUL_CELESTE, self::AZUL_CLARO];
        $ancho = (int) ceil(self::ANCHO / count($azules));

        foreach ($azules as $i => $azul) {
            [$r, $g, $b] = $this->rgb($azul);
            imagefilledrectangle(
                $lienzo,
                $i * $ancho,
                self::ALTO - 12,
                ($i + 1) * $ancho,
                self::ALTO,
                imagecolorallocate($lienzo, $r, $g, $b),
            );
        }
    }

    /**
     * Cada motivo, en el lienzo de 1024×1024 del pictograma.
     *
     * Son siluetas, no iconos de trazo: a 420 px y al 23 % de opacidad un
     * trazo fino desaparece, mientras que la silueta se sigue reconociendo.
     */
    private function dibujarMotivo(GdImage $capa, string $motivo, int $blanco): void
    {
        match ($motivo) {
            'vacuna' => $this->jeringa($capa),
            'corazon' => $this->corazon($capa, $blanco),
            'lactancia' => $this->manosConFigura($capa, $blanco),
            'sangre' => $this->gota($capa, $blanco),
            'quirofano' => $this->cruz($capa, $blanco),
            'reloj' => $this->reloj($capa, $blanco),
            'capacitacion' => $this->birrete($capa, $blanco),
            'empleo' => $this->maletin($capa, $blanco),
            'seguridad' => $this->escudo($capa, $blanco),
            'encuesta' => $this->barras($capa, $blanco),
        };
    }

    private function corazon(GdImage $capa, int $blanco): void
    {
        imagefilledellipse($capa, 360, 400, 380, 380, $blanco);
        imagefilledellipse($capa, 664, 400, 380, 380, $blanco);
        imagefilledpolygon($capa, [170, 470, 854, 470, 512, 900], $blanco);
    }

    private function gota(GdImage $capa, int $blanco): void
    {
        imagefilledpolygon($capa, [512, 120, 250, 560, 774, 560], $blanco);
        imagefilledellipse($capa, 512, 570, 524, 524, $blanco);
    }

    private function cruz(GdImage $capa, int $blanco): void
    {
        imagefilledrectangle($capa, 400, 160, 624, 864, $blanco);
        imagefilledrectangle($capa, 160, 400, 864, 624, $blanco);
    }

    private function escudo(GdImage $capa, int $blanco): void
    {
        imagefilledpolygon($capa, [512, 120, 860, 260, 860, 560, 512, 904, 164, 560, 164, 260], $blanco);
    }

    /**
     * Maletín. El asa se dibuja alta y ancha a propósito: al 23 % de opacidad
     * y reducida a 420 px, un asa pequeña se fundía con la tapa y el motivo
     * pasaba por un rectángulo cualquiera.
     */
    private function maletin(GdImage $capa, int $blanco): void
    {
        imagefilledrectangle($capa, 360, 150, 424, 330, $blanco); // montante izquierdo
        imagefilledrectangle($capa, 600, 150, 664, 330, $blanco); // montante derecho
        imagefilledrectangle($capa, 360, 150, 664, 214, $blanco); // travesaño
        imagefilledrectangle($capa, 130, 330, 894, 850, $blanco); // cuerpo
    }

    private function birrete(GdImage $capa, int $blanco): void
    {
        imagefilledpolygon($capa, [512, 220, 920, 400, 512, 580, 104, 400], $blanco);
        imagefilledrectangle($capa, 280, 470, 744, 760, $blanco);
        imagefilledrectangle($capa, 880, 400, 920, 700, $blanco);
    }

    private function barras(GdImage $capa, int $blanco): void
    {
        imagefilledrectangle($capa, 160, 620, 340, 880, $blanco);
        imagefilledrectangle($capa, 420, 400, 600, 880, $blanco);
        imagefilledrectangle($capa, 680, 200, 860, 880, $blanco);
    }

    /**
     * Reloj: disco lleno, interior devuelto a transparente y las agujas encima.
     *
     * El hueco se hace con la mezcla apagada, que SUSTITUYE el píxel en vez de
     * combinarlo: con la mezcla activa, pintar «transparente» sobre blanco no
     * borra nada, y el disco se quedaría macizo.
     */
    private function reloj(GdImage $capa, int $blanco): void
    {
        $transparente = imagecolorallocatealpha($capa, 0, 0, 0, 127);

        imagefilledellipse($capa, 512, 512, 800, 800, $blanco);
        imagefilledellipse($capa, 512, 512, 660, 660, $transparente);
        imagefilledrectangle($capa, 488, 240, 536, 536, $blanco);
        imagefilledrectangle($capa, 488, 488, 740, 536, $blanco);
    }

    /** Jeringa en diagonal: se dibuja recta y se rota entera. */
    private function jeringa(GdImage $capa): void
    {
        $pieza = $this->capaTransparente();
        $blanco = imagecolorallocate($pieza, 255, 255, 255);

        // Más gruesa de lo que pide el natural: al 23 % de opacidad y junto a
        // siluetas macizas —el corazón, el escudo—, una jeringa a escala real
        // se leía como un arañazo en la imagen.
        imagefilledrectangle($pieza, 300, 400, 700, 624, $blanco); // cuerpo
        imagefilledrectangle($pieza, 170, 440, 300, 584, $blanco); // émbolo
        imagefilledrectangle($pieza, 100, 390, 170, 634, $blanco); // pulsador
        imagefilledrectangle($pieza, 650, 350, 700, 674, $blanco); // pestaña
        imagefilledrectangle($pieza, 700, 470, 900, 554, $blanco); // aguja

        $rotada = imagerotate($pieza, 45, imagecolorallocatealpha($pieza, 0, 0, 0, 127));
        imagealphablending($rotada, false);
        imagesavealpha($rotada, true);

        // Rotar 45° amplía el lienzo a la diagonal (1024 → ~1448) y dentro de
        // él la jeringa ocupa proporcionalmente menos. Se devuelve al tamaño
        // del icono con remuestreo, no con un recorte centrado: recortando,
        // la pieza se veía a dos tercios del resto de los motivos.
        $lado = imagesx($rotada);
        imagecopyresampled($capa, $rotada, 0, 0, 0, 0, self::LIENZO_ICONO, self::LIENZO_ICONO, $lado, $lado);

        imagedestroy($pieza);
        imagedestroy($rotada);
    }

    /**
     * Adulto y niño, uno al lado del otro.
     *
     * El primer intento fueron dos manos sosteniendo una figura y el resultado
     * se leía como una flecha hacia abajo: la silueta de unas manos abiertas
     * necesita un trazo que a esta escala no sobrevive. Dos cuerpos con sus
     * cabezas se reconocen al instante y no se parecen a ninguna otra cosa.
     */
    private function manosConFigura(GdImage $capa, int $blanco): void
    {
        // Adulto.
        imagefilledellipse($capa, 390, 250, 200, 200, $blanco);
        imagefilledpolygon($capa, [390, 370, 560, 700, 560, 880, 220, 880, 220, 700], $blanco);

        // Niño, a su lado y a menor escala.
        imagefilledellipse($capa, 700, 430, 140, 140, $blanco);
        imagefilledpolygon($capa, [700, 510, 820, 720, 820, 880, 580, 880, 580, 720], $blanco);
    }

    /** Lienzo cuadrado del icono, completamente transparente y sin mezcla. */
    private function capaTransparente(): GdImage
    {
        $capa = imagecreatetruecolor(self::LIENZO_ICONO, self::LIENZO_ICONO);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);
        imagefilledrectangle(
            $capa,
            0,
            0,
            self::LIENZO_ICONO,
            self::LIENZO_ICONO,
            imagecolorallocatealpha($capa, 0, 0, 0, 127),
        );

        return $capa;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $valor = (int) hexdec(ltrim($hex, '#'));

        return [($valor >> 16) & 255, ($valor >> 8) & 255, $valor & 255];
    }
}
