<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Config\ConfigAplicacion;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Datos de PRUEBA para ver el sitio lleno durante el desarrollo.
 *
 * Nada de lo que hay aquí es información del hospital: los profesionales, los
 * servidores públicos, las noticias, los eventos, las convocatorias y las
 * cifras de los indicadores son inventados, para poder revisar maquetas,
 * paginaciones y buscador con volumen realista.
 *
 * Dos salvaguardas, porque un dato de prueba en el sitio real es exactamente
 * el problema que se corrigió el 2026-09-14 con el «98.4 % de satisfacción»:
 *
 * 1. **Se niega a correr en producción.** No hay bandera para saltárselo.
 * 2. **`--limpiar` lo deshace**, por las mismas claves que insertó. Los
 *    indicadores vuelven a valor 0 y sin publicar.
 *
 * Lo que NO siembra: documentos y datos abiertos, porque necesitan archivos
 * reales subidos, y trámites radicados, porque cada uno consume un consecutivo
 * y ensuciaría la numeración oficial de radicados.
 */
final class SembrarDemoCommand extends Command
{
    /** @var list<array{string, string, string, string}> nombre, slug, descripción, horario */
    private const array SERVICIOS = [
        ['Urgencias 24 horas', 'urgencias-24-horas', 'Atención de urgencias y emergencias todos los días del año, con triage de cinco niveles.', 'Todos los días, 24 horas'],
        ['Consulta externa', 'consulta-externa', 'Consulta de medicina general y especializada por agenda programada.', 'Lunes a viernes, 7:00 a. m. a 5:00 p. m.'],
        ['Hospitalización', 'hospitalizacion', 'Internación en salas de medicina interna, cirugía, pediatría y ginecobstetricia.', 'Todos los días, 24 horas'],
        ['Cirugía', 'cirugia', 'Procedimientos quirúrgicos programados y de urgencia en salas habilitadas.', 'Lunes a sábado, 6:00 a. m. a 6:00 p. m.'],
        ['Laboratorio clínico', 'laboratorio-clinico', 'Exámenes de laboratorio de baja, mediana y alta complejidad.', 'Lunes a sábado, 6:00 a. m. a 4:00 p. m.'],
        ['Imagenología', 'imagenologia', 'Radiografía, ecografía y tomografía con lectura especializada.', 'Lunes a sábado, 7:00 a. m. a 7:00 p. m.'],
    ];

    /** @var list<array{string, string, string, int, int}> nombre, slug, perfil, especialidad, autoriza */
    private const array PROFESIONALES = [
        ['Ana María Restrepo Calderón', 'ana-maria-restrepo-calderon', 'Médica internista con doce años de experiencia en hospitalización de adultos.', 1, 1],
        ['Jorge Iván Mendoza Arrieta', 'jorge-ivan-mendoza-arrieta', 'Pediatra, coordinador del servicio de hospitalización infantil.', 2, 1],
        ['Claudia Patricia Salas Ochoa', 'claudia-patricia-salas-ochoa', 'Ginecóloga y obstetra, atención de alto riesgo obstétrico.', 3, 1],
        ['Ricardo Andrés Villamil Peña', 'ricardo-andres-villamil-pena', 'Cirujano general, cirugía laparoscópica y de urgencias.', 4, 1],
        ['Marta Lucía Ospina Berrío', 'marta-lucia-ospina-berrio', 'Ortopedista y traumatóloga, cirugía de trauma y reemplazos articulares.', 5, 1],
        ['Camilo Esteban Duarte Nieto', 'camilo-esteban-duarte-nieto', 'Anestesiólogo. No autorizó la publicación de sus datos.', 6, 0],
    ];

