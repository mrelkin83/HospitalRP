<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Support\Slugs;
use App\Modules\Contenido\Entity\Audiencia;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Primeras campañas y comunicados del hospital.
 *
 * La sección «Campañas y comunicados» de la portada solo se pinta si hay algo
 * publicado, y la tabla estaba vacía: el visitante no veía ni el encabezado.
 * Esto la arranca con piezas de las dos audiencias para que Comunicaciones
 * tenga de dónde partir.
 *
 * Qué NO hace: inventar cifras, fechas de jornada, horarios ni resultados. El
 * texto se limita a programas que la entidad presta de forma permanente (PAI,
 * riesgo cardiovascular, lactancia) y a procesos internos que ya tienen su
 * página en el sitio (convocatorias, capacitación, seguridad y salud en el
 * trabajo). Todo es editable en Panel → Campañas, y ahí es donde deben
 * ajustarse fechas y lugares concretos antes de anunciarlos.
 *
 * Idempotente por slug: una campaña ya sembrada no se toca nunca más, para no
 * pisar lo que el hospital haya reescrito.
 */
final class SembrarCampanasCommand extends Command
{
    /**
     * @var list<array{titulo: string, resumen: string, contenido: string, poblacion: string, audiencia: Audiencia}>
     */
    private const array CAMPANAS = [
        [
            'titulo' => 'Vacunación gratuita del Programa Ampliado de Inmunizaciones',
            'resumen' => 'El esquema completo del PAI es gratuito y está disponible todo el año para '
                . 'niñas, niños, gestantes y adultos mayores.',
            'contenido' => '<p>La E.S.E. Hospital Rosario Pumarejo de López aplica de forma permanente el '
                . 'esquema del <strong>Programa Ampliado de Inmunizaciones (PAI)</strong> del Ministerio de '
                . 'Salud y Protección Social. La vacunación es <strong>gratuita</strong> y no requiere '
                . 'afiliación previa a una EPS.</p>'
                . '<p>Para asistir ten en cuenta:</p>'
                . '<ul>'
                . '<li>Lleva el carné de vacunación y el documento de identidad de la persona que se va a vacunar.</li>'
                . '<li>Si perdiste el carné, en el punto de vacunación se verifica el esquema y se expide uno nuevo.</li>'
                . '<li>Consulta horarios y punto de atención en <a href="/contacto">Contacto</a>.</li>'
                . '</ul>',
            'poblacion' => 'Niñas, niños, gestantes y adultos mayores',
            'audiencia' => Audiencia::Comunidad,
        ],
        [
            'titulo' => 'Control del riesgo cardiovascular: hipertensión y diabetes',
            'resumen' => 'Detectar a tiempo la presión alta y la glucemia elevada evita infartos, '
                . 'enfermedad renal y hospitalizaciones.',
            'contenido' => '<p>La hipertensión arterial y la diabetes avanzan durante años sin síntomas. '
                . 'Una toma de presión y una glucemia bastan para detectarlas a tiempo.</p>'
                . '<p>Te recomendamos consultar si:</p>'
                . '<ul>'
                . '<li>Tienes 40 años o más y no te has tomado la presión en el último año.</li>'
                . '<li>Hay hipertensión, diabetes o enfermedad renal en tu familia.</li>'
                . '<li>Presentas dolor de cabeza frecuente, visión borrosa, sed excesiva o cansancio sin causa.</li>'
                . '</ul>'
                . '<p>Solicita tu valoración en <a href="/citas">Citas</a>. Si tienes dolor en el pecho, '
                . 'dificultad para respirar o pérdida de fuerza en un lado del cuerpo, acude de inmediato a '
                . '<a href="/urgencias">Urgencias</a>.</p>',
            'poblacion' => 'Personas adultas',
            'audiencia' => Audiencia::Comunidad,
        ],
        [
            'titulo' => 'Acompañamiento a la lactancia materna',
            'resumen' => 'Apoyo a madres gestantes y lactantes en el inicio, el mantenimiento y las '
                . 'dificultades de la lactancia.',
            'contenido' => '<p>La leche materna es el único alimento que necesita un bebé durante sus '
                . 'primeros seis meses, y sigue siendo importante hasta los dos años junto con la '
                . 'alimentación complementaria.</p>'
                . '<p>En el hospital acompañamos a la madre y a su familia en el agarre, la posición, el '
                . 'dolor al amamantar, la producción de leche y la vuelta al trabajo o al estudio.</p>'
                . '<p>Pide orientación en tu control prenatal o agenda una consulta en '
                . '<a href="/citas">Citas</a>.</p>',
            'poblacion' => 'Madres gestantes y lactantes',
            'audiencia' => Audiencia::Comunidad,
        ],
        [
            'titulo' => 'Convocatorias de talento humano',
            'resumen' => 'Las vacantes y procesos de selección de la entidad se publican en el sitio '
                . 'web: ahí están los perfiles, los requisitos y las fechas.',
            'contenido' => '<p>Todas las convocatorias de la E.S.E. Hospital Rosario Pumarejo de López se '
                . 'publican en <a href="/convocatorias">Convocatorias</a>, con el perfil solicitado, los '
                . 'requisitos, el plazo y el medio para postularse.</p>'
                . '<p>Ningún proceso de selección de la entidad exige pagos ni intermediarios. Si recibes '
                . 'una oferta a nombre del hospital por fuera de estos canales, repórtala a través de '
                . '<a href="/atencion-ciudadania/pqrsdf">PQRSDF</a>.</p>',
            'poblacion' => 'Aspirantes y personal de la entidad',
            'audiencia' => Audiencia::Personal,
        ],
        [
            'titulo' => 'Plan Institucional de Capacitación',
            'resumen' => 'Formación dirigida a servidores públicos y contratistas de la entidad, con la '
                . 'programación publicada en documentos institucionales.',
            'contenido' => '<p>El Plan Institucional de Capacitación reúne la formación dirigida al talento '
                . 'humano de la entidad: seguridad del paciente, humanización del servicio, atención al '
                . 'ciudadano y actualización técnica por área.</p>'
                . '<p>La programación vigente, los soportes y los formatos de inscripción se publican en '
                . '<a href="/documentos">Documentos institucionales</a>. Coordina con tu jefe inmediato la '
                . 'participación en las sesiones que apliquen a tu cargo.</p>',
            'poblacion' => 'Servidores públicos y contratistas',
            'audiencia' => Audiencia::Personal,
        ],
        [
            'titulo' => 'Seguridad y salud en el trabajo: reporta los incidentes',
            'resumen' => 'Todo accidente o incidente laboral debe reportarse, incluso si no hubo lesión: '
                . 'ese reporte es lo que evita el siguiente.',
            'contenido' => '<p>El Sistema de Gestión de Seguridad y Salud en el Trabajo cubre a todo el '
                . 'personal de la entidad, vinculado y contratista.</p>'
                . '<p>Reporta <strong>siempre</strong>, y dentro de la misma jornada:</p>'
                . '<ul>'
                . '<li>Accidentes de trabajo, incluidos los de trayecto.</li>'
                . '<li>Incidentes sin lesión: un riesgo que no alcanzó a causar daño sigue estando ahí.</li>'
                . '<li>Exposición a material biológico o a sustancias peligrosas.</li>'
                . '<li>Condiciones inseguras en pisos, equipos, instalaciones eléctricas o iluminación.</li>'
                . '</ul>'
                . '<p>Los formatos de reporte están en <a href="/documentos">Documentos institucionales</a>.</p>',
            'poblacion' => 'Todo el personal de la entidad',
            'audiencia' => Audiencia::Personal,
        ],
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
            ->setName('semillas:campanas')
            ->setDescription('Crea las campañas y comunicados iniciales (sin tocar los que ya existen)')
            ->addOption('listar', null, InputOption::VALUE_NONE, 'Solo muestra qué haría, sin escribir')
            ->addOption(
                'borrador',
                null,
                InputOption::VALUE_NONE,
                'Las crea en borrador en vez de publicadas, para revisarlas antes de que salgan al sitio',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simulacion = (bool) $input->getOption('listar');
        $estado = $input->getOption('borrador') === true ? 'borrador' : 'publicado';
        $hoy = date('Y-m-d');
        $creadas = 0;

        foreach (self::CAMPANAS as $campana) {
            $slug = Slugs::desde($campana['titulo']);

            if ($this->conexion->fetchOne('SELECT id FROM campanas WHERE slug = ?', [$slug]) !== false) {
                continue;
            }

            if ($simulacion) {
                $io->writeln(sprintf('  %s  %s (%s)', $slug, $campana['titulo'], $campana['audiencia']->value));
                $creadas++;

                continue;
            }

            $this->conexion->insert('campanas', [
                'slug' => $slug,
                'titulo' => $campana['titulo'],
                'resumen' => $campana['resumen'],
                'contenido' => $campana['contenido'],
                'poblacion_objetivo' => $campana['poblacion'],
                'audiencia' => $campana['audiencia']->value,
                // Sin fecha de fin: son programas permanentes. Una jornada con
                // fecha concreta se crea desde el panel y ahí sí lleva vigencia,
                // porque anunciar una jornada vencida manda gente en balde.
                'inicio' => $hoy,
                'fin' => null,
                'estado_aprobacion' => $estado,
            ]);

            $creadas++;
        }

        if ($creadas > 0 && !$simulacion) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'campanas',
                descripcion: sprintf('Semillas de campañas: %d creadas en estado %s', $creadas, $estado),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf(
            '%d campañas creadas (%s), %d ya existían.',
            $creadas,
            $estado,
            count(self::CAMPANAS) - $creadas,
        ));

        if ($creadas > 0 && $estado === 'publicado') {
            $io->note(
                'El texto es de arranque y ya está al aire: revísalo en Panel → Campañas y ajusta '
                . 'fechas, lugares y horarios concretos antes de anunciarlos.',
            );
        }

        return Command::SUCCESS;
    }
}
