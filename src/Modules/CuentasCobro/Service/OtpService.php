<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;

/**
 * Códigos de un solo uso para el ingreso del contratista (§9).
 *
 * Cédula y número de contrato solos NO bastan: el número de contrato es
 * público (SECOP) y detrás hay cuenta bancaria, dirección y teléfono. El
 * código de 6 dígitos viaja por el canal disponible y se guarda hasheado.
 *
 * Canales: WhatsApp y SMS existen como puertos de la plataforma pero aún
 * sin implementación (CLAUDE.md §7 «preparado pero no implementado»), así
 * que hoy el canal real es el correo. La cadena de preferencia del
 * documento (WhatsApp → SMS → correo) queda en `canalPara()`: cuando el
 * hospital contrate mensajería, se enchufa ahí.
 */
final readonly class OtpService
{
    private const int MINUTOS_VIGENCIA = 10;
    private const int MAXIMO_POR_HORA = 5;
    private const int MAXIMO_INTENTOS = 5;

    public function __construct(
        private Connection $conexion,
        private NotificadorCorreo $correo,
    ) {
    }

    /**
     * Genera y envía un código. Devuelve una pista del destino («c···o@…»)
     * o un mensaje de error apto para pantalla.
     *
     * @param array<string, mixed> $contratista Fila de cc_contratistas.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function enviar(array $contratista, string $ip): array
    {
        $contratistaId = (int) $contratista['id'];

        $enviadosUltimaHora = (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM cc_otp WHERE contratista_id = ? AND creado_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$contratistaId],
        );

        if ($enviadosUltimaHora >= self::MAXIMO_POR_HORA) {
            return ['ok' => false, 'mensaje' => 'Ya se enviaron varios códigos en la última hora. Espere un momento y vuelva a intentarlo.'];
        }

        $correo = is_string($contratista['correo'] ?? null) ? trim((string) $contratista['correo']) : '';

        if ($correo === '') {
            return ['ok' => false, 'mensaje' => 'No tenemos un correo suyo registrado. Use la opción de primer ingreso con la fecha de su contrato.'];
        }

        $codigo = (string) random_int(100000, 999999);

        $this->conexion->insert('cc_otp', [
            'contratista_id' => $contratistaId,
            'canal' => 'CORREO',
            'codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT),
            'expira_en' => date('Y-m-d H:i:s', time() + self::MINUTOS_VIGENCIA * 60),
            'ip' => $ip,
        ]);

        try {
            $this->correo->enviar(
                $correo,
                'Su código para el portal de cuentas de cobro',
                "Su código de ingreso es: {$codigo}\n\n"
                . 'Vence en ' . self::MINUTOS_VIGENCIA . " minutos y solo sirve una vez.\n"
                . "Si usted no lo pidió, ignore este mensaje.\n\n"
                . 'E.S.E. Hospital Rosario Pumarejo de López',
            );
        } catch (\Throwable) {
            return ['ok' => false, 'mensaje' => 'No pudimos enviar el código en este momento. Inténtelo de nuevo en unos minutos.'];
        }

        return ['ok' => true, 'mensaje' => 'Le enviamos un código al correo ' . $this->ofuscarCorreo($correo) . '. Escríbalo abajo.'];
    }

    /**
     * Verifica un código: vigente, sin usar y con menos de 5 intentos.
     * Consume el código al acertar.
     */
    public function verificar(int $contratistaId, string $codigo): bool
    {
        if (preg_match('/^\d{6}$/', $codigo) !== 1) {
            return false;
        }

        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, codigo_hash, intentos FROM cc_otp
              WHERE contratista_id = ? AND usado_en IS NULL AND expira_en > NOW()
              ORDER BY id DESC LIMIT 3',
            [$contratistaId],
        );

        foreach ($filas as $fila) {
            if ((int) $fila['intentos'] >= self::MAXIMO_INTENTOS) {
                continue;
            }

            if (password_verify($codigo, (string) $fila['codigo_hash'])) {
                $this->conexion->update('cc_otp', ['usado_en' => date('Y-m-d H:i:s')], ['id' => $fila['id']]);

                return true;
            }

            $this->conexion->executeStatement('UPDATE cc_otp SET intentos = intentos + 1 WHERE id = ?', [$fila['id']]);
        }

        return false;
    }

    /** ¿Esta IP ya quemó sus 10 intentos fallidos de la última hora? */
    public function ipBloqueada(string $ip): bool
    {
        $intentos = (int) $this->conexion->fetchOne(
            'SELECT COALESCE(SUM(intentos), 0) FROM cc_otp WHERE ip = ? AND creado_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$ip],
        );

        return $intentos >= 10;
    }

    /** «elkin985@gmail.com» → «e···5@gmail.com»: pista sin regalar el dato. */
    private function ofuscarCorreo(string $correo): string
    {
        $partes = explode('@', $correo, 2);
        $usuario = $partes[0];
        $dominio = $partes[1] ?? '';

        if (mb_strlen($usuario) <= 2) {
            return $usuario[0] . '···@' . $dominio;
        }

        return mb_substr($usuario, 0, 1) . '···' . mb_substr($usuario, -1) . '@' . $dominio;
    }
}
