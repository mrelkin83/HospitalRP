<?php

declare(strict_types=1);

namespace App\Core\Storage;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Almacén de archivos subidos (CLAUDE.md §6, Seguridad).
 *
 * Tres decisiones que no se negocian:
 *  1. El tipo se decide leyendo el CONTENIDO (finfo), nunca la extensión ni
 *     el Content-Type que declaró el navegador: un `.php` renombrado a `.jpg`
 *     se rechaza aquí.
 *  2. Los archivos viven FUERA del document root, con nombre aleatorio y la
 *     extensión real detectada. Nada de lo subido es ejecutable ni adivinable.
 *  3. La entrega al navegador pasa siempre por un controlador que valida
 *     permisos; el servidor web jamás sirve `storage/` directamente.
 */
final readonly class AlmacenArchivos
{
    private const int TAMANO_MAXIMO = 20 * 1_048_576; // 20 MB

    /**
     * Tipos aceptados y las extensiones legítimas para cada uno. La lista
     * crece cuando una fase lo exija (p. ej. Office en gestión documental),
     * nunca «por si acaso».
     */
    private const array TIPOS_PERMITIDOS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'application/pdf' => ['pdf'],
    ];

    public function __construct(
        private Connection $conexion,
        private string $directorioBase,
    ) {
    }

    public function guardar(UploadedFile $subido, bool $publico, ?int $creadoPor, ?string $textoAlt = null): Archivo
    {
        $tamano = $subido->getSize();

        if (!is_int($tamano) || $tamano <= 0) {
            throw ArchivoInvalidoException::vacio();
        }

        if ($tamano > self::TAMANO_MAXIMO) {
            throw ArchivoInvalidoException::demasiadoGrande($tamano, self::TAMANO_MAXIMO);
        }

        // Tipo real por contenido. getMimeType() usa finfo sobre el archivo,
        // no el encabezado del navegador.
        $mime = (string) $subido->getMimeType();

        if (!isset(self::TIPOS_PERMITIDOS[$mime])) {
            throw ArchivoInvalidoException::tipoNoPermitido($mime);
        }

        $extensionDeclarada = strtolower($subido->getClientOriginalExtension());

        if (!in_array($extensionDeclarada, self::TIPOS_PERMITIDOS[$mime], true)) {
            throw ArchivoInvalidoException::extensionEnganosa($extensionDeclarada, $mime);
        }

        $hash = hash_file('sha256', $subido->getPathname());
        [$ancho, $alto] = $this->dimensiones($subido->getPathname(), $mime);

        // Nombre aleatorio: ni el nombre original (colisiones, caracteres
        // raros) ni el hash (haría adivinables las URLs de archivos privados).
        $rutaRelativa = sprintf(
            '%s/%s.%s',
            date('Y/m'),
            bin2hex(random_bytes(20)),
            $extensionDeclarada === 'jpeg' ? 'jpg' : $extensionDeclarada,
        );

        $destino = $this->directorioBase . '/' . dirname($rutaRelativa);
        $subido->move($destino, basename($rutaRelativa));

        $this->conexion->insert('archivos', [
            'nombre_original' => $subido->getClientOriginalName(),
            'ruta' => $rutaRelativa,
            'mime' => $mime,
            'extension' => $extensionDeclarada === 'jpeg' ? 'jpg' : $extensionDeclarada,
            'tamano' => $tamano,
            'hash_sha256' => $hash,
            'ancho' => $ancho,
            'alto' => $alto,
            'texto_alt' => $textoAlt,
            'publico' => $publico ? 1 : 0,
            'creado_por' => $creadoPor,
        ]);

        $id = (int) $this->conexion->lastInsertId();
        $fila = $this->conexion->fetchAssociative('SELECT * FROM archivos WHERE id = ?', [$id]);
        assert($fila !== false);

        return Archivo::desdeFila($fila);
    }

    /**
     * Imágenes recientes para la biblioteca de medios del panel.
     *
     * @return list<Archivo>
     */
    public function imagenesRecientes(int $limite = 60): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT * FROM archivos WHERE mime LIKE 'image/%' ORDER BY id DESC LIMIT " . max(1, $limite),
        );

        return array_map(Archivo::desdeFila(...), $filas);
    }

    public function porId(int $id): ?Archivo
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM archivos WHERE id = ?', [$id]);

        return $fila === false ? null : Archivo::desdeFila($fila);
    }

    public function rutaAbsoluta(Archivo $archivo): string
    {
        return $this->directorioBase . '/' . $archivo->ruta;
    }

    public function contarDescarga(Archivo $archivo): void
    {
        $this->conexion->executeStatement(
            'UPDATE archivos SET descargas = descargas + 1 WHERE id = ?',
            [$archivo->id],
        );
    }

    /**
     * @return array{?int, ?int}
     */
    private function dimensiones(string $ruta, string $mime): array
    {
        if (!str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $info = @getimagesize($ruta);

        return $info === false ? [null, null] : [$info[0], $info[1]];
    }
}