    /** @var list<array{string, string, string, string}> titulo, slug, resumen, contenido */
    private const array NOTICIAS = [
        ['Nueva sala de procedimientos menores entra en operación', 'nueva-sala-de-procedimientos-menores', 'La sala permite atender curaciones y suturas sin ocupar quirófano.', '<p>La nueva sala de procedimientos menores entró en operación esta semana y permite resolver curaciones, suturas y drenajes sin ocupar los quirófanos programados.</p>'],
        ['Jornada de donación de sangre con el banco departamental', 'jornada-de-donacion-de-sangre', 'Se recolectaron unidades suficientes para cubrir la demanda de la semana.', '<p>En articulación con el banco de sangre departamental se realizó una jornada de donación voluntaria en el hall principal del hospital.</p>'],
        ['Actualización de los horarios de consulta externa', 'actualizacion-horarios-consulta-externa', 'La consulta externa amplía su horario de atención en la tarde.', '<p>A partir de este mes la consulta externa atiende hasta las 5:00 p. m. de lunes a viernes.</p>'],
        ['Capacitación en seguridad del paciente para el personal asistencial', 'capacitacion-seguridad-del-paciente', 'Ciento veinte funcionarios participaron en el taller de seguridad del paciente.', '<p>El taller abordó identificación correcta del paciente, higiene de manos y prevención de caídas.</p>'],
        ['Resultados de la encuesta de satisfacción del trimestre', 'resultados-encuesta-satisfaccion-trimestre', 'La encuesta recogió la percepción de los usuarios sobre la atención recibida.', '<p>El informe completo está disponible en la sección de transparencia de este portal.</p>'],

        // A partir de aquí, volumen para que el listado pase de una página.
        // Con doce noticias por página (NoticiasPublicoController::POR_PAGINA)
        // hacen falta más de veinticuatro para llegar a una tercera, que es la
        // única forma de ver el paginador en su estado intermedio: con el botón
        // de «Recientes» y el de «Anteriores» a la vez.
        //
        // Los títulos son variados a propósito, y no «Noticia 7», «Noticia 8»:
        // así el reparto de portadas de `semillas:imagenes` les asigna motivos
        // distintos y la rejilla se revisa como se va a ver de verdad.
        ['Brigada de vacunación en zona rural del municipio', 'brigada-de-vacunacion-zona-rural', 'El equipo extramural llevó el esquema completo a los corregimientos.', '<p>La brigada recorrió los corregimientos con el esquema completo del Programa Ampliado de Inmunizaciones.</p>'],
        ['Campaña de donación de sangre para la temporada de fin de año', 'campana-donacion-sangre-fin-de-ano', 'La demanda de hemocomponentes sube en las semanas de mayor accidentalidad.', '<p>El banco de sangre departamental acompaña la campaña en el hall principal del hospital.</p>'],
        ['Tamizaje de riesgo cardiovascular en el hall principal', 'tamizaje-riesgo-cardiovascular-hall', 'Toma de presión arterial y glucometría sin cita previa.', '<p>El tamizaje se ofrece sin cita previa a toda persona mayor de cuarenta años que se acerque al hall principal.</p>'],
        ['La sala amiga de la familia lactante abre sus puertas', 'sala-amiga-familia-lactante', 'Un espacio reservado para la lactancia dentro de la institución.', '<p>La sala ofrece un espacio privado y acondicionado para madres en período de lactancia.</p>'],
        ['Nuevo equipo para el servicio de cirugía ambulatoria', 'nuevo-equipo-cirugia-ambulatoria', 'La dotación permite ampliar la programación semanal de procedimientos.', '<p>La nueva dotación entra en operación tras la verificación técnica de los equipos.</p>'],
        ['Ampliación del horario de atención del laboratorio clínico', 'ampliacion-horario-laboratorio-clinico', 'La toma de muestras se extiende a la jornada de la tarde.', '<p>La toma de muestras se extiende a la jornada de la tarde de lunes a viernes.</p>'],
        ['Taller de formación en atención humanizada', 'taller-formacion-atencion-humanizada', 'Dirigido al personal asistencial y administrativo de todas las áreas.', '<p>El taller trabaja el trato al usuario, la comunicación con la familia y el manejo de situaciones difíciles.</p>'],
        ['Convocatoria para el banco de hojas de vida de enfermería', 'convocatoria-banco-hojas-vida-enfermeria', 'La inscripción se realiza por la sección de convocatorias del portal.', '<p>Las condiciones de participación y el cronograma están publicados en la sección de convocatorias.</p>'],
        ['Simulacro de evacuación refuerza el plan de seguridad hospitalaria', 'simulacro-evacuacion-seguridad-hospitalaria', 'Participaron las áreas de hospitalización, urgencias y consulta externa.', '<p>El ejercicio permitió revisar las rutas de evacuación y los puntos de encuentro de cada servicio.</p>'],
        ['Resultados de la encuesta de percepción de usuarios', 'resultados-encuesta-percepcion-usuarios', 'El informe se publica en la sección de transparencia.', '<p>El informe con la metodología y los resultados está disponible en la sección de transparencia.</p>'],
        ['Jornada de salud oral en la primera infancia', 'jornada-salud-oral-primera-infancia', 'Valoración odontológica y educación en higiene bucal.', '<p>La jornada incluye valoración odontológica y educación en higiene bucal para niñas y niños.</p>'],
        ['Inicia el esquema de vacunación contra la influenza estacional', 'esquema-vacunacion-influenza-estacional', 'Dirigido a la población priorizada en los lineamientos nacionales.', '<p>La vacunación se aplica a la población priorizada en los lineamientos del Ministerio de Salud y Protección Social.</p>'],
        ['Nueva agenda en línea para la programación de citas', 'nueva-agenda-en-linea-citas', 'La solicitud de cita de consulta externa se puede hacer desde el portal.', '<p>La solicitud de cita de consulta externa se puede realizar desde el portal, sin desplazarse hasta la sede.</p>'],
        ['El hospital fortalece el programa de seguridad del paciente', 'fortalecimiento-programa-seguridad-paciente', 'Nuevas rondas de verificación en los servicios asistenciales.', '<p>El programa incorpora rondas de verificación periódicas en los servicios asistenciales.</p>'],
        ['Capacitación en reanimación cardiopulmonar para el personal', 'capacitacion-reanimacion-cardiopulmonar', 'Entrenamiento práctico con maniquíes en todas las áreas asistenciales.', '<p>El entrenamiento es práctico y se repite por turnos para cubrir a todo el personal asistencial.</p>'],
        ['Control de la hipertensión: nuevas jornadas de toma de presión', 'jornadas-control-hipertension', 'La presión alta avanza durante años sin dar síntomas.', '<p>La toma de presión es gratuita y no requiere remisión ni cita previa.</p>'],
        ['Convocatoria interna para el comité de ética hospitalaria', 'convocatoria-comite-etica-hospitalaria', 'Se invita a postularse a funcionarios de las áreas asistenciales.', '<p>Las postulaciones se reciben en la oficina de talento humano dentro del plazo publicado.</p>'],
        ['Mantenimiento programado en el servicio de urgencias', 'mantenimiento-programado-urgencias', 'La atención se traslada temporalmente sin suspender el servicio.', '<p>Durante el mantenimiento la atención se traslada a un área alterna, sin suspender el servicio en ningún momento.</p>'],
        ['Semana de la seguridad y salud en el trabajo', 'semana-seguridad-salud-en-el-trabajo', 'Actividades de prevención de riesgos para todo el personal.', '<p>La semana reúne actividades de prevención de riesgos laborales dirigidas a funcionarios y contratistas.</p>'],
        ['Informe de indicadores de calidad del último trimestre', 'informe-indicadores-calidad-trimestre', 'Oportunidad, seguridad y satisfacción, cada uno con su fuente.', '<p>Cada indicador se publica con su periodo y su fuente en la sección de indicadores del portal.</p>'],
        ['Se amplía el programa de acompañamiento a la lactancia materna', 'ampliacion-programa-lactancia-materna', 'Asesoría a madres gestantes y en período de lactancia.', '<p>El programa suma nuevos horarios de asesoría para madres gestantes y en período de lactancia.</p>'],
    ];

