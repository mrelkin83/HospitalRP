<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Service;

use App\Core\Config\ConfigDinamica;
use App\Modules\Sistema\Entity\FuncionIa;
use App\Modules\Sistema\Exception\IaDesactivadaException;
use App\Modules\Sistema\Repository\IaUsoRepository;
use App\Ports\FabricaAiProvider;
use App\Ports\ImagenIa;
use App\Ports\PeticionIa;
use App\Ports\ProveedorIaException;

/**
 * Orquestador de las funciones de IA del panel. Reglas que no se negocian
 * (CLAUDE.md §6): solo en el panel, resultado siempre como sugerencia
 * editable, y con la IA desactivada el sistema funciona igual — este
 * servicio es el único que sabe de IA y nadie depende de que responda.
 */
final readonly class AsistenteIa
{
    private const string INSTRUCCIONES = 'Eres el asistente editorial del sitio web de la '
        . 'E.S.E. Hospital Rosario Pumarejo de López, hospital público de Valledupar (Colombia). '
        . 'Escribes en español claro y sobrio para ciudadanos, sin jerga técnica ni promesas médicas. '
        . 'Respondes ÚNICAMENTE con lo pedido, sin preámbulos ni explicaciones.';

    public function __construct(
        private ConfigDinamica $config,
        private FabricaAiProvider $fabrica,
        private IaUsoRepository $usos,
    ) {
    }

    public function activa(): bool
    {
        return $this->config->booleano('ia.activada')
            && $this->fabrica->tieneClave($this->proveedor());
    }

    public function proveedor(): string
    {
        return $this->config->texto('ia.proveedor', 'anthropic');
    }

    public function modelo(): string
    {
        return $this->config->texto('ia.modelo', 'claude-sonnet-5');
    }

    /**
     * Ejecuta una función y devuelve el texto sugerido. La llamada queda en
     * `ia_usos` (también las fallidas); aplicar o descartar la sugerencia es
     * siempre decisión del funcionario en su formulario.
     *
     * @param array<string, string> $datos entradas de la función (titulo, texto, apuntes…)
     */
    public function sugerir(FuncionIa $funcion, array $datos, ?ImagenIa $imagen, ?int $usuarioId): string
    {
        if (!$this->activa()) {
            throw IaDesactivadaException::porConfiguracion();
        }

        $peticion = $this->peticionPara($funcion, $datos, $imagen);
        $inicio = hrtime(true);

        try {
            $respuesta = $this->fabrica->crear($this->proveedor(), $this->modelo())->sugerir($peticion);
        } catch (ProveedorIaException $e) {
            $this->registrar($funcion, 0, 0, $inicio, exito: false, error: $e->getMessage(), usuarioId: $usuarioId);

            throw $e;
        }

        $this->registrar(
            $funcion,
            $respuesta->tokensEntrada,
            $respuesta->tokensSalida,
            $inicio,
            exito: true,
            error: null,
            usuarioId: $usuarioId,
        );

        return $respuesta->texto;
    }

    // --- soporte ---

    /**
     * @param array<string, string> $datos
     */
    private function peticionPara(FuncionIa $funcion, array $datos, ?ImagenIa $imagen): PeticionIa
    {
        $titulo = trim($datos['titulo'] ?? '');
        $texto = trim($datos['texto'] ?? '');

        return match ($funcion) {
            FuncionIa::ResumenDocumento => new PeticionIa(
                self::INSTRUCCIONES,
                "Resume en máximo tres frases, para la ficha pública del documento, el siguiente texto"
                    . ($titulo !== '' ? " del documento titulado «{$titulo}»" : '') . ":\n\n{$texto}",
                maxTokens: 400,
            ),
            FuncionIa::SugerenciaEtiquetas => new PeticionIa(
                self::INSTRUCCIONES,
                "Propón entre tres y seis etiquetas para clasificar este documento: palabras o "
                    . "frases cortas en minúsculas, separadas por comas, sin numeración.\n\n"
                    . "Título: {$titulo}\n\n{$texto}",
                maxTokens: 200,
            ),
            FuncionIa::TextoAlternativo => new PeticionIa(
                self::INSTRUCCIONES,
                'Describe esta imagen en una sola frase de máximo 120 caracteres, como texto '
                    . 'alternativo para personas ciegas. No empieces con «imagen de» ni «foto de».',
                imagen: $imagen,
                maxTokens: 150,
            ),
            FuncionIa::MetadatosSeo => new PeticionIa(
                self::INSTRUCCIONES,
                "Devuelve SOLO un objeto JSON con las claves \"titulo\" (meta título, máximo 60 "
                    . "caracteres) y \"descripcion\" (meta descripción, máximo 155 caracteres) para "
                    . "esta pieza:\n\nTítulo: {$titulo}\n\n{$texto}",
                maxTokens: 300,
            ),
            FuncionIa::BorradorNoticia => new PeticionIa(
                self::INSTRUCCIONES,
                "Redacta un borrador de noticia institucional del hospital: entre tres y cinco "
                    . "párrafos, tono informativo y sobrio, sin inventar cifras ni declaraciones. "
                    . "Solo los párrafos del cuerpo, sin titular.\n\nApuntes del funcionario:\n{$texto}",
                maxTokens: 1500,
            ),
        };
    }

    private function registrar(
        FuncionIa $funcion,
        int $tokensEntrada,
        int $tokensSalida,
        int $inicioNs,
        bool $exito,
        ?string $error,
        ?int $usuarioId,
    ): void {
        $this->usos->registrar(
            $funcion,
            $this->proveedor(),
            $this->modelo(),
            $tokensEntrada,
            $tokensSalida,
            (int) ((hrtime(true) - $inicioNs) / 1_000_000),
            $exito,
            $error,
            $usuarioId,
        );
    }
}
