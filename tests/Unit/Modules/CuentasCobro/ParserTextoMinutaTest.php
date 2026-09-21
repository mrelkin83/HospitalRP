<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Service\ParserTextoMinuta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El parser que leen las dos rutas sin IA: la capa de texto de un PDF
 * exportado de Word y el OCR de un escaneo.
 *
 * El texto de muestra imita la plantilla real de las minutas del hospital
 * (recuadro, cláusulas numeradas, obligaciones) con datos FICTICIOS.
 */
#[CoversClass(ParserTextoMinuta::class)]
final class ParserTextoMinutaTest extends TestCase
{
    private const string MINUTA = <<<'TEXTO'
        E.S.E. HOSPITAL ROSARIO PUMAREJO DE LÓPEZ
        CONTRATO DE PRESTACIÓN DE SERVICIOS No. 1407-2026

        TIPO DE CONTRATO: PRESTACIÓN DE SERVICIOS DE APOYO A LA GESTIÓN
        CONTRATISTA: BERNARDO PRUEBA DOS            C.C. No. 1.100.000.002
        VALOR DEL CONTRATO: CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS
        M/CTE ($14.250.000)
        PLAZO: CINCO (5) MESES
        SUPERVISOR: PROFESIONAL UNIVERSITARIO

        Entre los suscritos a los 1 días del mes de agosto del año 2026, se
        celebra el presente contrato, previas las siguientes consideraciones:

        CLÁUSULA PRIMERA. OBJETO: Prestación de servicios de apoyo a la gestión
        como tecnólogo en sistemas para el mantenimiento preventivo y correctivo
        de los equipos de cómputo y redes de la E.S.E.

        CLÁUSULA TERCERA. FORMA DE PAGO: El valor del contrato se cancelará en
        cinco (5) cuotas mensuales iguales de DOS MILLONES OCHOCIENTOS CINCUENTA
        MIL PESOS ($2.850.000) cada una, correspondientes a los meses de agosto,
        septiembre, octubre, noviembre y diciembre de 2026, previa presentación
        de la cuenta de cobro, el informe de actividades y la planilla de pago
        de seguridad social.

        CLÁUSULA CUARTA. DISPONIBILIDAD PRESUPUESTAL: CDP No. 1611 del 24 de
        julio de 2026.

        CLÁUSULA QUINTA. PLAZO DE EJECUCIÓN: El plazo de ejecución será CINCO
        (5) MESES, contados a partir de la suscripción del acta de inicio.

        CLÁUSULA SEXTA. OBLIGACIONES ESPECÍFICAS: El contratista se obliga a:
        1. Cubrir el servicio de mantenimiento preventivo y correctivo
        hospitalario durante los turnos asignados, de lunes a domingo.
        2. Asegurar el funcionamiento continuo y eficaz de los equipos,
        minimizando las posibles paradas como consecuencia de averías.
        3. Presentar una relación mensual y física de repuestos y accesorios
        utilizados en el desarrollo contractual.
        4. Apoyar el proceso de bajas administrativas de equipos de cómputo.

        SISTEMA DE GESTIÓN INTEGRAL DE CALIDAD: El contratista deberá:
        1. Conocer y aplicar la política de calidad de la institución.
        2. Participar en las actividades de mejoramiento continuo que programe
        la oficina de calidad.

        CLÁUSULA OCTAVA. SUPERVISIÓN: La ejercerá el profesional designado.
        TEXTO;

    public function test_lee_el_recuadro_de_la_primera_pagina(): void
    {
        $datos = ParserTextoMinuta::interpretar(self::MINUTA);

        self::assertSame('1407-2026', $datos['numero_contrato']);
        self::assertSame(14250000, $datos['valor_total']);
        self::assertSame('2026-08-01', $datos['fecha_suscripcion']);
        self::assertSame('1100000002', $datos['contratista']['numero_documento']);
        self::assertStringContainsString('CATORCE MILLONES', $datos['valor_total_letras']);
    }

    public function test_lee_objeto_forma_de_pago_y_plazo(): void
    {
        $datos = ParserTextoMinuta::interpretar(self::MINUTA);

        self::assertStringContainsString('tecnólogo en sistemas', $datos['objeto']);
        self::assertStringContainsString('cinco (5) cuotas mensuales', $datos['forma_pago_texto']);
        self::assertSame('CINCO (5) MESES', $datos['plazo_clausula_5']);
    }

    public function test_transcribe_las_obligaciones_completas_y_separa_las_del_sgic(): void
    {
        $datos = ParserTextoMinuta::interpretar(self::MINUTA);

        self::assertCount(4, $datos['obligaciones_especificas']);
        self::assertStringStartsWith('Cubrir el servicio', $datos['obligaciones_especificas'][0]);
        self::assertStringContainsString('averías', $datos['obligaciones_especificas'][1]);
        self::assertCount(2, $datos['obligaciones_sgic']);
        self::assertStringContainsString('política de calidad', $datos['obligaciones_sgic'][0]);
    }

    public function test_un_pdf_exportado_de_word_basta_para_saltarse_la_ia(): void
    {
        self::assertTrue(ParserTextoMinuta::suficiente(ParserTextoMinuta::interpretar(self::MINUTA)));
    }

    /**
     * Lo que devuelve el OCR de un escaneo regular: rescata cuerpo pero
     * pierde el recuadro. Ahí la IA sigue siendo necesaria, y el sistema
     * no debe creerse que ya terminó.
     */
    public function test_un_escaneo_sin_recuadro_no_basta(): void
    {
        $soloCuerpo = (string) preg_replace('/^.*?Entre los suscritos/su', 'Entre los suscritos', self::MINUTA);
        $datos = ParserTextoMinuta::interpretar($soloCuerpo);

        self::assertArrayNotHasKey('valor_total', $datos);
        self::assertGreaterThanOrEqual(3, ParserTextoMinuta::riqueza($datos));
        self::assertFalse(ParserTextoMinuta::suficiente($datos));
    }

    public function test_un_texto_vacio_no_inventa_nada(): void
    {
        self::assertSame([], ParserTextoMinuta::interpretar('   '));
        self::assertFalse(ParserTextoMinuta::suficiente([]));
    }
}
