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
 * Semillas de los menús del sitio, incluidos los obligatorios de la
 * Res. 1519 (Transparencia, Atención a la Ciudadanía, Participa), marcados
 * como protegidos: se pueden renombrar y reordenar, no borrar.
 *
 * Idempotente por slug de menú: si el menú ya existe no se toca nada suyo.
 */
final class SembrarMenusCommand extends Command
{
    /**
     * slug => [nombre, ubicacion, protegido, items: [etiqueta, url, protegido][]]
     *
     * @var array<string, array{string, string, int, list<array{string, string, int}>}>
     */
    private const array MENUS = [
        'principal' => ['Menú principal', 'principal', 1, [
            ['Inicio', '/', 1],
            ['Servicios', '/servicios', 0],
            ['Especialidades', '/especialidades', 0],
            ['Directorio médico', '/directorio', 0],
            ['Sedes', '/sedes', 0],
            ['Noticias', '/noticias', 1],
            ['Transparencia', '/transparencia', 1],
        ]],
        'superior' => ['Barra superior', 'superior', 1, [
            ['Transparencia', '/transparencia', 1],
            ['Atención y Servicios a la Ciudadanía', '/atencion-ciudadania', 1],
            ['Participa', '/participa', 1],
        ]],
        'pie-enlaces' => ['Pie: enlaces de interés', 'pie_1', 0, [
            ['Mapa del sitio', '/mapa-del-sitio', 0],
            ['Política de privacidad', '/politica-privacidad-web', 1],
            ['Política de tratamiento de datos', '/politica-tratamiento-datos', 1],
            ['PQRSDF', '/atencion-ciudadania/pqrsdf', 1],
        ]],
        'pie-normatividad' => ['Pie: normatividad', 'pie_2', 0, [
            ['Sistema Único de Habilitación', '/transparencia/normatividad', 0],
            ['Informes y reportes', '/transparencia/planeacion-presupuesto-informes', 0],
            ['Datos abiertos', '/transparencia/datos-abiertos', 0],
            ['Notificaciones judiciales', '/notificaciones-judiciales', 1],
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
            ->setName('semillas:menus')
            ->setDescription('Crea los menús del sitio (sin tocar los que ya existen)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creados = 0;

        foreach (self::MENUS as $slug => [$nombre, $ubicacion, $protegido, $items]) {
            $existe = $this->conexion->fetchOne('SELECT id FROM menus WHERE slug = ?', [$slug]);

            if ($existe !== false) {
                continue;
            }

            $this->conexion->insert('menus', [
                'nombre' => $nombre,
                'slug' => $slug,
                'ubicacion' => $ubicacion,
                'protegido' => $protegido,
            ]);
            $menuId = (int) $this->conexion->lastInsertId();

            foreach ($items as $orden => [$etiqueta, $url, $itemProtegido]) {
                $this->conexion->insert('menu_items', [
                    'menu_id' => $menuId,
                    'etiqueta' => $etiqueta,
                    'url' => $url,
                    'orden' => $orden,
                    'protegido' => $itemProtegido,
                ]);
            }

            $creados++;
        }

        if ($creados > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'menus',
                descripcion: sprintf('Semillas de menús: %d menús creados', $creados),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d menús creados, %d ya existían.', $creados, count(self::MENUS) - $creados));

        return Command::SUCCESS;
    }
}
