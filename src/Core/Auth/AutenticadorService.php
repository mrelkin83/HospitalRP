<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use DateTimeImmutable;

/**
 * Reglas del inicio de sesión: verificación Argon2id, bloqueo por intentos y
 * auditoría de cada intento, bueno o malo (CLAUDE.md §6, Seguridad).
 *
 * La sesión HTTP no vive aquí: este servicio decide *si* alguien puede entrar;
 * `Sesion` decide cómo se recuerda que entró.
 */
final readonly class AutenticadorService
{
    private const int MAX_INTENTOS = 5;
    private const int MINUTOS_BLOQUEO = 15;

    /**
     * Hash señuelo contra el que se verifica cuando el correo no existe: la
     * respuesta tarda lo mismo que con un usuario real y el tiempo deja de
     * delatar qué correos están registrados.
     */
    private const string HASH_SENUELO = '$argon2id$v=19$m=65536,t=4,p=1$RHkvVXlIZnlOMnpxelFoOA$GA8UGW92zNRX9/Mr9f3/TGKPA83IGQJ85YlTkiu52Rc';

    public function __construct(
        private UsuarioRepository $usuarios,
        private HasherPassword $hasher,
        private AuditoriaService $auditoria,
    ) {
    }

    public function intentar(string $correo, string $password, ?string $ip, ?string $userAgent): Autenticacion
    {
        $usuario = $this->usuarios->porCorreo($correo);
        $ahora = new DateTimeImmutable();

        if ($usuario === null) {
            $this->hasher->verificar($password, self::HASH_SENUELO);
            $this->auditar(AccionAuditoria::IntentoFallido, null, sprintf('Correo no registrado: %s', $correo), $ip, $userAgent);

            return Autenticacion::invalida();
        }

        if ($usuario->bloqueadoEn($ahora)) {
            $this->auditar(AccionAuditoria::IntentoFallido, $usuario, 'Intento con la cuenta bloqueada', $ip, $userAgent);

            return Autenticacion::bloqueada();
        }

        if (!$this->hasher->verificar($password, $usuario->hashPassword)) {
            return $this->procesarFallo($usuario, $ahora, $ip, $userAgent);
        }

        if (!$usuario->activo) {
            $this->auditar(AccionAuditoria::IntentoFallido, $usuario, 'Intento con la cuenta desactivada', $ip, $userAgent);

            return Autenticacion::invalida();
        }

        $this->usuarios->registrarAccesoCorrecto($usuario->id, $this->ipBinaria($ip));
        $this->auditar(AccionAuditoria::InicioSesion, $usuario, null, $ip, $userAgent);

        return Autenticacion::correcta($usuario);
    }

    public function cerrarSesion(int $usuarioId, string $nombre, ?string $ip, ?string $userAgent): void
    {
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::CierreSesion,
            entidad: 'sesion',
            usuarioId: $usuarioId,
            usuarioNombre: $nombre,
            ip: $ip,
            userAgent: $userAgent,
        ));
    }

    private function procesarFallo(Usuario $usuario, DateTimeImmutable $ahora, ?string $ip, ?string $userAgent): Autenticacion
    {
        $intentos = $usuario->intentosFallidos + 1;
        $bloqueadoHasta = $intentos >= self::MAX_INTENTOS
            ? $ahora->modify(sprintf('+%d minutes', self::MINUTOS_BLOQUEO))
            : null;

        $this->usuarios->registrarFallo($usuario->id, $intentos, $bloqueadoHasta);
        $this->auditar(
            AccionAuditoria::IntentoFallido,
            $usuario,
            sprintf('Contraseña incorrecta (intento %d de %d)', $intentos, self::MAX_INTENTOS),
            $ip,
            $userAgent,
        );

        if ($bloqueadoHasta !== null) {
            $this->auditar(
                AccionAuditoria::BloqueoCuenta,
                $usuario,
                sprintf('Cuenta bloqueada %d minutos por intentos fallidos', self::MINUTOS_BLOQUEO),
                $ip,
                $userAgent,
            );
        }

        return Autenticacion::invalida();
    }

    private function auditar(AccionAuditoria $accion, ?Usuario $usuario, ?string $descripcion, ?string $ip, ?string $userAgent): void
    {
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'sesion',
            descripcion: $descripcion,
            usuarioId: $usuario?->id,
            usuarioNombre: $usuario?->nombre,
            ip: $ip,
            userAgent: $userAgent,
        ));
    }

    private function ipBinaria(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        $binario = inet_pton($ip);

        return $binario === false ? null : $binario;
    }
}
