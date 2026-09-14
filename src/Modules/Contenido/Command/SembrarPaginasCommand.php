<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Páginas institucionales del sitio. Nace de la auditoría del 2026-09-14
 * (docs/14 §5.1): el menú sembrado enlazaba `/participa` y
 * `/notificaciones-judiciales` sin que existiera página que las sirviera, así
 * que dos ítems protegidos por la Res. 1519 respondían 404.
 *
 * Dos estados a propósito:
 *
 * - `publicado`: páginas cuyo texto está **verificado** contra el sitio
 *   vigente del hospital o es puramente navegacional. Salen al aire.
 * - `borrador`: páginas cuyo contenido solo lo tiene el hospital. Aparecen
 *   en el panel listas para redactar, pero NO al público: publicar una ficha
 *   institucional vacía es peor que no tenerla.
 *
 * Idempotente por ruta: una página ya existente no se toca nunca, para no
 * pisar lo que el hospital haya redactado desde el panel.
 */
final class SembrarPaginasCommand extends Command
{
    private const string PUBLICADA = 'publicado';
    private const string BORRADOR = 'borrador';

    /**
     * ruta => [titulo, resumen, contenido HTML, estado, protegida]
     *
     * @var array<string, array{string, string, string, string, int}>
     */
    private const array PAGINAS = [
        '/el-hospital' => [
            'El Hospital',
            'Información institucional de la E.S.E. Hospital Rosario Pumarejo de López.',
            '<p>La E.S.E. Hospital Rosario Pumarejo de López es la institución prestadora '
                . 'de servicios de salud de referencia del departamento del Cesar, con sede única '
                . 'en Valledupar.</p>'
                . '<p>En esta sección encuentras la información institucional de la entidad:</p>'
                . '<ul>'
                . '<li><a href="/el-hospital/historia">Historia</a></li>'
                . '<li><a href="/el-hospital/mision-y-vision">Misión y visión</a></li>'
                . '<li><a href="/el-hospital/objetivos-y-funciones">Objetivos y funciones</a></li>'
                . '<li><a href="/el-hospital/principios-corporativos">Principios corporativos</a></li>'
                . '<li><a href="/el-hospital/organigrama">Organigrama</a></li>'
                . '<li><a href="/el-hospital/infraestructura">Infraestructura</a></li>'
                . '<li><a href="/el-hospital/niveles-de-atencion">Niveles de atención</a></li>'
                . '<li><a href="/el-hospital/agremiaciones-y-asociaciones">Agremiaciones y asociaciones</a></li>'
                . '<li><a href="/el-hospital/localizacion">Localización y horarios</a></li>'
                . '<li><a href="/el-hospital/politicas-institucionales">Políticas institucionales</a></li>'
                . '<li><a href="/el-hospital/certificado-de-accesibilidad">Certificado de accesibilidad</a></li>'
                . '</ul>',
            self::PUBLICADA,
            0,
        ],

        // Texto tomado literalmente del sitio vigente del hospital
        // (hrplopez.gov.co/sitio/index.php/es/niveles-de-atencion), medido en
        // el rastreo del 2026-09-14. No es contenido inventado.
        '/el-hospital/niveles-de-atencion' => [
            'Niveles de atención',
            'Servicios de II nivel y actividades de III y IV nivel de atención en salud.',
            '<p>Con más de cincuenta años como institución prestadora de servicios de salud, '
                . 'la E.S.E. Hospital Rosario Pumarejo de López ofrece experiencia en la prestación '
                . 'de servicios de salud de II nivel y algunas actividades de III y IV nivel de '
                . 'atención en salud.</p>'
                . '<h2>Servicios de II nivel</h2>'
                . '<ul>'
                . '<li>Urgencias médicas 24 horas</li>'
                . '<li>Servicios ambulatorios médicos (consultas y procedimientos)</li>'
                . '<li>Hospitalización con 106 camas</li>'
                . '<li>Medicina interna: 36 camas</li>'
                . '<li>Gineco-obstetricia: 13 camas</li>'
                . '<li>Cirugía general: 28 camas</li>'
                . '<li>Pediatría: 22 camas</li>'
                . '<li>Psiquiatría: 7 camas</li>'
                . '<li>Servicios obstétricos y partos con 1 quirófano</li>'
                . '<li>Servicio quirúrgico con 10 quirófanos</li>'
                . '<li>Laboratorio clínico de II nivel</li>'
                . '<li>Radiología de II nivel</li>'
                . '<li>Ecografía de II nivel</li>'
                . '</ul>'
                . '<h2>Actividades de III y IV nivel</h2>'
                . '<p>Se prestan a la población afiliada a los regímenes subsidiado, contributivo '
                . 'y especial, y a la población pobre no cubierta con subsidio a la demanda.</p>'
                . '<ul>'
                . '<li>Urgencias médicas 24 horas</li>'
                . '<li>Servicios ambulatorios médicos</li>'
                . '<li>Hospitalización con 17 camas</li>'
                . '<li>Unidad de cuidados intensivos: 5 camas</li>'
                . '<li>Neonatos: 12 camas</li>'
                . '<li>Servicios obstétricos y partos</li>'
                . '</ul>'
                . '<p>Las cifras provienen del sitio institucional vigente. '
                . 'Actualízalas desde el panel cuando cambie la capacidad instalada.</p>',
            self::PUBLICADA,
            0,
        ],

        // Ítem protegido de la Res. 1519. El texto es el marco normativo, que
        // es verificable; los espacios de participación concretos los
        // completa el hospital desde el panel.
        '/participa' => [
            'Participa',
            'Espacios, mecanismos y acciones de participación ciudadana de la E.S.E.',
            '<p>En esta sección encuentras información sobre los espacios, mecanismos y '
                . 'acciones que implementa la E.S.E. Hospital Rosario Pumarejo de López para '
                . 'fomentar la participación de la ciudadanía en las decisiones de la gestión '
                . 'pública.</p>'
                . '<p>Lo anterior en cumplimiento de la Política de Participación Ciudadana en la '
                . 'Gestión Pública y en armonización con la Política de Participación Social en '
                . 'Salud, conforme a la Ley 1757 de 2015, la Ley 1712 de 2014, el Decreto 1499 de '
                . '2017, la Resolución 1519 de 2020 del Ministerio de Tecnologías de la Información '
                . 'y las Comunicaciones y la Resolución 2063 de 2017 del Ministerio de Salud y '
                . 'Protección Social.</p>'
                . '<h2>Espacios donde puedes participar</h2>'
                . '<ul>'
                . '<li>Junta Directiva</li>'
                . '<li>SIAU — Servicio de Información y Atención al Usuario</li>'
                . '<li>Asociación de usuarios</li>'
                . '<li>Comité de Ética Hospitalaria</li>'
                . '<li>Audiencias públicas de rendición de cuentas</li>'
                . '</ul>'
                . '<h2>Cómo participar</h2>'
                . '<ul>'
                . '<li><a href="/atencion-ciudadania/pqrsdf">Radica una petición, queja, reclamo, '
                . 'sugerencia o denuncia</a></li>'
                . '<li><a href="/transparencia#seccion-6">Consulta la sección Participa del menú '
                . 'de transparencia</a></li>'
                . '<li><a href="/contacto">Escríbenos o llámanos</a></li>'
                . '</ul>',
            self::PUBLICADA,
            1,
        ],

        // Ítem protegido del pie. Los canales son los del inventario
        // institucional (docs/08), tomados del sitio vigente.
        '/notificaciones-judiciales' => [
            'Notificaciones judiciales',
            'Canal oficial para notificaciones judiciales de la E.S.E.',
            '<p>La E.S.E. Hospital Rosario Pumarejo de López recibe notificaciones judiciales '
                . 'en el siguiente canal oficial, conforme al artículo 197 del Código de '
                . 'Procedimiento Administrativo y de lo Contencioso Administrativo y al artículo 8 '
                . 'del Decreto 806 de 2020.</p>'
                . '<ul>'
                . '<li><strong>Correo electrónico:</strong> '
                . '<a href="mailto:notificacionesjudiciales@hrplopez.gov.co">'
                . 'notificacionesjudiciales@hrplopez.gov.co</a></li>'
                . '<li><strong>Dirección:</strong> Calle 16 n.º 17-141, barrio Hernando Santana '
                . '(avenida La Popa), Valledupar, Cesar</li>'
                . '<li><strong>Horario de radicación:</strong> lunes a viernes, 7:30 a. m. a '
                . '12:00 m. y 2:00 p. m. a 6:00 p. m.</li>'
                . '</ul>'
                . '<p>Este canal es exclusivo para notificaciones judiciales. Las peticiones, '
                . 'quejas y reclamos se radican en '
                . '<a href="/atencion-ciudadania/pqrsdf">el formulario de PQRSDF</a>.</p>',
            self::PUBLICADA,
            1,
        ],

        // Contenido que solo tiene el hospital: se crean en borrador para que
        // aparezcan en el panel, nunca vacías de cara al ciudadano.
        '/el-hospital/historia' => ['Historia', 'Historia de la E.S.E. Hospital Rosario Pumarejo de López.', '', self::BORRADOR, 0],
        '/el-hospital/mision-y-vision' => ['Misión y visión', 'Misión y visión institucional.', '', self::BORRADOR, 0],
        '/el-hospital/objetivos-y-funciones' => ['Objetivos y funciones', 'Objetivos y funciones de la entidad.', '', self::BORRADOR, 0],
        '/el-hospital/principios-corporativos' => ['Principios corporativos', 'Principios y valores corporativos.', '', self::BORRADOR, 0],
        '/el-hospital/infraestructura' => ['Infraestructura', 'Infraestructura física y dotación.', '', self::BORRADOR, 0],
        '/el-hospital/organigrama' => ['Organigrama', 'Estructura orgánica de la entidad.', '', self::BORRADOR, 0],
        '/el-hospital/agremiaciones-y-asociaciones' => ['Agremiaciones y asociaciones', 'Agremiaciones, asociaciones y grupos de interés.', '', self::BORRADOR, 0],
        '/el-hospital/localizacion' => ['Localización y horarios', 'Cómo llegar a la sede y horarios de atención.', '', self::BORRADOR, 0],
        '/el-hospital/politicas-institucionales' => ['Políticas institucionales', 'Políticas, lineamientos y manuales de la entidad.', '', self::BORRADOR, 0],
        '/el-hospital/certificado-de-accesibilidad' => ['Certificado de accesibilidad', 'Certificado de accesibilidad web de la entidad.', '', self::BORRADOR, 0],
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
            ->setName('semillas:paginas')
            ->setDescription('Crea las páginas institucionales del sitio (sin tocar las que ya existen)')
            ->addOption('listar', null, InputOption::VALUE_NONE, 'Solo muestra qué haría, sin escribir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simulacion = (bool) $input->getOption('listar');
        $creadas = 0;
        $publicadas = 0;

        foreach (self::PAGINAS as $ruta => [$titulo, $resumen, $contenido, $estado, $protegida]) {
            if (false !== $this->conexion->fetchOne('SELECT id FROM paginas WHERE ruta = ?', [$ruta])) {
                continue;
            }

            if ($simulacion) {
                $io->writeln(sprintf('  %s  %s (%s)', $ruta, $titulo, $estado));
                $creadas++;

                continue;
            }

            $this->conexion->insert('paginas', [
                'slug' => $this->slugDe($ruta),
                'ruta' => $ruta,
                'titulo' => $titulo,
                'resumen' => $resumen,
                'contenido' => $contenido === '' ? null : $contenido,
                'plantilla' => 'default',
                'meta_descripcion' => $resumen,
                'estado_aprobacion' => $estado,
                'aprobado_en' => $estado === self::PUBLICADA ? date('Y-m-d H:i:s') : null,
                'protegida' => $protegida,
            ]);

            $creadas++;

            if ($estado === self::PUBLICADA) {
                $publicadas++;
            }
        }

        if ($creadas > 0 && !$simulacion) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'paginas',
                descripcion: sprintf('Semillas de páginas institucionales: %d creadas (%d publicadas)', $creadas, $publicadas),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf(
            '%d páginas creadas (%d publicadas), %d ya existían.',
            $creadas,
            $publicadas,
            count(self::PAGINAS) - $creadas,
        ));

        return Command::SUCCESS;
    }

    /** El slug es el último segmento de la ruta; `/el-hospital` → `el-hospital`. */
    private function slugDe(string $ruta): string
    {
        $segmentos = explode('/', trim($ruta, '/'));

        return (string) end($segmentos);
    }
}
