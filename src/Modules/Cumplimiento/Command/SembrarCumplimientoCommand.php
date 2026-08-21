<?php

declare(strict_types=1);

namespace App\Modules\Cumplimiento\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Semillas de cumplimiento: políticas provisionales (aviso de privacidad,
 * cookies, accesibilidad) y las categorías documentales de la normativa que
 * el hospital ya publica en su sitio actual (ordenanzas, decretos, acuerdos,
 * resoluciones, circulares, leyes e informes).
 *
 * Idempotente. Los textos provisionales rigen mientras jurídica adopta los
 * definitivos: se reemplazan creando una versión nueva desde el panel.
 */
final class SembrarCumplimientoCommand extends Command
{
    /** @var array<string, array{string, string}> tipo => [titulo, contenido] */
    private const array POLITICAS = [
        'aviso_privacidad' => [
            'Aviso de privacidad (PROVISIONAL)',
            '<p>La E.S.E. Hospital Rosario Pumarejo de López, con domicilio en Valledupar (Cesar), '
            . 'es responsable del tratamiento de los datos personales recolectados a través de este '
            . 'sitio. Los datos se usan exclusivamente para gestionar solicitudes, trámites y '
            . 'comunicaciones institucionales, conforme a la Ley 1581 de 2012 y a la política de '
            . 'tratamiento de datos publicada en este sitio.</p>'
            . '<p>Puedes ejercer tus derechos de conocer, actualizar, rectificar y suprimir tus datos, '
            . 'o revocar tu autorización, mediante el trámite de protección de datos personales '
            . '(habeas data) o escribiendo a contacto@hrplopez.gov.co.</p>'
            . '<p><strong>Versión provisional</strong>, vigente mientras la oficina jurídica adopta el texto definitivo.</p>',
        ],
        'cookies' => [
            'Política de cookies (PROVISIONAL)',
            '<p>Este sitio usa únicamente cookies esenciales para su funcionamiento: la cookie de '
            . 'sesión del panel administrativo, la de consentimiento de cookies y las preferencias de '
            . 'accesibilidad (tema, contraste y tamaño de letra) guardadas en tu navegador.</p>'
            . '<p>El sitio NO usa cookies de publicidad ni de seguimiento. Si en el futuro se '
            . 'incorporan cookies analíticas, solo se activarán con tu consentimiento expreso a '
            . 'través del aviso de cookies, que puedes modificar en cualquier momento.</p>'
            . '<p><strong>Versión provisional</strong>, vigente mientras la oficina jurídica adopta el texto definitivo.</p>',
        ],
        'accesibilidad' => [
            'Declaración de accesibilidad (PROVISIONAL)',
            '<p>La E.S.E. Hospital Rosario Pumarejo de López se compromete con la accesibilidad '
            . 'digital de este sitio conforme a la Resolución 1519 de 2020 (Anexo 1) y el nivel AA de '
            . 'las WCAG 2.1: navegación completa por teclado, contraste verificado, textos '
            . 'alternativos en las imágenes, y modos de alto contraste, tamaño de letra y tema '
            . 'oscuro disponibles en la barra superior de todas las páginas.</p>'
            . '<p>Si encuentras una barrera de acceso, repórtala por el trámite de PQRSDF o al correo '
            . 'contacto@hrplopez.gov.co: nos ayuda a corregirla.</p>'
            . '<p><strong>Versión provisional</strong>, vigente mientras se completa la evaluación formal de conformidad.</p>',
        ],
    ];

    /** Normativa que el hospital ya publica (inventario del sitio actual). */
    private const array CATEGORIAS_DOCUMENTO = [
        'Ordenanzas', 'Decretos', 'Acuerdos', 'Resoluciones', 'Circulares', 'Leyes',
        'Informes de gestión', 'Estados financieros', 'Planes institucionales', 'Contratación',
    ];

    public function __construct(
        private readonly Connection $conexion,
        private readonly AuditoriaService $auditoria,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('semillas:cumplimiento')
            ->setDescription('Siembra políticas provisionales y categorías documentales (sin tocar lo existente)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $creadas = 0;

        foreach (self::POLITICAS as $tipo => [$titulo, $contenido]) {
            $existe = $this->conexion->fetchOne(
                "SELECT id FROM politicas WHERE tipo = ? AND version = 'provisional-1'",
                [$tipo],
            );

            if ($existe !== false) {
                continue;
            }

            $this->conexion->insert('politicas', [
                'tipo' => $tipo,
                'version' => 'provisional-1',
                'titulo' => $titulo,
                'contenido' => $contenido,
                'vigente_desde' => date('Y-m-d'),
                'activa' => 1,
            ]);
            $creadas++;
        }

        $orden = 0;

        foreach (self::CATEGORIAS_DOCUMENTO as $nombre) {
            $slug = Slugs::desde($nombre, 130);
            $existe = $this->conexion->fetchOne(
                "SELECT id FROM categorias WHERE tipo = 'documento' AND slug = ?",
                [$slug],
            );

            if ($existe !== false) {
                $orden++;
                continue;
            }

            $this->conexion->insert('categorias', [
                'tipo' => 'documento',
                'nombre' => $nombre,
                'slug' => $slug,
                'orden' => $orden++,
            ]);
            $creadas++;
        }

        if ($creadas > 0) {
            $this->auditoria->registrar(new RegistroAuditoria(
                accion: AccionAuditoria::Creacion,
                entidad: 'politicas',
                descripcion: sprintf('Semillas de cumplimiento: %d registros (políticas provisionales y categorías)', $creadas),
                usuarioNombre: 'consola',
            ));
        }

        $io->success(sprintf('%d registros creados.', $creadas));
        $io->warning('Políticas PROVISIONALES: la oficina jurídica adopta las definitivas creando una versión nueva en el panel.');

        return Command::SUCCESS;
    }
}
