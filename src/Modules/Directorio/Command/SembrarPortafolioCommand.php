<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Contenido del **Portafolio de Servicios HRPL 2026**, el documento
 * institucional firmado por la Agente Especial Interventora (Valledupar,
 * 2026), volcado a las secciones del sitio a las que pertenece cada dato.
 *
 * El portafolio venía como una presentación de 24 láminas aplanadas: el texto
 * y las fotos estaban horneados en una imagen por página, así que nada de esto
 * se podía enlazar, buscar ni leer con un lector de pantalla. Aquí cada lámina
 * queda convertida en el registro que le corresponde:
 *
 * - láminas 6 a 21 → `servicios` (descripción, cifras, horario y foto);
 * - lámina 9       → `especialidades`, la lista completa de 31 especialidades
 *                    y 14 subespecialidades;
 * - láminas 2 a 4  → páginas institucionales (quiénes somos, misión, visión);
 * - láminas 19-21  → páginas de banco de leche, madre canguro y docencia;
 * - lámina 22      → `configuracion`, los teléfonos de contacto.
 *
 * Las fotos salen de `database/seeders/portafolio/`, recortadas de las láminas
 * y convertidas a WebP. Entran por `AlmacenArchivos` para que queden idénticas
 * a una subida desde el panel y se puedan reemplazar desde Medios.
 *
 * **Qué pisa y qué no.** Un servicio o una especialidad que ya exista se
 * completa, nunca se duplica. Las páginas y la configuración solo se tocan si
 * `actualizado_por` sigue nulo, es decir, si nadie las ha editado desde el
 * panel: lo que escriba el hospital gana siempre sobre esta semilla.
 */
#[AsCommand(
    name: 'semillas:portafolio',
    description: 'Vuelca el Portafolio de Servicios 2026 a servicios, especialidades, páginas y contacto',
)]
final class SembrarPortafolioCommand extends Command
{
    private const string DIRECTORIO_FOTOS = __DIR__ . '/../../../../database/seeders/portafolio';

