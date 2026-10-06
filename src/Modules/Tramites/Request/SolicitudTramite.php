<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Request;

use App\Modules\Tramites\Entity\TipoTramite;
use Symfony\Component\HttpFoundation\Request;

/**
 * La solicitud del ciudadano, parseada y validada contra la definición del
 * tipo (campos dinámicos incluidos). Los mensajes de error se muestran tal
 * cual junto a cada campo.
 */
final readonly class SolicitudTramite
{
    private const array TIPOS_DOCUMENTO = ['CC', 'TI', 'CE', 'PA', 'RC', 'NIT', 'PEP', 'NUIP', 'OTRO'];
    private const array MEDIOS_RESPUESTA = ['correo', 'fisico', 'telefono', 'presencial'];

    /**
     * @param array<int, string> $valores campo_id => valor
     */
    public function __construct(
        public bool $anonimo,
        public ?string $nombre,
        public ?string $tipoDocumento,
        public ?string $numeroDocumento,
        public ?string $correo,
        public ?string $telefono,
        public ?string $direccion,
        public ?string $municipio,
        public string $medioRespuesta,
        public string $asunto,
        public string $descripcion,
        public array $valores,
        public bool $autorizaDatos,
        // El criterio 13.3.g del ITA enumera la dirección de correspondencia
        // completa: «Dirección, Barrio/Vereda/Corregimiento, Municipio/Distrito,
        // País». Barrio y país faltaban, y en Valledupar el corregimiento es
        // lo que hace ubicable una dirección rural.
        //
        // Van al final y con valor por omisión, no junto a `direccion`, porque
        // son opcionales: así quien ya construía la solicitud por posición
        // sigue funcionando. `desdePeticion()` usa argumentos nombrados, de
        // modo que el orden no afecta a la lectura del formulario.
        public ?string $barrio = null,
        public ?string $pais = null,
    ) {
    }

    public static function desdePeticion(Request $peticion, TipoTramite $tipo): self
    {
        $r = $peticion->request;
        $texto = static fn (string $clave, int $max): ?string => trim($r->getString($clave)) !== ''
            ? mb_substr(trim($r->getString($clave)), 0, $max)
            : null;

        $valores = [];

        foreach ($tipo->campos as $campo) {
            $valor = trim($r->getString('campo_' . $campo['id']));

            if ($valor !== '') {
                $valores[$campo['id']] = mb_substr($valor, 0, 2000);
            }
        }

        $medio = $r->getString('medio_respuesta');

        return new self(
            anonimo: $tipo->permiteAnonimo && $r->getBoolean('anonimo'),
            nombre: $texto('nombre', 200),
            tipoDocumento: in_array($r->getString('tipo_documento'), self::TIPOS_DOCUMENTO, true)
                ? $r->getString('tipo_documento')
                : null,
            numeroDocumento: $texto('numero_documento', 40),
            correo: $texto('correo', 160),
            telefono: $texto('telefono', 60),
            direccion: $texto('direccion', 255),
            barrio: $texto('barrio', 160),
            municipio: $texto('municipio', 120),
            // Por defecto Colombia: el ITA solo exige el país «en caso que sea
            // diferente al de Colombia», y pedírselo a todo el mundo añade un
            // campo que el 99 % deja igual.
            pais: $texto('pais', 120) ?? 'Colombia',
            medioRespuesta: in_array($medio, self::MEDIOS_RESPUESTA, true) ? $medio : 'correo',
            asunto: mb_substr(trim($r->getString('asunto')), 0, 300),
            descripcion: trim($r->getString('descripcion')),
            valores: $valores,
            autorizaDatos: $r->getBoolean('autoriza_datos'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function validar(TipoTramite $tipo): array
    {
        $errores = [];

        // La casilla de autorización JAMÁS viene premarcada y sin ella no
        // hay radicación (Ley 1581; criterio de aceptación de la fase).
        if (!$this->autorizaDatos) {
            $errores['autoriza_datos'] = 'Para radicar debes autorizar el tratamiento de tus datos personales. La casilla no viene marcada: márcala tú si estás de acuerdo.';
        }

        if ($this->asunto === '') {
            $errores['asunto'] = 'Escribe el asunto de tu solicitud.';
        }

        if ($this->descripcion === '') {
            $errores['descripcion'] = 'Cuéntanos tu solicitud con tus palabras.';
        }

        if (!$this->anonimo) {
            if ($this->nombre === null) {
                $errores['nombre'] = 'Escribe tu nombre completo.';
            }

            if ($this->numeroDocumento === null) {
                $errores['numero_documento'] = 'Escribe tu número de documento: con él consultarás el estado de tu solicitud.';
            }

            if ($this->tipoDocumento === null) {
                $errores['tipo_documento'] = 'Elige el tipo de documento.';
            }
        }

        if ($this->medioRespuesta === 'correo' && !$this->anonimo
            && ($this->correo === null || filter_var($this->correo, FILTER_VALIDATE_EMAIL) === false)
        ) {
            $errores['correo'] = 'Escribe un correo válido o elige otro medio de respuesta.';
        }

        foreach ($tipo->campos as $campo) {
            $valor = $this->valores[$campo['id']] ?? null;

            if ($campo['requerido'] && ($valor === null || $valor === '')) {
                $errores['campo_' . $campo['id']] = sprintf('Completa «%s».', $campo['etiqueta']);
                continue;
            }

            if ($valor === null) {
                continue;
            }

            if (in_array($campo['tipo_campo'], ['select', 'radio'], true)
                && $campo['opciones'] !== []
                && !in_array($valor, $campo['opciones'], true)
            ) {
                $errores['campo_' . $campo['id']] = sprintf('Elige una opción válida en «%s».', $campo['etiqueta']);
            }

            if ($campo['tipo_campo'] === 'correo' && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
                $errores['campo_' . $campo['id']] = sprintf('«%s» debe ser un correo válido.', $campo['etiqueta']);
            }

            if ($campo['tipo_campo'] === 'numero' && !is_numeric($valor)) {
                $errores['campo_' . $campo['id']] = sprintf('«%s» debe ser un número.', $campo['etiqueta']);
            }

            if ($campo['tipo_campo'] === 'fecha' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
                $errores['campo_' . $campo['id']] = sprintf('«%s» debe ser una fecha.', $campo['etiqueta']);
            }
        }

        return $errores;
    }
}
