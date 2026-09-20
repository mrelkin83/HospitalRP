<?php

declare(strict_types=1);

/**
 * Contenedor de servicios.
 *
 * Inyección por constructor en todo el proyecto, cero estado global
 * (CLAUDE.md §5). Los controladores son los únicos servicios públicos: el
 * Kernel los resuelve por nombre de clase. Todo lo demás se cablea por
 * autowiring y se mantiene privado.
 */

use App\Core\Audit\AuditoriaService;
use App\Core\Auth\AutenticadorService;
use App\Core\Auth\AutorizacionService;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\PoliticaPassword;
use App\Core\Auth\Sesion;
use App\Core\Auth\UsuarioRepository;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\ConfigDinamica;
use App\Core\Config\Env;
use App\Core\Database\ConexionFactory;
use App\Core\Database\ConfigBaseDatos;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Http\Admin\AvisosController;
use App\Http\Admin\PopupsController;
use App\Http\Admin\CampanasController;
use App\Http\Admin\CategoriasController;
use App\Http\Admin\CuentasContratistasController;
use App\Http\Admin\CuentasContratosController;
use App\Http\Admin\CuentasParametrosController;
use App\Http\Admin\CuentasSupervisoresController;
use App\Http\Admin\FaqController;
use App\Http\Admin\LoginController;
use App\Http\Admin\MediosController;
use App\Http\Admin\MenusController;
use App\Http\Admin\NoticiasController;
use App\Http\Admin\ConvocatoriasAdminController;
use App\Http\Admin\IndicadoresAdminController;
use App\Http\Admin\EncuestasAdminController;
use App\Http\Admin\EventosAdminController;
use App\Http\Admin\HeroesController;
use App\Http\Admin\ServidoresPublicosAdminController;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Http\Admin\DatasetsController;
use App\Http\Admin\DependenciasController;
use App\Http\Admin\DocumentosController;
use App\Http\Admin\BandejaTramitesController;
use App\Http\Admin\TiposTramiteController;
use App\Http\Admin\TransparenciaController;
use App\Http\Admin\EspecialidadesController;
use App\Http\Admin\IaController;
use App\Http\Admin\PaginasAdminController;
use App\Http\Admin\PoliticasController;
use App\Http\Admin\PanelController;
use App\Http\Admin\AuditoriaAdminController;
use App\Http\Admin\ConfiguracionController;
use App\Http\Admin\PerfilController;
use App\Http\Admin\RedireccionesController;
use App\Http\Admin\RolesController;
use App\Http\Admin\UsuariosController;
use App\Http\Admin\ProfesionalesController;
use App\Http\Admin\SedesController;
use App\Http\Admin\ServiciosController;
use App\Adapters\Correo\NotificadorSymfonyMailer;
use App\Adapters\Ia\FabricaProveedorIa;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;
use App\Modules\Busqueda\Service\BuscadorService;
use App\Modules\Busqueda\Service\IndexadorService;
use App\Modules\Sistema\Repository\DependenciaRepository;
use App\Modules\Sistema\Repository\AuditoriaRepository;
use App\Modules\Sistema\Repository\ConfiguracionRepository;
use App\Modules\Sistema\Repository\RolRepository;
use App\Modules\Sistema\Repository\UsuarioAdminRepository;
use App\Modules\Sistema\Service\CatalogoPermisos;
use App\Modules\Sistema\Service\CatalogoIconos;
use App\Modules\Sistema\Repository\IaUsoRepository;
use App\Modules\Convocatorias\Repository\ConvocatoriaRepository;
use App\Modules\Encuestas\Repository\EncuestaRepository;
use App\Modules\Indicadores\Repository\IndicadorRepository;
use App\Modules\Contenido\Repository\HeroRepository;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use App\Modules\CuentasCobro\Repository\SupervisorRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\EnvioCuentaService;
use App\Modules\CuentasCobro\Service\GeneradorDocumentosCuenta;
use App\Modules\CuentasCobro\Service\OtpService;
use App\Modules\CuentasCobro\Service\SesionContratista;
use App\Http\Web\CuentasDescargaController;
use App\Http\Web\CuentasNuevaController;
use App\Http\Web\CuentasPortalController;
use App\Modules\Eventos\Repository\EventoRepository;
use App\Modules\Directorio\Repository\ServidorPublicoRepository;
use App\Modules\Sistema\Repository\MenuRepository;
use App\Modules\Sistema\Repository\RedireccionRepository;
use App\Modules\Sistema\Service\AsistenteIa;
use App\Ports\FabricaAiProvider;
use App\Modules\Tramites\Repository\TipoTramiteRepository;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Modules\Tramites\Service\CalculadoraDiasHabiles;
use App\Modules\Tramites\Service\GeneradorRadicado;
use App\Modules\Tramites\Service\GestionTramiteService;
use App\Modules\Tramites\Service\RadicacionService;
use App\Modules\Cumplimiento\Repository\PoliticaRepository;
use App\Ports\NotificadorCorreo;
use App\Modules\Transparencia\Repository\DatasetRepository;
use App\Modules\Transparencia\Repository\TransparenciaRepository;
use App\Http\Web\BuscarController;
use App\Http\Web\DirectorioPublicoController;
use App\Http\Web\DocumentosPublicoController;
use App\Http\Web\EspecialidadesPublicoController;
use App\Http\Web\FaqPublicoController;
use App\Http\Web\LayoutPublico;
use App\Http\Web\CampanasPublicoController;
use App\Http\Web\NoticiasPublicoController;
use App\Http\Web\PaginasController;
use App\Http\Web\PoliticasPublicoController;
use App\Http\Web\SedesPublicoController;
use App\Http\Web\SeoController;
use App\Http\Web\ServiciosPublicoController;
use App\Http\Web\TramitesPublicoController;
use App\Http\Web\TransparenciaPublicoController;
use App\Http\Web\ConvocatoriasPublicoController;
use App\Http\Web\EncuestasPublicoController;
use App\Http\Web\EventosPublicoController;
use App\Http\Web\EstadisticasController;
use App\Http\Web\GaleriaController;
use App\Http\Web\MapaSitioController;
use App\Http\Web\ServidoresPublicosController;
use App\Http\Web\UrgenciasContactoController;
use App\Modules\Contenido\Repository\AvisoRepository;
use App\Modules\Contenido\Repository\PopupRepository;
use App\Modules\Contenido\Repository\CampanaRepository;
use App\Modules\Contenido\Repository\CategoriaRepository;
use App\Modules\Contenido\Repository\FaqRepository;
use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Contenido\Repository\PaginaRepository;
use App\Modules\Contenido\Service\CampanaService;
use App\Modules\Contenido\Service\PaginaService;
use App\Modules\Directorio\Repository\EspecialidadRepository;
use App\Modules\Documentos\Repository\DocumentoRepository;
use App\Modules\Documentos\Service\DocumentoService;
use App\Modules\Directorio\Repository\ProfesionalRepository;
use App\Modules\Directorio\Repository\SedeRepository;
use App\Modules\Directorio\Repository\ServicioRepository;
use App\Modules\Contenido\Service\NoticiaService;
use App\Modules\Contenido\Service\SanitizadorHtml;
use App\Modules\Contenido\Service\VersionadoService;
use App\Http\Web\ArchivoController;
use App\Http\Web\InicioController;
use App\Http\Web\MuestraDisenoController;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

