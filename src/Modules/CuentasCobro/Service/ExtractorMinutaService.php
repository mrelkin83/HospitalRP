<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Core\Config\ConfigDinamica;
use App\Modules\CuentasCobro\Repository\ExtraccionMinutaRepository;
use App\Modules\Sistema\Entity\FuncionIa;
use App\Modules\Sistema\Repository\IaUsoRepository;
use App\Ports\FabricaAiProvider;
use App\Ports\ImagenIa;
use App\Ports\PeticionIa;
use App\Ports\ProveedorIaException;

/**
 * La extracción asistida de una minuta (§10): convierte el PDF a
 * imágenes, se las muestra al proveedor de IA configurado en el panel y
 * guarda el JSON extraído junto a las alertas de inconsistencia.
 *
 * Reglas que no se negocian: nada se activa sin revisión humana, y si la
 * IA no está configurada o falla, la pantalla de revisión funciona igual
 * con el formulario vacío (carga manual con las imágenes al lado).
 */
final readonly class ExtractorMinutaService
{
    private const string INSTRUCCIONES = 'Eres un extractor de datos de contratos estatales '
        . 'colombianos de la E.S.E. Hospital Rosario Pumarejo de López. Recibes las páginas '
        . 'escaneadas de una minuta de prestación de servicios y respondes ÚNICAMENTE con un '
        . 'objeto JSON válido, sin explicaciones, sin markdown y sin texto fuera del JSON.';

    public function __construct(
        private ConfigDinamica $config,
        private FabricaAiProvider $fabrica,
        private IaUsoRepository $usos,
        private ConversorPdfImagenes $conversor,
        private ArchivosCuentas $archivos,
        private ValidadorExtraccion $validador,
        private ExtraccionMinutaRepository $extracciones,
    ) {
    }

    /** ¿Hay proveedor de IA listo para extraer? */
    public function iaDisponible(): bool
    {
        return $this->config->booleano('ia.activada')
            && $this->fabrica->tieneClave($this->config->texto('ia.proveedor', 'anthropic'));
    }

    /**
     * Convierte (si hace falta) y extrae. Deja la extracción en LISTA (con
     * o sin resultado) o en ERROR, y devuelve un mensaje para pantalla.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function procesar(int $extraccionId, ?int $usuarioId): array
    {
        $extraccion = $this->extracciones->porId($extraccionId);

        if ($extraccion === null) {
            return ['ok' => false, 'mensaje' => 'La extracción no existe.'];
        }

        $this->extracciones->actualizar($extraccionId, ['estado' => 'PROCESANDO', 'error' => null]);

        try {
            $imagenes = $this->imagenesDe($extraccion);
        } catch (\RuntimeException $e) {
            $this->extracciones->actualizar($extraccionId, ['estado' => 'ERROR', 'error' => $e->getMessage()]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        if (!$this->iaDisponible()) {
            // Sin IA no hay extracción, pero la revisión manual sigue viva.
            $this->extracciones->actualizar($extraccionId, [
                'estado' => 'LISTA',
                'alertas_json' => json_encode(['El asistente de IA no está configurado: llene el formulario a mano con las imágenes al lado.'], JSON_THROW_ON_ERROR),
                'terminado_en' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'mensaje' => 'Minuta convertida a imágenes. La IA no está configurada, así que la carga es manual.'];
        }

        return $this->extraerConIa($extraccionId, $imagenes, $usuarioId);
    }

    /**
     * Las imágenes 150 dpi de las páginas 1-5, convirtiendo si es la
     * primera vez.
     *
     * @param array<string, mixed> $extraccion
     *
     * @return list<string> Rutas relativas.
     */
    private function imagenesDe(array $extraccion): array
    {
        $subcarpeta = 'extracciones-' . (int) $extraccion['id'];
        $existentes = glob($this->archivos->rutaAbsoluta($subcarpeta) . '/ia-*.png') ?: [];

        if ($existentes !== []) {
            natsort($existentes);

            return array_values(array_map(
                static fn (string $abs): string => $subcarpeta . '/' . basename($abs),
                $existentes,
            ));
        }

        return $this->conversor->convertir((string) $extraccion['minuta_archivo'], $subcarpeta)['para_ia'];
    }

    /** @param list<string> $imagenes @return array{ok: bool, mensaje: string} */
    private function extraerConIa(int $extraccionId, array $imagenes, ?int $usuarioId): array
    {
        $proveedor = $this->config->texto('ia.proveedor', 'anthropic');
        $modelo = $this->config->texto('ia.modelo', 'claude-sonnet-5');
        $adjuntas = [];

        foreach ($imagenes as $ruta) {
            $adjuntas[] = new ImagenIa('image/png', base64_encode((string) file_get_contents($this->archivos->rutaAbsoluta($ruta))));
        }

        $peticion = new PeticionIa(
            self::INSTRUCCIONES,
            self::consigna(),
            maxTokens: 8000,
            imagenes: $adjuntas,
        );
        $inicio = hrtime(true);

        try {
            $respuesta = $this->fabrica->crear($proveedor, $modelo)->sugerir($peticion);
        } catch (ProveedorIaException $e) {
            $this->usos->registrar(FuncionIa::ExtraccionMinuta, $proveedor, $modelo, 0, 0, self::ms($inicio), false, $e->getMessage(), $usuarioId);
            $this->extracciones->actualizar($extraccionId, ['estado' => 'ERROR', 'proveedor' => $proveedor, 'modelo' => $modelo, 'error' => $e->getMessage()]);

            return ['ok' => false, 'mensaje' => 'El proveedor de IA falló: ' . $e->getMessage() . ' Puede llenar el formulario a mano.'];
        }

        $this->usos->registrar(FuncionIa::ExtraccionMinuta, $proveedor, $modelo, $respuesta->tokensEntrada, $respuesta->tokensSalida, self::ms($inicio), true, null, $usuarioId);

        $resultado = self::decodificar($respuesta->texto);

        if ($resultado === null) {
            $this->extracciones->actualizar($extraccionId, [
                'estado' => 'ERROR',
                'proveedor' => $proveedor,
                'modelo' => $modelo,
                'error' => 'La respuesta de la IA no fue un JSON legible.',
            ]);

            return ['ok' => false, 'mensaje' => 'La IA no devolvió un JSON legible; reintente o llene el formulario a mano.'];
        }

        $alertas = $this->validador->alertas($resultado);
        $this->extracciones->actualizar($extraccionId, [
            'estado' => 'LISTA',
            'proveedor' => $proveedor,
            'modelo' => $modelo,
            'resultado_json' => json_encode($resultado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'alertas_json' => json_encode($alertas, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'error' => null,
            'terminado_en' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'mensaje' => 'Extracción lista' . ($alertas !== [] ? ' con ' . count($alertas) . ' alerta(s) por revisar.' : ' sin alertas.')];
    }

    /** La consigna con el esquema exacto de §10 (pública: la usa también el arnés de verificación). */
    public static function consigna(): string
    {
        return <<<'CONSIGNA'
            Extrae los datos de la minuta de las imágenes y devuelve SOLO este JSON (usa null cuando un dato no aparezca):
            {
              "numero_contrato": "1407-2026",
              "ciudad": "Valledupar",
              "fecha_suscripcion": "AAAA-MM-DD",
              "tipo_contrato": "texto del recuadro TIPO DE CONTRATO",
              "objeto": "texto completo del OBJETO",
              "contratista": { "nombre_completo": "NOMBRES Y APELLIDOS", "tipo_documento": "CC", "numero_documento": "solo dígitos" },
              "valor_total": 14250000,
              "valor_total_letras": "las letras tal como están escritas",
              "plazo_texto": "el plazo que consideres correcto",
              "plazo_recuadro": "el plazo del recuadro de la página 1, tal cual",
              "plazo_clausula_5": "el plazo de la CLÁUSULA 5, tal cual",
              "garantias": false,
              "cargo_supervisor": "cargo del supervisor del recuadro",
              "cdp": [ { "numero": "1611", "fecha": "AAAA-MM-DD", "codigo_rubro": "…", "nombre_rubro": "…", "valor": null } ],
              "forma_pago_texto": "la cláusula FORMA DE PAGO completa",
              "requisitos_pago": [ "literal a", "literal b" ],
              "cuotas": [ { "numero": 1, "descripcion": "Agosto de 2026", "mes": "AAAA-MM", "valor": 2850000, "tipo": "COMPLETA" } ],
              "obligaciones_especificas": [ "texto completo de cada obligación numerada, en orden" ],
              "obligaciones_sgic": [ "las del SISTEMA DE GESTIÓN INTEGRAL DE CALIDAD" ],
              "fecha_firma": "AAAA-MM-DD",
              "confianza": { "valor_total": 0.98, "cuotas": 0.85 }
            }
            Reglas: fechas SIEMPRE en formato AAAA-MM-DD (la minuta puede traer DD-MM-AAAA); valores en pesos enteros sin puntos;
            las cuotas salen de la cláusula FORMA DE PAGO: si el primer pago cubre solo unos días del mes, su "tipo" es "PARCIAL";
            transcribe las obligaciones completas, sin resumirlas ni renumerarlas.
            CONSIGNA;
    }

    /** @return ?array<string, mixed> */
    public static function decodificar(string $texto): ?array
    {
        // Por si el modelo desobedece y envuelve el JSON en una valla.
        $limpio = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($texto)));
        $inicio = strpos($limpio, '{');
        $fin = strrpos($limpio, '}');

        if ($inicio === false || $fin === false || $fin <= $inicio) {
            return null;
        }

        $datos = json_decode(substr($limpio, $inicio, $fin - $inicio + 1), true);

        return is_array($datos) ? $datos : null;
    }

    private static function ms(int|float $inicio): int
    {
        return (int) ((hrtime(true) - $inicio) / 1_000_000);
    }
}
