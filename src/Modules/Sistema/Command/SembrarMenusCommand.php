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
 * La estructura sale del §6 de `docs/14`: el Joomla tenía 16 raíces y ~134
 * ítems repartidos en dos menús que se solapaban. Aquí son 7 raíces y como
 * mucho dos niveles, porque el criterio n.º 1 de CLAUDE.md es que el
 * ciudadano encuentre lo que busca desde el celular en menos de 30 segundos.
 *
 * Idempotente **por ítem** (menú + padre + url), no por menú: así una
 * instalación ya sembrada recibe los ítems nuevos sin duplicar ni pisar lo
 * que el hospital haya editado. Un ítem borrado a propósito desde el panel
 * reaparece si se vuelve a correr la semilla; es el precio de poder
 * completar menús existentes, y se corre a mano, no en cada despliegue.
 */
final class SembrarMenusCommand extends Command
{
    /**
     * slug => [nombre, ubicacion, protegido, items]
     * item  => [etiqueta, url, protegido, hijos]
     *
     * @var array<string, array{string, string, int, list<array{string, string, int, list<array{string, string, int}>}>}>
     */
    private const array MENUS = [
        'principal' => ['Menú principal', 'principal', 1, [
            ['El Hospital', '/el-hospital', 0, [
                ['Historia', '/el-hospital/historia', 0],
                ['Misión y visión', '/el-hospital/mision-y-vision', 0],
                ['Objetivos y funciones', '/el-hospital/objetivos-y-funciones', 0],
                ['Principios corporativos', '/el-hospital/principios-corporativos', 0],
                ['Organigrama', '/el-hospital/organigrama', 0],
                ['Infraestructura', '/el-hospital/infraestructura', 0],
                ['Niveles de atención', '/el-hospital/niveles-de-atencion', 0],
                ['Agremiaciones y asociaciones', '/el-hospital/agremiaciones-y-asociaciones', 0],
                ['Localización y horarios', '/el-hospital/localizacion', 0],
                ['Políticas institucionales', '/el-hospital/politicas-institucionales', 0],
                ['Certificado de accesibilidad', '/el-hospital/certificado-de-accesibilidad', 0],
            ]],
            ['Servicios', '/servicios', 0, [
                ['Portafolio de servicios', '/servicios', 0],
                ['Especialidades', '/especialidades', 0],
                ['Directorio médico', '/directorio', 0],
                ['Sedes y horarios', '/sedes', 0],
                ['Urgencias', '/urgencias', 0],
            ]],
            ['Programas', '/programas', 0, [
                ['IAMII', '/programas/iamii', 0],
                ['Banco de leche humana', '/programas/iamii/banco-de-leche', 0],
                ['Programa Madre Canguro', '/programas/iamii/madre-canguro', 0],
                ['Humanización', '/programas/humanizacion', 0],
                ['Docencia e investigación', '/programas/docencia', 0],
                ['Espacio etnocultural', '/programas/etnocultural', 0],
                ['Banco de sangre', '/programas/banco-de-sangre', 0],
            ]],
            ['Atención a la Ciudadanía', '/atencion-ciudadania', 1, [
                ['Radicar PQRSDF', '/atencion-ciudadania/pqrsdf', 1],
                ['Consultar un radicado', '/atencion-ciudadania/pqrsdf/consultar', 0],
                ['Trámites y servicios', '/atencion-ciudadania/tramites', 0],
                ['Solicitud de cita', '/citas', 0],
                ['Preguntas frecuentes', '/atencion-ciudadania/preguntas-frecuentes', 0],
                ['Glosario', '/atencion-ciudadania/glosario', 0],
                ['Deberes y derechos', '/atencion-ciudadania/deberes-y-derechos', 0],
                ['Encuestas de satisfacción', '/encuestas', 0],
                ['Contáctenos', '/contacto', 0],
            ]],
            ['Transparencia', '/transparencia', 1, [
                ['Información de la entidad', '/transparencia/informacion-de-la-entidad', 0],
                ['Directorio de servidores públicos', '/servidores-publicos', 0],
                ['Normativa', '/transparencia/normatividad', 0],
                ['Contratación', '/transparencia/contratacion', 0],
                ['Planeación, presupuesto e informes', '/transparencia/planeacion-presupuesto-informes', 0],
                ['Datos abiertos', '/transparencia/datos-abiertos', 0],
                ['Convocatorias', '/convocatorias', 0],
            ]],
            ['Participa', '/participa', 1, [
                ['Rendición de cuentas', '/transparencia/participa', 0],
                ['Calendario de participación', '/eventos', 0],
                ['Radicar PQRSDF', '/atencion-ciudadania/pqrsdf', 0],
            ]],
            ['Prensa', '/noticias', 1, [
                ['Noticias y boletines', '/noticias', 1],
                ['Calendario de eventos', '/eventos', 0],
            ]],
        ]],
        'superior' => ['Barra superior', 'superior', 1, [
            ['Transparencia', '/transparencia', 1, []],
            ['Atención y Servicios a la Ciudadanía', '/atencion-ciudadania', 1, []],
            ['Participa', '/participa', 1, []],
            ['Buscar', '/buscar', 0, []],
            // Sistema externo con login propio: el sitio nuevo NO muestra
            // resultados (dato clínico, CLAUDE.md §2.6), solo enlaza.
            ['Resultados de imagenología', 'https://resultadosrx.hrplopez.gov.co:444/login/index.php', 0, []],
        ]],
        'pie-enlaces' => ['Pie: enlaces de interés', 'pie_1', 0, [
            ['Mapa del sitio', '/mapa-del-sitio', 0, []],
            ['Política de privacidad', '/politica-privacidad-web', 1, []],
            ['Política de tratamiento de datos', '/politica-tratamiento-datos', 1, []],
            ['Política de cookies', '/politica-de-cookies', 1, []],
            ['Accesibilidad', '/accesibilidad', 1, []],
            ['PQRSDF', '/atencion-ciudadania/pqrsdf', 1, []],
        ]],
        'pie-normatividad' => ['Pie: normatividad', 'pie_2', 0, [
            ['Sistema Único de Habilitación', '/transparencia/reportes-sector-salud', 0, []],
            ['Informes y reportes', '/transparencia/planeacion-presupuesto-informes', 0, []],
            ['Datos abiertos', '/transparencia/datos-abiertos', 0, []],
            ['Notificaciones judiciales', '/notificaciones-judiciales', 1, []],
        ]],
        'pie-institucional' => ['Pie: gestión institucional', 'pie_3', 0, [
            ['Talento humano', '/talento-humano', 0, []],
            ['Convocatorias y empleo', '/convocatorias', 0, []],
            ['MECI', '/gestion/meci', 0, []],
            ['MIPG', '/gestion/mipg', 0, []],
            ['Control disciplinario interno', '/gestion/control-disciplinario', 0, []],
            ['Conflicto de intereses', '/gestion/conflicto-de-intereses', 0, []],
            ['Conciliaciones', '/gestion/conciliaciones', 0, []],
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
            ->setDescription('Crea o completa los menús del sitio (sin duplicar ni pisar lo editado)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creados = 0;

        foreach (self::MENUS as $slug => [$nombre, $ubicacion, $protegido, $items]) {
            $menuId = $this->asegurarMenu((string) $slug, $nombre, $ubicacion, $protegido, $creados);

            foreach ($items as $orden => [$etiqueta, $url, $itemProtegido, $hijos]) {
                $padreId = $this->asegurarItem($menuId, null, $etiqueta, $url, $orden, $itemProtegido, $creados);

                foreach ($hijos as $ordenHijo => [$etiquetaHija, $urlHija, $protegidoHijo]) {
                    $this->asegurarItem($menuId, $padreId, $etiquetaHija, $urlHija, $ordenHijo, $protegidoHijo, $creados);
                }
            }
        }

        if ($creados > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'menus',
                descripcion: sprintf('Semillas de menús: %d registros creados', $creados),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d registros de menú creados; el resto ya existía.', $creados));

        return Command::SUCCESS;
    }

    /** @param-out int $creados */
    private function asegurarMenu(string $slug, string $nombre, string $ubicacion, int $protegido, int &$creados): int
    {
        $id = $this->conexion->fetchOne('SELECT id FROM menus WHERE slug = ?', [$slug]);

        if ($id !== false) {
            return (int) $id;
        }

        $this->conexion->insert('menus', [
            'nombre' => $nombre,
            'slug' => $slug,
            'ubicacion' => $ubicacion,
            'protegido' => $protegido,
        ]);
        $creados++;

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * La identidad de un ítem es (menú, padre, url): el mismo destino puede
     * repetirse en menús distintos —PQRSDF vive en el principal y en el pie—
     * pero no dos veces bajo el mismo padre.
     *
     * @param-out int $creados
     */
    private function asegurarItem(
        int $menuId,
        ?int $padreId,
        string $etiqueta,
        string $url,
        int $orden,
        int $protegido,
        int &$creados,
    ): int {
        $id = $this->conexion->fetchOne(
            $padreId === null
                ? 'SELECT id FROM menu_items WHERE menu_id = ? AND url = ? AND padre_id IS NULL'
                : 'SELECT id FROM menu_items WHERE menu_id = ? AND url = ? AND padre_id = ?',
            $padreId === null ? [$menuId, $url] : [$menuId, $url, $padreId],
        );

        if ($id !== false) {
            return (int) $id;
        }

        $this->conexion->insert('menu_items', [
            'menu_id' => $menuId,
            'padre_id' => $padreId,
            'etiqueta' => $etiqueta,
            'url' => $url,
            'orden' => $orden,
            'protegido' => $protegido,
            'destino' => str_starts_with($url, 'http') ? 'nueva' : 'mismo',
        ]);
        $creados++;

        return (int) $this->conexion->lastInsertId();
    }
}
