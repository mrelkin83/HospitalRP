<?php

declare(strict_types=1);

namespace App\Modules\Indicadores\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Catálogo de indicadores y encuesta de satisfacción, listos para llenar.
 *
 * Siembra el ESQUELETO, no las cifras: cada indicador entra con valor 0 y
 * **sin publicar**. El valor lo carga un funcionario en Panel → Indicadores
 * con el dato real del periodo, y ahí decide publicarlo.
 *
 * Es deliberado. Una cifra de ocupación hospitalaria inventada por la consola
 * es exactamente el problema que se corrigió el 2026-09-14, cuando la portada
 * anunciaba un «98.4 % de satisfacción del paciente» que no respaldaba nadie.
 * Mientras no haya un valor publicado, la sección de la portada no se pinta:
 * ninguna cifra es mejor que una cifra falsa.
 *
 * Lo mismo con la encuesta: se crea en borrador, con las preguntas armadas,
 * para que Participación Ciudadana la revise y la abra cuando quiera.
 *
 * Idempotente por slug.
 */
final class SembrarIndicadoresCommand extends Command
{
    /**
     * grupo => lista de [nombre, unidad, meta, fuente, destacado]
     *
     * @var array<string, list<array{string, string, float|null, string, bool}>>
     */
    private const array INDICADORES = [
        'contratacion' => [
            ['Contratos suscritos en el periodo', 'contratos', null, 'Oficina Asesora Jurídica y de Contratación', false],
            ['Valor total contratado', 'millones de pesos', null, 'Oficina Asesora Jurídica y de Contratación', false],
            ['Procesos publicados en el SECOP', 'procesos', null, 'Oficina Asesora Jurídica y de Contratación', false],
        ],
        'ocupacion' => [
            ['Porcentaje de ocupación de camas', '%', null, 'Oficina de Planeación y Sistemas de Información', true],
            ['Promedio día estancia', 'días', null, 'Oficina de Planeación y Sistemas de Información', false],
            ['Giro cama', 'pacientes por cama', null, 'Oficina de Planeación y Sistemas de Información', false],
        ],
        'vitales' => [
            ['Egresos hospitalarios', 'egresos', null, 'Oficina de Estadística (RIPS)', true],
            ['Nacidos vivos atendidos', 'nacimientos', null, 'Oficina de Estadística (RIPS)', false],
            ['Consultas de urgencias atendidas', 'consultas', null, 'Oficina de Estadística (RIPS)', false],
        ],
    ];

    private const string ENCUESTA_SLUG = 'encuesta-de-satisfaccion-del-usuario';

    /** @var list<array{string, string, list<string>|null, bool}> enunciado, tipo, opciones, obligatoria */
    private const array PREGUNTAS = [
        ['¿Cómo califica la atención que recibió en el hospital?', 'escala', null, true],
        ['¿El tiempo de espera para ser atendido le pareció razonable?', 'opcion_unica', ['Sí', 'No', 'Parcialmente'], true],
        ['¿Recomendaría nuestros servicios a un familiar o conocido?', 'opcion_unica', ['Sí', 'No'], true],
        ['¿Qué podríamos mejorar?', 'texto', null, false],
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
            ->setName('semillas:indicadores')
            ->setDescription('Crea el catálogo de indicadores (sin cifras, sin publicar) y la encuesta de satisfacción en borrador')
            ->addOption('listar', null, InputOption::VALUE_NONE, 'Solo muestra qué haría, sin escribir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simulacion = (bool) $input->getOption('listar');

        // El periodo por omisión es el mes pasado: el mes en curso todavía no
        // tiene cifras cerradas que reportar.
        $periodo = date('Y-m-01', (int) strtotime('first day of last month'));
        $creados = 0;

        foreach (self::INDICADORES as $grupo => $indicadores) {
            foreach ($indicadores as $orden => [$nombre, $unidad, $meta, $fuente, $destacado]) {
                $slug = Slugs::desde($nombre);

                if ($this->conexion->fetchOne('SELECT id FROM indicadores WHERE slug = ?', [$slug]) !== false) {
                    continue;
                }

                if ($simulacion) {
                    $io->writeln(sprintf('  %-14s %s', $grupo, $nombre));
                    $creados++;

                    continue;
                }

                $this->conexion->insert('indicadores', [
                    'grupo' => $grupo,
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'periodo' => $periodo,
                    'valor' => 0,
                    'unidad' => $unidad,
                    'meta' => $meta,
                    'fuente' => $fuente,
                    'orden' => $orden + 1,
                    // Sin publicar: el dato todavía no existe.
                    'publicado' => 0,
                    'destacado' => $destacado ? 1 : 0,
                ]);

                $creados++;
            }
        }

        $encuesta = $this->sembrarEncuesta($simulacion);

        if (($creados > 0 || $encuesta) && !$simulacion) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'indicadores',
                descripcion: sprintf(
                    'Semillas de indicadores: %d indicadores sin publicar%s',
                    $creados,
                    $encuesta ? ' y la encuesta de satisfacción en borrador' : '',
                ),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d indicadores creados para el periodo %s.', $creados, $periodo));

        if ($encuesta) {
            $io->writeln('<info>Encuesta de satisfacción creada en borrador, con 4 preguntas.</info>');
        }

        $io->note([
            'Los indicadores entran con valor 0 y SIN publicar: la consola no inventa cifras del hospital.',
            'En Panel → Indicadores carga el valor del periodo y marca «Publicado». Al publicar el primero,',
            'la sección «Así va la entidad» aparece sola en la portada y en /estadisticas.',
            'La encuesta se activa desde Panel → Encuestas cambiando su estado a «abierta».',
        ]);

        return Command::SUCCESS;
    }

    private function sembrarEncuesta(bool $simulacion): bool
    {
        if ($this->conexion->fetchOne('SELECT id FROM encuestas WHERE slug = ?', [self::ENCUESTA_SLUG]) !== false) {
            return false;
        }

        if ($simulacion) {
            return true;
        }

        $this->conexion->insert('encuestas', [
            'titulo' => 'Encuesta de satisfacción del usuario',
            'slug' => self::ENCUESTA_SLUG,
            'descripcion' => 'Tu opinión nos ayuda a mejorar la atención. La encuesta es anónima y '
                . 'toma menos de dos minutos.',
            'estado' => 'borrador',
        ]);

        $encuestaId = (int) $this->conexion->lastInsertId();

        foreach (self::PREGUNTAS as $orden => [$enunciado, $tipo, $opciones, $obligatoria]) {
            $this->conexion->insert('encuesta_preguntas', [
                'encuesta_id' => $encuestaId,
                'enunciado' => $enunciado,
                'tipo' => $tipo,
                'opciones' => $opciones === null ? null : json_encode($opciones, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'obligatoria' => $obligatoria ? 1 : 0,
                'orden' => $orden + 1,
            ]);
        }

        return true;
    }
}