    /** @var list<array{string, string, string, string, int}> titulo, slug, tipo, lugar, días desde hoy */
    private const array EVENTOS = [
        ['Rendición de cuentas de la vigencia', 'rendicion-de-cuentas-vigencia', 'rendicion_cuentas', 'Auditorio principal del hospital', 12],
        ['Jornada de salud visual', 'jornada-de-salud-visual', 'jornada_salud', 'Consulta externa, primer piso', 5],
        ['Taller de humanización del servicio', 'taller-de-humanizacion-del-servicio', 'capacitacion', 'Sala de capacitaciones', 20],
        ['Audiencia pública de participación ciudadana', 'audiencia-publica-participacion', 'participacion_ciudadana', 'Auditorio principal del hospital', 32],
    ];

    /** @var list<array{string, string, string, string, int, int}> titulo, slug, tipo, estado, días de apertura, días de cierre */
    private const array CONVOCATORIAS = [
        ['Convocatoria para auxiliares de enfermería', 'convocatoria-auxiliares-de-enfermeria', 'empleo', 'abierta', -5, 15],
        ['Invitación a cotizar insumos de laboratorio', 'invitacion-cotizar-insumos-laboratorio', 'proveedores', 'abierta', -2, 8],
        ['Contratación de servicios de mantenimiento hospitalario', 'contratacion-mantenimiento-hospitalario', 'contratacion', 'cerrada', -40, -10],
    ];

