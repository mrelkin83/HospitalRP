<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use Doctrine\DBAL\Connection;

/**
 * Las validaciones del servidor sobre lo que la IA extrajo de una minuta
 * (§10 paso 3). Cada hallazgo es una alerta en lenguaje del revisor: no
 * bloquean, señalan — la minuta manda aunque venga mal escrita, y esa
 * contradicción es exactamente lo que el humano debe ver en rojo.
 */
final readonly class ValidadorExtraccion
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @param array<string, mixed> $r El JSON extraído (esquema de §10).
     *
     * @return list<string>
     */
    public function alertas(array $r): array
    {
        return [...$this->alertasDeDatos($r), ...$this->alertasDeDuplicados($r)];
    }

    /**
     * Las que no necesitan base de datos (puras, con prueba unitaria).
     *
     * @param array<string, mixed> $r
     *
     * @return list<string>
     */
    public function alertasDeDatos(array $r): array
    {
        $alertas = [];

        // 0. El tipo de contrato: si no es prestación de servicios, la
        //    extracción no podrá aplicarse (lotes con compraventas mezcladas).
        if (!self::esPrestacionDeServicios($r)) {
            $tipoLeido = trim((string) ($r['tipo_contrato'] ?? ''));
            $alertas[] = 'Este documento no parece un contrato de prestación de servicios'
                . ($tipoLeido !== '' ? " (dice: «{$tipoLeido}»)" : ' (el tipo no se pudo leer)')
                . '. Solo se cargan contratos de prestación de servicios, servicios profesionales o apoyo a la gestión; no podrá aplicarse.';
        }
        $valorTotal = (int) ($r['valor_total'] ?? 0);
        $cuotas = is_array($r['cuotas'] ?? null) ? $r['cuotas'] : [];

        // 1. La suma de las cuotas debe dar el valor total exacto.
        if ($valorTotal > 0 && $cuotas !== []) {
            $suma = 0;

            foreach ($cuotas as $cuota) {
                $suma += (int) ($cuota['valor'] ?? 0);
            }

            if ($suma !== $valorTotal) {
                $alertas[] = sprintf(
                    'La suma de las cuotas (%s) no da el valor total (%s): diferencia de %s.',
                    NumeroALetras::moneda($suma),
                    NumeroALetras::moneda($valorTotal),
                    NumeroALetras::moneda(abs($valorTotal - $suma)),
                );
            }
        }

        // 2. El valor en letras contra el valor en número.
        $letras = self::normalizar((string) ($r['valor_total_letras'] ?? ''));

        if ($valorTotal > 0 && $letras !== '') {
            $esperadas = self::normalizar(NumeroALetras::pesos($valorTotal));

            if (!str_contains($esperadas, $letras) && !str_contains($letras, self::normalizar(NumeroALetras::entero($valorTotal, true)))) {
                $alertas[] = 'El valor en letras de la minuta no coincide con el valor en número; revise cuál es el correcto.';
            }
        }

        // 3. El plazo del recuadro contra el de la cláusula 5 (el caso real
        //    del 1473-2026: «4 meses» arriba y «2 meses» abajo).
        $recuadro = self::normalizar((string) ($r['plazo_recuadro'] ?? ''));
        $clausula = self::normalizar((string) ($r['plazo_clausula_5'] ?? ''));

        if ($recuadro !== '' && $clausula !== '' && $recuadro !== $clausula) {
            $alertas[] = sprintf(
                'El plazo del recuadro («%s») no coincide con el de la cláusula 5 («%s»); decida cuál vale.',
                trim((string) $r['plazo_recuadro']),
                trim((string) $r['plazo_clausula_5']),
            );
        }

        // 4. El número de cuotas contra los meses del plazo.
        $meses = self::mesesDelPlazo($recuadro !== '' ? $recuadro : $clausula);

        if ($meses > 0 && $cuotas !== [] && abs(count($cuotas) - $meses) > 1) {
            $alertas[] = sprintf(
                'El plazo habla de %d meses pero el cronograma trae %d cuotas; revise la forma de pago.',
                $meses,
                count($cuotas),
            );
        }

        // 5. Objeto «profesional» con tipo «apoyo a la gestión», o al revés.
        $objeto = self::normalizar((string) ($r['objeto'] ?? ''));
        $tipo = self::normalizar((string) ($r['tipo_contrato'] ?? ''));

        if ($tipo !== '' && $objeto !== '') {
            if (str_contains($tipo, 'APOYO A LA GESTION') && str_contains($objeto, 'PROFESIONAL')) {
                $alertas[] = 'El tipo dice «apoyo a la gestión» pero el objeto habla de servicios profesionales; revise la clasificación.';
            } elseif (str_contains($tipo, 'PROFESIONAL') && str_contains($objeto, 'APOYO A LA GESTION')) {
                $alertas[] = 'El tipo dice «profesionales» pero el objeto habla de apoyo a la gestión; revise la clasificación.';
            }
        }

        // 6. Obligaciones que no comparten ni una palabra con el objeto
        //    (heurística simple, solo aviso, §10).
        $obligaciones = is_array($r['obligaciones_especificas'] ?? null) ? $r['obligaciones_especificas'] : [];

        if ($objeto !== '' && $obligaciones !== [] && !self::compartenVocabulario($objeto, $obligaciones)) {
            $alertas[] = 'Las obligaciones específicas no mencionan ninguna palabra del objeto; verifique que correspondan a esta minuta.';
        }

        return $alertas;
    }

    /**
     * Las que sí consultan la base: cédulas y números repetidos.
     *
     * @param array<string, mixed> $r
     *
     * @return list<string>
     */
    public function alertasDeDuplicados(array $r): array
    {
        $alertas = [];
        $numero = trim((string) ($r['numero_contrato'] ?? ''));

        if ($numero !== '' && $this->conexion->fetchOne('SELECT id FROM cc_contratos WHERE numero = ?', [$numero]) !== false) {
            $alertas[] = sprintf('Ya existe un contrato registrado con el número %s.', $numero);
        }

        $cedula = trim((string) ($r['contratista']['numero_documento'] ?? ''));
        $nombre = self::normalizar((string) ($r['contratista']['nombre_completo'] ?? ''));

        if ($cedula !== '') {
            $existente = $this->conexion->fetchOne(
                'SELECT nombre_completo FROM cc_contratistas WHERE numero_documento = ?',
                [$cedula],
            );

            if (is_string($existente) && $nombre !== '' && self::normalizar($existente) !== $nombre) {
                $alertas[] = sprintf(
                    'La cédula %s ya está registrada a nombre de %s y la minuta dice otro nombre; revise antes de crear un duplicado.',
                    $cedula,
                    $existente,
                );
            }
        }

        return $alertas;
    }

    // ------------------------------------------------------------------
    // Ayudas

    /**
     * Solo entran al módulo los contratos de prestación de servicios (los
     * «profesionales» y los «de apoyo a la gestión» incluidos). Compraventas,
     * suministros u obras que aparezcan en los lotes escaneados se rechazan.
     *
     * @param array<string, mixed> $r
     */
    public static function esPrestacionDeServicios(array $r): bool
    {
        $tipo = self::normalizar((string) ($r['tipo_contrato'] ?? ''));
        $objeto = self::normalizar((string) ($r['objeto'] ?? ''));

        foreach (['COMPRAVENTA', 'COMPRA VENTA', 'SUMINISTRO', 'ARRENDAMIENTO', 'OBRA PUBLICA', 'CONTRATO DE OBRA'] as $vetado) {
            if (str_contains($tipo, $vetado)) {
                return false;
            }
        }

        if (str_contains($tipo, 'PRESTACION DE SERVICIOS') || str_contains($tipo, 'APOYO A LA GESTION')) {
            return true;
        }

        // Sin tipo legible (OCR pobre), decide el objeto.
        return $tipo === '' && (str_contains($objeto, 'PRESTAR') || str_contains($objeto, 'PRESTACION DE SERVICIOS'));
    }
    // ------------------------------------------------------------------

    /** Mayúsculas, sin tildes ni signos: para comparar texto de escáner. */
    private static function normalizar(string $texto): string
    {
        $mayus = mb_strtoupper(trim($texto), 'UTF-8');
        $mayus = strtr($mayus, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Z0-9 ]+/', ' ', $mayus)));
    }

    /** «CINCO (5) MESES» → 5; «25 DIAS Y 5 MESES» → 5; sin meses → 0. */
    private static function mesesDelPlazo(string $plazoNormalizado): int
    {
        if (preg_match('/(\d+)\s*MES/', $plazoNormalizado, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }

    /** @param list<mixed> $obligaciones */
    private static function compartenVocabulario(string $objetoNormalizado, array $obligaciones): bool
    {
        $vacias = ['DE', 'LA', 'EL', 'LOS', 'LAS', 'DEL', 'PARA', 'COMO', 'EN', 'CON', 'A', 'Y', 'O', 'QUE', 'SE',
            'PRESTACION', 'SERVICIOS', 'SERVICIO', 'ESE', 'HOSPITAL', 'ROSARIO', 'PUMAREJO', 'LOPEZ', 'GESTION', 'APOYO'];
        $palabrasObjeto = array_diff(
            array_filter(explode(' ', $objetoNormalizado), static fn (string $p): bool => mb_strlen($p) > 3),
            $vacias,
        );

        foreach ($obligaciones as $obligacion) {
            $texto = self::normalizar((string) $obligacion);

            foreach ($palabrasObjeto as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return true;
                }
            }
        }

        return false;
    }
}
