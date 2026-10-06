<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\PoliticaPassword;
use App\Modules\Sistema\Repository\UsuarioAdminRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Devuelve el acceso al panel a una cuenta que perdió su contraseña.
 *
 * El restablecimiento normal lo hace un administrador desde el panel; este
 * comando existe para el caso en que no queda nadie dentro que pueda hacerlo
 * —el administrador único olvidó la clave o se bloqueó a sí mismo—, y la
 * alternativa sería un UPDATE a mano contra la tabla, sin política de
 * contraseña ni rastro en auditoría.
 *
 * Entrega siempre una credencial temporal: quien corre la consola no debe
 * quedarse con la contraseña definitiva del funcionario, así que el primer
 * ingreso obliga a cambiarla.
 */
final class RestablecerPasswordCommand extends Command
{
    public function __construct(
        private readonly UsuarioAdminRepository $usuarios,
        private readonly HasherPassword $hasher,
        private readonly AuditoriaService $auditoria,
        private readonly PoliticaPassword $politica,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('usuarios:restablecer-pass')
            ->setDescription('Genera una contraseña temporal para un usuario del panel y desbloquea su cuenta')
            ->addOption('correo', null, InputOption::VALUE_REQUIRED, 'Correo de ingreso de la cuenta')
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Contraseña a fijar. Si se omite, se genera una temporal y se muestra una sola vez',
            )
            ->addOption(
                'reactivar',
                null,
                InputOption::VALUE_NONE,
                'Vuelve a activar la cuenta si estaba inactiva o dada de baja',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $correo = trim((string) $input->getOption('correo'));

        if ($correo === '') {
            $io->error('Falta --correo: indica la cuenta cuyo acceso se restablece.');

            return Command::INVALID;
        }

        $usuario = $this->usuarios->porCorreo($correo);

        if ($usuario === null) {
            $io->error(sprintf('No hay ningún usuario con el correo «%s».', $correo));

            return Command::FAILURE;
        }

        $id = (int) $usuario['id'];
        $inhabilitada = (int) $usuario['activo'] !== 1 || $usuario['eliminado_en'] !== null;

        // Restablecer la clave de una cuenta apagada no devuelve el acceso:
        // el login la rechaza igual. Se avisa en vez de dejar al funcionario
        // probando una contraseña que nunca va a funcionar.
        if ($inhabilitada && !$input->getOption('reactivar')) {
            $io->error([
                sprintf('La cuenta «%s» está inactiva o dada de baja.', $correo),
                'Repite el comando con --reactivar si de verdad debe volver a entrar.',
            ]);

            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        $temporal = false;

        if (!is_string($password) || $password === '') {
            $password = $this->politica->generar();
            $temporal = true;
        } else {
            $problemas = $this->politica->problemas(
                $password,
                $correo,
                (string) $usuario['nombre'],
            );

            if ($problemas !== []) {
                $io->error('La contraseña no cumple la política del panel:');
                $io->listing($problemas);

                return Command::INVALID;
            }
        }

        // Siempre con cambio obligatorio, incluso si la clave vino dictada:
        // la conoce quien corrió la consola, no solo su dueño.
        $this->usuarios->cambiarPassword($id, $this->hasher->hash($password), true);

        if ($inhabilitada) {
            $this->usuarios->actualizar($id, ['activo' => 1, 'eliminado_en' => null]);
        }

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::RestablecerPassword,
            entidad: 'usuarios',
            entidadId: $id,
            descripcion: sprintf(
                'Contraseña de «%s» restablecida desde la consola%s',
                $correo,
                $inhabilitada ? ' (cuenta reactivada)' : '',
            ),
            usuarioNombre: 'consola',
        ));

        $io->success(sprintf('Acceso restablecido para %s (id %d).', $correo, $id));

        if ($inhabilitada) {
            $io->text('La cuenta estaba inhabilitada y se reactivó.');
        }

        if ($temporal) {
            $io->text([
                'Contraseña temporal (se muestra una sola vez):',
                '',
                '    ' . $password,
                '',
                'Deberá cambiarse en el primer ingreso.',
            ]);
        }

        return Command::SUCCESS;
    }
}