    /**
     * Servicios del portafolio, en el orden en que los presenta el documento.
     *
     * La clave es el slug, que es lo único estable: el nombre lo reescribe el
     * hospital desde el panel cuando quiera, pero cambiar el slug rompería las
     * URL ya publicadas. Por eso «Sala de cirugía» conserva el slug `cirugia`
     * con el que nació el sitio.
     *
     * Forma: slug => [nombre, descripción, cifras, horario, teléfono].
     *
     * @var array<string, array{string, string, list<array{string, string}>, ?string, ?string}>
     */
    private const array SERVICIOS = [
        'urgencias-24-horas' => [
            'Urgencias',
            "Nuestro servicio de urgencias funciona las 24 horas del día, los 7 días de la semana, siendo la puerta de entrada a nuestra institución.\n\n"
                . "Brindamos servicios integrales diferenciales por grupo etario, contando con un ingreso exclusivo para población pediátrica y para adultos.\n\n"
                . 'Nuestro talento humano altamente calificado nos permite brindar la atención inicial a los pacientes que requieren ser atendidos de manera inmediata.',
            [['9', 'consultorios de urgencias'], ['55', 'camillas en urgencia adulto'], ['18', 'camillas de observación pediátrica'], ['10', 'camillas de urgencia obstétrica']],
            '24 horas, todos los días del año',
            '(605) 5712339',
        ],
        'consulta-externa' => [
            'Consulta externa',
            "Contamos con 21 consultorios y un equipo de profesionales altamente capacitados para la atención de nuestros usuarios.\n\n"
                . "Disponemos de 49 profesionales entre especialistas y subespecialistas para la atención de los pacientes del Cesar y de toda su área de influencia.\n\n"
                . 'Comprometidos con tu salud en cada etapa de tu vida: acceso ágil, trato digno y más bienestar para todos. La lista completa de especialidades y subespecialidades está en la sección Especialidades del sitio.',
            [['21', 'consultorios'], ['49', 'especialistas y subespecialistas']],
            'Agendamiento: lunes a viernes, jornada continua de 7:00 a. m. a 5:00 p. m.',
            '317 5153642',
        ],
        'hospitalizacion' => [
            'Servicios de hospitalización',
            "Brindamos atención integral al paciente que requiere de internación en un ambiente seguro y confortable, garantizando la seguridad del paciente.\n\n"
                . "Contamos con 269 camas distribuidas así:\n"
                . "Hospitalización general: 179 camas.\n"
                . "Salud mental: 34 camas.\n"
                . "UCI adultos: 18 camas.\n"
                . "UCI pediátrica: 7 camas.\n"
                . "UCI neonatal: 13 camas.\n"
                . "UCI intermedio neonatal: 7 camas.\n"
                . "Cuidado básico neonatal: 9 camas.\n"
                . 'Intermedio adulto: 1 cama y pediátrico: 1 cama.',
            [['269', 'camas habilitadas'], ['179', 'de hospitalización general'], ['18', 'camas de UCI adultos'], ['13', 'camas de UCI neonatal']],
            'Hospitalización las 24 horas',
            null,
        ],
        'cirugia' => [
            'Sala de cirugía',
            "Contamos con 8 quirófanos para la realización de cirugías de mediana y alta complejidad, de manera ambulatoria, hospitalaria y de urgencias, las 24 horas.\n\n"
                . "La distribución de las salas es:\n"
                . "1 sala de partos.\n"
                . "1 sala de legrado.\n"
                . "2 quirófanos para obstetricia.\n"
                . "2 quirófanos para cirugías de urgencias.\n"
                . "4 quirófanos para cirugía programada.\n\n"
                . 'Nuestro compromiso es brindar una atención quirúrgica segura, oportuna y humanizada, cuidando siempre de ti y de tu familia.',
            [['8', 'quirófanos'], ['4', 'para cirugía programada'], ['24 h', 'cirugía de urgencias']],
            'Cirugía programada y de urgencias las 24 horas',
            null,
        ],
        'atencion-del-parto' => [
            'Atención del parto',
            "Trabajamos por la atención integral del binomio madre - hijo acorde a los lineamientos de la estrategia IAMI. Para esto contamos con cuatro consultorios en consulta externa, incluyendo consulta de Alto Riesgo Obstétrico, y hospitalización materna con más de 25 camas hospitalarias, con quirófanos y salas de parto exclusivas.\n\n"
                . 'Contamos con el área de vacunación, la cual cumple con los estándares de calidad para transporte, mantenimiento y aplicación de las vacunas, cumpliendo así con los lineamientos del Ministerio de Protección Social y el Programa Ampliado de Inmunizaciones (PAI).',
            [['4', 'consultorios de consulta externa'], ['25+', 'camas de hospitalización materna'], ['PAI', 'área de vacunación']],
            'Atención del parto las 24 horas',
            null,
        ],
        'atencion-en-salud-mental' => [
            'Atención en salud mental',
            "Brindamos atención y acompañamiento integral a los pacientes en todas las edades con patologías mentales.\n\n"
                . "Contamos con espacios amplios para su atención y ofrecemos servicios de urgencias en salud mental, consulta por psicología y psiquiatría, terapia física y ocupacional, y hospitalización con la disponibilidad de 34 camas equipadas acorde a la normatividad vigente.\n\n"
                . 'Atención integral, servicios especializados, atención de urgencias las 24 horas y hospitalización disponible.',
            [['34', 'camas equipadas'], ['24 h', 'urgencias en salud mental']],
            'Urgencias en salud mental: 24 horas. Consulta: lunes a viernes.',
            null,
        ],
        'transporte-asistencial-basico-y-medicalizado' => [
            'Transporte asistencial básico y medicalizado',
            "Ponemos a su disposición una flota de 5 ambulancias completamente equipadas acorde a la normatividad vigente.\n\n"
                . 'De ellas, 3 son medicalizadas y 2 básicas, para el transporte de nuestros usuarios con calidad, seguridad y humanización.',
            [['5', 'ambulancias equipadas'], ['3', 'medicalizadas'], ['2', 'básicas']],
            'Disponible 24 horas',
            '(605) 5712339',
        ],
        'resonancia-magnetica' => [
            'Resonancia magnética',
            "El Hospital Rosario Pumarejo de López continúa avanzando en el fortalecimiento de sus servicios con la puesta en marcha de tecnología de alta complejidad, como lo es el Resonador Magnético, que permitirá fortalecer el diagnóstico oportuno y brindar una atención cada vez más integral a nuestros usuarios.\n\n"
                . 'El hospital ya presta servicios de radiología, tomografía e imágenes diagnósticas, y ha venido fortaleciendo su dotación biomédica.',
            [['Alta', 'complejidad tecnológica'], ['Oportuno', 'diagnóstico'], ['Integral', 'atención para todos']],
            null,
            null,
        ],
        'ecografia-obstetrica-3d-y-4d' => [
            'Ecografía obstétrica 3D y 4D',
            'El Hospital Rosario Pumarejo de López cuenta con tecnología para la realización de ecografías obstétricas 3D y 4D, fortaleciendo los servicios de diagnóstico y atención integral a las gestantes durante las diferentes etapas del embarazo.',
            [['3D y 4D', 'tecnología avanzada'], ['Alta', 'resolución de imagen'], ['Integral', 'atención para ti y tu bebé']],
            null,
            null,
        ],
        'imagenologia' => [
            'Imagenología',
            "Imágenes diagnósticas con tecnología y talento humano al servicio de tu salud:\n"
                . "Radiografía convencional.\n"
                . "Ecografía, Doppler y mamografía.\n"
                . "Tomografías.\n"
                . "Resonancias magnéticas: imágenes de alta resolución para el diagnóstico preciso y no invasivo de diversas condiciones médicas.\n"
                . "Ecocardiografía fetal y neurosonografía fetal: diagnósticos especializados para el bienestar del bebé desde el vientre materno.\n"
                . 'Ecografía de detalle anatómico y translucencia nucal.',
            [],
            null,
            null,
        ],
        'laboratorio-clinico' => [
            'Toma de muestras y laboratorio clínico',
            "Toma de muestras de laboratorio clínico con altos estándares de seguridad y calidad.\n\n"
                . 'El servicio incluye además hemoderivados, patología y estudios de audiología.',
            [],
            null,
            null,
        ],
        'laboratorio-de-genetica' => [
            'Laboratorio de genética',
            'Análisis y diagnósticos genéticos especializados para el cuidado de tu salud y la de tu familia.',
            [],
            null,
            null,
        ],
        'terapias-fisica-respiratoria-y-fonoaudiologia' => [
            'Terapias física, respiratoria y fonoaudiología',
            'Terapias especializadas para mejorar la movilidad, la respiración, la comunicación y la calidad de vida.',
            [],
            null,
            null,
        ],
        'banco-de-leche-humana' => [
            'Banco de leche humana',
            "Promovemos la lactancia materna en forma exclusiva hasta los seis meses y educamos a la madre y a su familia en cómo seguir amamantando al bebé y alternar su alimentación de manera complementaria hasta los dos años de edad, a fin de preservar el adecuado crecimiento y desarrollo de niños y niñas.\n\n"
                . "Nuestros receptores son recién nacidos prematuros o de bajo peso que no succionan, infectados, en desnutrición, portadores de inmunodeficiencia y a quienes se les ha practicado una cirugía.\n\n"
                . 'Los receptores que no pueden alimentarse directamente reciben leche segura y pasteurizada, garantizando su bienestar y desarrollo.',
            [],
            null,
            '315 7045300',
        ],
        'programa-madre-canguro' => [
            'Programa Madre Canguro',
            "Esta estrategia, reconocida por la Organización Mundial de la Salud (OMS), permite reducir la morbimortalidad y mejorar la calidad de vida de la población infantil.\n\n"
                . "Este programa está dirigido a los niños y niñas que nacen con menos de 37 semanas de gestación y/o con peso menor de 2.500 gramos, a quienes se les realiza una atención integral y especializada por un equipo interdisciplinario, realizando actividades de sensibilización, motivación y capacitación a la madre y su entorno familiar hasta los dos años de vida.\n\n"
                . 'Brindamos acompañamiento especializado a madres y bebés prematuros para promover su desarrollo y bienestar, y capacitamos a las familias para fortalecer el cuidado y el vínculo afectivo.',
            [['< 37', 'semanas de gestación'], ['< 2.500 g', 'de peso al nacer'], ['2 años', 'de acompañamiento']],
            null,
            null,
        ],
    ];

