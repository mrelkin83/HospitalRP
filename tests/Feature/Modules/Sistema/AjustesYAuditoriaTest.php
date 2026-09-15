<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Modules\Sistema\Repository\AuditoriaRepository;
use App\Modules\Sistema\Repository\ConfiguracionRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Ajustes del sitio y bandeja de auditoría (Fase 10).
 */
#[CoversClass(ConfiguracionRepository::class)]
#[CoversClass(AuditoriaRepository::class)]
final class AjustesYAuditoriaTest extends TestCase
{
    private static Connection $conexion;
    private ConfiguracionRepository $configuracion;
    private AuditoriaRepository $auditoria;

    /**
     * Etiqueta propia de cada prueba.
     *
     * La tabla `auditoria` es append-only por disparador en la base: no se
     * puede vaciar entre pruebas, ni debería poderse. Así que cada prueba
     * marca sus filas con una entidad única y filtra por ella en vez de
     * suponer que la tabla empieza vacía.
     */
    private string $entidad;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM configuracion');
        $this->configuracion = new ConfiguracionRepository(self::$conexion);
        $this->auditoria = new AuditoriaRepository(self::$conexion);
        $this->entidad = 'prueba_' . bin2hex(random_bytes(4));
    }

    public function testGuardarDevuelveSoloLoQueDeVerdadCambio(): void
    {
        $this->crearAjuste('contacto.pbx', '(605) 5712339', 'PBX', 'contacto');
        $this->crearAjuste('contacto.correo', 'contacto@hrplopez.gov.co', 'Correo', 'contacto');

        $cambios = $this->configuracion->guardar([
            'contacto.pbx' => '(605) 9999999',
            'contacto.correo' => 'contacto@hrplopez.gov.co',
        ], usuarioId: null);

        // El correo llegó igual: auditarlo como cambio llenaría el registro de
        // ruido y escondería los cambios reales.
        self::assertSame(['contacto.pbx'], array_keys($cambios));
        self::assertSame('(605) 5712339', $cambios['contacto.pbx']['antes']);
        self::assertSame('(605) 9999999', $cambios['contacto.pbx']['despues']);
    }

    public function testGuardarIgnoraLasClavesQueNoExistenEnLaTabla(): void
    {
        $this->crearAjuste('contacto.pbx', '(605) 5712339', 'PBX', 'contacto');

        $cambios = $this->configuracion->guardar(['clave.inventada' => 'lo que sea'], usuarioId: null);

        self::assertSame([], $cambios);
        self::assertFalse(self::$conexion->fetchOne('SELECT id FROM configuracion WHERE clave = ?', ['clave.inventada']));
    }

    public function testGuardarNoTocaLosAjustesMarcadosComoNoEditables(): void
    {
        $this->crearAjuste('sistema.version', '2.6', 'Versión', 'sitio', editable: false);

        $cambios = $this->configuracion->guardar(['sistema.version' => '9.9'], usuarioId: null);

        self::assertSame([], $cambios);
        self::assertSame('2.6', self::$conexion->fetchOne('SELECT valor FROM configuracion WHERE clave = ?', ['sistema.version']));
    }

    public function testLosGruposSalenEnElOrdenPensadoParaElFuncionario(): void
    {
        $this->crearAjuste('redes.facebook', '', 'Facebook', 'redes');
        $this->crearAjuste('entidad.nombre', 'E.S.E.', 'Nombre', 'entidad');
        $this->crearAjuste('contacto.pbx', '(605) 5712339', 'PBX', 'contacto');

        // La identidad de la entidad antes que las redes sociales: quien entra
        // a ajustes busca primero el teléfono, no el enlace de Facebook.
        self::assertSame(['entidad', 'contacto', 'redes'], array_keys($this->configuracion->porGrupos()));
    }

    public function testLaBandejaDeAuditoriaFiltraPorAccion(): void
    {
        $servicio = new AuditoriaService(self::$conexion);
        $servicio->registrar($this->registro(AccionAuditoria::CambioPassword, 'Cambió su contraseña'));
        $servicio->registrar($this->registro(AccionAuditoria::Creacion, 'Creó una noticia'));

        $resultado = $this->auditoria->buscar([
            'accion' => AccionAuditoria::CambioPassword->value,
            'entidad' => $this->entidad,
        ]);

        self::assertSame(1, $resultado['total']);
        self::assertSame('Cambió su contraseña', $resultado['registros'][0]['descripcion']);
    }

    public function testLaBandejaDevuelveLaIpEnTextoYNoEnBinario(): void
    {
        (new AuditoriaService(self::$conexion))->registrar(
            $this->registro(AccionAuditoria::InicioSesion, 'Entró al panel', '190.85.12.4'),
        );

        $resultado = $this->auditoria->buscar(['entidad' => $this->entidad]);

        self::assertSame('190.85.12.4', $resultado['registros'][0]['ip_texto']);
    }

    public function testLaPaginaPedidaSeAjustaAlRangoQueExiste(): void
    {
        (new AuditoriaService(self::$conexion))->registrar($this->registro(AccionAuditoria::Creacion, 'Único'));

        // Pedir una página que no existe debe devolver la última que sí, no
        // una pantalla vacía sin explicación de por qué.
        $resultado = $this->auditoria->buscar(['entidad' => $this->entidad], pagina: 9999);

        self::assertSame($resultado['paginas'], $resultado['pagina']);
        self::assertCount(1, $resultado['registros']);
    }

    public function testElUltimoRegistradoApareceDePrimeroEnLaPortada(): void
    {
        $servicio = new AuditoriaService(self::$conexion);
        $servicio->registrar($this->registro(AccionAuditoria::Creacion, 'Lo viejo'));
        $servicio->registrar($this->registro(AccionAuditoria::Creacion, 'Lo nuevo'));

        self::assertSame('Lo nuevo', $this->auditoria->ultimos(5)[0]['descripcion']);
    }

    private function registro(AccionAuditoria $accion, string $descripcion, ?string $ip = null): RegistroAuditoria
    {
        return new RegistroAuditoria(
            accion: $accion,
            entidad: $this->entidad,
            entidadId: 1,
            descripcion: $descripcion,
            usuarioNombre: 'Funcionaria de prueba',
            ip: $ip,
        );
    }

    private function crearAjuste(
        string $clave,
        string $valor,
        string $etiqueta,
        string $grupo,
        bool $editable = true,
    ): void {
        self::$conexion->insert('configuracion', [
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => 'texto',
            'grupo' => $grupo,
            'etiqueta' => $etiqueta,
            'orden' => 0,
            'editable' => $editable ? 1 : 0,
        ]);
    }
}