    /** @var list<array{string, string}> pregunta, respuesta */
    private const array FAQ = [
        ['¿Cómo solicito una cita médica?', '<p>Puedes solicitarla en línea desde la sección de Citas, por el call center o presencialmente en la ventanilla de consulta externa.</p>'],
        ['¿Qué documentos debo llevar a mi cita?', '<p>Documento de identidad, carné de la EPS y la orden médica cuando aplique.</p>'],
        ['¿Cómo pido una copia de mi historia clínica?', '<p>Radica la solicitud por PQRSDF adjuntando tu documento de identidad. Si la pide un tercero, debe presentar autorización firmada.</p>'],
        ['¿Atienden urgencias sin cita previa?', '<p>Sí. El servicio de urgencias atiende las 24 horas, todos los días, sin cita previa.</p>'],
        ['¿Cuál es el horario de visitas a hospitalización?', '<p>El horario de visitas se informa en cada servicio y puede variar por condiciones sanitarias.</p>'],
        ['¿Dónde presento una queja o un reclamo?', '<p>En la sección de PQRSDF de este portal, en el buzón físico del hospital o en la oficina de atención al ciudadano.</p>'],
    ];

    /** @var list<array{string, string, string, string, string}> nombres, apellidos, slug, cargo, vinculación */
    private const array SERVIDORES = [
        ['Luis Fernando', 'Ariza Molina', 'luis-fernando-ariza-molina', 'Gerente', 'libre_nombramiento'],
        ['Diana Carolina', 'Pérez Guzmán', 'diana-carolina-perez-guzman', 'Subgerente científica', 'libre_nombramiento'],
        ['Hernán Darío', 'Cuadrado Silva', 'hernan-dario-cuadrado-silva', 'Subgerente administrativo y financiero', 'libre_nombramiento'],
        ['Yolanda', 'Mejía Barros', 'yolanda-mejia-barros', 'Jefe de la Oficina de Control Interno', 'planta'],
        ['Andrés Felipe', 'Torres Lozano', 'andres-felipe-torres-lozano', 'Profesional universitario de Talento Humano', 'planta'],
    ];

    /** slug de indicador => valor de prueba */
    private const array INDICADORES = [
        'contratos-suscritos-en-el-periodo' => 128,
        'valor-total-contratado' => 4350,
        'procesos-publicados-en-el-secop' => 37,
        'porcentaje-de-ocupacion-de-camas' => 82.5,
        'promedio-dia-estancia' => 4.2,
        'giro-cama' => 6.1,
        'egresos-hospitalarios' => 1540,
        'nacidos-vivos-atendidos' => 212,
        'consultas-de-urgencias-atendidas' => 7890,
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
        private readonly ConfigAplicacion $aplicacion,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('semillas:demo')
            ->setDescription('Llena el sitio con datos FICTICIOS para desarrollo (nunca en producción)')
            ->addOption('limpiar', null, InputOption::VALUE_NONE, 'Borra exactamente lo que este comando insertó');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->aplicacion->entorno->esProduccion()) {
            $io->error([
                'Este comando no corre en producción, y no hay bandera para forzarlo.',
                'Inventa nombres de médicos, de directivos y cifras de ocupación hospitalaria: publicarlos '
                . 'en el sitio real sería información institucional falsa.',
            ]);

            return Command::FAILURE;
        }

        if ($input->getOption('limpiar') === true) {
            return $this->limpiar($io);
        }

        $sedeId = (int) $this->conexion->fetchOne('SELECT id FROM sedes ORDER BY id LIMIT 1');

        if ($sedeId === 0) {
            $io->error('No hay ninguna sede. Corre antes `semillas:directorio`.');

            return Command::FAILURE;
        }

