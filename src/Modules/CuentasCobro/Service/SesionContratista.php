<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Core\Auth\Sesion;

/**
 * La sesión del contratista en el portal /cuentas/.
 *
 * ADAPTACIÓN declarada sobre §9 del documento: el documento pide «cookie
 * distinta», pero PHP maneja UNA sesión nativa por petición y la plataforma
 * ya la gobierna con Sesion (HttpOnly, Secure, SameSite=Strict, ID
 * regenerado al autenticar). Duplicar el mecanismo a mano sería reconstruir
 * peor lo que ya existe, así que el portal usa la MISMA cookie endurecida
 * con su propio espacio de claves `cc_*`, que nunca se cruza con la del
 * panel: autenticarse aquí no da acceso allá ni al revés.
 *
 * Lo que sí es propio del portal: la inactividad expira a los 30 minutos,
 * pensando en un computador prestado o familiar.
 */
final readonly class SesionContratista
{
    private const string CLAVE_ID = 'cc_contratista_id';
    private const string CLAVE_CONTRATO = 'cc_contrato_id';
    private const string CLAVE_ULTIMO_USO = 'cc_ultimo_uso';
    private const string CLAVE_PENDIENTE = 'cc_pendiente';
    private const string CLAVE_PERSISTENTE = 'cc_persistente';
    private const int MINUTOS_INACTIVIDAD = 30;

    /**
     * «Mantener la sesión iniciada»: 30 días.
     *
     * La inactividad corta de arriba está pensada para un computador
     * prestado; quien marca la casilla dice que el equipo es suyo, y a
     * un contratista que entra una vez al mes pedirle la clave cada media
     * hora no protege nada, solo estorba.
     */
    private const int MINUTOS_PERSISTENTE = 43200;

    public function __construct(private Sesion $sesion)
    {
    }

    /** El ingreso es por contrato: cédula o número de contrato + contraseña. */
    public function abrir(int $contratistaId, int $contratoId, bool $persistente = false): void
    {
        $this->sesion->iniciar();

        // Nueva identidad de sesión al autenticar (fijación de sesión),
        // igual que hace Sesion::autenticar con el panel.
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[self::CLAVE_ID] = $contratistaId;
        $_SESSION[self::CLAVE_CONTRATO] = $contratoId;
        $_SESSION[self::CLAVE_ULTIMO_USO] = time();
        $_SESSION[self::CLAVE_PERSISTENTE] = $persistente;
        unset($_SESSION[self::CLAVE_PENDIENTE]);
    }

    /**
     * Entre «le enviamos el código» y «el código es correcto» hay que
     * recordar a quién se le envió, sin darle sesión todavía.
     */
    public function recordarPendiente(int $contratistaId, int $contratoId): void
    {
        $this->sesion->iniciar();
        $_SESSION[self::CLAVE_PENDIENTE] = [$contratistaId, $contratoId, time()];
    }

    /** @return ?array{0: int, 1: int} [contratistaId, contratoId] */
    public function pendiente(): ?array
    {
        $this->sesion->iniciar();
        $dato = $_SESSION[self::CLAVE_PENDIENTE] ?? null;

        // La antesala vive lo mismo que el código: 10 minutos.
        if (!is_array($dato) || count($dato) !== 3 || time() - (int) $dato[2] > 600) {
            return null;
        }

        return [(int) $dato[0], (int) $dato[1]];
    }

    public function contratoId(): ?int
    {
        if ($this->contratistaId() === null) {
            return null;
        }

        $contrato = $_SESSION[self::CLAVE_CONTRATO] ?? null;

        return is_int($contrato) ? $contrato : null;
    }

    /**
     * El contratista autenticado, o nulo si no hay sesión o se venció por
     * inactividad. Cada consulta renueva el reloj: mientras esté llenando
     * su cuenta, no se le echa.
     */
    public function contratistaId(): ?int
    {
        $this->sesion->iniciar();

        $id = $_SESSION[self::CLAVE_ID] ?? null;

        if (!is_int($id)) {
            return null;
        }

        $ultimoUso = $_SESSION[self::CLAVE_ULTIMO_USO] ?? 0;
        $minutos = ($_SESSION[self::CLAVE_PERSISTENTE] ?? false) === true
            ? self::MINUTOS_PERSISTENTE
            : self::MINUTOS_INACTIVIDAD;

        if (!is_int($ultimoUso) || time() - $ultimoUso > $minutos * 60) {
            $this->cerrar();

            return null;
        }

        $_SESSION[self::CLAVE_ULTIMO_USO] = time();

        return $id;
    }

    public function cerrar(): void
    {
        $this->sesion->iniciar();
        unset(
            $_SESSION[self::CLAVE_ID],
            $_SESSION[self::CLAVE_CONTRATO],
            $_SESSION[self::CLAVE_ULTIMO_USO],
            $_SESSION[self::CLAVE_PENDIENTE],
        );
    }

    public function tokenCsrf(): string
    {
        $this->sesion->iniciar();

        return $this->sesion->tokenCsrf();
    }

    public function validarCsrf(?string $token): bool
    {
        $this->sesion->iniciar();

        return $this->sesion->validarCsrf($token);
    }
}
