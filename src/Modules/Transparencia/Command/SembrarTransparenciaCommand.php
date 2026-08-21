<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Semilla del menú de Transparencia según el Anexo 2 de la Res. 1519 de 2020
 * (versión estándar). ⚠️ El hospital debe cotejarla con la versión VIGENTE
 * del anexo: desde el panel se puede renombrar, marcar «no aplica» y
 * completar, sin tocar código.
 *
 * Idempotente por código de sección. Siembra además el enlace a SECOP en
 * contratación (criterio de aceptación de la Fase 4).
 */
final class SembrarTransparenciaCommand extends Command
{
    /**
     * codigo => [nombre, hijos: codigo => nombre]
     *
     * @var array<string, array{string, array<string, string>}>
     */
    private const array SECCIONES = [
        '1' => ['Información de la entidad', [
            '1.1' => 'Misión, visión, funciones y deberes',
            '1.2' => 'Estructura orgánica — organigrama',
            '1.3' => 'Directorio institucional (sedes y horarios)',
            '1.4' => 'Directorio de servidores públicos y empleados',
            '1.5' => 'Directorio de entidades del sector',
            '1.6' => 'Calendario de actividades y eventos',
            '1.7' => 'Procesos y procedimientos',
        ]],
        '2' => ['Normativa', [
            '2.1' => 'Leyes y normas que rigen a la entidad',
            '2.2' => 'Decretos',
            '2.3' => 'Resoluciones y otros actos administrativos',
            '2.4' => 'Circulares',
        ]],
        '3' => ['Contratación', [
            '3.1' => 'Plan Anual de Adquisiciones',
            '3.2' => 'Publicación de procesos contractuales (SECOP)',
            '3.3' => 'Ejecución de contratos',
            '3.4' => 'Manual de contratación',
        ]],
        '4' => ['Planeación, presupuesto e informes', [
            '4.1' => 'Presupuesto general y ejecución presupuestal',
            '4.2' => 'Plan de acción y plan de desarrollo institucional',
            '4.3' => 'Informes de gestión, evaluación y auditoría',
            '4.4' => 'Estados financieros',
            '4.5' => 'Informes a organismos de inspección, vigilancia y control',
            '4.6' => 'Planes institucionales (Decreto 612)',
        ]],
        '5' => ['Trámites', [
            '5.1' => 'Trámites y servicios de la entidad',
        ]],
        '6' => ['Participa', [
            '6.1' => 'Rendición de cuentas',
            '6.2' => 'Consulta y participación ciudadana',
            '6.3' => 'Asociación de usuarios',
        ]],
        '7' => ['Datos abiertos', [
            '7.1' => 'Conjuntos de datos publicados',
        ]],
        '8' => ['Información específica para grupos de interés', [
            '8.1' => 'Información para niñas, niños y adolescentes',
            '8.2' => 'Información para mujeres',
        ]],
        '9' => ['Obligación de reporte de información específica', [
            '9.1' => 'Sistema Único de Habilitación',
            '9.2' => 'Informes exigidos por normativa del sector salud',
        ]],
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
            ->setName('semillas:transparencia')
            ->setDescription('Siembra las secciones del Anexo 2 de la Res. 1519 (sin tocar lo existente)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creadas = 0;

        foreach (self::SECCIONES as $codigo => [$nombre, $hijos]) {
            // PHP convierte las claves «1»…«9» a int; los códigos son texto.
            $padreId = $this->asegurarSeccion((string) $codigo, $nombre, null, (int) $codigo, $creadas);

            $orden = 0;

            foreach ($hijos as $codigoHijo => $nombreHijo) {
                $this->asegurarSeccion((string) $codigoHijo, $nombreHijo, $padreId, $orden++, $creadas);
            }
        }

        // Criterio de la fase: enlace a SECOP presente en contratación.
        $seccionSecop = $this->conexion->fetchOne("SELECT id FROM transparencia_secciones WHERE codigo = '3.2'");

        if ($seccionSecop !== false) {
            $yaExiste = $this->conexion->fetchOne(
                "SELECT id FROM transparencia_items WHERE seccion_id = ? AND url_externa LIKE '%colombiacompra%'",
                [(int) $seccionSecop],
            );

            if ($yaExiste === false) {
                $this->conexion->insert('transparencia_items', [
                    'seccion_id' => (int) $seccionSecop,
                    'url_externa' => 'https://www.colombiacompra.gov.co/secop/secop-ii',
                    'titulo' => 'Procesos contractuales de la entidad en SECOP II',
                    'descripcion' => 'Consulta los procesos de contratación publicados por la E.S.E. en el Sistema Electrónico de Contratación Pública.',
                    'orden' => 0,
                ]);
                $creadas++;
            }
        }

        if ($creadas > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'transparencia_secciones',
                descripcion: sprintf('Semilla del Anexo 2 (Res. 1519): %d registros creados', $creadas),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d registros creados.', $creadas));
        $io->warning('Cotejar la estructura con la versión VIGENTE del Anexo 2 de la Res. 1519.');

        return Command::SUCCESS;
    }

    /**
     * @param-out int $creadas
     */
    private function asegurarSeccion(string $codigo, string $nombre, ?int $padreId, int $orden, int &$creadas): int
    {
        $id = $this->conexion->fetchOne('SELECT id FROM transparencia_secciones WHERE codigo = ?', [$codigo]);

        if ($id !== false) {
            return (int) $id;
        }

        $this->conexion->insert('transparencia_secciones', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'padre_id' => $padreId,
            'orden' => $orden,
            'obligatoria' => 1,
        ]);
        $creadas++;

        return (int) $this->conexion->lastInsertId();
    }
}
