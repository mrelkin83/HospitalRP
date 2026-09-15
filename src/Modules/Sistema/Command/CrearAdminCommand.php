<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\PoliticaPassword;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crea (o completa) el rol administrador y un usuario con ese rol.
 *
 * Es la puerta de entrada inicial al panel: sin este comando no existe ningún
 * usuario y el login no tiene a quién dejar pasar. La gestión de usuarios
 * desde el panel llega con el módulo Sistema; esto es solo el arranque.
 */
final class CrearAdminCommand extends Command
{
    public function __construct(
        private readonly Connection $conexion,
        private readonly HasherPassword $hasher,
        private readonly AuditoriaService $auditoria,
        private readonly PoliticaPassword $politica,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('usuarios:crear-admin')
            ->setDescription('Crea un usuario administrador para entrar al panel')
            ->addOption('nombre', null, InputOption::VALUE_REQUIRED, 'Nombre completo del funcionario')
            ->addOption('correo', null, InputOption::VALUE_REQUIRED, 'Correo institucional (será el usuario de ingreso)')
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Contraseña inicial. Si se omite, se genera una aleatoria y se muestra una sola vez',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $nombre = (string) $input->getOption('nombre');
        $correo = (string) $input->getOption('correo');
        $password = $input->getOption('password');

        if ($nombre === '' || $correo === '') {
            $io->error('Faltan datos: --nombre y --correo son obligatorios.');

            return Command::INVALID;
        }

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $io->error(sprintf('El correo «%s» no es válido.', $correo));

            return Command::INVALID;
        }

        $yaExiste = $this->conexion->fetchOne(
            'SELECT id FROM usuarios WHERE correo = ?',
            [$correo],
        );

        if ($yaExiste !== false) {
            $io->error(sprintf('Ya existe un usuario con el correo «%s».', $correo));

            return Command::FAILURE;
        }

        $passwordGenerada = false;

        if (!is_string($password) || $password === '') {
            $password = $this->politica->generar();
            $passwordGenerada = true;
        } else {
            // Una clave dictada en la consola pasa por la misma política que
            // el panel. Si no, el arranque del sitio sería el único punto por
            // donde entraría una contraseña débil, y es justo el de la cuenta
            // con más permisos.
            $problemas = $this->politica->problemas($password, $correo, $nombre);

            if ($problemas !== []) {
                $io->error('La contraseña no cumple la política del panel:');
                $io->listing($problemas);

                return Command::INVALID;
            }
        }

        $usuarioId = $this->conexion->transactional(function () use ($nombre, $correo, $password): int {
            $rolId = $this->rolAdministradorId();

            $this->conexion->insert('usuarios', [
                'nombre' => $nombre,
                'correo' => $correo,
                'hash_password' => $this->hasher->hash($password),
                'rol_id' => $rolId,
                'activo' => 1,
                // La contraseña inicial la conoce quien corrió la consola:
                // el sistema obliga a cambiarla en el primer ingreso.
                'debe_cambiar_pass' => 1,
            ]);

            return (int) $this->conexion->lastInsertId();
        });

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Creacion,
            entidad: 'usuarios',
            entidadId: $usuarioId,
            descripcion: sprintf('Usuario administrador «%s» creado desde la consola', $correo),
            usuarioNombre: 'consola',
        ));

        $io->success(sprintf('Usuario administrador creado: %s (id %d).', $correo, $usuarioId));

        if ($passwordGenerada) {
            $io->text([
                'Contraseña inicial (se muestra una sola vez):',
                '',
                '    ' . $password,
                '',
                'Deberá cambiarse en el primer ingreso.',
            ]);
        }

        return Command::SUCCESS;
    }

    private function rolAdministradorId(): int
    {
        $id = $this->conexion->fetchOne("SELECT id FROM roles WHERE slug = 'administrador'");

        if ($id !== false) {
            return (int) $id;
        }

        $this->conexion->insert('roles', [
            'nombre' => 'Administrador',
            'slug' => 'administrador',
            'descripcion' => 'Acceso total al panel. Rol protegido del sistema.',
            // El comodín se resuelve en el paso 8 (permisos granulares).
            'permisos' => '["*"]',
            'protegido' => 1,
        ]);

        return (int) $this->conexion->lastInsertId();
    }
}

