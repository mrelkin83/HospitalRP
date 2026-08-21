<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Documentos;

use App\Core\Audit\AuditoriaService;
use App\Core\Auth\Actor;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;
use App\Modules\Busqueda\Service\IndexadorService;
use App\Modules\Documentos\Entity\EstadoDocumento;
use App\Modules\Documentos\Exception\PublicacionIncompletaException;
use App\Modules\Documentos\Repository\DocumentoRepository;
use App\Modules\Documentos\Request\DocumentoDatos;
use App\Modules\Documentos\Request\FiltrosDocumento;
use App\Modules\Documentos\Service\DocumentoService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterios de aceptación de la Fase 3, contra MySQL real.
 */
#[CoversClass(DocumentoService::class)]
#[CoversClass(DocumentoRepository::class)]
final class DocumentoServiceTest extends TestCase
{
    private static Connection $conexion;
    private DocumentoRepository $documentos;
    private DocumentoService $servicio;
    private Actor $actor;
    private int $archivoId;
    private int $dependenciaId;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM documento_etiqueta');
        self::$conexion->executeStatement('DELETE FROM documento_versiones');
        self::$conexion->executeStatement('DELETE FROM documentos');
        self::$conexion->executeStatement('DELETE FROM etiquetas');
        self::$conexion->executeStatement("DELETE FROM dependencias WHERE nombre LIKE 'Prueba%'");

        $this->documentos = new DocumentoRepository(self::$conexion);
        $this->servicio = new DocumentoService(
            $this->documentos,
            new AuditoriaService(self::$conexion),
            new IndexadorService(self::$conexion, new IndiceBusquedaRepository(self::$conexion)),
        );
        $this->actor = new Actor(7, 'María Pérez', '203.0.113.7', 'PHPUnit');

