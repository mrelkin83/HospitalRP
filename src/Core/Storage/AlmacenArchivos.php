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
        // Formatos abiertos de datos (Fase 4). finfo reporta CSV y a veces
        // JSON como text/plain: la extensión declarada desambigua, y ninguno
        // de estos formatos es ejecutable.
        'text/csv' => ['csv'],
        'application/json' => ['json'],
        'text/plain' => ['csv', 'json'],
        'text/xml' => ['xml'],
        'application/xml' => ['xml'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
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

    /**
     * Imágenes para la galería PÚBLICA. Se diferencia de `imagenesRecientes`
     * en una condición que no es opcional: `publico = 1`.
     *
     * La biblioteca del panel enseña todo lo subido, incluidos los adjuntos
     * de trámites, que son privados. Reutilizar aquel método para la galería
     * habría publicado los documentos que adjunta la ciudadanía.
     *
     * @return array{imagenes: list<Archivo>, total: int}
     */
    public function imagenesPublicas(int $pagina, int $porPagina): array
    {
        $total = (int) $this->conexion->fetchOne(
            "SELECT COUNT(*) FROM archivos WHERE publico = 1 AND mime LIKE 'image/%'",
        );

        $filas = $this->conexion->fetchAllAssociative(sprintf(
            "SELECT * FROM archivos WHERE publico = 1 AND mime LIKE 'image/%%'
              ORDER BY id DESC
              LIMIT %d OFFSET %d",
            max(1, $porPagina),
            max(0, ($pagina - 1) * $porPagina),
        ));

        return ['imagenes' => array_map(Archivo::desdeFila(...), $filas), 'total' => $total];
    }

    public function porId(int $id): ?Archivo
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM archivos WHERE id = ?', [$id]);

        return $fila === false ? null : Archivo::desdeFila($fila);
    }

    /**
     * @param list<int> $ids
     * @return array<int, Archivo> indexado por id
     */
    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM archivos WHERE id IN (?)',
            [array_values(array_unique($ids))],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );

        $resultado = [];

        foreach ($filas as $fila) {
            $archivo = Archivo::desdeFila($fila);
            $resultado[$archivo->id] = $archivo;
        }

        return $resultado;
    }

    /**
     * Dónde se está usando un archivo, por sección y con su número.
     *
     * Existe porque las claves foráneas mienten por omisión: ocho de las once
     * que apuntan aquí son `ON DELETE SET NULL`, así que borrar una imagen no
     * falla —deja en blanco la portada de una campaña publicada y nadie se
     * entera— y tres son `RESTRICT`, que revientan con un error de base de
     * datos en la cara del funcionario. Se pregunta antes de borrar.
     *
     * @return array<string, int> sección legible => cuántos contenidos la usan
     */
    public function usos(int $id): array
    {
        $referencias = [
            'noticias' => ['noticias', 'imagen_id'],
            'campañas' => ['campanas', 'imagen_id'],
            'páginas' => ['paginas', 'imagen_id'],
            'sedes' => ['sedes', 'imagen_id'],
            'servicios' => ['servicios', 'imagen_id'],
            'especialidades' => ['especialidades', 'imagen_id'],
            'directorio médico' => ['profesionales', 'foto_id'],
            'documentos' => ['documentos', 'archivo_id'],
            'versiones de documento' => ['documento_versiones', 'archivo_id'],
            'datos abiertos' => ['dataset_recursos', 'archivo_id'],
            'adjuntos de trámites' => ['tramite_adjuntos', 'archivo_id'],
        ];

        $usos = [];

        foreach ($referencias as $seccion => [$tabla, $columna]) {
            $total = (int) $this->conexion->fetchOne(
                sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $tabla, $columna),
                [$id],
            );

            if ($total > 0) {
                $usos[$seccion] = $total;
            }
        }

        return $usos;
    }

    /**
     * Borra el archivo de la biblioteca y del disco.
     *
     * Devuelve `false` si está en uso: retirar una imagen que sostiene una
     * campaña publicada no es tarea de un botón sin aviso. Quien la quiera
     * fuera, primero la quita del contenido.
     */
    public function eliminar(int $id): bool
    {
        $archivo = $this->porId($id);

        if ($archivo === null || $this->usos($id) !== []) {
            return false;
        }

        // Primero la fila y después el disco: si se cae entre medias, queda un
        // archivo huérfano —que no rompe nada— y no una fila que apunta a un
        // archivo que ya no existe, que sí rompe la página.
        $this->conexion->delete('archivos', ['id' => $id]);

        $ruta = $this->rutaAbsoluta($archivo);

        if (is_file($ruta)) {
            @unlink($ruta);
        }

        return true;
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
