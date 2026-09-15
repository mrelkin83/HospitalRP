<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Semilla de los tipos de trámite de v1 con sus estados y campos, y
 * de una política de tratamiento de datos PROVISIONAL (la vigente la carga
 * la oficina jurídica; sin política activa no hay casilla de autorización).
 *
 * ⚠️ TÉRMINOS PROVISIONALES según la Ley 1755 de 2015 (peticiones) y la
 * Ley 1581 de 2012 (habeas data). La oficina jurídica los valida y ajusta
 * desde el panel; el fundamento legal de cada tipo lo deja explícito.
 *
 * Idempotente por slug de tipo y por (tipo, versión) de la política.
 */
final class SembrarTramitesCommand extends Command
{
    /**
     * slug => [nombre, descripcion, dias, fundamento, anonimo, adjuntos, campos]
     * donde adjuntos = [máximo de archivos, ¿es obligatorio adjuntar?]
     * y campos = [codigo => [etiqueta, tipo_campo, requerido, opciones|null, ayuda|null]]
     *
     * @var array<string, array{string, string, int, string, int, array{int, bool}, array<string, array{string, string, int, ?list<string>, ?string}>}>
     */
    private const array TIPOS = [
        'pqrsdf' => [
            'PQRSDF',
            'Peticiones, quejas, reclamos, sugerencias, denuncias y felicitaciones.',
            15,
            'Ley 1755 de 2015, art. 14 — TÉRMINO PROVISIONAL, pendiente validación de la oficina jurídica',
            1,
            [3, false],
            [
                'tipo_solicitud' => ['¿Qué deseas presentar?', 'select', 1,
                    ['Petición', 'Queja', 'Reclamo', 'Sugerencia', 'Denuncia', 'Felicitación'], null],
            ],
        ],
        'solicitud-de-cita' => [
            'Solicitud de cita',
            'Solicita tu cita médica. Te contactaremos para confirmar fecha y hora.',
            5,
            'Término interno de servicio — PROVISIONAL, pendiente validación de la oficina jurídica',
            0,
            [3, false],
            [
                'especialidad' => ['Especialidad o servicio que necesitas', 'texto', 1, null, 'Por ejemplo: medicina general, pediatría, laboratorio'],
                'jornada' => ['Jornada de preferencia', 'select', 0, ['Mañana', 'Tarde', 'Cualquiera'], null],
            ],
        ],
        'solicitud-de-historia-clinica' => [
            'Solicitud de historia clínica',
            'Radica la solicitud de copia de tu historia clínica. La entrega se hace por canal controlado verificando tu identidad; nunca por este sitio.',
            10,
            'Ley 1755 de 2015, art. 14.1 (documentos) y Ley 23 de 1981 — PROVISIONAL, pendiente validación jurídica',
            0,
            [3, false],
            [
                'parentesco' => ['¿Quién solicita?', 'select', 1,
                    ['Titular (yo mismo)', 'Padre o madre de menor', 'Representante legal', 'Familiar de persona fallecida'], null],
                'rango_fechas' => ['Periodo de atención que necesitas', 'texto', 0, null, 'Por ejemplo: enero a marzo de 2026'],
            ],
        ],
        'solicitud-de-certificados' => [
            'Solicitud de certificados',
            'Certificados de atención y otras constancias.',
            10,
            'Ley 1755 de 2015, art. 14.1 — PROVISIONAL, pendiente validación de la oficina jurídica',
            0,
            [3, false],
            [
                'tipo_certificado' => ['Tipo de certificado', 'select', 1,
                    ['Certificado de atención', 'Constancia de hospitalización', 'Otro'], null],
            ],
        ],
        'habeas-data' => [
            'Protección de datos personales (habeas data)',
            'Consulta, actualiza, corrige o solicita la supresión de tus datos personales, o revoca tu autorización.',
            15,
            'Ley 1581 de 2012, arts. 14 y 15 — PROVISIONAL, pendiente validación de la oficina jurídica',
            0,
            [3, false],
            [
                'tipo_solicitud' => ['¿Qué necesitas?', 'select', 1,
                    ['Consultar mis datos', 'Actualizar mis datos', 'Corregir mis datos', 'Suprimir mis datos', 'Revocar mi autorización'], null],
            ],
        ],
        // --- Trabajadores y contratistas de la entidad ---
        // Van por el mismo canal público de trámites y NO por un portal con
        // login: el portal del colaborador está fuera de v1 (CLAUDE.md §7) y
        // la intranet se retiró por decisión del hospital el 2026-09-14.
        // Radicar con cédula y recibir por correo cubre la necesidad sin
        // sostener un sistema de cuentas.
        'certificado-laboral' => [
            'Certificado laboral',
            'Solicita tu certificado laboral o de contrato. Lo reciben trabajadores de planta y contratistas de la entidad.',
            10,
            'Ley 1755 de 2015, art. 14.1 — PROVISIONAL, pendiente validación de la oficina jurídica',
            0,
            [3, false],
            [
                'vinculacion' => ['Tipo de vinculación', 'select', 1,
                    ['Planta', 'Contrato de prestación de servicios', 'Ya no trabajo en la entidad'], null],
                'destino' => ['¿Para qué lo necesitas?', 'texto', 0, null,
                    'Por ejemplo: trámite bancario, visa, otra entidad. Ayuda a redactar el certificado.'],
                'con_salario' => ['¿Debe incluir el valor del salario u honorarios?', 'select', 1,
                    ['Sí', 'No'], null],
            ],
        ],
        'radicacion-de-cuentas-de-cobro' => [
            'Radicación de cuentas de cobro',
            'Radica tu cuenta de cobro como contratista de la entidad. Adjunta la cuenta y los soportes del periodo.',
            15,
            'Término interno de servicio — PROVISIONAL, pendiente validación de la oficina jurídica',
            0,
            // Sin adjunto no hay cuenta que radicar: el formulario los exige.
            [5, true],
            [
                'numero_contrato' => ['Número del contrato', 'texto', 1, null, 'Tal como aparece en el contrato'],
                'periodo_cobrado' => ['Periodo que cobras', 'texto', 1, null, 'Por ejemplo: del 1 al 30 de septiembre de 2026'],
                'valor' => ['Valor de la cuenta de cobro', 'texto', 1, null, 'En pesos, sin puntos ni comas'],
            ],
        ],
    ];