        self::$conexion->insert('archivos', [
            'nombre_original' => 'documento.pdf',
            'ruta' => '2026/08/prueba.pdf',
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 1000,
            'hash_sha256' => str_repeat('a', 64),
        ]);
        $this->archivoId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('dependencias', ['nombre' => 'Prueba Jurídica']);
        $this->dependenciaId = (int) self::$conexion->lastInsertId();
    }

    public function testSinDependenciaNoSePuedePublicar(): void
    {
        $id = $this->servicio->crear($this->datos(dependenciaId: null), $this->archivoId, $this->actor);

        try {
            $this->servicio->publicar($id, $this->actor);
            self::fail('Debió rechazar la publicación.');
        } catch (PublicacionIncompletaException $e) {
            self::assertStringContainsString('dependencia responsable', $e->getMessage());
        }

        // Sigue sin publicar y por tanto invisible al público.
        self::assertNull($this->documentos->publicoPorId($id));
    }

    public function testPublicarUnBorradorCompletoLoVuelveVigenteYVisible(): void
    {
        $id = $this->servicio->crear($this->datos(), $this->archivoId, $this->actor);

        $this->servicio->publicar($id, $this->actor);

        $documento = $this->documentos->publicoPorId($id);
        self::assertNotNull($documento);
        self::assertSame(EstadoDocumento::Vigente, $documento->estado);
    }

    public function testElDerogadoNoApareceEnLosResultadosPorDefecto(): void
    {
        $vigente = $this->servicio->crear($this->datos(titulo: 'Resolución vigente de tarifas'), $this->archivoId, $this->actor);
        $derogado = $this->servicio->crear($this->datos(titulo: 'Resolución derogada de tarifas'), $this->archivoId, $this->actor);

        $this->servicio->publicar($vigente, $this->actor);
        $this->servicio->publicar($derogado, $this->actor);
        $this->servicio->cambiarEstado($derogado, EstadoDocumento::Derogado, $this->actor);

        $porDefecto = $this->documentos->buscarPublicos($this->filtros([]));
        self::assertSame(1, $porDefecto['total']);
        self::assertSame($vigente, $porDefecto['documentos'][0]->id);

        // Pidiéndolos a propósito, sí aparecen.
        $derogados = $this->documentos->buscarPublicos($this->filtros(['estado' => 'derogado']));
        self::assertSame(1, $derogados['total']);
        self::assertSame($derogado, $derogados['documentos'][0]->id);

        // Y su ficha sigue accesible, con enlace al que lo reemplaza.
        self::assertNotNull($this->documentos->publicoPorId($derogado));
    }

    public function testNuevaVersionActualizaElArchivoYConservaElHistorial(): void
    {
        $id = $this->servicio->crear($this->datos(), $this->archivoId, $this->actor);

        self::$conexion->insert('archivos', [
            'nombre_original' => 'documento-v2.pdf',
            'ruta' => '2026/08/prueba-v2.pdf',
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 2000,
            'hash_sha256' => str_repeat('b', 64),
        ]);
        $archivoV2 = (int) self::$conexion->lastInsertId();

        $version = $this->servicio->nuevaVersion($id, $archivoV2, 'Corrección de anexos', $this->actor);

        self::assertSame(2, $version);
        self::assertSame($archivoV2, $this->documentos->porId($id)?->archivoId);

        $historial = $this->documentos->versionesDe($id);
        self::assertCount(2, $historial);
        self::assertSame('Corrección de anexos', $historial[0]['notas']);
        self::assertSame($this->archivoId, $historial[1]['archivo_id']);
    }

    /**
     * Criterio: el buscador responde en menos de 300 ms sobre mil documentos.
     */
    public function testElBuscadorRespondeEnMenosDe300MsSobreMilDocumentos(): void
    {
        $insertar = 'INSERT INTO documentos (titulo, slug, resumen, archivo_id, dependencia_id, fecha_documento, estado, publicado) VALUES ';
        $valores = [];
        $temas = ['contratación', 'tarifas', 'vacunación', 'presupuesto', 'talento humano', 'facturación'];

        for ($i = 1; $i <= 1000; $i++) {
            $tema = $temas[$i % count($temas)];
            $valores[] = sprintf(
                "('Resolución %d sobre %s', 'resolucion-%d', 'Documento de prueba sobre %s del año.', %d, %d, '%d-0%d-15', 'vigente', 1)",
                $i,
                $tema,
                $i,
                $tema,
                $this->archivoId,
                $this->dependenciaId,
                2018 + ($i % 8),
                1 + ($i % 9),
            );
        }

        self::$conexion->executeStatement($insertar . implode(',', $valores));

        $inicio = hrtime(true);
        $resultado = $this->documentos->buscarPublicos($this->filtros(['q' => 'vacunación resolución']));
        $conTexto = (hrtime(true) - $inicio) / 1_000_000;

        $inicio = hrtime(true);
        $this->documentos->buscarPublicos($this->filtros(['dependencia' => (string) $this->dependenciaId, 'anio' => '2020']));
        $conFiltros = (hrtime(true) - $inicio) / 1_000_000;

        self::assertGreaterThan(0, $resultado['total']);
        self::assertLessThan(300, $conTexto, sprintf('La búsqueda de texto tardó %.1f ms.', $conTexto));
        self::assertLessThan(300, $conFiltros, sprintf('La búsqueda con filtros tardó %.1f ms.', $conFiltros));
    }

    // --- soporte ---

    private function datos(
        string $titulo = 'Resolución 123 de 2026',
        ?int $dependenciaId = -1,
    ): DocumentoDatos {
        return new DocumentoDatos(
            titulo: $titulo,
            resumen: 'Resumen del documento de prueba.',
            categoriaId: null,
            dependenciaId: $dependenciaId === -1 ? $this->dependenciaId : $dependenciaId,
            numeroActo: 'Resolución 123',
            fechaDocumento: '2026-08-01',
            vigenciaDesde: null,
            vigenciaHasta: null,
            reemplazaAId: null,
            serieDocumental: null,
            subserieDocumental: null,
            tiempoRetencion: null,
            etiquetas: 'tarifas, contratación',
        );
    }

    /**
     * @param array<string, string> $query
     */
    private function filtros(array $query): FiltrosDocumento
    {
        return FiltrosDocumento::desdePeticion(
            \Symfony\Component\HttpFoundation\Request::create('/documentos', 'GET', $query),
        );
    }
}
