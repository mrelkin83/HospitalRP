<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Modules\Sistema\Repository\RedireccionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Redirecciones desde el Joomla viejo (inventario en docs/08). Idempotente:
 * un origen existente nunca se sobreescribe. Los prefijos `*` atrapan las
 * sub-URL que no vale la pena mapear una por una; la red de seguridad final
 * lleva cualquier resto de `/sitio/` a la portada en lugar de a un 404.
 */
#[AsCommand(name: 'semillas:redirecciones', description: 'Siembra las redirecciones 301 del sitio Joomla anterior')]
final class SembrarRedireccionesCommand extends Command
{
    private const string JOOMLA = '/sitio/index.php/es';

    /**
     * origen (relativo al Joomla) => destino nuevo.
     *
     * Los prefijos salen del rastreo de `docs/14`, no de suposiciones: la
     * auditoría del 2026-09-14 encontró que `/el-hospital`, `/prensa` y
     * `/servicios` —que esta semilla mapeaba— **no existen** en el Joomla.
     * Los reales son `/nuestro-hospital`, `/prensahrpl` y
     * `/servicio-al-ciudadano/servicios`. Entre paréntesis, el número de URL
     * medidas bajo cada prefijo.
     *
     * @var array<string, string>
     */
    private const array MAPA = [
        // Atención a la ciudadanía (27 URL)
        '/peticiones-quejas-y-reclamos' => '/atencion-ciudadania/pqrsdf',
        '/peticiones-quejas-y-reclamos/*' => '/atencion-ciudadania/pqrsdf',
        '/solicitud-pqr' => '/atencion-ciudadania/pqrsdf',
        '/servicio-al-ciudadano/peticiones-quejas-y-reclamos' => '/atencion-ciudadania/pqrsdf',
        '/servicio-al-ciudadano/preguntas-frecuentes' => '/atencion-ciudadania/preguntas-frecuentes',
        '/servicio-al-ciudadano/tramites' => '/atencion-ciudadania/tramites',
        '/servicio-al-ciudadano/consulta-citas' => '/citas',
        '/servicio-al-ciudadano/contactenos' => '/contacto',
        '/servicio-al-ciudadano/servicios' => '/servicios',
        '/servicio-al-ciudadano/transparencia-y-acceso-a-la-informacion-publica' => '/transparencia',
        '/servicio-al-ciudadano' => '/atencion-ciudadania',
        '/servicio-al-ciudadano/*' => '/atencion-ciudadania',
        '/tramites-y-servicios' => '/atencion-ciudadania/tramites',
        '/tramites-y-servicios/*' => '/atencion-ciudadania/tramites',

        // Transparencia y sus insumos (110 informes + 65 normativa + 12 contratación)
        '/transparencia' => '/transparencia',
        '/transparencia/*' => '/transparencia',
        '/normativa' => '/transparencia/normatividad',
        '/normativa/*' => '/transparencia/normatividad',
        // Regla EXACTA antes del comodín: el repositorio resuelve primero por
        // origen exacto, así que esta gana sobre `/informes/*`. La URL está
        // citada textualmente en el oficio de Supersalud 20264200002708501, y
        // mandarla al índice de la sección rompería el «enlace directo y
        // funcional» que exige su orden 9.
        '/informes/reportes-de-control-interno' => '/transparencia/informes-de-control-interno',
        '/informes' => '/transparencia/planeacion-presupuesto-informes',
        '/informes/*' => '/transparencia/planeacion-presupuesto-informes',
        '/contratacion' => '/transparencia/contratacion',
        '/contratacion/*' => '/transparencia/contratacion',
        '/plan-anual-de-adquisiciones' => '/transparencia/contratacion',
        '/plan-anual-de-adquisiciones/*' => '/transparencia/contratacion',
        '/programas-y-proyectos' => '/transparencia/planeacion-presupuesto-informes',
        '/programas-y-proyectos/*' => '/transparencia/planeacion-presupuesto-informes',
        '/rendicion-cuentas' => '/transparencia/participa',
        '/rendicion-cuentas/*' => '/transparencia/participa',
        '/participa' => '/participa',
        '/participa/*' => '/participa',
        '/informacion-para-ninos-2' => '/transparencia/grupos-de-interes',
        '/informacion-para-ninos-2/*' => '/transparencia/grupos-de-interes',
        '/meci' => '/transparencia',
        '/meci/*' => '/transparencia',
        '/mipg' => '/transparencia',
        '/mipg/*' => '/transparencia',
        '/cinteres' => '/transparencia',
        '/cinteres/*' => '/transparencia',
        '/controldisciplinariointerno' => '/transparencia',
        '/controldisciplinariointerno/*' => '/transparencia',
        '/conciliaciones' => '/transparencia',
        '/conciliaciones/*' => '/transparencia',

        // Institucional (13 URL bajo el prefijo REAL /nuestro-hospital)
        '/nuestro-hospital' => '/el-hospital',
        '/nuestro-hospital/*' => '/el-hospital',
        '/niveles-de-atencion' => '/el-hospital/niveles-de-atencion',
        '/niveles-de-atencion/*' => '/el-hospital/niveles-de-atencion',

        // Talento humano (50 URL)
        '/rr-hh' => '/transparencia',
        '/rr-hh/*' => '/transparencia',

        // Programas asistenciales (25 IAMII + docencia, humanización, etc.)
        '/iamii' => '/servicios',
        '/iamii/*' => '/servicios',
        '/docencia' => '/servicios',
        '/docencia/*' => '/servicios',
        '/humanizacion' => '/servicios',
        '/humanizacion/*' => '/servicios',
        '/etnocultural' => '/servicios',
        '/etnocultural/*' => '/servicios',
        '/banco-de-sangre' => '/servicios',
        '/pediatria' => '/especialidades',

        // Prensa (prefijo REAL /prensahrpl, 7 URL) y noticias
        '/prensahrpl' => '/noticias',
        '/prensahrpl/*' => '/noticias',
        '/noticias' => '/noticias',
        '/noticias/*' => '/noticias',
        '/galeria-de-imagenes' => '/noticias',
        '/galeria-de-imagenes/*' => '/noticias',

        // Directorio, búsqueda y mapa
        '/directorio' => '/directorio',
        '/directorio/*' => '/directorio',
        '/buscar' => '/buscar',
        '/buscar/*' => '/buscar',
        '/mapa-del-sitio' => '/mapa-del-sitio',

        // Retirado por decisión del hospital (2026-09-14): la intranet de
        // usuarios no se migra ni como enlace. Va a la portada, no a un 404.
        '/acceder-al-sistema' => '/',

        // Componentes internos del Joomla: nunca tuvieron equivalente.
        '/component/*' => '/',
    ];

    public function __construct(private readonly RedireccionRepository $redirecciones)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $creadas = 0;

        foreach (self::MAPA as $origen => $destino) {
            $creadas += (int) $this->redirecciones->crearSiNoExiste(self::JOOMLA . $origen, $destino);
        }

        // Redes de seguridad, de la más específica a la más amplia.
        $creadas += (int) $this->redirecciones->crearSiNoExiste(self::JOOMLA . '/*', '/');
        $creadas += (int) $this->redirecciones->crearSiNoExiste('/sitio/*', '/');

        $output->writeln(sprintf('<info>Redirecciones: %d nuevas, el resto ya existía.</info>', $creadas));

        return Command::SUCCESS;
    }
}
