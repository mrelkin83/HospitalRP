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
 * Semillas de la tabla `configuracion`: crea las claves que el sitio público
 * necesita, con valores provisionales tomados de frontend.png.
 *
 * Idempotente y respetuosa: si la clave ya existe NO toca su valor — lo que
 * el hospital haya editado en el panel manda sobre cualquier semilla.
 */
final class SembrarConfiguracionCommand extends Command
{
    /**
     * clave => [valor, tipo, grupo, etiqueta, ayuda]
     *
     * @var array<string, array{string, string, string, string, ?string}>
     */
    private const array SEMILLAS = [
        'entidad.nombre' => [
            'E.S.E. Hospital Rosario Pumarejo de López',
            'texto', 'entidad', 'Nombre de la entidad', null,
        ],
        'entidad.eslogan' => [
            'Comprometidos con tu salud y bienestar',
            'texto', 'entidad', 'Eslogan', 'Aparece bajo el nombre en el encabezado',
        ],
        'entidad.nit' => [
            '892.300.445-1',
            'texto', 'entidad', 'NIT', null,
        ],
        'contacto.pbx' => [
            '(605) 5712339',
            'texto', 'contacto', 'PBX', null,
        ],
        'contacto.emergencias' => [
            '(605) 5712339',
            'texto', 'contacto', 'Teléfono de emergencias', 'Verificar extensión directa de urgencias',
        ],
        'contacto.call_center' => [
            '(+57) 304 3251875',
            'texto', 'contacto', 'Call center (citas)', 'También 311 4232493 y 311 4131075',
        ],
        'contacto.correo' => [
            'contacto@hrplopez.gov.co',
            'texto', 'contacto', 'Correo de atención al ciudadano', null,
        ],
        'contacto.correo_citas' => [
            'citasmedicas@hrplopez.gov.co',
            'texto', 'contacto', 'Correo de citas médicas', null,
        ],
        'contacto.correo_ventanilla' => [
            'ventanillaunica@hrplopez.gov.co',
            'texto', 'contacto', 'Ventanilla única (Ext. 3502)', null,
        ],
        'contacto.correo_notificaciones' => [
            'notificacionesjudiciales@hrplopez.gov.co',
            'texto', 'contacto', 'Correo de notificaciones judiciales', 'Obligatorio según la Res. 1519',
        ],
        'contacto.linea_etica_correo' => [
            'oficialcumplimiento@hrplopez.gov.co',
            'texto', 'contacto', 'Línea ética anticorrupción (correo)', null,
        ],
        'contacto.linea_etica_telefono' => [
            '(+57) 318 3546240',
            'texto', 'contacto', 'Línea ética anticorrupción (teléfono)', null,
        ],
        'contacto.direccion' => [
            'Calle 16 No. 17-141, Barrio Hernando Santana (Av. La Popa), Valledupar, Cesar',
            'texto', 'contacto', 'Dirección de la sede', null,
        ],
        'contacto.horario' => [
            'Lunes a viernes, 7:30 a. m. a 12:00 m. y 2:00 a 6:00 p. m.',
            'texto', 'contacto', 'Horario de atención administrativa', null,
        ],
        // --- Portada: el carrusel de campañas, administrable (2026-09-15) ---
        // Estaban en constantes de PHP: cambiar de 7 a 10 segundos exigía un
        // despliegue. Son decisiones de Comunicaciones, no de ingeniería.
        'portada.campanas_cantidad' => [
            '6',
            'numero', 'portada', 'Campañas por pestaña', 'Cuántas entran en el carrusel de la portada. Entre 1 y 12; el resto se ve en /campanas',
        ],
        'portada.campanas_segundos' => [
            '7',
            'numero', 'portada', 'Segundos por campaña', 'Cuánto dura cada una en pantalla antes de pasar sola. 0 apaga la rotación automática',
        ],
        'portada.campanas_orden' => [
            'recientes',
            'texto', 'portada', 'Orden de las campañas', 'Escribe «recientes» (las últimas publicadas primero) o «vencen_antes» (las que terminan pronto primero)',
        ],

        'portada.heroes_segundos' => [
            '8',
            'numero', 'portada', 'Segundos por héroe', 'Cuánto dura en pantalla cada diapositiva del héroe antes de pasar sola. 0 apaga la rotación. Los héroes se crean en Panel → Héroe de la portada',
        ],

        // --- Portada: lo que rodea al héroe (2026-09-15) ---
        // El TEXTO de cada héroe —píldora, título, párrafo y botones— NO está
        // aquí: vive en la tabla `heroes`, una fila por diapositiva, porque hay
        // varias y cada una dice lo suyo. Lo que queda en Ajustes es lo que es
        // único y no se repite por diapositiva: el buscador, la tarjeta
        // ilustrada y los dos avisos flotantes.
        'hero.buscador_leyenda' => [
            'Buscar trámites, médicos, noticias...',
            'texto', 'hero', 'Texto guía del buscador', 'Lo que se lee dentro de la caja de búsqueda mientras está vacía',
        ],
        // La tarjeta ilustrada de la derecha es adorno, pero adorno con
        // palabras: si el hospital le cambia el nombre al portal, esto también.
        'hero.tarjeta_rotulo' => [
            'Ecosistema Digital de Salud',
            'texto', 'hero', 'Tarjeta: rótulo superior', 'Vacío, no se dibuja',
        ],
        'hero.tarjeta_version' => [
            '',
            'texto', 'hero', 'Tarjeta: distintivo de versión', 'Vacío de fábrica a propósito: un número de versión del portal no le dice nada al ciudadano',
        ],
        'hero.tarjeta_titulo' => [
            'Rosario Digital',
            'texto', 'hero', 'Tarjeta: título', null,
        ],
        'hero.tarjeta_texto' => [
            'Tu portal clínico unificado e interactivo.',
            'texto', 'hero', 'Tarjeta: frase bajo el título', null,
        ],
        'hero.tarjeta_pie' => [
            'Protección de datos conforme a la Ley 1581',
            'texto', 'hero', 'Tarjeta: leyenda del pie', 'Va junto al icono del escudo. Vacía, el pie de la tarjeta no se dibuja',
        ],
        // Los dos avisos flotantes afirman un estado del servicio —«sin
        // esperas», «agendar en 2 minutos»— que nadie mide. Se apagan
        // enteros, y vienen apagados: es el mismo motivo por el que se
        // sacaron del héroe las cifras escritas a mano.
        'hero.avisos_visibles' => [
            '0',
            'booleano', 'hero', 'Mostrar los dos avisos flotantes', 'Actívalos solo si alguien se hace cargo de que lo que dicen siga siendo cierto',
        ],
        'hero.aviso1_rotulo' => [
            'Atención en sede',
            'texto', 'hero', 'Aviso 1: rótulo', null,
        ],
        'hero.aviso1_texto' => [
            'Normal (sin esperas)',
            'texto', 'hero', 'Aviso 1: estado', 'Afirma cómo está el servicio ahora mismo: mantenlo al día o deja los avisos apagados',
        ],
        'hero.aviso2_rotulo' => [
            'Próxima cita',
            'texto', 'hero', 'Aviso 2: rótulo', null,
        ],
        'hero.aviso2_texto' => [
            'Agendar en 2 minutos',
            'texto', 'hero', 'Aviso 2: estado', null,
        ],

        // --- Pie de página y marca (2026-09-15) ---
        // Estaban escritos dentro de `publico/base.php`: cambiar el rótulo de
        // una columna del pie exigía un despliegue. Lo que es texto o imagen
        // pasa aquí; la maqueta (cuántas columnas, dónde va cada bloque) sigue
        // en la plantilla, que es diseño y no contenido.
        'marca.logo' => [
            '/img/logo_pequeno.jpeg',
            'texto', 'entidad', 'Logotipo de la entidad', 'Ruta del archivo. Se usa en el encabezado y en el pie',
        ],
        'pie.titulo_marca' => [
            'República de Colombia',
            'texto', 'pie', 'Rótulo sobre el nombre de la entidad', null,
        ],
        'pie.titulo_enlaces' => [
            'Enlaces de interés',
            'texto', 'pie', 'Título de la 2.ª columna', 'Sus enlaces se editan en Menús del sitio, ubicación «pie_1»',
        ],
        'pie.titulo_normatividad' => [
            'Normatividad',
            'texto', 'pie', 'Título de la 3.ª columna', 'Sus enlaces se editan en Menús del sitio, ubicación «pie_2»',
        ],
        'pie.titulo_atencion' => [
            'Atención al ciudadano',
            'texto', 'pie', 'Título de la 4.ª columna', null,
        ],
        'pie.derechos' => [
            'Todos los derechos reservados.',
            'texto', 'pie', 'Leyenda de derechos', 'Va detrás de «© año, nombre de la entidad»',
        ],
        'pie.leyenda_normativa' => [
            'Cumplimos con la Ley 1712 de 2014 y la Resolución 1519 de 2020.',
            'texto', 'pie', 'Leyenda normativa', 'Declara el cumplimiento de la ley de transparencia. Cambiarla solo si cambia la norma',
        ],
        'govco.url' => [
            'https://www.gov.co/',
            'texto', 'pie', 'Enlace del Portal GOV.CO', 'Marca oficial del Estado: no cambiar salvo indicación de MinTIC',
        ],
        // ITA 9.2. Se siembra VACÍA a propósito: el enlace debe apuntar al
        // perfil real de la entidad en datos.gov.co, y publicar uno inventado
        // es peor que no publicar ninguno. Mientras esté vacía, el sitio
        // simplemente no muestra la sección.
        'datos_abiertos.portal_url' => [
            '',
            'texto', 'pie', 'Perfil en datos.gov.co',
            'Enlace al perfil de la entidad en el Portal de Datos Abiertos. Exigido por el criterio 9.2 del ITA. Déjalo vacío mientras no exista el perfil',
        ],
        'govco.leyenda' => [
            'Portal Único del Estado Colombiano',
            'texto', 'pie', 'Leyenda de la barra GOV.CO', 'Marca oficial del Estado: no cambiar salvo indicación de MinTIC',
        ],
        'govco.logo' => [
            '/img/logo_gov.co.png',
            'texto', 'pie', 'Logotipo GOV.CO', 'Marca oficial del Estado: no cambiar salvo indicación de MinTIC',
        ],
        'redes.facebook' => ['https://www.facebook.com/Hospital-Rosario-Pumarejo-De-Lopez', 'texto', 'redes', 'Facebook (URL)', 'Verificar URL exacta'],
        'redes.instagram' => ['https://www.instagram.com/hospitalrosariovalledupar', 'texto', 'redes', 'Instagram (URL)', null],
        'redes.tiktok' => ['', 'texto', 'redes', 'TikTok (URL)', 'Pegar la URL del perfil, por ejemplo https://www.tiktok.com/@usuario'],
        'redes.youtube' => ['https://www.youtube.com/channel/UCdbJFQt1lBHhGjOpSC62SEQ', 'texto', 'redes', 'YouTube (URL)', null],
        'redes.x' => ['https://x.com/ESEHospitalRos1', 'texto', 'redes', 'X / Twitter (URL)', null],
        'redes.linkedin' => ['', 'texto', 'redes', 'LinkedIn (URL)', null],
        // IA del panel (Fase 8): apagada por defecto; las claves de API van
        // en el .env del servidor, jamás en esta tabla.
        'ia.activada' => ['0', 'booleano', 'ia', 'Asistente de IA activado', 'Se configura en Panel → IA'],
        'ia.proveedor' => ['anthropic', 'texto', 'ia', 'Proveedor de IA', null],
        'ia.modelo' => ['claude-sonnet-5', 'texto', 'ia', 'Modelo de IA', null],
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
            ->setName('semillas:configuracion')
            ->setDescription('Crea las claves de configuración del sitio (sin pisar valores editados)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creadas = 0;
        $orden = 0;

        foreach (self::SEMILLAS as $clave => [$valor, $tipo, $grupo, $etiqueta, $ayuda]) {
            $afectadas = $this->conexion->executeStatement(
                'INSERT IGNORE INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                [$clave, $valor, $tipo, $grupo, $etiqueta, $ayuda, $orden++],
            );

            $creadas += (int) $afectadas;
        }

        if ($creadas > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'configuracion',
                descripcion: sprintf('Semillas de configuración: %d claves creadas', $creadas),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf(
            '%d claves creadas, %d ya existían y no se tocaron.',
            $creadas,
            count(self::SEMILLAS) - $creadas,
        ));

        return Command::SUCCESS;
    }
}
