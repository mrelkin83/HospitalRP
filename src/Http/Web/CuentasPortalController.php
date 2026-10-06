<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Auth\HasherPassword;
use App\Core\Support\Vistas;
use App\Modules\CuentasCobro\Repository\ContratistaRepository;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\SesionContratista;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El portal del contratista: ingreso (P0), inicio (P1) y «Mis datos»
 * (P1a) del documento del módulo.
 *
 * Todo está diseñado para adultos mayores con poca experiencia
 * tecnológica (§2): una tarea por pantalla, un botón grande, lenguaje
 * cotidiano y nada de contraseñas. La identidad es cédula + número de
 * contrato + código de un solo uso.
 */
final readonly class CuentasPortalController
{
    /**
     * Cookie de «recordar mi cuenta». Guarda SOLO con qué dato entra y
     * cuál es —cédula o número de contrato—, nunca la contraseña: sirve
     * para rellenar el campo, no para entrar. La sesión viaja aparte, en
     * su propia cookie endurecida.
     */
    private const string COOKIE_CUENTA = 'cc_recordar';

    private const int DIAS_RECUERDO = 60;

    public function __construct(
        private Vistas $vistas,
        private SesionContratista $sesion,
        private ContratistaRepository $contratistas,
        private CuentaRepository $cuentas,
        private ParametrosCuentasRepository $parametros,
        private HasherPassword $hasher,
        private ArchivosCuentas $archivos,
        private Connection $conexion,
    ) {
    }

    // ------------------------------------------------------------------
    // P0 — Ingreso
    // ------------------------------------------------------------------

    public function ingreso(Request $peticion): Response
    {
        if ($this->sesion->contratoId() !== null) {
            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        [$modo, $identificador] = $this->cuentaRecordada($peticion);

        return $this->pagina('publico/cuentas/ingreso', [
            'error' => $this->flash('cc_error'),
            'mensaje' => $this->flash('cc_mensaje'),
            'modo' => $modo,
            'identificador' => $identificador,
            // Si el dato vino de la cookie, la casilla ya estaba marcada:
            // desmarcarla sin querer y perder el recuerdo sería molesto.
            'recordar' => $identificador !== '',
            'persistente' => false,
        ] + self::textosDelModo($modo));
    }

    /**
     * Entrar con cédula O número de contrato, más la contraseña propia
     * (el OTP de seis dígitos se retiró: dificultaba el proceso).
     */
    public function entrar(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $ip = (string) $peticion->getClientIp();

        if ($this->ipBloqueada($ip)) {
            return $this->volverConError('/cuentas/ingreso', 'Demasiados intentos desde esta conexión. Espere una hora e inténtelo de nuevo.');
        }

        $par = $this->buscarPorIdentificador(
            $peticion->request->getString('modo'),
            $peticion->request->getString('identificador'),
        );

        if ($par === null) {
            $this->registrarIntentoFallido($ip);

            return $this->volverConError('/cuentas/ingreso', 'No encontramos un contrato activo con ese dato. Revise su copia del contrato o comuníquese con la oficina de Contratación del hospital.');
        }

        [$contratista, $contrato] = $par;

        if ($contratista['password_hash'] === null || $contratista['password_hash'] === '') {
            $this->guardarFlash('cc_mensaje', 'Todavía no tiene contraseña. Créela aquí con la fecha de su contrato.');

            return new RedirectResponse('/cuentas/crear-contrasena', Response::HTTP_SEE_OTHER);
        }

        if (!$this->hasher->verificar($peticion->request->getString('contrasena'), (string) $contratista['password_hash'])) {
            $this->registrarIntentoFallido($ip, (int) $contratista['id']);

            return $this->volverConError('/cuentas/ingreso', 'La contraseña no coincide. Si la olvidó, use «Olvidé mi contraseña» aquí abajo.');
        }

        $this->sesion->abrir(
            (int) $contratista['id'],
            (int) $contrato['id'],
            $peticion->request->getBoolean('persistente'),
        );

        $respuesta = new RedirectResponse(
            (int) $contratista['datos_completos'] === 1 ? '/cuentas/inicio' : '/cuentas/mis-datos',
            Response::HTTP_SEE_OTHER,
        );

        $respuesta->headers->setCookie($this->cookieCuenta(
            $peticion->request->getBoolean('recordar') ? $peticion->request->getString('modo') : null,
            $peticion->request->getString('identificador'),
        ));

        return $respuesta;
    }

    /**
     * La cookie que recuerda con qué dato entra esta persona.
     *
     * Con `$modo = null` la borra: al desmarcar la casilla, el dato tiene
     * que desaparecer del equipo —que es justo lo que se espera al
     * desmarcarla en un computador prestado—.
     */
    private function cookieCuenta(?string $modo, string $identificador): Cookie
    {
        $valor = $modo === null ? '' : ($modo === 'contrato' ? 'contrato' : 'cedula') . '|' . $identificador;

        return Cookie::create(
            name: self::COOKIE_CUENTA,
            value: $valor,
            expire: $valor === '' ? 1 : time() + self::DIAS_RECUERDO * 24 * 3600,
            path: '/cuentas',
            httpOnly: true,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    /**
     * Lo recordado en este equipo.
     *
     * @return array{0: string, 1: string} [modo, identificador]
     */
    private function cuentaRecordada(Request $peticion): array
    {
        $crudo = (string) $peticion->cookies->get(self::COOKIE_CUENTA, '');

        if ($crudo === '' || !str_contains($crudo, '|')) {
            return ['cedula', ''];
        }

        [$modo, $identificador] = explode('|', $crudo, 2);
        $identificador = trim(mb_substr($identificador, 0, 40));

        // La cookie la escribe el navegador y se puede manipular: lo que
        // salga de ella se trata como lo que es, un dato sin confirmar que
        // solo rellena un campo.
        if (preg_match('/^[0-9A-Za-z\-]{0,40}$/', $identificador) !== 1) {
            return ['cedula', ''];
        }

        return [$modo === 'contrato' ? 'contrato' : 'cedula', $identificador];
    }

    /** Crear (o volver a crear, tras un restablecimiento) la contraseña. */
    public function crearPasswordFormulario(Request $peticion): Response
    {
        return $this->paginaDeClave($peticion, false);
    }

    /**
     * «Olvidé mi contraseña»: la misma pantalla, pero aceptando que ya
     * exista una.
     *
     * La prueba de identidad es la de siempre —la fecha de suscripción,
     * que está en su copia del contrato y no es pública— más el bloqueo
     * por intentos desde la misma IP. No se abre una puerta nueva: es la
     * misma que ya usaba quien entraba por primera vez. La alternativa
     * era que 1.500 contratistas llamaran a Contratación.
     */
    public function recuperarFormulario(Request $peticion): Response
    {
        return $this->paginaDeClave($peticion, true);
    }

    private function paginaDeClave(Request $peticion, bool $recuperando): Response
    {
        [$modo, $identificador] = $this->cuentaRecordada($peticion);

        return $this->pagina('publico/cuentas/clave', [
            'accion' => $recuperando ? '/cuentas/recuperar-contrasena' : '/cuentas/crear-contrasena',
            'titulo' => $recuperando ? 'Recuperar su contraseña' : 'Crear su contraseña',
            'intro' => $recuperando
                ? 'Si olvidó su contraseña, confirme la fecha de su contrato y escriba una nueva.'
                : 'Es su primera vez: confirme la fecha de su contrato y escoja una contraseña.',
            'boton' => $recuperando ? 'Cambiar la contraseña y entrar' : 'Crear contraseña y entrar',
            'error' => $this->flash('cc_error'),
            'mensaje' => $this->flash('cc_mensaje'),
            'modo' => $modo,
            'identificador' => $identificador,
        ] + self::textosDelModo($modo));
    }

    /**
     * Cómo se le pide el dato de identificación según el desplegable.
     *
     * «Cédula o número de contrato» obliga a decidir otra vez lo que ya se
     * decidió arriba. El servidor pinta el texto del modo con el que carga
     * la página y `cuentas-acceso.js` lo cambia al vuelo; sin JavaScript
     * la pantalla sigue diciendo lo correcto.
     *
     * @return array{rotulo: string, ayuda: string, ejemplo: string}
     */
    private static function textosDelModo(string $modo): array
    {
        if ($modo === 'contrato') {
            return [
                'rotulo' => 'Ingrese su número de contrato',
                'ayuda' => 'Está en la primera página de su copia del contrato. Por ejemplo: 1407-2026.',
                'ejemplo' => '1407-2026',
            ];
        }

        return [
            'rotulo' => 'Ingrese su número de cédula',
            'ayuda' => 'Solo los números, sin puntos ni comas. Por ejemplo: 1100000002.',
            'ejemplo' => '1100000002',
        ];
    }

    public function crearPassword(Request $peticion): Response
    {
        return $this->guardarClaveNueva($peticion, false);
    }

    /** El envío de «olvidé mi contraseña». */
    public function recuperar(Request $peticion): Response
    {
        return $this->guardarClaveNueva($peticion, true);
    }

    /**
     * Fija la contraseña tras confirmar la identidad con la fecha del
     * contrato, y abre la sesión.
     *
     * Crear y recuperar son el mismo trámite con una sola diferencia: al
     * crear, tener ya una contraseña es un error («use el ingreso»); al
     * recuperar, es justamente el caso. Escribirlo dos veces habría
     * duplicado el control de intentos y la verificación de la fecha, que
     * es exactamente donde no conviene tener dos copias.
     */
    private function guardarClaveNueva(Request $peticion, bool $recuperando): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $volverA = $recuperando ? '/cuentas/recuperar-contrasena' : '/cuentas/crear-contrasena';
        $ip = (string) $peticion->getClientIp();

        if ($this->ipBloqueada($ip)) {
            return $this->volverConError($volverA, 'Demasiados intentos desde esta conexión. Espere una hora.');
        }

        $r = $peticion->request;
        $par = $this->buscarPorIdentificador($r->getString('modo'), $r->getString('identificador'));

        if ($par === null) {
            $this->registrarIntentoFallido($ip);

            return $this->volverConError($volverA, 'No encontramos un contrato activo con ese dato.');
        }

        [$contratista, $contrato] = $par;
        $tieneClave = $contratista['password_hash'] !== null && $contratista['password_hash'] !== '';

        if (!$recuperando && $tieneClave) {
            return $this->volverConError('/cuentas/ingreso', 'Usted ya tiene contraseña. Si la olvidó, use «Olvidé mi contraseña».');
        }

        // La verificación de identidad: la fecha de suscripción, que está
        // en su copia del contrato (y no es pública como el número).
        if (trim($r->getString('fecha_suscripcion')) !== (string) $contrato['fecha_suscripcion']) {
            $this->registrarIntentoFallido($ip, (int) $contratista['id']);

            return $this->volverConError($volverA, 'La fecha no coincide con la del contrato. Revísela en su copia.');
        }

        $contrasena = $r->getString('contrasena');

        if (mb_strlen($contrasena) < 8) {
            return $this->volverConError($volverA, 'La contraseña debe tener al menos 8 caracteres. Puede ser una frase fácil de recordar.');
        }

        if ($contrasena !== $r->getString('contrasena_confirmacion')) {
            return $this->volverConError($volverA, 'Las dos contraseñas no coinciden. Escríbalas de nuevo.');
        }

        $this->contratistas->actualizar((int) $contratista['id'], [
            'password_hash' => $this->hasher->hash($contrasena),
        ]);

        // Cambiar la clave limpia el castigo por intentos fallidos: quien
        // acaba de probar quién es no tiene por qué arrastrarlo.
        $this->olvidarIntentos($ip, (int) $contratista['id']);
        $this->sesion->abrir((int) $contratista['id'], (int) $contrato['id']);

        return new RedirectResponse(
            (int) $contratista['datos_completos'] === 1 ? '/cuentas/inicio' : '/cuentas/mis-datos',
            Response::HTTP_SEE_OTHER,
        );
    }

    public function salir(Request $peticion): Response
    {
        $this->sesion->cerrar();

        return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // P1 — Inicio del contratista
    // ------------------------------------------------------------------

    public function inicio(Request $peticion): Response
    {
        $contexto = $this->contextoAutenticado();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista, $contrato] = $contexto;

        if ((int) $contratista['datos_completos'] !== 1) {
            return new RedirectResponse('/cuentas/mis-datos', Response::HTTP_SEE_OTHER);
        }

        $contratoId = (int) $contrato['id'];

        return $this->pagina('publico/cuentas/inicio', [
            'contratista' => $contratista,
            'contrato' => $contrato,
            'editable' => $this->cuentas->editableDelContrato($contratoId),
            'enRevision' => $this->cuentas->enRevisionDelContrato($contratoId),
            'cuotaDisponible' => $this->cuentas->proximaCuotaDisponible($contratoId),
            'cuotaFutura' => $this->cuentas->proximaCuotaFutura($contratoId),
            'anteriores' => $this->cuentas->delContrato($contratoId),
            'mensaje' => $this->flash('cc_mensaje'),
            'error' => $this->flash('cc_error'),
        ]);
    }

    // ------------------------------------------------------------------
    // P1a — Mis datos (obligatoria mientras datos_completos = 0)
    // ------------------------------------------------------------------

    public function misDatos(Request $peticion): Response
    {
        $contexto = $this->contextoAutenticado();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista] = $contexto;
        $pedido = max(1, min(3, $peticion->query->getInt('paso', 1)));
        $permitido = self::pasoMaximo($contratista);

        // Adelantarse está prohibido, volver atrás no. Sin esto, escribir
        // «?paso=3» en la barra del navegador saltaba la validación entera
        // del paso 2: los datos del banco se guardan al ENVIAR ese paso, y
        // quien no lo envía nunca no los llena nunca.
        if ($pedido > $permitido) {
            $this->guardarFlash('cc_error', sprintf(
                'Antes de seguir hay que terminar el paso %d: falta %s.',
                $permitido,
                implode(', ', self::loQueFaltaDelPaso($contratista, $permitido)),
            ));

            return new RedirectResponse('/cuentas/mis-datos?paso=' . $permitido, Response::HTTP_SEE_OTHER);
        }

        $paso = $pedido;

        return $this->pagina('publico/cuentas/mis-datos', [
            'contratista' => $contratista,
            'paso' => $paso,
            // El banco guardado va en la lista aunque se haya retirado del
            // catálogo: quitarlo de la oferta no puede borrárselo a quien
            // ya lo tenía.
            'bancos' => $this->parametros->bancos((string) ($contratista['banco'] ?? '')),
            'faltantes' => self::loQueFalta($contratista),
            'error' => $this->flash('cc_error'),
            'mensaje' => $this->flash('cc_mensaje'),
        ]);
    }

    public function guardarMisDatos(Request $peticion): Response
    {
        if (($rechazo = $this->rechazoCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $contexto = $this->contextoAutenticado();

        if ($contexto instanceof Response) {
            return $contexto;
        }

        [$contratista] = $contexto;
        $contratistaId = (int) $contratista['id'];
        $paso = max(1, min(3, $peticion->attributes->getInt('paso')));
        $r = $peticion->request;

        if ($paso === 1) {
            $celular = trim($r->getString('celular'));
            $correo = trim($r->getString('correo'));

            if (preg_match('/^3\d{9}$/', $celular) !== 1) {
                return $this->volverConError('/cuentas/mis-datos?paso=1', 'El celular debe tener 10 números y empezar por 3, por ejemplo 3001234567.');
            }

            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                return $this->volverConError('/cuentas/mis-datos?paso=1', 'Ese correo no parece completo. Revíselo, por ejemplo: nombre@correo.com.');
            }

            $this->contratistas->actualizar($contratistaId, [
                'celular' => $celular,
                'correo' => $correo !== '' ? $correo : null,
            ]);

            return new RedirectResponse('/cuentas/mis-datos?paso=2', Response::HTTP_SEE_OTHER);
        }

        if ($paso === 2) {
            $banco = trim($r->getString('banco'));
            $numeroCuenta = trim($r->getString('numero_cuenta'));
            $tipoCuenta = $r->getString('tipo_cuenta');

            if ($banco === '' || $numeroCuenta === '' || !in_array($tipoCuenta, ['AHORROS', 'CORRIENTE'], true)) {
                return $this->volverConError('/cuentas/mis-datos?paso=2', 'Complete el banco, el tipo de cuenta y el número de cuenta: ahí le consignan su pago.');
            }

            // El desplegable manda: un banco que no esté en el catálogo solo
            // puede venir de un formulario manipulado.
            if (!in_array($banco, $this->parametros->bancos((string) ($contratista['banco'] ?? '')), true)) {
                return $this->volverConError('/cuentas/mis-datos?paso=2', 'Elija su banco de la lista.');
            }

            $campos = [
                'banco' => mb_substr($banco, 0, 100),
                'tipo_cuenta' => $tipoCuenta,
                'numero_cuenta' => mb_substr($numeroCuenta, 0, 40),
            ];

            $certificacion = $peticion->files->get('certificacion_bancaria');

            if ($certificacion instanceof UploadedFile) {
                try {
                    $campos['certificacion_bancaria_archivo'] = $this->archivos->guardar($certificacion, 'contratistas');
                } catch (\InvalidArgumentException $e) {
                    return $this->volverConError('/cuentas/mis-datos?paso=2', $e->getMessage());
                }
            } elseif (($contratista['certificacion_bancaria_archivo'] ?? null) === null) {
                // Es el soporte de la cuenta donde Tesorería consigna: sin
                // él, el número de cuenta es una cifra que nadie verificó.
                return $this->volverConError(
                    '/cuentas/mis-datos?paso=2',
                    'Falta la certificación bancaria. La emite su banco y puede tomarle una foto; sin ella no podemos consignarle.',
                );
            }

            $this->contratistas->actualizar($contratistaId, $campos);

            return new RedirectResponse('/cuentas/mis-datos?paso=3', Response::HTTP_SEE_OTHER);
        }

        return $this->guardarDocumentosYFirma($peticion, $contratista);
    }

    /**
     * Paso 3 de «Mis datos»: cédula, RUT, firma (subida o dibujada) y la
     * autorización de tratamiento de datos con fecha e IP (Ley 1581).
     *
     * @param array<string, mixed> $contratista
     */
    private function guardarDocumentosYFirma(Request $peticion, array $contratista): Response
    {
        $contratistaId = (int) $contratista['id'];
        $volverA = '/cuentas/mis-datos?paso=3';
        $campos = [];

        // La misma puerta del paso 3, también para quien envíe el
        // formulario sin haber pasado por la pantalla.
        if (self::pasoMaximo($contratista) < 3) {
            $pendiente = self::pasoMaximo($contratista);

            return $this->volverConError('/cuentas/mis-datos?paso=' . $pendiente, sprintf(
                'Antes de subir sus documentos hay que terminar el paso %d: falta %s.',
                $pendiente,
                implode(', ', self::loQueFaltaDelPaso($contratista, $pendiente)),
            ));
        }

        foreach (['rut_archivo' => 'rut'] as $columna => $campo) {
            $archivo = $peticion->files->get($campo);

            if ($archivo instanceof UploadedFile) {
                try {
                    $campos[$columna] = $this->archivos->guardar($archivo, 'contratistas');
                } catch (\InvalidArgumentException $e) {
                    return $this->volverConError($volverA, $e->getMessage());
                }
            }
        }

        if (($problema = $this->guardarCedula($peticion, $contratista, $campos)) !== null) {
            return $this->volverConError($volverA, $problema);
        }

        $firmaSubida = $peticion->files->get('firma');
        $firmaDibujada = trim($peticion->request->getString('firma_dibujada'));

        try {
            if ($firmaSubida instanceof UploadedFile) {
                $campos['firma_archivo'] = $this->archivos->guardar($firmaSubida, 'firmas');
                $campos['firma_cargada_en'] = date('Y-m-d H:i:s');
            } elseif ($firmaDibujada !== '') {
                $campos['firma_archivo'] = $this->archivos->guardarFirmaDibujada($firmaDibujada, 'firmas');
                $campos['firma_cargada_en'] = date('Y-m-d H:i:s');
            }
        } catch (\InvalidArgumentException $e) {
            return $this->volverConError($volverA, $e->getMessage());
        }

        // La autorización de datos personales: casilla NO premarcada, con
        // evidencia de fecha e IP (CLAUDE.md §6 y §3 del módulo).
        if ($contratista['acepto_tratamiento_datos_en'] === null) {
            if (!$peticion->request->getBoolean('acepto_datos')) {
                return $this->volverConError($volverA, 'Para continuar debe leer y aceptar la autorización de tratamiento de datos personales.');
            }

            $campos['acepto_tratamiento_datos_en'] = date('Y-m-d H:i:s');
            $campos['acepto_tratamiento_datos_ip'] = (string) $peticion->getClientIp();
        }

        if ($campos !== []) {
            $this->contratistas->actualizar($contratistaId, $campos);
        }

        // ¿Ya está todo? Contacto, banco con su certificación, firma y anexos.
        $al_dia = $this->contratistas->porId($contratistaId);
        $faltantes = $al_dia === null ? ['sus datos'] : self::loQueFalta($al_dia);

        if ($faltantes === []) {
            $this->contratistas->actualizar($contratistaId, ['datos_completos' => 1]);
            $this->guardarFlash('cc_mensaje', 'Sus datos quedaron completos. Ya puede presentar su cuenta de cobro.');

            return new RedirectResponse('/cuentas/inicio', Response::HTTP_SEE_OTHER);
        }

        // Se dice QUÉ falta y no «revise los documentos»: mandar a alguien a
        // buscar en tres pantallas lo que el sistema ya sabe es maltratarlo.
        return $this->volverConError($volverA, 'Todavía falta: ' . implode(', ', $faltantes) . '.');
    }

    /**
     * La copia de la cédula: un PDF con las dos caras, o dos fotos.
     *
     * El escaneo ya viene completo en un archivo; la cámara del celular no
     * puede con las dos caras de una sola toma. Por eso el campo admite
     * varios archivos y la exigencia depende de lo que suban, no de un
     * número fijo de casillas.
     *
     * @param array<string, mixed>  $contratista
     * @param array<string, mixed>  $campos      Se le añaden las columnas a guardar.
     *
     * @return ?string El problema a mostrar, o null si todo bien.
     */
    private function guardarCedula(Request $peticion, array $contratista, array &$campos): ?string
    {
        $subidos = $peticion->files->all()['cedula'] ?? [];
        $archivos = array_values(array_filter(
            is_array($subidos) ? $subidos : [$subidos],
            static fn (mixed $archivo): bool => $archivo instanceof UploadedFile,
        ));

        if ($archivos === []) {
            return null;
        }

        $pdf = null;

        foreach ($archivos as $archivo) {
            if ($archivo->getMimeType() === 'application/pdf') {
                $pdf = $archivo;
                break;
            }
        }

        try {
            // Un PDF manda sobre todo lo demás: se supone que trae las dos
            // caras, así que el reverso suelto deja de tener sentido.
            if ($pdf !== null) {
                $campos['cedula_archivo'] = $this->archivos->guardar($pdf, 'contratistas');
                $campos['cedula_reverso_archivo'] = null;

                return null;
            }

            $campos['cedula_archivo'] = $this->archivos->guardar($archivos[0], 'contratistas');

            if (isset($archivos[1])) {
                $campos['cedula_reverso_archivo'] = $this->archivos->guardar($archivos[1], 'contratistas');

                return null;
            }
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        // Una sola foto y ninguna guardada antes: falta la otra cara.
        if (($contratista['cedula_reverso_archivo'] ?? null) === null) {
            return 'Subió una sola foto de la cédula. Tome también la otra cara y seleccione las dos fotos juntas (si prefiere, suba un PDF con ambas).';
        }

        return null;
    }

    /**
     * Hasta qué paso de «Mis datos» puede llegar esta persona.
     *
     * Cada paso abre el siguiente, y solo cuando está completo de verdad:
     * el 2 pide el banco Y su certificación, que es el soporte de la cuenta
     * donde Tesorería consigna.
     *
     * @param array<string, mixed> $contratista
     */
    private static function pasoMaximo(array $contratista): int
    {
        if (($contratista['celular'] ?? null) === null) {
            return 1;
        }

        if (self::loQueFaltaDelPaso($contratista, 2) !== []) {
            return 2;
        }

        return 3;
    }

    /**
     * Lo que le falta a un paso concreto, en el idioma del contratista.
     *
     * @param array<string, mixed> $contratista
     *
     * @return list<string>
     */
    private static function loQueFaltaDelPaso(array $contratista, int $paso): array
    {
        $exigidos = $paso === 1
            ? ['celular' => 'su número de celular']
            : [
                'banco' => 'el banco donde le consignan',
                'tipo_cuenta' => 'el tipo de cuenta',
                'numero_cuenta' => 'el número de su cuenta',
                'certificacion_bancaria_archivo' => 'la certificación bancaria',
            ];
        $faltan = [];

        foreach ($exigidos as $columna => $comoSeLlama) {
            if (($contratista[$columna] ?? null) === null || $contratista[$columna] === '') {
                $faltan[] = $comoSeLlama;
            }
        }

        return $faltan;
    }

    /**
     * Qué le falta a un contratista para poder presentar cuentas, en su
     * propio idioma.
     *
     * @param array<string, mixed> $contratista
     *
     * @return list<string>
     */
    private static function loQueFalta(array $contratista): array
    {
        $exigidos = [
            'celular' => 'su número de celular',
            'banco' => 'el banco donde le consignan',
            'numero_cuenta' => 'el número de su cuenta',
            'certificacion_bancaria_archivo' => 'la certificación bancaria',
            'cedula_archivo' => 'la copia de su cédula',
            'rut_archivo' => 'su RUT',
            'firma_archivo' => 'su firma',
            'acepto_tratamiento_datos_en' => 'la autorización de tratamiento de datos',
        ];
        $faltan = [];

        foreach ($exigidos as $columna => $comoSeLlama) {
            if (($contratista[$columna] ?? null) === null || $contratista[$columna] === '') {
                $faltan[] = $comoSeLlama;
            }
        }

        // La cédula en PDF ya trae las dos caras; en foto, hacen falta las
        // dos. Por eso el reverso se exige solo cuando lo subido es imagen.
        $cedula = (string) ($contratista['cedula_archivo'] ?? '');

        if ($cedula !== ''
            && !str_ends_with($cedula, '.pdf')
            && ($contratista['cedula_reverso_archivo'] ?? null) === null) {
            $faltan[] = 'la otra cara de su cédula';
        }

        return $faltan;
    }

    // ------------------------------------------------------------------
    // Ayudas compartidas del portal
    // ------------------------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|Response
     *         [contratista, contrato] o la redirección al ingreso.
     */
    private function contextoAutenticado(): array|Response
    {
        $contratistaId = $this->sesion->contratistaId();
        $contratoId = $this->sesion->contratoId();

        if ($contratistaId === null || $contratoId === null) {
            return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
        }

        $contratista = $this->contratistas->porId($contratistaId);
        $contrato = $this->conexion->fetchAssociative(
            "SELECT k.*, s.nombre_completo AS supervisor_nombre
               FROM cc_contratos k
               LEFT JOIN cc_supervisores s ON s.id = k.supervisor_id
              WHERE k.id = ? AND k.contratista_id = ? AND k.estado IN ('ACTIVO', 'SUSPENDIDO')",
            [$contratoId, $contratistaId],
        );

        if ($contratista === null || (int) $contratista['activo'] !== 1 || $contrato === false) {
            $this->sesion->cerrar();

            return new RedirectResponse('/cuentas/ingreso', Response::HTTP_SEE_OTHER);
        }

        return [$contratista, $contrato];
    }
    /**
     * Encuentra al contratista y su contrato ACTIVO a partir de UNO de los
     * dos identificadores que ofrece el selector del ingreso.
     *
     * @return ?array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function buscarPorIdentificador(string $modo, string $identificador): ?array
    {
        $identificador = trim($identificador);

        if ($identificador === '') {
            return null;
        }

        if ($modo === 'contrato') {
            $contrato = $this->conexion->fetchAssociative(
                "SELECT * FROM cc_contratos WHERE numero = ? AND estado = 'ACTIVO'",
                [$identificador],
            );

            if ($contrato === false) {
                return null;
            }

            $contratista = $this->contratistas->porId((int) $contrato['contratista_id']);

            return ($contratista === null || (int) $contratista['activo'] !== 1)
                ? null
                : [$contratista, $contrato];
        }

        // Por defecto, cédula: solo dígitos, como se guarda el documento.
        $cedula = preg_replace('/\D/', '', $identificador) ?? '';

        if ($cedula === '') {
            return null;
        }

        $contratista = $this->contratistas->porDocumento($cedula);

        if ($contratista === null || (int) $contratista['activo'] !== 1) {
            return null;
        }

        // El contrato ACTIVO más reciente del contratista.
        $contrato = $this->conexion->fetchAssociative(
            "SELECT * FROM cc_contratos WHERE contratista_id = ? AND estado = 'ACTIVO'
             ORDER BY fecha_suscripcion DESC, id DESC LIMIT 1",
            [(int) $contratista['id']],
        );

        return $contrato === false ? null : [$contratista, $contrato];
    }

    /** Diez intentos fallidos por conexión en una hora y se cierra la puerta. */
    private function ipBloqueada(string $ip): bool
    {
        $cuantos = (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM cc_ingreso_intentos
             WHERE ip = ? AND creado_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$ip],
        );

        return $cuantos >= 10;
    }

    private function registrarIntentoFallido(string $ip, ?int $contratistaId = null): void
    {
        $this->conexion->insert('cc_ingreso_intentos', [
            'contratista_id' => $contratistaId,
            'ip' => $ip,
        ]);
    }

    /**
     * Borra los intentos fallidos de esta conexión y de esta persona.
     *
     * Se llama al fijar una contraseña nueva: quien acaba de demostrar
     * quién es —con la fecha de su contrato— no tiene por qué arrastrar
     * el castigo de los intentos anteriores, que son justamente los de
     * alguien que no lograba entrar.
     */
    private function olvidarIntentos(string $ip, int $contratistaId): void
    {
        $this->conexion->executeStatement(
            'DELETE FROM cc_ingreso_intentos WHERE ip = ? OR contratista_id = ?',
            [$ip, $contratistaId],
        );
    }

    /** @param array<string, mixed> $datos */
    private function pagina(string $vista, array $datos): Response
    {
        return new Response($this->vistas->render($vista, $datos + [
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    private function rechazoCsrf(Request $peticion): ?Response
    {
        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->volverConError('/cuentas/ingreso', 'Su sesión expiró. Vuelva a empezar.');
    }

    private function volverConError(string $volverA, string $mensaje): RedirectResponse
    {
        $this->guardarFlash('cc_error', $mensaje);

        return new RedirectResponse($volverA, Response::HTTP_SEE_OTHER);
    }

    private function guardarFlash(string $clave, string $valor): void
    {
        $_SESSION['_flash'][$clave] = $valor;
    }

    private function flash(string $clave): ?string
    {
        $valor = $_SESSION['_flash'][$clave] ?? null;
        unset($_SESSION['_flash'][$clave]);

        return is_string($valor) ? $valor : null;
    }
}
