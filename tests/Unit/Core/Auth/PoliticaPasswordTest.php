<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Auth;

use App\Core\Auth\PoliticaPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de contraseña del panel (Fase 10).
 *
 * Se prueba lo que la política debe rechazar, no solo lo que acepta: una
 * validación que deja pasar «hospital2026» no protege nada, y el sitio es de
 * una entidad pública con datos de pacientes detrás.
 */
#[CoversClass(PoliticaPassword::class)]
final class PoliticaPasswordTest extends TestCase
{
    private PoliticaPassword $politica;

    protected function setUp(): void
    {
        $this->politica = new PoliticaPassword();
    }

    public function testAceptaUnaContrasenaLargaConLetrasYNumeros(): void
    {
        self::assertTrue($this->politica->aceptable('Jazmin7Puente4Rio'));
    }

    #[DataProvider('contrasenasInaceptables')]
    public function testRechazaLoQueNoCumple(string $password, string $porque): void
    {
        self::assertFalse($this->politica->aceptable($password), $porque);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function contrasenasInaceptables(): iterable
    {
        yield 'corta' => ['Ab3cdef', 'menos de 12 caracteres'];
        yield 'sin numeros' => ['contrasenasegura', 'no tiene ningún dígito'];
        yield 'sin letras' => ['123456789012345', 'no tiene ninguna letra'];
        yield 'previsible' => ['Password2026Xy', 'contiene una palabra del diccionario corto'];
        yield 'con la entidad' => ['HospitalRosario26', 'contiene el nombre de la entidad'];
        yield 'con espacios al borde' => [' Jazmin7Puente4 ', 'empieza y termina con espacio'];
    }

    public function testRechazaLaQueContieneElUsuarioDelCorreo(): void
    {
        $problemas = $this->politica->problemas('mgomez2026Puente', 'mgomez@hrplopez.gov.co', 'María Gómez');

        self::assertContains('No puede contener tu usuario de correo.', $problemas);
    }

    public function testRechazaLaQueContieneElNombreDelFuncionario(): void
    {
        $problemas = $this->politica->problemas('Rodriguez2026Sol', 'jr@hrplopez.gov.co', 'Julián Rodriguez');

        self::assertContains('No puede contener tu nombre ni tus apellidos.', $problemas);
    }

    public function testLasPreposicionesDelNombreNoCuentanComoIdentificadoras(): void
    {
        // «de la» aparece en medio mundo de los nombres compuestos del país:
        // prohibirlas volvería inaceptable casi cualquier contraseña.
        self::assertTrue($this->politica->aceptable('Jazmin7Puente4Rio', 'ana@hrplopez.gov.co', 'Ana de la Cruz'));
    }

    public function testDevuelveTodosLosProblemasDeUnaVezYNoSoloElPrimero(): void
    {
        $problemas = $this->politica->problemas('abc');

        self::assertGreaterThanOrEqual(2, count($problemas));
    }

    public function testLaContrasenaGeneradaCumpleLaPropiaPolitica(): void
    {
        // Si el sistema generara claves que él mismo rechaza, el funcionario
        // quedaría atrapado en la pantalla de cambio obligatorio.
        for ($i = 0; $i < 50; $i++) {
            $generada = $this->politica->generar();

            self::assertTrue(
                $this->politica->aceptable($generada),
                sprintf('«%s» no cumple: %s', $generada, implode(' ', $this->politica->problemas($generada))),
            );
        }
    }

    public function testLaContrasenaGeneradaNoRepiteValores(): void
    {
        $generadas = [];

        for ($i = 0; $i < 100; $i++) {
            $generadas[] = $this->politica->generar();
        }

        self::assertCount(100, array_unique($generadas));
    }

    public function testLaContrasenaGeneradaEvitaCaracteresAmbiguos(): void
    {
        // Se dicta por teléfono: un 0 y una O suenan igual, y una l y un 1 se
        // ven igual en la letra de quien la anota.
        for ($i = 0; $i < 30; $i++) {
            self::assertDoesNotMatchRegularExpression('/[0O1lI]/', $this->politica->generar());
        }
    }
}
