<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Carga los documentos de seguridad digital del Anexo 3 (ITA 15.1) y los
 * cuelga del menú de Transparencia.
 *
 * Los PDF viven en `storage/documentos-ita/`, fuera de git: son binarios de
 * varios megas que entran por el módulo documental, no por el repositorio.
 *
 * Idempotente por número de acto: volver a correrlo no duplica ni pisa lo que
 * el hospital haya corregido desde el panel.
 */
final class SembrarDocumentosSeguridadCommand extends Command
{
    private const string DIRECTORIO = __DIR__ . '/../../../../storage/documentos-ita';

    /**
     * `[archivo, título, número de acto, fecha, resumen, código de sección]`
     *
     * @var list<array{string, string, string, ?string, string, string}>
     */
    private const array DOCUMENTOS = [
        [
            'psi-res501.pdf',
            'Política de Seguridad de la Información (PSI) — Resolución 501 de 2024',
            'Resolución 501 de 2024',
            '2024-11-27',
            'Política de Seguridad de la Información adoptada por la E.S.E. Documento firmado. '
                . 'ADVERTENCIA: el PDF es un escaneo sin capa de texto, así que no puede leerse con '
                . 'lector de pantalla (criterio 1.1.i del Anexo 1 de la Res. 1519). La versión '
                . 'accesible se publica en /politica-de-seguridad-de-la-informacion.',
            '1.17',
        ],
        [
            'mspi-2026.pdf',
            'Plan de Seguridad y Privacidad de la Información (MSPI) — vigencia 2026',
            'GTI-PL-001 v004',
            '2026-01-30',
            'Plan vigente, con el cronograma de actividades de la vigencia 2026. PENDIENTE: el '
                . 'encabezado de las 13 páginas todavía dice «versión 002 — 31/01/2024», pese a que '
                . 'su propio control de cambios registra la versión 004 del 30/01/2026. Debe '
                . 'corregirlo la Oficina de Sistemas antes de reportarlo al ITA.',
            '1.17',
        ],
        [
            'mspi.pdf',
            'Plan de Seguridad y Privacidad de la Información (MSPI) — versión 001 de 2023',
            'GTI-PL-001 v001',
            '2023-01-30',
            'Versión histórica del plan: es la que servía el sitio anterior en la URL reportada al '
                . 'ITA. Se conserva para trazabilidad; la vigente es la de 2026.',
            '1.17',
        ],
        [
            'lineamiento-res373.pdf',
            'Lineamiento de Política de Seguridad — Resolución 373',
            'Resolución 373',
            null,
            'Lineamiento de política de seguridad que el sitio anterior enlazaba desde el pie. '
                . 'El PDF es un escaneo sin capa de texto.',
            '1.17',
        ],
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AlmacenArchivos $almacen,
        private readonly AuditoriaService $auditoria,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('semillas:documentos-seguridad')
            ->setDescription('Carga los documentos de seguridad digital del Anexo 3 y los enlaza en Transparencia (ITA 15.1)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creados = 0;
        $faltantes = [];

        foreach (self::DOCUMENTOS as [$archivo, $titulo, $acto, $fecha, $resumen, $seccion]) {
            $ruta = self::DIRECTORIO . '/' . $archivo;

            if (!is_file($ruta)) {
                $faltantes[] = $archivo;

                continue;
            }

            if (false !== $this->conexion->fetchOne('SELECT id FROM documentos WHERE numero_acto = ?', [$acto])) {
                continue;
            }

            // `test: true` porque el archivo sale del disco y no de una subida
            // HTTP; sin eso UploadedFile rechaza la ruta.
            $guardado = $this->almacen->guardar(
                new UploadedFile($ruta, $archivo, 'application/pdf', null, true),
                publico: false,
                creadoPor: null,
            );

            $this->conexion->insert('documentos', [
                'titulo' => $titulo,
                'slug' => $this->slugLibre($titulo),
                'resumen' => $resumen,
                'archivo_id' => $guardado->id,
                'numero_acto' => $acto,
                // Sin fecha conocida se usa hoy: la columna es NOT NULL e
                // inventar una fecha de expedición sería peor que aproximarla.
                'fecha_documento' => $fecha ?? date('Y-m-d'),
                'serie_documental' => 'Gestión de Tecnología y Sistemas de Información',
                'subserie_documental' => 'Seguridad y privacidad de la información',
                'estado' => 'vigente',
                'publicado' => 1,
            ]);
            $documentoId = (int) $this->conexion->lastInsertId();
            $creados++;

            $this->enlazarEnTransparencia($seccion, $documentoId, $titulo, $resumen);
        }

        if ($creados > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'documentos',
                descripcion: sprintf('Semilla de documentos de seguridad digital: %d publicados', $creados),
                usuarioNombre: 'consola',
            ));
        }

        if ($faltantes !== []) {
            $io->warning(sprintf(
                "No se encontraron en storage/documentos-ita/: %s.\nDescárgalos del sitio vigente antes de volver a correr el comando.",
                implode(', ', $faltantes),
            ));
        }

        $io->success(sprintf('%d documentos publicados.', $creados));
        $io->note('El MSPI vigente tiene el encabezado desactualizado (dice v002/2024, es v004/2026). Corregirlo antes de reportarlo al ITA.');

        return Command::SUCCESS;
    }

    private function enlazarEnTransparencia(string $codigo, int $documentoId, string $titulo, string $resumen): void
    {
        $seccionId = $this->conexion->fetchOne(
            'SELECT id FROM transparencia_secciones WHERE codigo = ?',
            [$codigo],
        );

        if ($seccionId === false) {
            return;
        }

        $yaExiste = $this->conexion->fetchOne(
            'SELECT id FROM transparencia_items WHERE seccion_id = ? AND documento_id = ?',
            [(int) $seccionId, $documentoId],
        );

        if ($yaExiste !== false) {
            return;
        }

        $this->conexion->insert('transparencia_items', [
            'seccion_id' => (int) $seccionId,
            'documento_id' => $documentoId,
            'titulo' => $titulo,
            'descripcion' => mb_substr($resumen, 0, 500),
            'orden' => 0,
        ]);
    }

    private function slugLibre(string $titulo): string
    {
        $base = Slugs::desde($titulo, 300);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM documentos WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}
