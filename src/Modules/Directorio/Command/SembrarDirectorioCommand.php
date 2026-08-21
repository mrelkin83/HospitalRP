<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Semillas del directorio: las tres sedes de frontend.png (datos
 * provisionales, se corrigen en el panel) y especialidades típicas de una
 * E.S.E. de mediana complejidad. Idempotente por slug; nunca pisa lo editado.
 *
 * NO siembra profesionales: son datos personales que requieren autorización
 * individual (Ley 1581).
 */
final class SembrarDirectorioCommand extends Command
{
    /** @var array<string, array{string, string, string, int}> nombre => [direccion, telefono, horario, urgencias] */
    private const array SEDES = [
        'Sede Principal' => ['Cra. 24 #16-85', '(607) 123 4567', 'Lunes a viernes: 7:00 a. m. – 5:00 p. m.', 1],
        'Sede La Nevada' => ['Cl. 37 #4-25', '(607) 234 5678', 'Lunes a viernes: 7:00 a. m. – 4:00 p. m.', 1],
        'Sede San Martín' => ['Cl. 19 #12-45', '(607) 345 6789', 'Lunes a viernes: 7:00 a. m. – 4:00 p. m.', 1],
    ];

    private const array ESPECIALIDADES = [
        'Medicina interna', 'Pediatría', 'Ginecología y obstetricia', 'Cirugía general',
        'Ortopedia y traumatología', 'Anestesiología', 'Urología', 'Psiquiatría',
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
            ->setName('semillas:directorio')
            ->setDescription('Crea sedes y especialidades provisionales (sin tocar las existentes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creadas = 0;

        $orden = 0;

        foreach (self::SEDES as $nombre => [$direccion, $telefono, $horario, $urgencias]) {
            $slug = Slugs::desde($nombre, 170);

            if (false !== $this->conexion->fetchOne('SELECT id FROM sedes WHERE slug = ?', [$slug])) {
                continue;
            }

            $this->conexion->insert('sedes', [
                'nombre' => $nombre,
                'slug' => $slug,
                'direccion' => $direccion,
                'municipio' => 'Valledupar',
                'departamento' => 'Cesar',
                'telefono' => $telefono,
                'horario' => json_encode([$horario, 'Urgencias: 24 horas'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'tiene_urgencias' => $urgencias,
                'orden' => $orden++,
            ]);

            $creadas++;
        }

        $orden = 0;

        foreach (self::ESPECIALIDADES as $nombre) {
            $slug = Slugs::desde($nombre, 170);

            if (false !== $this->conexion->fetchOne('SELECT id FROM especialidades WHERE slug = ?', [$slug])) {
                continue;
            }

            $this->conexion->insert('especialidades', [
                'nombre' => $nombre,
                'slug' => $slug,
                'orden' => $orden++,
            ]);

            $creadas++;
        }

        if ($creadas > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'sedes',
                descripcion: sprintf('Semillas de directorio: %d registros provisionales creados', $creadas),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d registros creados. Verificar direcciones y teléfonos reales en el panel.', $creadas));

        return Command::SUCCESS;
    }
}