    /**
     * Texto alternativo de cada foto, por nombre de archivo.
     *
     * Describe lo que se ve, no de qué va la página: el titular ya dice el
     * servicio, y repetirlo solo hace que un lector de pantalla lo lea dos
     * veces (CLAUDE.md §6, Frontend).
     *
     * @var array<string, string>
     */
    private const array TEXTOS_ALT = [
        'urgencias-24-horas' => 'Fachada del servicio de urgencias del hospital con el equipo asistencial formado al frente',
        'consulta-externa' => 'Sala de espera de consulta externa con usuarios sentados frente a las ventanillas del SIAU',
        'hospitalizacion' => 'Unidad de cuidados intensivos neonatal con incubadoras y monitores',
        'cirugia' => 'Equipo quirúrgico operando bajo las lámparas de un quirófano',
        'atencion-del-parto' => 'Una madre recibe a su recién nacido en brazos de la enfermera, en la sala de partos',
        'atencion-en-salud-mental' => 'Profesional de psicología durante una consulta con una paciente en el consultorio',
        'transporte-asistencial-basico-y-medicalizado' => 'Ambulancia del hospital estacionada frente a la institución',
        'resonancia-magnetica' => 'Sala del resonador magnético con la camilla al frente del equipo',
        'ecografia-obstetrica-3d-y-4d' => 'Ecógrafo obstétrico con una imagen 3D de un bebé en la pantalla',
        'imagenologia' => 'Sala de radiología con el equipo de rayos X y la camilla',
        'banco-de-leche-humana' => 'Profesional del banco de leche organizando frascos de leche materna en el congelador',
        'programa-madre-canguro' => 'Madre con su bebé prematuro en posición canguro',
        'pagina-mision' => 'Profesional de enfermería tomando la presión arterial a un usuario en el consultorio',
        'pagina-vision' => 'Médica tomando la presión arterial a un paciente durante la consulta',
        'pagina-quienes-somos' => 'Equipo quirúrgico del hospital durante una intervención',
        'pagina-docencia' => 'Grupo de estudiantes y profesionales en formación reunidos en el auditorio del hospital',
        'pagina-banco-de-leche' => 'Madre extrayendo leche materna con ayuda del equipo del banco de leche',
    ];

