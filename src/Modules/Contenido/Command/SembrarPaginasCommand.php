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
     * Contenido migrado del Joomla, generado fuera de banda (ver el docblock
     * de ese archivo). Va aparte de PAGINAS porque son 47 páginas de texto
     * del hospital: embutirlas en una constante haría ilegible esta clase.
     */
    private const string ARCHIVO_MIGRADO = __DIR__ . '/../../../../database/seeders/contenido-joomla.php';

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

        // Índices de sección: son navegación, no afirmaciones institucionales,
        // así que se publican aunque el hospital no haya redactado nada.
        '/programas' => [
            'Programas',
            'Programas asistenciales y de promoción de la E.S.E.',
            '<p>Programas asistenciales, de promoción y de humanización de la E.S.E. Hospital '
                . 'Rosario Pumarejo de López.</p>'
                . '<ul>'
                . '<li><a href="/programas/iamii">IAMII — Institución Amiga de la Mujer y la Infancia</a></li>'
                . '<li><a href="/programas/iamii/banco-de-leche">Banco de leche humana</a></li>'
                . '<li><a href="/programas/iamii/madre-canguro">Programa Madre Canguro</a></li>'
                . '<li><a href="/programas/humanizacion">Programa de humanización</a></li>'
                . '<li><a href="/programas/docencia">Docencia, servicio e investigación</a></li>'
                . '<li><a href="/programas/etnocultural">Espacio etnocultural</a></li>'
                . '<li><a href="/programas/banco-de-sangre">Banco de sangre</a></li>'
                . '</ul>',
            self::PUBLICADA,
            0,
        ],
        '/talento-humano' => [
            'Talento humano',
            'Manual de funciones, asignaciones salariales, capacitación y bienestar.',
            '<p>Información de gestión del talento humano de la entidad.</p>'
                . '<ul>'
                . '<li><a href="/convocatorias">Convocatorias y ofertas de empleo</a></li>'
                . '<li><a href="/talento-humano/manual-de-funciones">Manual de funciones</a></li>'
                . '<li><a href="/talento-humano/asignaciones-salariales">Asignaciones salariales</a></li>'
                . '<li><a href="/talento-humano/evaluacion-del-desempeno">Evaluación del desempeño</a></li>'
                . '<li><a href="/talento-humano/plan-de-capacitacion">Plan institucional de capacitación</a></li>'
                . '<li><a href="/talento-humano/bienestar-social">Bienestar social</a></li>'
                . '<li><a href="/talento-humano/reglamento-interno">Reglamento interno de trabajo</a></li>'
                . '<li><a href="/talento-humano/induccion">Inducción y reinducción</a></li>'
                . '<li><a href="/talento-humano/formatos">Formatos de talento humano</a></li>'
                . '</ul>',
            self::PUBLICADA,
            0,
        ],
        '/gestion' => [
            'Gestión institucional',
            'MECI, MIPG, control disciplinario y conflicto de intereses.',
            '<p>Instrumentos de control interno y gestión de la entidad.</p>'
                . '<ul>'
                . '<li><a href="/gestion/meci">MECI — Modelo Estándar de Control Interno</a></li>'
                . '<li><a href="/gestion/mipg">MIPG — Modelo Integrado de Planeación y Gestión</a></li>'
                . '<li><a href="/gestion/control-disciplinario">Control disciplinario interno</a></li>'
                . '<li><a href="/gestion/conflicto-de-intereses">Conflicto de intereses</a></li>'
                . '<li><a href="/gestion/conciliaciones">Conciliaciones</a></li>'
                . '<li><a href="/gestion/programas-y-proyectos">Programas y proyectos de inversión</a></li>'
                . '</ul>',
            self::PUBLICADA,
            0,
        ],
        // Las cinco páginas que el Joomla enlaza desde su menú pero publica
        // sin texto aprovechable. Se afirma solo lo verificable —que el
        // programa existe, porque está en el menú del propio hospital— y se
        // encamina al ciudadano a un canal real. Publicarlas así es mejor que
        // dejar el ítem de menú en 404, y el panel las completa sin código.
        '/programas/iamii/banco-de-leche' => [
            'Banco de leche humana',
            'Banco de leche humana de la E.S.E. Hospital Rosario Pumarejo de López.',
            '<p>El Banco de Leche Humana hace parte de la estrategia '
                . '<a href="/programas/iamii">IAMII</a> de la E.S.E. Hospital Rosario Pumarejo '
                . 'de López.</p>'
                . '<p>Si quieres donar leche materna o necesitas orientación sobre lactancia, '
                . 'comunícate con el hospital por los <a href="/contacto">canales de atención</a>.</p>'
                . '<p>Estamos ampliando la información de esta página.</p>',
            self::PUBLICADA,
            0,
        ],
        '/programas/iamii/madre-canguro' => [
            'Programa Madre Canguro',
            'Programa Madre Canguro de la E.S.E. Hospital Rosario Pumarejo de López.',
            '<p>El Programa Madre Canguro acompaña a las familias de recién nacidos '
                . 'prematuros o de bajo peso, y hace parte de la estrategia '
                . '<a href="/programas/iamii">IAMII</a>.</p>'
                . '<p>Para conocer horarios y requisitos de ingreso al programa, consulta los '
                . '<a href="/contacto">canales de atención</a> del hospital.</p>'
                . '<p>Estamos ampliando la información de esta página.</p>',
            self::PUBLICADA,
            0,
        ],
        '/programas/banco-de-sangre' => [
            'Banco de sangre',
            'Banco de sangre de la E.S.E. Hospital Rosario Pumarejo de López.',
            '<p>El Banco de Sangre de la E.S.E. Hospital Rosario Pumarejo de López recibe '
                . 'donantes voluntarios.</p>'
                . '<p>Para conocer horarios, requisitos y jornadas de donación, consulta el '
                . '<a href="/eventos">calendario de eventos</a> o los '
                . '<a href="/contacto">canales de atención</a>.</p>'
                . '<p>Estamos ampliando la información de esta página.</p>',
            self::PUBLICADA,
            0,
        ],
        '/programas/etnocultural' => [
            'Espacio etnocultural',
            'Espacio etnocultural de la E.S.E. Hospital Rosario Pumarejo de López.',
            '<p>El espacio etnocultural reconoce la diversidad de las comunidades que atiende '
                . 'la E.S.E. Hospital Rosario Pumarejo de López en el departamento del Cesar.</p>'
                . '<p>Si perteneces a una comunidad étnica y necesitas orientación sobre la '
                . 'atención en salud, escríbenos por los <a href="/contacto">canales de '
                . 'atención</a> o radica una <a href="/atencion-ciudadania/pqrsdf">PQRSDF</a>.</p>'
                . '<p>Estamos ampliando la información de esta página.</p>',
            self::PUBLICADA,
            0,
        ],
        '/atencion-ciudadania/deberes-y-derechos' => [
            'Deberes y derechos de los usuarios',
            'Deberes y derechos de los usuarios de la E.S.E.',
            '<p>Los deberes y derechos de los usuarios del Sistema General de Seguridad Social '
                . 'en Salud están definidos en la <strong>Ley 1751 de 2015</strong> (ley '
                . 'estatutaria de salud) y en la <strong>Resolución 13437 de 1991</strong> del '
                . 'entonces Ministerio de Salud, que adopta el decálogo de los derechos de los '
                . 'pacientes.</p>'
                . '<p>Si consideras que alguno de tus derechos no se respetó, puedes '
                . '<a href="/atencion-ciudadania/pqrsdf">radicar una PQRSDF</a>. También puedes '
                . 'acudir a la <a href="/participa">asociación de usuarios</a>.</p>'
                . '<p>Estamos publicando la versión institucional completa de este documento.</p>',
            self::PUBLICADA,
            0,
        ],

        '/programas/iamii/pasos' => [
            'Los diez pasos IAMII',
            'Los diez pasos de la estrategia Institución Amiga de la Mujer y la Infancia.',
            '<p>La estrategia IAMII se despliega en diez pasos.</p>'
                . '<ul>'
                . '<li><a href="/programas/iamii/pasos/paso-1">Paso 1</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-2">Paso 2</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-3">Paso 3</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-4">Paso 4</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-5">Paso 5</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-6">Paso 6</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-7">Paso 7</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-8">Paso 8</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-9">Paso 9</a></li>'
                . '<li><a href="/programas/iamii/pasos/paso-10">Paso 10</a></li>'
                . '</ul>',
            self::PUBLICADA,
            0,
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

        $rellenadas = 0;

        $categorias = $this->sembrarCategorias();

        foreach ($this->paginasASembrar() as $ruta => [$titulo, $resumen, $contenido, $estado, $protegida]) {
            $existente = $this->conexion->fetchAssociative(
                'SELECT id, contenido, estado_aprobacion FROM paginas WHERE ruta = ?',
                [$ruta],
            );

            if ($existente !== false) {
                // Una corrida anterior pudo dejar la página en borrador vacío
                // porque aún no existía el contenido migrado. Rellenarla NO es
                // pisar trabajo ajeno: solo se toca si sigue sin texto y en
                // borrador. Lo que el hospital haya escrito queda intacto.
                if (
                    $contenido !== ''
                    && !$simulacion
                    && $existente['estado_aprobacion'] === self::BORRADOR
                    && ($existente['contenido'] === null || trim((string) $existente['contenido']) === '')
                ) {
                    $this->conexion->update('paginas', [
                        'contenido' => $contenido,
                        'estado_aprobacion' => $estado,
                        'aprobado_en' => $estado === self::PUBLICADA ? date('Y-m-d H:i:s') : null,
                    ], ['id' => (int) $existente['id']]);
                    $rellenadas++;
                }

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

        if (($creadas > 0 || $rellenadas > 0) && !$simulacion) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'paginas',
                descripcion: sprintf(
                    'Semillas de páginas institucionales: %d creadas (%d publicadas), %d borradores vacíos rellenados',
                    $creadas,
                    $publicadas,
                    $rellenadas,
                ),
                usuarioNombre: 'consola',
            ));
        }

        if ($categorias > 0) {
            $io->writeln(sprintf('<info>%d categorías de noticia creadas.</info>', $categorias));
        }

        $io->success(sprintf(
            '%d páginas creadas (%d publicadas), %d borradores vacíos rellenados, %d ya existían con contenido.',
            $creadas,
            $publicadas,
            $rellenadas,
            count($this->paginasASembrar()) - $creadas - $rellenadas,
        ));

        return Command::SUCCESS;
    }

    /**
     * Las páginas escritas a mano más el contenido migrado del Joomla.
     *
     * El contenido migrado manda sobre la versión escrita a mano de la misma
     * ruta: es el texto real del hospital, no un resumen del asistente. Una
     * página migrada sin texto aprovechable cae a borrador, por la misma
     * razón que el resto: al aire solo sale lo que tiene contenido.
     *
     * @return array<string, array{string, string, string, string, int}>
     */
    private function paginasASembrar(): array
    {
        $paginas = self::PAGINAS;

        if (!is_file(self::ARCHIVO_MIGRADO)) {
            return $paginas;
        }

        /** @var array<string, array{string, string}> $migradas */
        $migradas = require self::ARCHIVO_MIGRADO;

        foreach ($migradas as $ruta => [$titulo, $html]) {
            // Si el Joomla no trajo texto, NO se pisa lo escrito a mano: si
            // no, una página con reseña propia caería a borrador y su ítem de
            // menú respondería 404, que es justo el defecto que esta fase
            // vino a cerrar (docs/14 §5.1).
            if ($html === '' && ($paginas[$ruta][2] ?? '') !== '') {
                continue;
            }

            $paginas[$ruta] = [
                $titulo,
                $paginas[$ruta][1] ?? $titulo . ' de la E.S.E. Hospital Rosario Pumarejo de López.',
                $html,
                $html === '' ? self::BORRADOR : self::PUBLICADA,
                $paginas[$ruta][4] ?? 0,
            ];
        }

        return $paginas;
    }

    /**
     * Categorías de noticia que en el Joomla eran secciones propias del menú
     * de Prensa. Aquí son categorías porque son lo mismo —una publicación con
     * fecha— y así el hospital las administra desde el módulo de noticias en
     * lugar de pedir una plantilla nueva por cada una.
     *
     * @var array<string, string> slug => nombre
     */
    private const array CATEGORIAS_NOTICIA = [
        'boletines-de-prensa' => 'Boletines de prensa',
        'comunicados' => 'Comunicados oficiales',
    ];

    /**
     * La Revista Conexión Salud es un PDF periódico con portada y fecha, o
     * sea un documento. Va como categoría documental y no como módulo propio:
     * la gestión documental ya le da metadatos, vigencia, versiones y TRD, que
     * es más de lo que tenía en el Joomla.
     *
     * @var array<string, string> slug => nombre
     */
    private const array CATEGORIAS_DOCUMENTO = [
        'revista-conexion-salud' => 'Revista Conexión Salud',
    ];

    /** @return int categorías creadas */
    private function sembrarCategorias(): int
    {
        $creadas = 0;
        $orden = 0;

        $porTipo = [
            'noticia' => self::CATEGORIAS_NOTICIA,
            'documento' => self::CATEGORIAS_DOCUMENTO,
        ];

        foreach ($porTipo as $tipo => $categorias) {
        foreach ($categorias as $slug => $nombre) {
            $existe = $this->conexion->fetchOne(
                'SELECT id FROM categorias WHERE tipo = ? AND slug = ?',
                [$tipo, $slug],
            );

            if ($existe !== false) {
                $orden++;

                continue;
            }

            $this->conexion->insert('categorias', [
                'tipo' => $tipo,
                'nombre' => $nombre,
                'slug' => $slug,
                'orden' => $orden++,
            ]);
            $creadas++;
        }
        }

        return $creadas;
    }

    /** El slug es el último segmento de la ruta; `/el-hospital` → `el-hospital`. */
    private function slugDe(string $ruta): string
    {
        $segmentos = explode('/', trim($ruta, '/'));

        return (string) end($segmentos);
    }
}
