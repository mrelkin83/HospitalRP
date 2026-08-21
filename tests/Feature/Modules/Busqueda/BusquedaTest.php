<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Busqueda;

use App\Modules\Busqueda\Entity\TipoIndice;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;
use App\Modules\Busqueda\Service\BuscadorService;
use App\Modules\Busqueda\Service\IndexadorService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de aceptación de la Fase 7: buscar «certificado laboral»
 * devuelve el trámite de certificados; publicar deja buscable sin correr
 * nada a mano; lo que no da resultados queda registrado para crear la FAQ.
 */
#[CoversClass(IndexadorService::class)]
#[CoversClass(BuscadorService::class)]
#[CoversClass(IndiceBusquedaRepository::class)]
final class BusquedaTest extends TestCase
{
    private static Connection $conexion;
    private IndexadorService $indexador;
    private BuscadorService $buscador;
    private IndiceBusquedaRepository $indice;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        // Los trámites de otros tests referencian estados y tipos: se
        // limpian de hoja a raíz para no chocar con las FK RESTRICT.
        foreach ([
            'tramite_valores', 'tramite_seguimiento', 'tramite_adjuntos', 'tramites',
            'tramite_campos', 'tramite_estados',
            'indice_busqueda', 'busquedas_log', 'tipos_tramite', 'noticias', 'servicios', 'faq',
        ] as $tabla) {
            self::$conexion->executeStatement("DELETE FROM {$tabla}");
        }

        $this->indice = new IndiceBusquedaRepository(self::$conexion);
        $this->indexador = new IndexadorService(self::$conexion, $this->indice);
        $this->buscador = new BuscadorService($this->indice);
    }

    public function testCertificadoLaboralDevuelveElTramiteDeCertificados(): void
    {
        // La descripción usa el PLURAL a propósito: el buscador debe casar
        // «certificado laboral» por prefijo, sin exigir la forma exacta.
        self::$conexion->insert('tipos_tramite', [
            'nombre' => 'Solicitud de certificados',
            'slug' => 'certificados',
            'descripcion' => 'Certificados laborales, de atención o de vinculación expedidos por el hospital.',
            'activo' => 1,
        ]);
        self::$conexion->insert('noticias', [
            'slug' => 'jornada-laboral',
            'titulo' => 'Jornada de bienestar laboral',
            'resumen' => 'El personal recibió su certificado de participación en la jornada laboral.',
            'contenido' => '<p>Detalle de la jornada laboral y entrega de certificado.</p>',
            'estado_aprobacion' => 'publicado',
        ]);

        $this->indexador->reconstruirTodo();
        $resultado = $this->buscador->buscar('certificado laboral');

        self::assertGreaterThan(0, $resultado['total']);

        // El trámite encabeza los grupos aunque la noticia también coincida.
        $primerGrupo = array_key_first($resultado['grupos']);
        self::assertSame('tramite_tipo', $primerGrupo);
        self::assertSame(
            '/atencion-ciudadania/tramites/certificados',
            $resultado['grupos']['tramite_tipo']['resultados'][0]['url'],
        );
    }

    public function testReindexarPorEventoMeteYSacaDelIndice(): void
    {
        self::$conexion->insert('servicios', [
            'nombre' => 'Terapia respiratoria',
            'slug' => 'terapia-respiratoria',
            'descripcion' => 'Atención de terapia respiratoria para pacientes hospitalizados.',
            'activo' => 1,
        ]);
        $id = (int) self::$conexion->lastInsertId();

        $this->indexador->reindexar(TipoIndice::Servicio, $id);
        self::assertSame(1, $this->contarIndice('servicio', $id));

        // Desactivado deja de ser visible: la misma llamada lo saca.
        self::$conexion->update('servicios', ['activo' => 0], ['id' => $id]);
        $this->indexador->reindexar(TipoIndice::Servicio, $id);
        self::assertSame(0, $this->contarIndice('servicio', $id));
    }

    public function testElHtmlSeIndexaComoTextoPlano(): void
    {
        self::$conexion->insert('noticias', [
            'slug' => 'telemedicina',
            'titulo' => 'Nuevo servicio de orientación',
            'resumen' => 'Orientación telefónica.',
            'contenido' => '<p>Atención de <strong>neumología</strong> con enfoque preventivo.</p>',
            'estado_aprobacion' => 'publicado',
        ]);

        $this->indexador->reconstruirTodo();

        $contenido = self::$conexion->fetchOne(
            "SELECT contenido FROM indice_busqueda WHERE tipo = 'noticia'",
        );
        self::assertSame('Atención de neumología con enfoque preventivo.', $contenido);
    }

    public function testLaBusquedaSinResultadoQuedaRegistrada(): void
    {
        $resultado = $this->buscador->buscar('resonancia magnética');

        self::assertSame(0, $resultado['total']);

        $sinResultado = $this->indice->terminosSinResultado();
        self::assertCount(1, $sinResultado);
        self::assertSame('resonancia magnética', $sinResultado[0]['termino']);
        self::assertSame(1, (int) $sinResultado[0]['veces']);
    }

    public function testUnTerminoDeMenosDeTresLetrasNoConsultaNiRegistra(): void
    {
        $resultado = $this->buscador->buscar('  ab ');

        self::assertSame(0, $resultado['total']);
        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM busquedas_log'));
    }

    public function testLaFaqPublicadaEntraAlIndiceYLaOcultaNo(): void
    {
        self::$conexion->insert('faq', ['pregunta' => '¿Cómo agendo una cita de oftalmología?', 'respuesta' => 'Llama al call center.', 'publicada' => 1]);
        self::$conexion->insert('faq', ['pregunta' => '¿Oftalmología atiende sábados?', 'respuesta' => 'Borrador interno.', 'publicada' => 0]);

        $this->indexador->reconstruirTodo();
        $resultado = $this->buscador->buscar('oftalmología');

        self::assertSame(1, $resultado['total']);
        self::assertSame(
            '/atencion-ciudadania/preguntas-frecuentes',
            $resultado['grupos']['faq']['resultados'][0]['url'],
        );
    }

    // --- soporte ---

    private function contarIndice(string $tipo, int $entidadId): int
    {
        return (int) self::$conexion->fetchOne(
            'SELECT COUNT(*) FROM indice_busqueda WHERE tipo = ? AND entidad_id = ?',
            [$tipo, $entidadId],
        );
    }
}