    /**
     * Especialidades y subespecialidades de la lámina 9.
     *
     * Se escriben en el castellano corriente con el que las busca un
     * ciudadano, no en la nomenclatura del REPS: la lámina dice «Otras
     * consultas Ortopedia de mano» porque así se reporta la habilitación, pero
     * quien entra al sitio escribe «ortopedia de mano».
     *
     * Las ocho sembradas por `semillas:directorio` no se repiten: anestesia,
     * ginecobstetricia, medicina interna, cirugía general, pediatría,
     * psiquiatría, urología y ortopedia ya existen con su propio slug.
     *
     * @var list<string>
     */
    private const array ESPECIALIDADES = [
        'Cardiología', 'Cirugía pediátrica', 'Dermatología', 'Dolor y cuidados paliativos',
        'Endocrinología', 'Enfermería', 'Gastroenterología', 'Genética', 'Infectología',
        'Nefrología', 'Neumología', 'Neurología', 'Nutrición y dietética', 'Oftalmología',
        'Otorrinolaringología', 'Psicología', 'Toxicología',
        'Infectología pediátrica', 'Gastroenterología pediátrica', 'Endocrinología pediátrica',
        'Psiquiatría pediátrica', 'Perinatología', 'Ortopedia de mano', 'Reumatología pediátrica',
        'Ortopedia de columna', 'Cardiología pediátrica', 'Cirugía plástica y estética',
        'Cirugía vascular', 'Ginecología oncológica', 'Nefrología pediátrica', 'Neonatología',
        'Neumología pediátrica', 'Neurocirugía', 'Ortopedia pediátrica', 'Cirugía maxilofacial',
        'Vacunación', 'Hematología pediátrica', 'Neurología pediátrica',
    ];

    /**
     * Teléfonos de la lámina 22 que el sitio aún no tenía.
     *
     * @var array<string, array{string, string, string}> clave => [valor, etiqueta, ayuda]
     */
    private const array CONTACTO = [
        'contacto.call_center_alterno' => [
            '300 7533534',
            'Call center (línea alterna)',
            'Segunda línea de agendamiento de citas del portafolio 2026.',
        ],
        'contacto.call_center_horario' => [
            'Jornada continua de 7:00 a. m. a 5:00 p. m., de lunes a viernes',
            'Horario del call center',
            'Horario en que contestan las líneas de citas.',
        ],
        'contacto.citabot' => [
            '314 5678347',
            'CitaBot 2026',
            'Línea de agendamiento automático por mensajería.',
        ],
        'contacto.banco_leche' => [
            '315 7045300',
            'Banco de leche humana',
            'Línea directa del banco de leche humana.',
        ],
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
        private readonly AlmacenArchivos $almacen,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sin-fotos', null, InputOption::VALUE_NONE, 'Siembra solo los textos, sin cargar las fotos del portafolio')
            ->addOption('rehacer', null, InputOption::VALUE_NONE, 'Vuelve a volcar los textos del portafolio sobre los servicios, pisando lo editado en el panel');
    }

