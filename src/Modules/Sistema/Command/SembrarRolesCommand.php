<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Modules\Sistema\Service\CatalogoPermisos;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Roles de trabajo de la entidad, cada uno con lo mínimo para su oficio.
 *
 * Hasta ahora solo existía «Administrador» con `["*"]`: quien entraba a cargar
 * una noticia podía además cambiar el NIT de la entidad, crear usuarios o
 * mirar el registro de auditoría. No es un detalle de comodidad —es el
 * principio de mínimo privilegio, y en una entidad pública además vacía de
 * sentido la trazabilidad: si todos pueden todo, la auditoría no distingue
 * responsabilidades.
 *
 * Dos decisiones que conviene entender antes de tocar la lista:
 *
 * - **Redactar y publicar están separados.** `contenido.editar` crea y edita;
 *   `contenido.publicar` aprueba y saca al aire. Es el flujo editorial de
 *   CLAUDE.md §6: quien escribe no es necesariamente quien aprueba.
 * - **Ningún rol de esta lista administra el sistema.** Usuarios, roles,
 *   ajustes, menús, redirecciones e IA se quedan en «Administrador». Repartir
 *   `sistema.usuarios` es dar la llave para fabricarse cualquier otro permiso.
 *
 * Son un punto de partida editable: el hospital ajusta cada rol desde
 * Panel → Sistema → Roles y permisos sin tocar código.
 *
 * Idempotente por slug: un rol que ya existe no se toca, para no pisar los
 * permisos que la entidad haya ajustado.
 */
final class SembrarRolesCommand extends Command
{
    /** @var array<string, array{nombre: string, descripcion: string, permisos: list<string>}> */
    private const array ROLES = [
        'redactor' => [
            'nombre' => 'Redactor de contenido',
            'descripcion' => 'Escribe noticias, páginas y campañas y las envía a revisión. No publica.',
            'permisos' => [
                'contenido.ver',
                'contenido.editar',
                'directorio.ver',
            ],
        ],
        'editor' => [
            'nombre' => 'Editor de Comunicaciones',
            'descripcion' => 'Revisa lo que escriben los redactores, publica, y maneja eventos y agenda.',
            'permisos' => [
                'contenido.ver',
                'contenido.editar',
                'contenido.publicar',
                'eventos.ver',
                'eventos.editar',
                'convocatorias.ver',
                'directorio.ver',
            ],
        ],
        'atencion-ciudadano' => [
            'nombre' => 'Atención al ciudadano',
            'descripcion' => 'Atiende la bandeja de PQRSDF y consulta encuestas. No edita el sitio.',
            'permisos' => [
                'tramites.gestionar',
                'contenido.ver',
                'encuestas.ver',
                'directorio.ver',
                'documentos.ver',
            ],
        ],
        'transparencia' => [
            'nombre' => 'Transparencia y datos abiertos',
            'descripcion' => 'Mantiene el menú ITA, la gestión documental, los datos abiertos y los indicadores.',
            'permisos' => [
                'transparencia.ver',
                'transparencia.editar',
                'documentos.ver',
                'documentos.editar',
                'documentos.publicar',
                'indicadores.ver',
                'indicadores.editar',
            ],
        ],
        'talento-humano' => [
            'nombre' => 'Talento humano',
            'descripcion' => 'Publica convocatorias y mantiene el directorio de servidores públicos y profesionales.',
            'permisos' => [
                'convocatorias.ver',
                'convocatorias.editar',
                'directorio.ver',
                'directorio.editar',
                'sistema.dependencias',
            ],
        ],
        'control-interno' => [
            'nombre' => 'Control interno (solo lectura)',
            'descripcion' => 'Ve el panel sin poder modificar nada, y consulta el registro de auditoría.',
            'permisos' => [
                'contenido.ver',
                'documentos.ver',
                'transparencia.ver',
                'convocatorias.ver',
                'eventos.ver',
                'encuestas.ver',
                'indicadores.ver',
                'directorio.ver',
                'sistema.auditoria',
            ],
        ],
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
        private readonly CatalogoPermisos $catalogo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('semillas:roles')
            ->setDescription('Crea roles de ejemplo con permisos acotados (sin tocar los que ya existen)')
            ->addOption('listar', null, InputOption::VALUE_NONE, 'Solo muestra qué haría, sin escribir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simulacion = (bool) $input->getOption('listar');

        // Un permiso que no esté en el catálogo no tendría casilla en el panel:
        // quedaría escrito en la fila del rol y nadie podría quitárselo después.
        // Mejor fallar aquí que dejar un rol imposible de editar.
        $conocidos = $this->catalogo->todos();
        $filas = [];
        $creados = 0;

        foreach (self::ROLES as $slug => $rol) {
            $desconocidos = array_diff($rol['permisos'], $conocidos);

            if ($desconocidos !== []) {
                $io->error(sprintf(
                    'El rol «%s» pide permisos que no están en CatalogoPermisos: %s',
                    $rol['nombre'],
                    implode(', ', $desconocidos),
                ));

                return Command::FAILURE;
            }

            $existe = false !== $this->conexion->fetchOne('SELECT id FROM roles WHERE slug = ?', [$slug]);
            $filas[] = [$rol['nombre'], count($rol['permisos']), $existe ? 'ya existía' : 'creado'];

            if ($existe || $simulacion) {
                continue;
            }

            $this->conexion->insert('roles', [
                'nombre' => $rol['nombre'],
                'slug' => $slug,
                'descripcion' => $rol['descripcion'],
                'permisos' => json_encode($rol['permisos'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                // Ninguno es protegido: el hospital puede ajustarlos o borrarlos.
                'protegido' => 0,
            ]);

            $creados++;
        }

        if ($creados > 0 && !$simulacion) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'roles',
                descripcion: sprintf('Semillas de roles: %d roles con permisos acotados', $creados),
                usuarioNombre: 'consola',
            ));
        }

        $io->table(['Rol', 'Permisos', 'Estado'], $filas);
        $io->success(sprintf('%d roles creados.', $creados));
        $io->note([
            'Ninguno de estos roles administra usuarios, roles ni ajustes del sitio: eso se queda en «Administrador».',
            'Se asignan a cada funcionario en Panel → Sistema → Usuarios, y se ajustan en Roles y permisos.',
        ]);

        return Command::SUCCESS;
    }
}
