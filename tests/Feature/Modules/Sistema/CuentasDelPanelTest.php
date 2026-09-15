<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Core\Auth\HasherPassword;
use App\Modules\Sistema\Repository\RolRepository;
use App\Modules\Sistema\Repository\UsuarioAdminRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Gestión de cuentas y roles desde el panel (Fase 10).
 *
 * Lo que se fija aquí es lo que no puede romperse nunca: que el conteo de
 * administradores activos sea fiable —de él depende la baranda que impide
 * dejar la entidad sin nadie que reparta permisos— y que una baja no borre
 * el rastro de quien firmó actos administrativos.
 */
#[CoversClass(UsuarioAdminRepository::class)]
#[CoversClass(RolRepository::class)]
final class CuentasDelPanelTest extends TestCase
{
    private static Connection $conexion;
    private UsuarioAdminRepository $usuarios;
    private RolRepository $roles;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM usuarios');
        self::$conexion->executeStatement('DELETE FROM roles');

        $this->usuarios = new UsuarioAdminRepository(self::$conexion);
        $this->roles = new RolRepository(self::$conexion);
    }

    public function testElRolGuardaYDevuelveSusPermisosComoLista(): void
    {
        $id = $this->roles->crear('Prensa', 'prensa', 'Publica noticias', ['contenido.ver', 'contenido.editar']);

        $rol = $this->roles->porId($id);

        self::assertNotNull($rol);
        self::assertSame(['contenido.ver', 'contenido.editar'], $rol['permisos']);
    }

    public function testUnRolSinPermisosSeGuardaComoListaVaciaYNoComoObjeto(): void
    {
        // json_encode([]) da «[]», pero json_encode de un array asociativo
        // vacío daría «{}» y AutorizacionService no sabría leerlo.
        $id = $this->roles->crear('Sin permisos', 'sin-permisos', null, []);

        self::assertSame('[]', self::$conexion->fetchOne('SELECT permisos FROM roles WHERE id = ?', [$id]));
    }

    public function testCuentaLosAdministradoresPorSuComodinYNoPorElNombreDelRol(): void
    {
        // Un rol puede llamarse «Coordinador» y tener «*»: lo que manda es el
        // permiso, no cómo lo bautizó el funcionario que lo creó.
        $total = $this->roles->crear('Coordinador general', 'coordinador', null, ['*']);
        $limitado = $this->roles->crear('Prensa', 'prensa', null, ['contenido.ver']);

        $this->crearUsuario('mandamas@hrplopez.gov.co', $total);
        $this->crearUsuario('prensa@hrplopez.gov.co', $limitado);

        self::assertSame(1, $this->usuarios->administradoresActivos());
    }

    public function testLosDesactivadosYLosDadosDeBajaNoCuentanComoAdministradores(): void
    {
        $rol = $this->roles->crear('Administrador', 'administrador', null, ['*']);

        $activo = $this->crearUsuario('activo@hrplopez.gov.co', $rol);
        $inactivo = $this->crearUsuario('inactivo@hrplopez.gov.co', $rol);
        $baja = $this->crearUsuario('baja@hrplopez.gov.co', $rol);

        $this->usuarios->actualizar($inactivo, ['activo' => 0]);
        $this->usuarios->eliminar($baja);

        self::assertSame(1, $this->usuarios->administradoresActivos());
        self::assertGreaterThan(0, $activo);
    }

    public function testDarDeBajaNoBorraLaFilaPorqueLaAuditoriaApuntaAEllaTodavia(): void
    {
        $rol = $this->roles->crear('Prensa', 'prensa', null, ['contenido.ver']);
        $id = $this->crearUsuario('sale@hrplopez.gov.co', $rol);

        $this->usuarios->eliminar($id);

        $fila = $this->usuarios->porId($id);

        self::assertNotNull($fila, 'La cuenta desapareció: el histórico se quedaría sin responsable.');
        self::assertNotNull($fila['eliminado_en']);
        self::assertSame(0, (int) $fila['activo']);
    }

    public function testElCorreoSigueOcupadoTrasLaBajaParaNoConfundirElHistorico(): void
    {
        $rol = $this->roles->crear('Prensa', 'prensa', null, []);
        $id = $this->crearUsuario('reutilizable@hrplopez.gov.co', $rol);

        $this->usuarios->eliminar($id);

        self::assertTrue($this->usuarios->correoOcupado('reutilizable@hrplopez.gov.co'));
    }

    public function testCambiarLaPasswordLimpiaElBloqueoPorIntentosFallidos(): void
    {
        $rol = $this->roles->crear('Prensa', 'prensa', null, []);
        $id = $this->crearUsuario('bloqueada@hrplopez.gov.co', $rol);

        self::$conexion->executeStatement(
            'UPDATE usuarios SET intentos_fallidos = 5, bloqueado_hasta = NOW() + INTERVAL 10 MINUTE WHERE id = ?',
            [$id],
        );

        $this->usuarios->cambiarPassword($id, (new HasherPassword())->hash('Jazmin7Puente4Rio'), false);

        $fila = $this->usuarios->porId($id);

        self::assertNotNull($fila);
        self::assertSame(0, (int) $fila['intentos_fallidos']);
        self::assertNull($fila['bloqueado_hasta']);
        self::assertSame(0, (int) $fila['debe_cambiar_pass']);
    }

    public function testElRestablecimientoDejaLaCuentaObligadaACambiarLaClave(): void
    {
        $rol = $this->roles->crear('Prensa', 'prensa', null, []);
        $id = $this->crearUsuario('olvidadiza@hrplopez.gov.co', $rol);

        $this->usuarios->cambiarPassword($id, (new HasherPassword())->hash('Temporal2026Xyz'), true);

        $fila = $this->usuarios->porId($id);

        self::assertNotNull($fila);
        self::assertSame(1, (int) $fila['debe_cambiar_pass']);
    }

    public function testElListadoOrdinarioOcultaLasCuentasDadasDeBaja(): void
    {
        $rol = $this->roles->crear('Prensa', 'prensa', null, []);
        $this->crearUsuario('queda@hrplopez.gov.co', $rol);
        $this->usuarios->eliminar($this->crearUsuario('se-va@hrplopez.gov.co', $rol));

        self::assertCount(1, $this->usuarios->todos());
        self::assertCount(2, $this->usuarios->todos(true));
    }

    public function testElConteoDeUsuariosPorRolIgnoraLosDadosDeBaja(): void
    {
        $rol = $this->roles->crear('Prensa', 'prensa', null, []);
        $this->crearUsuario('uno@hrplopez.gov.co', $rol);
        $this->usuarios->eliminar($this->crearUsuario('dos@hrplopez.gov.co', $rol));

        self::assertSame(1, $this->roles->usuariosCon($rol));
    }

    private function crearUsuario(string $correo, int $rolId): int
    {
        return $this->usuarios->crear([
            'nombre' => 'Funcionario ' . $correo,
            'correo' => $correo,
            'hash_password' => (new HasherPassword())->hash('Jazmin7Puente4Rio'),
            'rol_id' => $rolId,
            'activo' => 1,
            'debe_cambiar_pass' => 0,
        ]);
    }
}