    protected function execute(InputInterface $entrada, OutputInterface $salida): int
    {
        $io = new SymfonyStyle($entrada, $salida);
        $io->title('Portafolio de Servicios 2026');

        $conFotos = !(bool) $entrada->getOption('sin-fotos');
        $primerVolcado = (bool) $entrada->getOption('rehacer')
            || false === $this->conexion->fetchOne("SELECT id FROM configuracion WHERE clave = 'portafolio.volcado_en'");

        if (!$primerVolcado) {
            $io->text('El portafolio ya se volcó antes: solo se completan los campos vacíos. Usa --rehacer para repetirlo entero.');
        }

        $resumen = [
            'servicios' => $this->sembrarServicios($io, $conFotos, $primerVolcado),
            'especialidades' => $this->sembrarEspecialidades($io),
            'sedes' => $this->sembrarSedes($io),
            'paginas' => $this->sembrarPaginas($io, $conFotos),
            'contacto' => $this->sembrarContacto($io),
        ];

        $this->marcarVolcado();

        $total = array_sum($resumen);

        if ($total === 0) {
            $io->success('Todo el portafolio ya estaba publicado. No se tocó nada.');

            return Command::SUCCESS;
        }

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Actualizacion,
            entidad: 'servicios',
            descripcion: sprintf(
                'Portafolio de Servicios 2026: %d servicios, %d especialidades, %d páginas y %d datos de contacto',
                $resumen['servicios'],
                $resumen['especialidades'],
                $resumen['paginas'],
                $resumen['contacto'],
            ),
            usuarioNombre: 'consola',
        ));

        $io->success(sprintf('%d registros sembrados o completados desde el portafolio.', $total));
        $io->note('Las fotos y los textos se reemplazan desde Panel → Servicios y Panel → Páginas.');

        return Command::SUCCESS;
    }

    // --- bloques ---

    /**
     * `servicios` no tiene `actualizado_por`, así que no hay forma de saber si
     * un texto lo reescribió un funcionario. Por eso el volcado completo se
     * hace **una sola vez**, la primera: a partir de ahí el comando solo
     * rellena los campos que sigan vacíos y crea los servicios que falten.
     * Sin esto, una segunda ejecución devolvería cada descripción al texto del
     * PDF y borraría el trabajo del panel.
     */
    private function sembrarServicios(SymfonyStyle $io, bool $conFotos, bool $primerVolcado): int
    {
        $io->section('Servicios');
        $tocados = 0;
        $orden = 0;

        foreach (self::SERVICIOS as $slug => [$nombre, $descripcion, $destacados, $horario, $telefono]) {
            $orden++;
            $existente = $this->conexion->fetchAssociative('SELECT * FROM servicios WHERE slug = ?', [$slug]);

            $delPortafolio = [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'destacados' => $this->destacadosJson($destacados),
                'horario' => $horario,
                'telefono' => $telefono,
            ];

            if ($existente === false) {
                $this->conexion->insert('servicios', $delPortafolio + [
                    'slug' => $slug,
                    'orden' => $orden,
                    'activo' => 1,
                    'imagen_id' => $conFotos ? $this->subirFoto($slug) : null,
                ]);
                $io->text(sprintf('  <info>+</info> %s', $nombre));
                $tocados++;

                continue;
            }

            $campos = $primerVolcado
                ? array_filter($delPortafolio, static fn (?string $v): bool => $v !== null)
                : $this->soloVacios($delPortafolio, $existente);

            // La foto solo se pone si el servicio no tenía ninguna: volver a
            // ejecutar esto no debe pisar la que haya subido Comunicaciones.
            if ($conFotos && $existente['imagen_id'] === null && ($id = $this->subirFoto($slug)) !== null) {
                $campos['imagen_id'] = $id;
            }

            if ($primerVolcado) {
                $campos['orden'] = $orden;
                $campos['activo'] = 1;
            }

            if ($campos === []) {
                continue;
            }

            $this->conexion->update('servicios', $campos, ['id' => (int) $existente['id']]);
            $io->text(sprintf('  <comment>~</comment> %s', $nombre));
            $tocados++;
        }

        return $tocados;
    }

    private function sembrarSedes(SymfonyStyle $io): int
    {
        $io->section('Dónde se presta');

        $servicios = $this->vincularSedes('servicios', 'servicio_sede', 'servicio_id');
        $especialidades = $this->vincularSedes('especialidades', 'especialidad_sede', 'especialidad_id');

        $io->text(sprintf('  %d servicios y %d especialidades atados a la sede.', $servicios, $especialidades));

        return $servicios + $especialidades;
    }

    /**
     * Ata a las sedes activas lo que no esté atado a ninguna.
     *
     * Sin esto, «Dónde se presta» sale vacío en el detalle de cada servicio y
     * el listado de especialidades anuncia «0 sede(s)»: el portafolio describe
     * la oferta de un hospital de sede única, así que todo lo que trae se
     * presta donde está el hospital.
     *
     * Solo toca los registros sin ninguna sede. Si alguien desmarcó una sede a
     * propósito desde el panel, esa decisión se respeta.
     */
    private function vincularSedes(string $tabla, string $puente, string $columna): int
    {
        $sedeIds = $this->conexion->fetchFirstColumn('SELECT id FROM sedes WHERE activa = 1');

        if ($sedeIds === []) {
            return 0;
        }

        $huerfanos = $this->conexion->fetchFirstColumn(sprintf(
            'SELECT t.id FROM %s t LEFT JOIN %s p ON p.%s = t.id WHERE p.%s IS NULL',
            $tabla,
            $puente,
            $columna,
            $columna,
        ));

        foreach ($huerfanos as $id) {
            foreach ($sedeIds as $sedeId) {
                $this->conexion->insert($puente, [$columna => (int) $id, 'sede_id' => (int) $sedeId]);
            }
        }

        return count($huerfanos);
    }

    /**
     * Los campos del portafolio que el registro todavía no tiene llenos.
     *
     * @param array<string, ?string> $delPortafolio
     * @param array<string, mixed>   $existente
     *
     * @return array<string, string>
     */
    private function soloVacios(array $delPortafolio, array $existente): array
    {
        $campos = [];

        foreach ($delPortafolio as $columna => $valor) {
            $actual = $existente[$columna] ?? null;

            if ($valor !== null && ($actual === null || trim((string) $actual) === '')) {
                $campos[$columna] = $valor;
            }
        }

        return $campos;
    }

    private function sembrarEspecialidades(SymfonyStyle $io): int
    {
        $io->section('Especialidades');

        $orden = (int) $this->conexion->fetchOne('SELECT COALESCE(MAX(orden), 0) FROM especialidades');
        $creadas = 0;

        foreach (self::ESPECIALIDADES as $nombre) {
            $slug = Slugs::desde($nombre, 170);

            if (false !== $this->conexion->fetchOne('SELECT id FROM especialidades WHERE slug = ?', [$slug])) {
                continue;
            }

            $this->conexion->insert('especialidades', [
                'nombre' => $nombre,
                'slug' => $slug,
                'orden' => ++$orden,
            ]);

            $creadas++;
        }

        $io->text(sprintf('  %d especialidades y subespecialidades nuevas.', $creadas));

        return $creadas;
    }

    private function sembrarPaginas(SymfonyStyle $io, bool $conFotos): int
    {
        $io->section('Páginas institucionales');
        $tocadas = 0;

        // true  = el portafolio es la versión vigente de esa misma página y
        //         sustituye el texto heredado del Joomla.
        // false = el portafolio aporta un apartado nuevo y se añade al final
        //         sin tocar lo que la página ya decía.
        $paginas = [
            '/el-hospital/mision-y-vision' => [
                true,
                'pagina-mision',
                'Misión y visión institucional de la E.S.E. Hospital Rosario Pumarejo de López.',
                '<h2>Misión</h2>'
                    . '<p>La E.S.E. Hospital Rosario Pumarejo de López presta <strong>servicios integrales de salud '
                    . 'de mediana y alta complejidad</strong> en el orden departamental del Cesar y su área de '
                    . 'influencia, con talento humano idóneo, comprometido con el <strong>mejoramiento continuo</strong>, '
                    . 'la atención segura, humanizada, centrada en el usuario, la familia y en el contexto multiétnico '
                    . 'y cultural, fortalecido con la relación <strong>docencia - servicio</strong> y la '
                    . '<strong>sostenibilidad financiera y ambiental</strong>.</p>'
                    . '<h2>Visión</h2>'
                    . '<p>La E.S.E. Hospital Rosario Pumarejo de López en el <strong>2027</strong> será una '
                    . '<strong>institución referente</strong> en el departamento del Cesar y su área de influencia, '
                    . '<strong>autosostenible financieramente</strong>, con un equipo humano calificado y orientado a '
                    . 'la <strong>excelencia de servicios integrales</strong> de salud de alta complejidad y la '
                    . 'transformación como <strong>Hospital Universitario</strong>.</p>'
                    . '<p class="texto-suave">Texto del Portafolio de Servicios 2026.</p>',
            ],
            '/el-hospital' => [
                false,
                'pagina-quienes-somos',
                null,
                '<h2>Quiénes somos</h2>'
                    . '<ul>'
                    . '<li>Somos una Empresa Social del Estado que presta servicios de salud de mediana y alta '
                    . 'complejidad en el Cesar, sur de La Guajira y el Magdalena, ubicada en la ciudad de Valledupar.</li>'
                    . '<li>Somos una entidad pública descentralizada del orden departamental, con personería jurídica, '
                    . 'patrimonio propio y autonomía administrativa y financiera.</li>'
                    . '<li>Contamos con capacidad instalada acorde con los servicios prestados y con la suficiencia de '
                    . 'recursos técnicos, tecnológicos y científicos, ajustados en el marco de los requerimientos de '
                    . 'calidad en salud, según la normatividad vigente.</li>'
                    . '</ul>',
            ],
            '/programas/iamii/banco-de-leche' => [
                true,
                'pagina-banco-de-leche',
                'Banco de leche humana: lactancia materna exclusiva y leche segura y pasteurizada para recién nacidos.',
                '<h2>Banco de leche humana</h2>'
                    . '<p>Promovemos la lactancia materna en forma exclusiva hasta los seis meses y educamos a la madre '
                    . 'y a su familia en cómo seguir amamantando al bebé y alternar su alimentación de manera '
                    . 'complementaria hasta los dos años de edad, a fin de preservar el adecuado crecimiento y '
                    . 'desarrollo de niños y niñas.</p>'
                    . '<p>Nuestros receptores son recién nacidos prematuros o de bajo peso que no succionan, '
                    . 'infectados, en desnutrición, portadores de inmunodeficiencia y a quienes se les ha practicado '
                    . 'una cirugía. Quienes no pueden alimentarse directamente reciben leche segura y pasteurizada, '
                    . 'garantizando su bienestar y desarrollo.</p>'
                    . '<p>Línea directa del banco de leche humana: <strong>315 7045300</strong>.</p>',
            ],
            '/programas/iamii/madre-canguro' => [
                true,
                'programa-madre-canguro',
                'Programa Madre Canguro: atención integral a prematuros y bebés de bajo peso al nacer.',
                '<h2>Programa Madre Canguro</h2>'
                    . '<p>Esta estrategia, reconocida por la Organización Mundial de la Salud (OMS), permite reducir la '
                    . 'morbimortalidad y mejorar la calidad de vida de la población infantil.</p>'
                    . '<p>El programa está dirigido a los niños y niñas que nacen con menos de 37 semanas de gestación '
                    . 'y/o con peso menor de 2.500 gramos, a quienes se les realiza una atención integral y '
                    . 'especializada por un equipo interdisciplinario, con actividades de sensibilización, motivación y '
                    . 'capacitación a la madre y su entorno familiar hasta los dos años de vida.</p>'
                    . '<p>Brindamos acompañamiento especializado a madres y bebés prematuros para promover su '
                    . 'desarrollo y bienestar, y capacitamos a las familias para fortalecer el cuidado y el vínculo '
                    . 'afectivo.</p>',
            ],
            '/programas/docencia' => [
                false,
                'pagina-docencia',
                null,
                '<h2>Convenios docencia - servicio</h2>'
                    . '<p>Con el propósito de formar talento humano en salud hemos establecido acuerdos con '
                    . 'instituciones educativas y organizaciones, a fin de generar un vínculo en el cual se les brinda '
                    . 'entrenamiento que les proporcione conocimientos, habilidades y destrezas de acuerdo a su grado '
                    . 'de formación.</p>'
                    . '<p>Tenemos convenios interinstitucionales con:</p>'
                    . '<ul>'
                    . '<li>Universidad Popular del Cesar (UPC)</li>'
                    . '<li>Universidad Antonio Nariño</li>'
                    . '<li>Fundación Universitaria del Área Andina</li>'
                    . '<li>Fundación Universitaria Juan N. Corpas</li>'
                    . '<li>Corporación Universitaria Remington</li>'
                    . '<li>Universidad Metropolitana</li>'
                    . '<li>Corporación Universitaria Rafael Núñez</li>'
                    . '<li>Fundación Universitaria San Martín</li>'
                    . '<li>Universidad Cooperativa de Colombia</li>'
                    . '<li>Universidad de Santander (UDES)</li>'
                    . '<li>Universidad Nacional Abierta y a Distancia (UNAD)</li>'
                    . '<li>Universidad de Cartagena</li>'
                    . '<li>Universidad Libre</li>'
                    . '<li>Universidad del Magdalena</li>'
                    . '<li>Universidad Simón Bolívar</li>'
                    . '<li>Universidad del Sinú</li>'
                    . '<li>Universidad El Bosque</li>'
                    . '<li>Universidad de La Guajira</li>'
                    . '</ul>',
            ],
        ];

        foreach ($paginas as $ruta => [$reemplaza, $foto, $resumen, $html]) {
            $fila = $this->conexion->fetchAssociative(
                'SELECT id, contenido, resumen, imagen_id, actualizado_por FROM paginas WHERE ruta = ?',
                [$ruta],
            );

            if ($fila === false) {
                $io->text(sprintf('  <comment>?</comment> %s no existe; la crea semillas:paginas.', $ruta));

                continue;
            }

            if ($fila['actualizado_por'] !== null) {
                $io->text(sprintf('  <comment>·</comment> %s la editó el hospital; se respeta.', $ruta));

                continue;
            }

            $campos = [];
            $contenido = $this->contenidoConBloque($reemplaza ? '' : (string) ($fila['contenido'] ?? ''), $html);

            if ($contenido !== (string) ($fila['contenido'] ?? '')) {
                $campos['contenido'] = $contenido;
            }

            if ($resumen !== null && $resumen !== (string) ($fila['resumen'] ?? '')) {
                $campos['resumen'] = $resumen;
                $campos['meta_descripcion'] = $resumen;
            }

            if ($conFotos && $fila['imagen_id'] === null && ($imagenId = $this->subirFoto($foto)) !== null) {
                $campos['imagen_id'] = $imagenId;
            }

            if ($campos === []) {
                continue;
            }

            $this->conexion->update('paginas', $campos, ['id' => (int) $fila['id']]);
            $io->text(sprintf('  <info>~</info> %s', $ruta));
            $tocadas++;
        }

        return $tocadas;
    }

    private function sembrarContacto(SymfonyStyle $io): int
    {
        $io->section('Contacto');
        $tocados = 0;
        $orden = 11;

        foreach (self::CONTACTO as $clave => [$valor, $etiqueta, $ayuda]) {
            if (false !== $this->conexion->fetchOne('SELECT id FROM configuracion WHERE clave = ?', [$clave])) {
                continue;
            }

            $this->conexion->insert('configuracion', [
                'clave' => $clave,
                'valor' => $valor,
                'tipo' => 'texto',
                'grupo' => 'contacto',
                'etiqueta' => $etiqueta,
                'ayuda' => $ayuda,
                'orden' => ++$orden,
            ]);

            $io->text(sprintf('  <info>+</info> %s = %s', $etiqueta, $valor));
            $tocados++;
        }

        // El call center del sitio venía del Joomla anterior. El portafolio
        // 2026 trae otro número; se cambia solo si nadie lo tocó en el panel.
        $actual = $this->conexion->fetchAssociative(
            "SELECT valor, actualizado_por FROM configuracion WHERE clave = 'contacto.call_center'",
        );

        if ($actual !== false && $actual['actualizado_por'] === null && $actual['valor'] !== '317 5153642') {
            $this->conexion->update(
                'configuracion',
                ['valor' => '317 5153642'],
                ['clave' => 'contacto.call_center'],
            );
            $io->text(sprintf('  <comment>~</comment> Call center: %s → 317 5153642 (portafolio 2026)', $actual['valor']));
            $tocados++;
        }

        return $tocados;
    }

    // --- soporte ---

    /**
     * Deja constancia de que el volcado completo ya ocurrió.
     *
     * Va en `configuracion` y no en un archivo porque es estado del sitio, no
     * del despliegue: si mañana se restaura un respaldo de la base, la marca
     * viaja con él. `editable = 0` para que no aparezca en Panel → Ajustes:
     * no es un dato que un funcionario deba cambiar.
     */
    private function marcarVolcado(): void
    {
        if (false !== $this->conexion->fetchOne("SELECT id FROM configuracion WHERE clave = 'portafolio.volcado_en'")) {
            return;
        }

        $this->conexion->insert('configuracion', [
            'clave' => 'portafolio.volcado_en',
            'valor' => date('Y-m-d H:i:s'),
            'tipo' => 'texto',
            'grupo' => 'sistema',
            'etiqueta' => 'Portafolio de servicios volcado el',
            'ayuda' => 'Fecha del primer volcado del Portafolio de Servicios 2026 al sitio.',
            'editable' => 0,
        ]);
    }

    /**
     * @param list<array{string, string}> $destacados
     */
    private function destacadosJson(array $destacados): ?string
    {
        if ($destacados === []) {
            return null;
        }

        return json_encode(
            array_map(static fn (array $d): array => ['cifra' => $d[0], 'texto' => $d[1]], $destacados),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Inserta el bloque del portafolio en el contenido de una página.
     *
     * El marcador permite volver a ejecutar el comando sin duplicar el bloque
     * ni borrar lo que la página ya tuviera por otro lado: si el bloque está,
     * se reemplaza; si no, se añade al final.
     */
    private function contenidoConBloque(string $contenido, string $bloque): string
    {
        $marcado = "<!-- portafolio-2026 -->\n" . $bloque . "\n<!-- /portafolio-2026 -->";
        $patron = '/<!-- portafolio-2026 -->.*?<!-- \/portafolio-2026 -->/s';

        if (preg_match($patron, $contenido) === 1) {
            return (string) preg_replace($patron, str_replace('$', '\$', $marcado), $contenido);
        }

        return trim($contenido) === '' ? $marcado : trim($contenido) . "\n" . $marcado;
    }

    /**
     * Sube una foto recortada del portafolio a la biblioteca de medios.
     *
     * Devuelve null si el archivo no está: el comando debe poder correr en un
     * despliegue donde las semillas binarias no se copiaron, y un servicio sin
     * foto se muestra igual, con su icono.
     */
    private function subirFoto(string $nombreArchivo): ?int
    {
        $ruta = sprintf('%s/%s.webp', self::DIRECTORIO_FOTOS, $nombreArchivo);

        if (!is_file($ruta)) {
            return null;
        }

        // Copia temporal porque `AlmacenArchivos::guardar()` mueve el archivo:
        // sin esto, la segunda ejecución ya no encontraría la semilla.
        $temporal = sprintf('%s/portafolio-%s.webp', sys_get_temp_dir(), bin2hex(random_bytes(6)));

        if (!copy($ruta, $temporal)) {
            return null;
        }

        try {
            return $this->almacen->guardar(
                // `test: true`: el archivo lo escribió este proceso y no una
                // subida HTTP, así que `move()` no puede exigir is_uploaded_file.
                subido: new UploadedFile($temporal, $nombreArchivo . '.webp', 'image/webp', null, true),
                publico: true,
                creadoPor: null,
                textoAlt: self::TEXTOS_ALT[$nombreArchivo] ?? null,
            )->id;
        } finally {
            if (is_file($temporal)) {
                unlink($temporal);
            }
        }
    }
}