        $resumen = [
            ['Servicios', $this->sembrarServicios($sedeId)],
            ['Profesionales', $this->sembrarProfesionales()],
            ['Noticias', $this->sembrarNoticias()],
            ['Eventos', $this->sembrarEventos()],
            ['Convocatorias', $this->sembrarConvocatorias()],
            ['Preguntas frecuentes', $this->sembrarFaq()],
            ['Servidores públicos', $this->sembrarServidores()],
            ['Indicadores con cifra', $this->sembrarIndicadores()],
        ];

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Creacion,
            entidad: 'demo',
            descripcion: 'Semillas de DEMOSTRACIÓN insertadas (datos ficticios, entorno '
                . $this->aplicacion->entorno->value . ')',
            usuarioNombre: 'consola',
        ));

        $io->table(['Conjunto', 'Filas nuevas'], $resumen);
        $io->success('Datos de prueba insertados.');
        $io->note([
            'TODO esto es ficticio: personas, noticias, convocatorias y cifras.',
            'Para que el buscador los encuentre: php bin/consola busqueda:indexar',
            'Para borrarlo todo:                  php bin/consola semillas:demo --limpiar',
        ]);

        return Command::SUCCESS;
    }

    private function sembrarServicios(int $sedeId): int
    {
        $nuevos = 0;

        foreach (self::SERVICIOS as $orden => [$nombre, $slug, $descripcion, $horario]) {
            if ($this->existe('servicios', 'slug', $slug)) {
                continue;
            }

            $this->conexion->insert('servicios', [
                'nombre' => $nombre,
                'slug' => $slug,
                'descripcion' => $descripcion,
                'horario' => $horario,
                'orden' => $orden + 1,
                'activo' => 1,
            ]);

            $this->conexion->insert('servicio_sede', [
                'servicio_id' => (int) $this->conexion->lastInsertId(),
                'sede_id' => $sedeId,
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarProfesionales(): int
    {
        $nuevos = 0;

        foreach (self::PROFESIONALES as $orden => [$nombre, $slug, $perfil, $especialidadId, $autoriza]) {
            if ($this->existe('profesionales', 'slug', $slug)) {
                continue;
            }

            $this->conexion->insert('profesionales', [
                'nombre' => $nombre,
                'slug' => $slug,
                'perfil' => $perfil,
                // Uno entra sin autorización a propósito: así se comprueba en el
                // sitio que la regla se cumple y no sale en el directorio.
                'autoriza_publicacion' => $autoriza,
                'orden' => $orden + 1,
                'activo' => 1,
            ]);

            $this->conexion->insert('profesional_especialidad', [
                'profesional_id' => (int) $this->conexion->lastInsertId(),
                'especialidad_id' => $especialidadId,
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarNoticias(): int
    {
        $categoriaId = $this->conexion->fetchOne(
            "SELECT id FROM categorias WHERE tipo = 'noticia' AND slug = 'boletines-de-prensa'",
        );
        $nuevos = 0;

        foreach (self::NOTICIAS as $i => [$titulo, $slug, $resumen, $contenido]) {
            if ($this->existe('noticias', 'slug', $slug)) {
                continue;
            }

            $this->conexion->insert('noticias', [
                'slug' => $slug,
                'titulo' => $titulo,
                'resumen' => $resumen,
                'contenido' => $contenido,
                'categoria_id' => $categoriaId === false ? null : (int) $categoriaId,
                'destacada' => $i === 0 ? 1 : 0,
                'estado_aprobacion' => 'publicado',
                'aprobado_en' => date('Y-m-d H:i:s'),
                // Escalonadas hacia atrás: cinco piezas del mismo día no se
                // parecen a nada real y esconden los fallos de ordenación.
                'publicar_en' => date('Y-m-d H:i:s', (int) strtotime('-' . ($i * 3 + 1) . ' days')),
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarEventos(): int
    {
        $nuevos = 0;

        foreach (self::EVENTOS as [$titulo, $slug, $tipo, $lugar, $dias]) {
            if ($this->existe('eventos', 'slug', $slug)) {
                continue;
            }

            $inicio = (int) strtotime('+' . $dias . ' days 9:00');

            $this->conexion->insert('eventos', [
                'titulo' => $titulo,
                'slug' => $slug,
                'descripcion' => '<p>Evento de demostración para revisar el calendario del portal.</p>',
                'tipo' => $tipo,
                'inicio' => date('Y-m-d H:i:s', $inicio),
                'fin' => date('Y-m-d H:i:s', $inicio + 7200),
                'lugar' => $lugar,
                'publicado' => 1,
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarConvocatorias(): int
    {
        $nuevos = 0;

        foreach (self::CONVOCATORIAS as [$titulo, $slug, $tipo, $estado, $desdeDias, $hastaDias]) {
            if ($this->existe('convocatorias', 'slug', $slug)) {
                continue;
            }

            $this->conexion->insert('convocatorias', [
                'titulo' => $titulo,
                'slug' => $slug,
                'tipo' => $tipo,
                'descripcion' => '<p>Convocatoria de demostración. Los requisitos y el cronograma reales '
                    . 'los publica la entidad en cada proceso.</p>',
                'estado' => $estado,
                'apertura' => date('Y-m-d H:i:s', (int) strtotime($desdeDias . ' days 8:00')),
                'cierre' => date('Y-m-d H:i:s', (int) strtotime($hastaDias . ' days 17:00')),
                'publicada' => 1,
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarFaq(): int
    {
        $nuevos = 0;

        foreach (self::FAQ as $orden => [$pregunta, $respuesta]) {
            if ($this->existe('faq', 'pregunta', $pregunta)) {
                continue;
            }

            $this->conexion->insert('faq', [
                'pregunta' => $pregunta,
                'respuesta' => $respuesta,
                'orden' => $orden + 1,
                'publicada' => 1,
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarServidores(): int
    {
        $dependenciaId = $this->conexion->fetchOne('SELECT id FROM dependencias ORDER BY id LIMIT 1');
        $nuevos = 0;

        foreach (self::SERVIDORES as $orden => [$nombres, $apellidos, $slug, $cargo, $vinculacion]) {
            if ($this->existe('servidores_publicos', 'slug', $slug)) {
                continue;
            }

            $this->conexion->insert('servidores_publicos', [
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'slug' => $slug,
                'cargo' => $cargo,
                'dependencia_id' => $dependenciaId === false ? null : (int) $dependenciaId,
                'tipo_vinculacion' => $vinculacion,
                'correo_institucional' => $slug . '@ejemplo.local',
                'orden' => $orden + 1,
                'publicado' => 1,
            ]);

            $nuevos++;
        }

        return $nuevos;
    }

    private function sembrarIndicadores(): int
    {
        $tocados = 0;

        foreach (self::INDICADORES as $slug => $valor) {
            $tocados += $this->conexion->executeStatement(
                'UPDATE indicadores SET valor = ?, publicado = 1 WHERE slug = ?',
                [$valor, $slug],
            );
        }

        return $tocados;
    }

    private function limpiar(SymfonyStyle $io): int
    {
        $borradas = 0;

        $borradas += $this->borrarPor('servicios', 'slug', array_column(self::SERVICIOS, 1));
        $borradas += $this->borrarPor('profesionales', 'slug', array_column(self::PROFESIONALES, 1));
        $borradas += $this->borrarPor('noticias', 'slug', array_column(self::NOTICIAS, 1));
        $borradas += $this->borrarPor('eventos', 'slug', array_column(self::EVENTOS, 1));
        $borradas += $this->borrarPor('convocatorias', 'slug', array_column(self::CONVOCATORIAS, 1));
        $borradas += $this->borrarPor('faq', 'pregunta', array_column(self::FAQ, 0));
        $borradas += $this->borrarPor('servidores_publicos', 'slug', array_column(self::SERVIDORES, 2));

        // Los indicadores no se borran: el catálogo lo sembró otro comando y es
        // del hospital. Solo se les quita la cifra de prueba y la publicación.
        $indicadores = $this->conexion->executeStatement(
            'UPDATE indicadores SET valor = 0, publicado = 0 WHERE slug IN (?)',
            [array_keys(self::INDICADORES)],
            [ArrayParameterType::STRING],
        );

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Eliminacion,
            entidad: 'demo',
            descripcion: sprintf(
                'Semillas de demostración retiradas: %d filas y %d indicadores despublicados',
                $borradas,
                $indicadores,
            ),
            usuarioNombre: 'consola',
        ));

        $io->success(sprintf('%d filas borradas y %d indicadores devueltos a 0 sin publicar.', $borradas, $indicadores));
        $io->note('Recuerda reindexar el buscador: php bin/consola busqueda:indexar');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $valores
     */
    private function borrarPor(string $tabla, string $columna, array $valores): int
    {
        if ($valores === []) {
            return 0;
        }

        return $this->conexion->executeStatement(
            sprintf('DELETE FROM %s WHERE %s IN (?)', $tabla, $columna),
            [$valores],
            [ArrayParameterType::STRING],
        );
    }

    private function existe(string $tabla, string $columna, string $valor): bool
    {
        return false !== $this->conexion->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE %s = ?', $tabla, $columna),
            [$valor],
        );
    }
}