    private const array ESTADOS = [
        // codigo => [nombre, descripcion_publica, color, inicial, final, notifica]
        'recibido' => ['Recibido', 'Tu solicitud fue radicada y está en fila para revisión.', '#0030C9', 1, 0, 1],
        'en_gestion' => ['En gestión', 'Un funcionario está gestionando tu solicitud.', '#92400E', 0, 0, 1],
        'respondido' => ['Respondido', 'Tu solicitud fue respondida por el medio que elegiste.', '#166B4C', 0, 0, 1],
        'cerrado' => ['Cerrado', 'La solicitud quedó cerrada.', '#4B5563', 0, 1, 0],
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
            ->setName('semillas:tramites')
            ->setDescription('Siembra la política provisional y los tipos de trámite de v1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creados = 0;

        // Política de tratamiento de datos, versión provisional.
        $politica = $this->conexion->fetchOne(
            "SELECT id FROM politicas WHERE tipo = 'tratamiento_datos' AND version = 'provisional-1'",
        );

        if ($politica === false) {
            $this->conexion->insert('politicas', [
                'tipo' => 'tratamiento_datos',
                'version' => 'provisional-1',
                'titulo' => 'Política de tratamiento de datos personales (PROVISIONAL)',
                'contenido' => '<p>La E.S.E. Hospital Rosario Pumarejo de López trata los datos personales '
                    . 'que recolecta a través de este sitio únicamente para gestionar y responder las '
                    . 'solicitudes radicadas, conforme a la Ley 1581 de 2012. Los datos no se comparten '
                    . 'con terceros salvo obligación legal, y puedes ejercer tus derechos de consulta, '
                    . 'actualización, corrección y supresión mediante el trámite de habeas data.</p>'
                    . '<p><strong>Versión provisional:</strong> este texto rige mientras la oficina '
                    . 'jurídica adopta la política definitiva.</p>',
                'vigente_desde' => date('Y-m-d'),
                'activa' => 1,
            ]);
            $creados++;
        }

        $orden = 0;

        foreach (self::TIPOS as $slug => [$nombre, $descripcion, $dias, $fundamento, $anonimo, [$maxAdjuntos, $adjuntoObligatorio], $campos]) {
            if (false !== $this->conexion->fetchOne('SELECT id FROM tipos_tramite WHERE slug = ?', [$slug])) {
                $orden++;
                continue;
            }

            $this->conexion->insert('tipos_tramite', [
                'nombre' => $nombre,
                'slug' => $slug,
                'descripcion' => $descripcion,
                'prefijo_radicado' => 'HRPL',
                'dias_termino' => $dias,
                'tipo_dias' => 'habiles',
                'fundamento_legal' => $fundamento,
                'permite_anonimo' => $anonimo,
                'requiere_adjunto' => $adjuntoObligatorio ? 1 : 0,
                'max_adjuntos' => $maxAdjuntos,
                'orden' => $orden++,
            ]);
            $tipoId = (int) $this->conexion->lastInsertId();
            $creados++;

            $ordenEstado = 0;

            foreach (self::ESTADOS as $codigo => [$nombreEstado, $descripcionPublica, $color, $inicial, $final, $notifica]) {
                $this->conexion->insert('tramite_estados', [
                    'tipo_tramite_id' => $tipoId,
                    'codigo' => $codigo,
                    'nombre' => $nombreEstado,
                    'descripcion_publica' => $descripcionPublica,
                    'color' => $color,
                    'orden' => $ordenEstado++,
                    'es_inicial' => $inicial,
                    'es_final' => $final,
                    'notifica_ciudadano' => $notifica,
                ]);
            }

            $ordenCampo = 0;

            foreach ($campos as $codigo => [$etiqueta, $tipoCampo, $requerido, $opciones, $ayuda]) {
                $this->conexion->insert('tramite_campos', [
                    'tipo_tramite_id' => $tipoId,
                    'codigo' => $codigo,
                    'etiqueta' => $etiqueta,
                    'ayuda' => $ayuda,
                    'tipo_campo' => $tipoCampo,
                    'opciones' => $opciones !== null ? json_encode($opciones, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
                    'requerido' => $requerido,
                    'orden' => $ordenCampo++,
                ]);
            }
        }

        if ($creados > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'tipos_tramite',
                descripcion: sprintf('Semilla de trámites: %d registros con términos PROVISIONALES (Ley 1755)', $creados),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d registros creados.', $creados));
        $io->warning('Términos PROVISIONALES: la oficina jurídica debe validarlos (se ajustan desde el panel).');

        return Command::SUCCESS;
    }
}