return static function (Env $env): ContainerBuilder {
    $contenedor = new ContainerBuilder();

    // El Env ya viene construido desde bootstrap.php: es el único servicio que
    // no puede crear el contenedor, porque el contenedor depende de él.
    $contenedor->set(Env::class, $env);

    $contenedor->register(ConfigAplicacion::class, ConfigAplicacion::class)
        ->setFactory([ConfigAplicacion::class, 'desde'])
        ->setArguments([new Reference(Env::class)])
        ->setPublic(true);

    // --- Base de datos ---
    // Perezosa por diseño de DBAL: registrar la conexión no abre socket alguno;
    // el primer query lo abre. Una página sin consultas no paga la conexión.
    $contenedor->register(ConfigBaseDatos::class, ConfigBaseDatos::class)
        ->setFactory([ConfigBaseDatos::class, 'desde'])
        ->setArguments([new Reference(Env::class)]);

    $contenedor->register(Connection::class, Connection::class)
        ->setFactory([ConexionFactory::class, 'crear'])
        ->setArguments([new Reference(ConfigBaseDatos::class)]);

    $contenedor->register(ConfigDinamica::class, ConfigDinamica::class)
        ->setAutowired(true);

    // --- Auditoría ---
    $contenedor->register(AuditoriaService::class, AuditoriaService::class)
        ->setAutowired(true);

    // --- Autenticación y sesión ---
    $contenedor->register(HasherPassword::class, HasherPassword::class);

    $contenedor->register(PoliticaPassword::class, PoliticaPassword::class);

    $contenedor->register(UsuarioRepository::class, UsuarioRepository::class)
        ->setAutowired(true);

    $contenedor->register(AutenticadorService::class, AutenticadorService::class)
        ->setAutowired(true);

    // Sesion y AutorizacionService son públicas además de los controladores:
    // el Kernel las resuelve por nombre para el control de acceso de rutas.
    $contenedor->register(Sesion::class, Sesion::class)
        ->setFactory([Sesion::class, 'desde'])
        ->setArguments([new Reference(Env::class), new Reference(ConfigAplicacion::class)])
        ->setPublic(true);

    $contenedor->register(AutorizacionService::class, AutorizacionService::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- Vistas ---
    $contenedor->register(Vistas::class, Vistas::class)
        ->setArguments([dirname(__DIR__) . '/resources/views']);

    // --- Almacenamiento de archivos ---
    $contenedor->register(AlmacenArchivos::class, AlmacenArchivos::class)
        ->setArguments([
            new Reference(Connection::class),
            dirname(__DIR__) . '/storage/uploads',
        ]);

    // --- Módulo Sistema ---
    $contenedor->register(MenuRepository::class, MenuRepository::class)
        ->setAutowired(true);

    $contenedor->register(UsuarioAdminRepository::class, UsuarioAdminRepository::class)
        ->setAutowired(true);

    $contenedor->register(RolRepository::class, RolRepository::class)
        ->setAutowired(true);

    $contenedor->register(ConfiguracionRepository::class, ConfiguracionRepository::class)
        ->setAutowired(true);

    $contenedor->register(AuditoriaRepository::class, AuditoriaRepository::class)
        ->setAutowired(true);

    $contenedor->register(CatalogoPermisos::class, CatalogoPermisos::class);

    $contenedor->register(CatalogoIconos::class, CatalogoIconos::class);

    $contenedor->register(ServidorPublicoRepository::class, ServidorPublicoRepository::class)
        ->setAutowired(true);

    $contenedor->register(ConvocatoriaRepository::class, ConvocatoriaRepository::class)
        ->setAutowired(true);

    $contenedor->register(EventoRepository::class, EventoRepository::class)
        ->setAutowired(true);

    $contenedor->register(HeroRepository::class, HeroRepository::class)
        ->setAutowired(true);

    $contenedor->register(EncuestaRepository::class, EncuestaRepository::class)
        ->setAutowired(true);

    $contenedor->register(IndicadorRepository::class, IndicadorRepository::class)
        ->setAutowired(true);

    $contenedor->register(DependenciaRepository::class, DependenciaRepository::class)
        ->setAutowired(true);

    // --- Módulo Transparencia ---
    $contenedor->register(TransparenciaRepository::class, TransparenciaRepository::class)
        ->setAutowired(true);

    $contenedor->register(DatasetRepository::class, DatasetRepository::class)
        ->setAutowired(true);

    // --- Módulo Trámites ---
    $contenedor->register(NotificadorCorreo::class, NotificadorSymfonyMailer::class)
        ->setFactory([NotificadorSymfonyMailer::class, 'desde'])
        ->setArguments([new Reference(Env::class)]);

    $contenedor->register(TipoTramiteRepository::class, TipoTramiteRepository::class)
        ->setAutowired(true);

    $contenedor->register(TramiteRepository::class, TramiteRepository::class)
        ->setAutowired(true);

    $contenedor->register(CalculadoraDiasHabiles::class, CalculadoraDiasHabiles::class)
        ->setAutowired(true);

    $contenedor->register(GeneradorRadicado::class, GeneradorRadicado::class)
        ->setAutowired(true);

    $contenedor->register(RadicacionService::class, RadicacionService::class)
        ->setAutowired(true);

    $contenedor->register(GestionTramiteService::class, GestionTramiteService::class)
        ->setAutowired(true);

    $contenedor->register(PoliticaRepository::class, PoliticaRepository::class)
        ->setAutowired(true);

    // --- Módulo Documentos ---
    $contenedor->register(DocumentoRepository::class, DocumentoRepository::class)
        ->setAutowired(true);

    $contenedor->register(DocumentoService::class, DocumentoService::class)
        ->setAutowired(true);

    // El Kernel la consulta en los 404, igual que Sesion: pública.
    $contenedor->register(RedireccionRepository::class, RedireccionRepository::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- IA del panel (Fase 8) ---
    $contenedor->register(FabricaAiProvider::class, FabricaProveedorIa::class)
        ->setFactory([FabricaProveedorIa::class, 'desde'])
        ->setArguments([new Reference(Env::class)]);

    $contenedor->register(IaUsoRepository::class, IaUsoRepository::class)
        ->setAutowired(true);

    $contenedor->register(AsistenteIa::class, AsistenteIa::class)
        ->setAutowired(true);

    // --- Módulo Búsqueda ---
    $contenedor->register(IndiceBusquedaRepository::class, IndiceBusquedaRepository::class)
        ->setAutowired(true);

    $contenedor->register(IndexadorService::class, IndexadorService::class)
        ->setAutowired(true);

    $contenedor->register(BuscadorService::class, BuscadorService::class)
        ->setAutowired(true);

    // --- Módulo Contenido ---
    $contenedor->register(SanitizadorHtml::class, SanitizadorHtml::class);

    $contenedor->register(VersionadoService::class, VersionadoService::class)
        ->setAutowired(true);

    $contenedor->register(NoticiaRepository::class, NoticiaRepository::class)
        ->setAutowired(true);

    $contenedor->register(CategoriaRepository::class, CategoriaRepository::class)
        ->setAutowired(true);

    $contenedor->register(NoticiaService::class, NoticiaService::class)
        ->setAutowired(true);

    $contenedor->register(CampanaRepository::class, CampanaRepository::class)
        ->setAutowired(true);

    $contenedor->register(FaqRepository::class, FaqRepository::class)
        ->setAutowired(true);

    $contenedor->register(PaginaService::class, PaginaService::class)
        ->setAutowired(true);

    $contenedor->register(CampanaService::class, CampanaService::class)
        ->setAutowired(true);

    $contenedor->register(AvisoRepository::class, AvisoRepository::class)
        ->setAutowired(true);

    $contenedor->register(PopupRepository::class, PopupRepository::class)
        ->setAutowired(true);

    // --- Módulo Directorio ---
    $contenedor->register(SedeRepository::class, SedeRepository::class)
        ->setAutowired(true);

    $contenedor->register(EspecialidadRepository::class, EspecialidadRepository::class)
        ->setAutowired(true);

    $contenedor->register(ServicioRepository::class, ServicioRepository::class)
        ->setAutowired(true);

    $contenedor->register(ProfesionalRepository::class, ProfesionalRepository::class)
        ->setAutowired(true);

    $contenedor->register(PaginaRepository::class, PaginaRepository::class)
        ->setAutowired(true);

    // --- Controladores del sitio público ---
    $contenedor->register(LayoutPublico::class, LayoutPublico::class)
        ->setAutowired(true);

    $contenedor->register(InicioController::class, InicioController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(NoticiasPublicoController::class, NoticiasPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CampanasPublicoController::class, CampanasPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(BuscarController::class, BuscarController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(FaqPublicoController::class, FaqPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(SeoController::class, SeoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PaginasController::class, PaginasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(MapaSitioController::class, MapaSitioController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EstadisticasController::class, EstadisticasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(GaleriaController::class, GaleriaController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PantallaAdmin::class, PantallaAdmin::class)
        ->setAutowired(true);

    $contenedor->register(IndicadoresAdminController::class, IndicadoresAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(UsuariosController::class, UsuariosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(RolesController::class, RolesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ConfiguracionController::class, ConfiguracionController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(AuditoriaAdminController::class, AuditoriaAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(RedireccionesController::class, RedireccionesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PerfilController::class, PerfilController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ConvocatoriasAdminController::class, ConvocatoriasAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EventosAdminController::class, EventosAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(HeroesController::class, HeroesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EncuestasAdminController::class, EncuestasAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ServidoresPublicosAdminController::class, ServidoresPublicosAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ServidoresPublicosController::class, ServidoresPublicosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ConvocatoriasPublicoController::class, ConvocatoriasPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EventosPublicoController::class, EventosPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EncuestasPublicoController::class, EncuestasPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(SedesPublicoController::class, SedesPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EspecialidadesPublicoController::class, EspecialidadesPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ServiciosPublicoController::class, ServiciosPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(DirectorioPublicoController::class, DirectorioPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(UrgenciasContactoController::class, UrgenciasContactoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(DocumentosPublicoController::class, DocumentosPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(TransparenciaPublicoController::class, TransparenciaPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(TramitesPublicoController::class, TramitesPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PoliticasPublicoController::class, PoliticasPublicoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ArchivoController::class, ArchivoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(MuestraDisenoController::class, MuestraDisenoController::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- Controladores del panel ---
    $contenedor->register(LoginController::class, LoginController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PanelController::class, PanelController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(IaController::class, IaController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(NoticiasController::class, NoticiasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(MediosController::class, MediosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PaginasAdminController::class, PaginasAdminController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CampanasController::class, CampanasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(AvisosController::class, AvisosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PopupsController::class, PopupsController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(FaqController::class, FaqController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CategoriasController::class, CategoriasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(SedesController::class, SedesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(EspecialidadesController::class, EspecialidadesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ServiciosController::class, ServiciosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(ProfesionalesController::class, ProfesionalesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(DocumentosController::class, DocumentosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(DependenciasController::class, DependenciasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(TransparenciaController::class, TransparenciaController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(BandejaTramitesController::class, BandejaTramitesController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(TiposTramiteController::class, TiposTramiteController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PoliticasController::class, PoliticasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(DatasetsController::class, DatasetsController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(MenusController::class, MenusController::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- Módulo de cuentas de cobro (fase 1) ---

    $contenedor->register(ContratistaRepository::class, ContratistaRepository::class)
        ->setAutowired(true);

    $contenedor->register(SupervisorRepository::class, SupervisorRepository::class)
        ->setAutowired(true);

    $contenedor->register(ContratoRepository::class, ContratoRepository::class)
        ->setAutowired(true);

    $contenedor->register(ParametrosCuentasRepository::class, ParametrosCuentasRepository::class)
        ->setAutowired(true);

    $contenedor->register(CuentasContratosController::class, CuentasContratosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CuentasContratistasController::class, CuentasContratistasController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CuentasSupervisoresController::class, CuentasSupervisoresController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CuentasParametrosController::class, CuentasParametrosController::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- Módulo de cuentas de cobro (fase 2: portal del contratista) ---

    $contenedor->register(CuentaRepository::class, CuentaRepository::class)
        ->setAutowired(true);

    $contenedor->register(SesionContratista::class, SesionContratista::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(OtpService::class, OtpService::class)
        ->setAutowired(true);

    // Los archivos del módulo viven fuera del document root y aparte de la
    // mediateca pública: son datos personales servidos con permiso.
    $contenedor->register(ArchivosCuentas::class, ArchivosCuentas::class)
        ->setArguments([dirname(__DIR__) . '/storage/cuentas']);

    $contenedor->register(GeneradorDocumentosCuenta::class, GeneradorDocumentosCuenta::class)
        ->setArguments([
            new Reference(Connection::class),
            new Reference(ParametrosCuentasRepository::class),
            new Reference(ArchivosCuentas::class),
            dirname(__DIR__) . '/templates/pdf',
            dirname(__DIR__) . '/public/img/logo.png',
        ]);

    $contenedor->register(EnvioCuentaService::class, EnvioCuentaService::class)
        ->setAutowired(true);

    $contenedor->register(CuentasPortalController::class, CuentasPortalController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CuentasNuevaController::class, CuentasNuevaController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(CuentasDescargaController::class, CuentasDescargaController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->compile();

    return $contenedor;
};
