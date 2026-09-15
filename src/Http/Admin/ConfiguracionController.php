<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\ConfiguracionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajustes del sitio: los datos que cambian sin que cambie el código.
 *
 * Esta pantalla es la regla de oro #2 de CLAUDE.md hecha formulario. El PBX,
 * el correo de notificaciones judiciales, la dirección de la sede o el enlace
 * de Instagram estaban en la base de datos desde la Fase 0, pero solo se
 * podían tocar por consola: el hospital tenía que pedir un despliegue para
 * corregir un número de teléfono.
 *
 * Cada ajuste se dibuja según su `tipo`, y cada cambio queda auditado con su
 * valor anterior: si mañana el correo de PQRSDF aparece cambiado, el registro
 * dice quién lo cambió, cuándo y desde qué IP.
 */
final readonly class ConfiguracionController
{
    private const string TITULO = 'Ajustes del sitio';
    private const string RUTA = '/admin/configuracion';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private ConfiguracionRepository $configuracion,
    ) {
    }

    public function formulario(Request $peticion): Response
    {
        $guardados = $this->pantalla->sacarDeLaSesion('configuracion_guardada');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/configuracion/formulario', [
            'grupos' => $this->configuracion->porGrupos(),
            'cambios' => is_array($guardados) ? $guardados : [],
            'mensaje' => $peticion->query->has('guardado')
                ? 'Ajustes guardados. Los cambios ya se ven en el sitio público.'
                : null,
            'sinCambios' => $peticion->query->has('sin_cambios'),
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $enviados = $peticion->request->all('ajustes');
        $valores = [];

        foreach ($enviados as $clave => $valor) {
            if (!is_string($clave)) {
                continue;
            }

            $fila = $this->configuracion->porClave($clave);

            if ($fila === null || (int) $fila['editable'] !== 1) {
                continue;
            }

            $normalizado = $this->normalizar($fila, $valor);

            if ($normalizado !== null) {
                $valores[$clave] = $normalizado;
            }
        }

        // Las casillas no marcadas no llegan en el POST: un booleano ausente
        // es un «no», no un «déjalo como estaba». Sin esto, un ajuste activado
        // no se podría volver a apagar nunca.
        foreach ($this->configuracion->porGrupos() as $grupo) {
            foreach ($grupo['ajustes'] as $ajuste) {
                $clave = (string) $ajuste['clave'];

                if ((string) $ajuste['tipo'] === 'booleano' && !isset($valores[$clave])) {
                    $valores[$clave] = '0';
                }
            }
        }

        $cambios = $this->configuracion->guardar($valores, $this->pantalla->usuarioId());

        if ($cambios === []) {
            return new RedirectResponse(self::RUTA . '?sin_cambios=1', Response::HTTP_SEE_OTHER);
        }

        foreach ($cambios as $clave => $cambio) {
            $this->pantalla->auditar(
                $peticion,
                AccionAuditoria::CambioConfiguracion,
                'configuracion',
                0,
                mb_substr(sprintf(
                    'Cambió «%s» (%s): «%s» → «%s»',
                    $cambio['etiqueta'],
                    $clave,
                    $this->recortar($cambio['antes'] ?? ''),
                    $this->recortar($cambio['despues']),
                ), 0, 500),
            );
        }

        $this->pantalla->guardarEnLaSesion('configuracion_guardada', array_keys($cambios));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * Devuelve el valor listo para guardar, o null si el campo no es válido
     * para su tipo y hay que dejar el valor anterior intacto.
     *
     * @param array<string, mixed> $ajuste
     */
    private function normalizar(array $ajuste, mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return match ((string) $ajuste['tipo']) {
            'booleano' => in_array($valor, ['1', 'on', 'true'], true) ? '1' : '0',
            'numero' => is_numeric($valor) ? $valor : null,
            // Un color mal formado dejaría el sitio con un estilo roto y sin
            // pista de por qué: se rechaza en vez de guardarse.
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $valor) === 1 ? $valor : null,
            'json' => $this->jsonValido($valor) ? $valor : null,
            'texto' => mb_substr($valor, 0, 500),
            default => $valor,
        };
    }

    private function jsonValido(string $valor): bool
    {
        if ($valor === '') {
            return true;
        }

        json_decode($valor);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function recortar(string $texto): string
    {
        return mb_strlen($texto) > 80 ? mb_substr($texto, 0, 80) . '…' : $texto;
    }
}
