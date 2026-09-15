<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Storage\AlmacenArchivos;
use App\Modules\Contenido\Service\GeneradorPortadas;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Portadas de relleno para las noticias, campañas y comunicados que no tengan.
 *
 * El listado de noticias y las tarjetas de campañas solo pintan la imagen si
 * hay `imagen_id`; sin ella la tarjeta arranca directamente en el titular y la
 * sección entera queda como una lista de enlaces. Once de las doce piezas
 * sembradas estaban así.
 *
 * Lo que se pone es relleno declarado, no una foto que aparente ser del
 * hospital: un degradado de marca con el pictograma del tema (ver
 * {@see GeneradorPortadas}). En cuanto Comunicaciones tenga fotos propias se
 * suben desde Panel → Medios y se reemplazan una a una.
 *
 * Idempotente: solo toca filas con `imagen_id` nulo. Volver a ejecutarlo
 * después de que alguien haya puesto su foto no la pisa.
 */
#[AsCommand(
    name: 'semillas:imagenes',
    description: 'Pone portadas de relleno a las noticias, campañas y comunicados que no tengan imagen',
)]
final class SembrarImagenesCommand extends Command
{
    /**
     * Motivo por slug de las piezas que siembran los demás comandos.
     *
     * Va primero el slug porque es lo único estable: el título lo reescribe
     * Comunicaciones cuando quiere; el slug no cambia una vez publicado,
     * porque rompería las URLs.
     *
     * @var array<string, string>
     */
    private const array POR_SLUG = [
        // Noticias
        'jornada-de-prevencion-cardiovascular' => 'corazon',
        'nueva-sala-de-procedimientos-menores' => 'quirofano',
        'jornada-de-donacion-de-sangre' => 'sangre',
        'actualizacion-horarios-consulta-externa' => 'reloj',
        'capacitacion-seguridad-del-paciente' => 'seguridad',
        'resultados-encuesta-satisfaccion-trimestre' => 'encuesta',
        // Campañas y comunicados
        'vacunacion-gratuita-del-programa-ampliado-de-inmunizaciones' => 'vacuna',
        'control-del-riesgo-cardiovascular-hipertension-y-diabetes' => 'corazon',
        'acompanamiento-a-la-lactancia-materna' => 'lactancia',
        'convocatorias-de-talento-humano' => 'empleo',
        'plan-institucional-de-capacitacion' => 'capacitacion',
        'seguridad-y-salud-en-el-trabajo-reporta-los-incidentes' => 'seguridad',
    ];

    /**
     * Palabras del título => motivo, para lo que escriba el hospital después.
     *
     * Se recorre en orden y gana la primera que aparezca, así que lo más
     * específico va arriba: «donación» tiene que ganarle a «jornada».
     *
     * @var array<string, string>
     */
    private const array POR_PALABRA = [
        'vacun' => 'vacuna',
        'inmuniza' => 'vacuna',
        'sangre' => 'sangre',
        'donaci' => 'sangre',
        'cardiovascular' => 'corazon',
        'hipertensi' => 'corazon',
        'diabetes' => 'corazon',
        'lactancia' => 'lactancia',
        // «Sala amiga de la familia LACTANTE» se llevó la cruz de quirófano:
        // el mapa conocía el sustantivo y no el adjetivo, y como las claves se
        // buscan a principio de palabra, «lactancia» no alcanza a «lactante».
        'lactante' => 'lactancia',
        'materna' => 'lactancia',
        'gestante' => 'lactancia',
        'infancia' => 'lactancia',
        'quir' => 'quirofano',
        'cirug' => 'quirofano',
        'procedimiento' => 'quirofano',
        'urgencia' => 'quirofano',
        'horario' => 'reloj',
        'agenda' => 'reloj',
        'cita' => 'reloj',
        'capacita' => 'capacitacion',
        'formaci' => 'capacitacion',
        'inducci' => 'capacitacion',
        'convocatoria' => 'empleo',
        'talento humano' => 'empleo',
        'vacante' => 'empleo',
        'concurso' => 'empleo',
        'encuesta' => 'encuesta',
        'satisfacci' => 'encuesta',
        'indicador' => 'encuesta',
        'resultado' => 'encuesta',
        'seguridad' => 'seguridad',
        'riesgo' => 'seguridad',
        'incidente' => 'seguridad',
    ];

