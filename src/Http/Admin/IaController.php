<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Cifrador;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Entity\FuncionIa;
use App\Modules\Sistema\Exception\IaDesactivadaException;
use App\Modules\Sistema\Repository\IaUsoRepository;
use App\Modules\Sistema\Service\AsistenteIa;
use App\Ports\FabricaAiProvider;
use App\Ports\ImagenIa;
use App\Ports\ProveedorIaException;
use Doctrine\DBAL\Connection;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * IA del panel: configuración (activada, proveedor, clave, URL base y
 * modelo), consumo, y el punto único de sugerencias que usan los
 * formularios. Las claves de API se administran AQUÍ (patrón del módulo
 * de IA del proyecto SEPUD): se guardan cifradas en la base y jamás se
 * vuelven a mostrar; el .env queda solo como respaldo de arranque.
 */
final readonly class IaController
{
    private const int MAX_IMAGEN_BYTES = 5_000_000;
    private const array MIMES_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private AsistenteIa $asistente,
        private FabricaAiProvider $fabrica,
        private IaUsoRepository $usos,
        private Connection $conexion,
        private AuditoriaService $auditoria,
        private Cifrador $cifrador,
    ) {
    }

    public function configuracion(Request $peticion): Response
    {
        $claves = [];

        foreach (array_keys(FabricaAiProvider::PROVEEDORES) as $codigo) {
            $claves[$codigo] = $this->fabrica->tieneClave($codigo);
        }

        return $this->pagina($this->vistas->render('admin/ia/configuracion', [
            'activada' => $this->asistente->activa(),
            'activadaConfig' => $this->valorConfig('ia.activada') === '1',
            'proveedor' => $this->asistente->proveedor(),
            'modelo' => $this->asistente->modelo(),
            'urlBase' => $this->valorConfig('ia.url_base'),
            'cifradorListo' => $this->cifrador->disponible(),
            'proveedores' => FabricaAiProvider::PROVEEDORES,
            'claves' => $claves,
            'consumo' => $this->usos->consumoPorFuncion(),
            'recientes' => $this->usos->recientes(10),
            'funciones' => FuncionIa::cases(),
            'mensaje' => $peticion->query->has('guardado') ? 'Configuración guardada.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function guardarConfiguracion(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if (!$this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return new RedirectResponse('/admin/ia', Response::HTTP_SEE_OTHER);
        }

        $r = $peticion->request;
        $activada = $r->getBoolean('activada');
        $proveedor = $r->getString('proveedor');
        $modelo = trim($r->getString('modelo'));

        if (!isset(FabricaAiProvider::PROVEEDORES[$proveedor])) {
            return new RedirectResponse('/admin/ia', Response::HTTP_SEE_OTHER);
        }

        $this->guardarClave('ia.activada', $activada ? '1' : '0', 'booleano', 'Asistente de IA activado');
        $this->guardarClave('ia.proveedor', $proveedor, 'texto', 'Proveedor de IA');
        $this->guardarClave('ia.modelo', mb_substr($modelo, 0, 80), 'texto', 'Modelo de IA');

        // La URL base: pasarela compatible con Anthropic (OneProvider, por
        // ejemplo) o el endpoint del proveedor «personalizado».
        $urlBase = trim($r->getString('url_base'));

        if ($urlBase === '' || preg_match('#^https://[^\s]+$#i', $urlBase) === 1) {
            $this->guardarClave('ia.url_base', mb_substr($urlBase, 0, 200), 'texto', 'URL base del proveedor de IA', editable: false);
        }

        // La clave de API del proveedor elegido: se guarda CIFRADA y no se
        // vuelve a mostrar. Vacía = conservar la actual; la casilla borra.
        $claveApi = trim($r->getString('clave_api'));
        $claveCambio = null;

        if ($r->getBoolean('clave_borrar')) {
            $this->guardarClave('ia.clave_' . $proveedor, '', 'texto', 'Clave de API (cifrada)', editable: false);
            $claveCambio = 'clave eliminada';
        } elseif ($claveApi !== '') {
            $this->guardarClave('ia.clave_' . $proveedor, $this->cifrador->cifrar($claveApi), 'texto', 'Clave de API (cifrada)', editable: false);
            $claveCambio = 'clave actualizada';
        }

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Actualizacion,
            entidad: 'configuracion',
            entidadId: 0,
            descripcion: sprintf(
                'Configuró la IA del panel: %s, proveedor %s, modelo %s%s',
                $activada ? 'activada' : 'desactivada',
                $proveedor,
                $modelo !== '' ? $modelo : '(predeterminado)',
                $claveCambio !== null ? ', ' . $claveCambio : '',
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        return new RedirectResponse('/admin/ia?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /**
     * Los modelos disponibles del proveedor pedido (JSON): en vivo desde
     * su API cuando hay clave, o la lista de referencia si no. Alimenta el
     * botón «Cargar modelos» de la pantalla de configuración.
     */
    public function modelos(Request $peticion): Response
    {
        $proveedor = $peticion->query->getString('proveedor');

        if (!isset(FabricaAiProvider::PROVEEDORES[$proveedor])) {
            return $this->error('Proveedor no reconocido.', Response::HTTP_BAD_REQUEST);
        }

        // Caché de una hora por proveedor: la pantalla carga la lista sola
        // en cada visita sin castigar la API del proveedor, y un modelo
        // recién publicado aparece a más tardar en una hora (o de una vez
        // con «Actualizar ahora»).
        $claveCache = 'ia.modelos_' . $proveedor;

        if (!$peticion->query->getBoolean('refrescar')) {
            $cache = json_decode((string) $this->valorConfig($claveCache), true);

            if (is_array($cache) && (time() - (int) ($cache['t'] ?? 0)) < 3600 && ($cache['origen'] ?? '') === 'api') {
                return new JsonResponse(['origen' => 'api', 'modelos' => $cache['modelos'], 'nota' => null]);
            }
        }

        $resultado = $this->fabrica->listarModelos($proveedor);

        if ($resultado['origen'] === 'api') {
            $this->guardarClave($claveCache, json_encode([
                't' => time(),
                'origen' => 'api',
                'modelos' => $resultado['modelos'],
            ], JSON_THROW_ON_ERROR), 'json', 'Modelos del proveedor (caché)', editable: false);
        }

        return new JsonResponse($resultado);
    }

    /**
     * Punto único de sugerencias (JSON). Cualquier usuario autenticado del
     * panel puede pedirlas: aplicar el resultado sigue limitado por los
     * permisos de cada formulario, y nada se aplica automáticamente.
     */
    public function sugerir(Request $peticion): Response
    {
        $this->sesion->iniciar();

        try {
            /** @var array<string, mixed> $datos */
            $datos = json_decode($peticion->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->error('La solicitud no es JSON válido.', Response::HTTP_BAD_REQUEST);
        }

        if (!$this->sesion->validarCsrf((string) ($datos['_token'] ?? ''))) {
            return $this->error('Tu sesión expiró. Recarga la página e intenta de nuevo.', Response::HTTP_FORBIDDEN);
        }

        $funcion = FuncionIa::tryFrom((string) ($datos['funcion'] ?? ''));

        if ($funcion === null) {
            return $this->error('Función de IA desconocida.', Response::HTTP_BAD_REQUEST);
        }

        $imagen = null;

        if ($funcion === FuncionIa::TextoAlternativo) {
            $imagen = $this->imagenDesde($datos);

            if ($imagen === null) {
                return $this->error(
                    'La imagen debe ser JPG, PNG o WebP de máximo 5 MB.',
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        try {
            $sugerencia = $this->asistente->sugerir($funcion, [
                'titulo' => mb_substr((string) ($datos['titulo'] ?? ''), 0, 300),
                'texto' => mb_substr((string) ($datos['texto'] ?? ''), 0, 30_000),
            ], $imagen, $this->sesion->usuarioId());
        } catch (IaDesactivadaException $e) {
            return $this->error($e->getMessage(), Response::HTTP_CONFLICT);
        } catch (ProveedorIaException) {
            return $this->error(
                'El proveedor de IA no respondió bien. Intenta de nuevo en un momento; el detalle quedó registrado.',
                Response::HTTP_BAD_GATEWAY,
            );
        }

        if ($funcion === FuncionIa::MetadatosSeo) {
            return new JsonResponse(['sugerencia' => $this->metadatosDesde($sugerencia)]);
        }

        return new JsonResponse(['sugerencia' => $sugerencia]);
    }

    // --- soporte ---

    /**
     * @param array<string, mixed> $datos
     */
    private function imagenDesde(array $datos): ?ImagenIa
    {
        $mime = (string) ($datos['imagen_mime'] ?? '');
        $base64 = (string) ($datos['imagen_base64'] ?? '');

        if (!in_array($mime, self::MIMES_IMAGEN, true) || $base64 === '' || strlen($base64) > self::MAX_IMAGEN_BYTES) {
            return null;
        }

        // Validación real del contenido, no solo del mime declarado.
        $binario = base64_decode($base64, true);

        if ($binario === false || getimagesizefromstring($binario) === false) {
            return null;
        }

        return new ImagenIa($mime, $base64);
    }

    /**
     * El modelo debe devolver JSON con titulo y descripcion; si divaga, la
     * respuesta cruda va al campo de descripción para que el funcionario la
     * edite — sugerencia, no verdad.
     *
     * @return array{titulo: string, descripcion: string}
     */
    private function metadatosDesde(string $sugerencia): array
    {
        $limpia = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $sugerencia));

        try {
            $json = json_decode($limpia, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $json = null;
        }

        if (is_array($json) && isset($json['titulo'], $json['descripcion'])) {
            return [
                'titulo' => mb_substr((string) $json['titulo'], 0, 200),
                'descripcion' => mb_substr((string) $json['descripcion'], 0, 320),
            ];
        }

        return ['titulo' => '', 'descripcion' => mb_substr($limpia, 0, 320)];
    }

    /** `editable: false` esconde la fila de la pantalla genérica de Ajustes (los secretos cifrados). */
    private function guardarClave(string $clave, string $valor, string $tipo, string $etiqueta, bool $editable = true): void
    {
        $this->conexion->executeStatement(
            'INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, editable, actualizado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = VALUES(actualizado_por)',
            [$clave, $valor, $tipo, 'ia', $etiqueta, $editable ? 1 : 0, $this->sesion->usuarioId()],
        );
    }

    private function valorConfig(string $clave): ?string
    {
        $valor = $this->conexion->fetchOne('SELECT valor FROM configuracion WHERE clave = ?', [$clave]);

        return is_string($valor) ? $valor : null;
    }

    private function error(string $mensaje, int $estado): JsonResponse
    {
        return new JsonResponse(['error' => $mensaje], $estado);
    }

    private function pagina(string $contenido): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Asistente de IA',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
            'conEditor' => false,
        ]);

        $respuesta = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
