<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Modules\Transparencia\Repository\EnteVigilanciaRepository;
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
            '1.8' => 'Estructura funcional',
            '1.9' => 'Mapas y cartas descriptivas de los procesos',
            '1.10' => 'Escalas salariales por categoría de empleo',
            '1.11' => 'Directorio de agremiaciones, asociaciones y grupos de interés',
            '1.12' => 'Servicio al público: normas, formularios y protocolos de atención',
            '1.13' => 'Mecanismo de presentación de peticiones, quejas y reclamos',
            '1.14' => 'Información sobre decisiones que pueden afectar al público',
            '1.15' => 'Entes y autoridades que vigilan a la entidad',
            '1.16' => 'Hojas de vida de directivos y servidores públicos',
            '1.17' => 'Plan de seguridad y privacidad de la información (MSPI)',
        ]],
        '2' => ['Normativa', [
            '2.1' => 'Leyes y normas que rigen a la entidad',
            '2.2' => 'Decretos',
            '2.3' => 'Resoluciones y otros actos administrativos',
            '2.4' => 'Circulares',
            '2.5' => 'Decreto único reglamentario del sector',
            '2.6' => 'Vínculo a la gaceta oficial departamental y al diario oficial',
            '2.7' => 'Políticas, lineamientos y manuales',
            '2.8' => 'Búsqueda de normas (SUIN y sistema único de información normativa)',
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
            '4.7' => 'Distribución presupuestal de proyectos de inversión',
            '4.8' => 'Proyectos de inversión',
            '4.9' => 'Informes de empalme',
            '4.10' => 'Informe de rendición de cuentas ante la Contraloría',
            '4.11' => 'Informe de rendición de cuentas a la ciudadanía',
            '4.12' => 'Informe pormenorizado de control interno',
            '4.13' => 'Informes de software',
            '4.14' => 'Defensa pública y prevención del daño antijurídico',
            '4.15' => 'Informe trimestral de acceso a información, quejas y reclamos',
            // ITA 6.8.b y orden 8 del oficio de Supersalud 20264200002708501:
            // la sección donde se publica el Informe Especial de Seguimiento al
            // ITA. El Joomla la llamaba «REPORTES DE CONTROL INTERNO / INFORMES
            // DE SEGUIMIENTOS CONTROL INTERNO» y ese nombre está citado en un
            // expediente, así que se conserva en vez de absorberla en 4.3.
            '4.16' => 'Informes de seguimiento de Control Interno',
        ]],
        '5' => ['Trámites', [
            '5.1' => 'Trámites y servicios de la entidad',
        ]],
        '6' => ['Participa', [
            '6.1' => 'Rendición de cuentas',
            '6.2' => 'Consulta y participación ciudadana',
            '6.3' => 'Asociación de usuarios',
            '6.4' => 'Mecanismos de participación ciudadana',
            '6.5' => 'Estrategias de participación ciudadana',
            '6.6' => 'Plan anticorrupción y de atención al ciudadano',
            '6.7' => 'Convocatorias',
            '6.8' => 'Calendario de participación ciudadana',
            '6.9' => 'Formulario de inscripción ciudadana',
            '6.10' => 'Canal de interacción para la participación ciudadana',
            '6.11' => 'Diagnóstico e identificación de problemas',
            '6.12' => 'Planeación y presupuesto participativo',
            '6.13' => 'Consulta ciudadana',
            '6.14' => 'Colaboración e innovación',
            '6.15' => 'Control social',
            '6.16' => 'Política institucional de participación ciudadana',
        ]],
        '7' => ['Datos abiertos', [
            '7.1' => 'Conjuntos de datos publicados',
            '7.2' => 'Información mínima requerida',
            '7.3' => 'Registro de Activos de Información',
            '7.4' => 'Índice de Información Clasificada y Reservada',
            '7.5' => 'Esquema de Publicación de Información',
            '7.6' => 'Programa de Gestión Documental',
            '7.7' => 'Tablas de Retención Documental',
            '7.8' => 'Registro de publicaciones',
            '7.9' => 'Costos de reproducción de la información pública',
            '7.10' => 'Mecanismos para presentar quejas y reclamos por acciones u omisiones',
            '7.11' => 'Informe de peticiones, quejas, reclamos y sugerencias',
        ]],
        '8' => ['Información específica para grupos de interés', [
            '8.1' => 'Información para niñas, niños y adolescentes',
            '8.2' => 'Información para mujeres',
            // ITA 10.1.c. Faltaba, y la sección pesa 2 % del índice. Cubre a
            // comunidades étnicas, personas con discapacidad, víctimas,
            // migrantes y adultos mayores: el hospital ya produce contenido
            // para varios de esos públicos (p. ej. /programas/etnocultural),
            // solo no colgaba de aquí.
            '8.3' => 'Información para otros grupos de interés',
        ]],
        '9' => ['Obligación de reporte de información específica', [
            '9.1' => 'Sistema Único de Habilitación',
            '9.2' => 'Informes exigidos por normativa del sector salud',
            '9.3' => 'Normatividad especial del sector salud',
        ]],
        // Sección 11 del esquema que publica hoy el Joomla (docs/14 §3). No
        // hay sección 10: el «Directorio de entidades del sector» que el
        // Joomla numeraba así ya vive en 1.5, y duplicarlo confundiría al
        // ciudadano y al ITA.
        '11' => ['Sistema Integral de Gestión del Riesgo', [
            '11.1' => 'Política de administración del riesgo',
            '11.2' => 'Mapa de riesgos institucional',
            '11.3' => 'Seguimiento al mapa de riesgos',
        ]],
    ];

    /**
     * Ítems que no son un documento sino un destino fijo: una plataforma del
     * Estado o una pantalla propia del sitio. Se siembran aquí porque un
     * funcionario no debería tener que teclear la URL del SECOP ni acordarse
     * de dónde vive el Registro de Activos.
     *
     * `[código de sección, URL, título, descripción]`. La URL hace de clave de
     * idempotencia: sembrar dos veces no duplica el ítem.
     *
     * @var list<array{string, string, string, string}>
     */
    private const array ENLACES = [
        ['3.2', 'https://www.colombiacompra.gov.co/secop/secop-ii',
            'Procesos contractuales de la entidad en SECOP II',
            'Consulta los procesos de contratación publicados por la E.S.E. en el Sistema Electrónico de Contratación Pública.'],

        // ITA 9.1: los tres instrumentos de la Ley 1712 art. 6 lit. d. Son
        // tablas publicadas en el sitio, no documentos adjuntos.
        ['7.3', '/transparencia/instrumentos-de-gestion/registro-de-activos-de-informacion',
            'Registro de Activos de Información',
            'Inventario de las categorías de información que produce y administra la entidad. Se consulta en línea y se descarga en CSV.'],
        ['7.4', '/transparencia/instrumentos-de-gestion/indice-de-informacion-clasificada-y-reservada',
            'Índice de Información Clasificada y Reservada',
            'Información que la entidad reserva o clasifica, con el fundamento jurídico de la excepción y el plazo de la reserva.'],
        ['7.5', '/transparencia/instrumentos-de-gestion/esquema-de-publicacion-de-informacion',
            'Esquema de Publicación de Información',
            'Qué información publica la entidad, con qué frecuencia se actualiza y dónde se consulta.'],

        // ITA 5.3: estado de ejecución de los contratos, como tabla publicada
        // y no como PDF. Fue una de las pérdidas de 2026.
        ['3.3', '/transparencia/ejecucion-contractual',
            'Estado de ejecución de los contratos',
            'Valor, porcentaje ejecutado, recursos desembolsados y pendientes, y adiciones de cada contrato. Se consulta en línea y se descarga en CSV.'],

        // ITA 6.10: informe trimestral de acceso a la información.
        ['4.15', '/transparencia/informe-de-acceso-a-la-informacion',
            'Informe de acceso a la información, quejas y reclamos',
            'Solicitudes recibidas, tiempos de respuesta, traslados y negaciones de acceso, trimestre a trimestre.'],

        // ITA 4.2.a: buscador de normas del Estado.
        ['2.8', 'https://www.suin-juriscol.gov.co/',
            'Sistema Único de Información Normativa — SUIN Juriscol',
            'Buscador oficial de normas del Estado colombiano, a cargo del Ministerio de Justicia y del Derecho.'],
    ];

    /**
     * Entes y autoridades que vigilan a la E.S.E. (ITA 3.13).
     *
     * Solo se siembran datos VERIFICABLES. La dirección, el teléfono y el
     * correo de Supersalud y de la Procuraduría salen del membrete de los dos
     * oficios que originaron este trabajo; del resto se siembra el nombre, el
     * tipo de control y el dominio oficial, y los datos de contacto quedan
     * vacíos para que los complete el hospital desde el panel.
     *
     * Inventar un teléfono en la página que le dice al ciudadano ante quién
     * quejarse sería peor que dejar el campo vacío: la vista simplemente omite
     * lo que no está.
     *
     * `[nombre, tipo, dirección, teléfono, correo, url, mecanismos, orden]`
     *
     * @var list<array{string, string, ?string, ?string, ?string, ?string, string, int}>
     */
    private const array ENTES = [
        [
            'Superintendencia Nacional de Salud',
            'administrativo',
            'Carrera 68 A n.º 24 B - 10, Torre 3, pisos 4, 9 y 10, Bogotá D.C.',
            '+57 601 744 2000',
            null,
            'https://www.supersalud.gov.co',
            'La E.S.E. se encuentra en medida de intervención forzosa administrativa para administrar. '
                . 'La Superintendencia ejerce inspección, vigilancia y control, y hace seguimiento a la medida.',
            0,
        ],
        [
            'Procuraduría General de la Nación',
            'disciplinario',
            'Carrera 5 n.º 15-80, piso 18, Bogotá D.C.',
            '+57 601 587 8750',
            'patrimoniotransparenciaintegridad@procuraduria.gov.co',
            'https://www.procuraduria.gov.co',
            'Vigilancia de la conducta de los servidores públicos y verificación anual del Índice de '
                . 'Transparencia y Acceso a la Información (ITA).',
            1,
        ],
        [
            'Contraloría General de la República',
            'fiscal',
            null,
            null,
            null,
            'https://www.contraloria.gov.co',
            'Vigilancia del manejo de los recursos públicos y de la gestión fiscal de la entidad.',
            2,
        ],
        [
            'Contraloría General del Departamento del Cesar',
            'fiscal',
            null,
            null,
            null,
            null,
            'Control fiscal territorial sobre los recursos que administra la E.S.E.',
            3,
        ],
        [
            'Ministerio de Salud y Protección Social',
            'regulatorio',
            null,
            null,
            null,
            'https://www.minsalud.gov.co',
            'Fija la política y la reglamentación del sector salud que la entidad debe cumplir.',
            4,
        ],
        [
            'Secretaría de Salud Departamental del Cesar',
            'administrativo',
            null,
            null,
            null,
            null,
            'Inspección, vigilancia y control en el ámbito departamental, y habilitación de servicios.',
            5,
        ],
        [
            'Archivo General de la Nación',
            'regulatorio',
            null,
            null,
            null,
            'https://www.archivogeneral.gov.co',
            'Regula la gestión documental y convalida las Tablas de Retención Documental de la entidad.',
            6,
        ],
        [
            'Veedurías ciudadanas y Asociación de Usuarios',
            'social',
            null,
            null,
            null,
            null,
            'La ciudadanía ejerce control social sobre la entidad a través de las veedurías y de la '
                . 'Asociación de Usuarios. Consulta cómo participar en el menú Participa.',
            7,
        ],
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
        private readonly EnteVigilanciaRepository $entes,
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

        foreach (self::ENLACES as [$codigoSeccion, $url, $titulo, $descripcion]) {
            $creadas += $this->asegurarEnlace($codigoSeccion, $url, $titulo, $descripcion);
        }

        // ITA 3.13: entes y autoridades que vigilan.
        foreach (self::ENTES as [$nombre, $tipo, $direccion, $telefono, $correo, $url, $mecanismos, $orden]) {
            $creadas += (int) $this->entes->crearSiNoExiste($nombre, [
                'tipo_control' => $tipo,
                'direccion' => $direccion,
                'telefono' => $telefono,
                'correo' => $correo,
                'url' => $url,
                'mecanismos' => $mecanismos,
                'orden' => $orden,
            ]);
        }

        // Los entes se publican en su propia pantalla; el ítem 1.15 del menú
        // la enlaza para que el evaluador llegue por donde busca.
        $creadas += $this->asegurarEnlace(
            '1.15',
            '/transparencia/entes-de-control',
            'Entes y autoridades que vigilan a la entidad',
            'Autoridades de inspección, vigilancia y control, con sus datos de contacto y el tipo de control que ejercen.',
        );

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
     * Crea el ítem si su sección existe y la URL todavía no está. Devuelve
     * cuántos registros creó (0 o 1) para el conteo del comando.
     */
    private function asegurarEnlace(string $codigoSeccion, string $url, string $titulo, string $descripcion): int
    {
        $seccionId = $this->conexion->fetchOne(
            'SELECT id FROM transparencia_secciones WHERE codigo = ?',
            [$codigoSeccion],
        );

        if ($seccionId === false) {
            return 0;
        }

        $yaExiste = $this->conexion->fetchOne(
            'SELECT id FROM transparencia_items WHERE seccion_id = ? AND url_externa = ?',
            [(int) $seccionId, $url],
        );

        if ($yaExiste !== false) {
            return 0;
        }

        $this->conexion->insert('transparencia_items', [
            'seccion_id' => (int) $seccionId,
            'url_externa' => $url,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'orden' => 0,
        ]);

        return 1;
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