    /** Cuando ni el slug ni el título dicen nada: la cruz médica. */
    private const string MOTIVO_POR_DEFECTO = 'quirofano';

    /** @var array<string, string> tabla => etiqueta para el informe */
    private const array TABLAS = [
        'noticias' => 'Noticias',
        'campanas' => 'Campañas y comunicados',
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
        private readonly AlmacenArchivos $almacen,
        private readonly GeneradorPortadas $generador,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $entrada, OutputInterface $salida): int
    {
        $io = new SymfonyStyle($entrada, $salida);
        $io->title('Portadas de relleno');

        $puestas = 0;

        foreach (self::TABLAS as $tabla => $etiqueta) {
            $pendientes = $this->conexion->fetchAllAssociative(sprintf(
                'SELECT id, slug, titulo FROM %s WHERE eliminado_en IS NULL AND imagen_id IS NULL ORDER BY id',
                $tabla,
            ));

            if ($pendientes === []) {
                $io->text(sprintf('%s: nada pendiente.', $etiqueta));

                continue;
            }

            $io->section($etiqueta);

            foreach ($pendientes as $fila) {
                $motivo = $this->motivoDe((string) $fila['slug'], (string) $fila['titulo']);
                $archivoId = $this->crearPortada($motivo);

                $this->conexion->update($tabla, ['imagen_id' => $archivoId], ['id' => (int) $fila['id']]);
                $io->text(sprintf('  <info>%s</info> ← %s', $fila['slug'], $motivo));
                $puestas++;
            }
        }

        if ($puestas === 0) {
            $io->success('Todo el contenido ya tenía imagen. No se tocó nada.');

            return Command::SUCCESS;
        }

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Creacion,
            entidad: 'archivos',
            descripcion: sprintf('semillas:imagenes puso %d portadas de relleno', $puestas),
            usuarioNombre: 'consola',
        ));

        $io->success(sprintf('%d portadas puestas. Se reemplazan desde Panel → Medios.', $puestas));

        return Command::SUCCESS;
    }

    /**
     * Genera la portada y la registra en la biblioteca de medios.
     *
     * Pasa por `AlmacenArchivos` y no por un INSERT a mano para que el archivo
     * quede exactamente igual que uno subido desde el panel —misma ruta, mismo
     * hash, mismas dimensiones— y aparezca en Medios como cualquier otro.
     *
     * `texto_alt` se deja nulo a propósito: la plantilla lo vuelca en `alt` y
     * `alt=""` es lo correcto para una imagen decorativa. El titular que
     * acompaña a la tarjeta ya dice de qué va; que un lector de pantalla lea
     * además «degradado azul con un pictograma» solo estorba.
     */
    private function crearPortada(string $motivo): int
    {
        $temporal = sprintf('%s/portada-%s-%s.jpg', sys_get_temp_dir(), $motivo, bin2hex(random_bytes(6)));
        $this->generador->generar($motivo, $temporal);

        try {
            $archivo = $this->almacen->guardar(
                // `test: true` porque el archivo lo escribió este proceso y no
                // una subida HTTP: sin eso, `move()` exige `is_uploaded_file`.
                subido: new UploadedFile($temporal, sprintf('portada-%s.jpg', $motivo), 'image/jpeg', null, true),
                publico: true,
                creadoPor: null,
                textoAlt: null,
            );
        } finally {
            // `guardar()` mueve el archivo; si falló antes, el temporal sigue ahí.
            if (is_file($temporal)) {
                unlink($temporal);
            }
        }

        return $archivo->id;
    }

    private function motivoDe(string $slug, string $titulo): string
    {
        if (isset(self::POR_SLUG[$slug])) {
            return self::POR_SLUG[$slug];
        }

        $texto = mb_strtolower($titulo . ' ' . $slug);

        foreach (self::POR_PALABRA as $palabra => $motivo) {
            // Principio de palabra, no subcadena suelta. Con `str_contains`,
            // «Información general para la ciudadanía» se llevaba el birrete
            // de capacitación porque «in-formaci-ón» contiene «formaci».
            if (preg_match('/\b' . preg_quote($palabra, '/') . '/u', $texto) === 1) {
                return $motivo;
            }
        }

        return self::MOTIVO_POR_DEFECTO;
    }
}
