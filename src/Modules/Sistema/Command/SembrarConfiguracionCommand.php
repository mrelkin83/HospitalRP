<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Semillas de la tabla `configuracion`: crea las claves que el sitio público
 * necesita, con valores provisionales tomados de frontend.png.
 *
 * Idempotente y respetuosa: si la clave ya existe NO toca su valor — lo que
 * el hospital haya editado en el panel manda sobre cualquier semilla.
 */
final class SembrarConfiguracionCommand extends Command
{
    /**
     * clave => [valor, tipo, grupo, etiqueta, ayuda]
     *
     * @var array<string, array{string, string, string, string, ?string}>
     */
    private const array SEMILLAS = [
        'entidad.nombre' => [
            'E.S.E. Hospital Rosario Pumarejo de López',
            'texto', 'entidad', 'Nombre de la entidad', null,
        ],
        'entidad.eslogan' => [
            'Comprometidos con tu salud y bienestar',
            'texto', 'entidad', 'Eslogan', 'Aparece bajo el nombre en el encabezado',
        ],
        'entidad.nit' => [
            '892.300.445-1',
            'texto', 'entidad', 'NIT', null,
        ],
        'contacto.linea_nacional' => [
            '01 8000 123 456',
            'texto', 'contacto', 'Línea única nacional', 'Verificar el número real antes de salir a producción',
        ],
        'contacto.pbx' => [
            '(607) 123 4567',
            'texto', 'contacto', 'PBX', 'Verificar el número real antes de salir a producción',
        ],
        'contacto.emergencias' => [
            '(607) 123 4567',
            'texto', 'contacto', 'Teléfono de emergencias', 'Verificar el número real antes de salir a producción',
        ],
        'contacto.correo' => [
            'contacto@hospitalrosariopumarejo.gov.co',
            'texto', 'contacto', 'Correo de atención al ciudadano', null,
        ],
        'contacto.correo_notificaciones' => [
            'notificacionesjudiciales@hospitalrosariopumarejo.gov.co',
            'texto', 'contacto', 'Correo de notificaciones judiciales', 'Obligatorio según la Res. 1519',
        ],
        'contacto.direccion' => [
            'Cra. 24 #16-85, Valledupar, Cesar',
            'texto', 'contacto', 'Dirección de la sede principal', null,
        ],
        'contacto.horario' => [
            'Lunes a viernes, 7:00 a. m. a 5:00 p. m.',
            'texto', 'contacto', 'Horario de atención administrativa', null,
        ],
        'redes.facebook' => ['', 'texto', 'redes', 'Facebook (URL)', null],
        'redes.instagram' => ['', 'texto', 'redes', 'Instagram (URL)', null],
        'redes.youtube' => ['', 'texto', 'redes', 'YouTube (URL)', null],
        'redes.x' => ['', 'texto', 'redes', 'X / Twitter (URL)', null],
        'redes.linkedin' => ['', 'texto', 'redes', 'LinkedIn (URL)', null],
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('semillas:configuracion')
            ->setDescription('Crea las claves de configuración del sitio (sin pisar valores editados)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creadas = 0;
        $orden = 0;

        foreach (self::SEMILLAS as $clave => [$valor, $tipo, $grupo, $etiqueta, $ayuda]) {
            $afectadas = $this->conexion->executeStatement(
                'INSERT IGNORE INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                [$clave, $valor, $tipo, $grupo, $etiqueta, $ayuda, $orden++],
            );

            $creadas += (int) $afectadas;
        }

        if ($creadas > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'configuracion',
                descripcion: sprintf('Semillas de configuración: %d claves creadas', $creadas),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf(
            '%d claves creadas, %d ya existían y no se tocaron.',
            $creadas,
            count(self::SEMILLAS) - $creadas,
        ));

        return Command::SUCCESS;
    }
}
