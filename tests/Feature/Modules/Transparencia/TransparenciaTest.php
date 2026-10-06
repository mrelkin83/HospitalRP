<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Transparencia;

use App\Core\Audit\AuditoriaService;
use App\Modules\Transparencia\Command\SembrarTransparenciaCommand;
use App\Modules\Transparencia\Repository\EnteVigilanciaRepository;
use App\Modules\Transparencia\Repository\DatasetRepository;
use App\Modules\Transparencia\Repository\TransparenciaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterios de aceptación de la Fase 4 contra MySQL real.
 */
#[CoversClass(TransparenciaRepository::class)]
#[CoversClass(DatasetRepository::class)]
#[CoversClass(SembrarTransparenciaCommand::class)]
final class TransparenciaTest extends TestCase
{
    private static Connection $conexion;
    private TransparenciaRepository $transparencia;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM dataset_recursos');
        self::$conexion->executeStatement('DELETE FROM datasets');
        self::$conexion->executeStatement('DELETE FROM transparencia_items');
        self::$conexion->executeStatement('DELETE FROM transparencia_secciones');

        $this->transparencia = new TransparenciaRepository(self::$conexion);
    }

    public function testLaSemillaCreaTodaSeccionObligatoriaYElEnlaceASecop(): void
    {
        $this->sembrar();

        // Criterio 1: toda sección obligatoria existe, aunque esté vacía.
        foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $codigo) {
            self::assertNotFalse(
                self::$conexion->fetchOne(
                    'SELECT id FROM transparencia_secciones WHERE codigo = ? AND obligatoria = 1',
                    [$codigo],
                ),
                sprintf('Falta la sección obligatoria %s.', $codigo),
            );
        }

        // Criterio 4: enlace a SECOP presente en contratación.
        $secop = self::$conexion->fetchAssociative(
            "SELECT i.* FROM transparencia_items i
               JOIN transparencia_secciones s ON s.id = i.seccion_id
              WHERE s.codigo LIKE '3%' AND i.url_externa LIKE '%colombiacompra%'",
        );
        self::assertNotFalse($secop, 'El enlace a SECOP no está en la sección de contratación.');

        // Y es idempotente: correrla otra vez no duplica nada.
        $total = (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM transparencia_secciones');
        $this->sembrar();
        self::assertSame($total, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM transparencia_secciones'));
    }

    public function testElAdministradorVeDeUnVistazoLasSeccionesVacias(): void
    {
        $this->sembrar();

        // Recién sembrado: casi todo está vacío (3.2 tiene el ítem de SECOP).
        $vacias = $this->transparencia->obligatoriasVacias();
        $codigosVacios = array_column($vacias, 'codigo');

        self::assertContains('2.1', $codigosVacios);
        self::assertNotContains('3.2', $codigosVacios);

        // Al añadir un ítem publicado, la sección sale del indicador.
        $seccion = (int) self::$conexion->fetchOne("SELECT id FROM transparencia_secciones WHERE codigo = '2.1'");
        $this->transparencia->crearItem([
            'seccion_id' => $seccion,
            'url_externa' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=56882',
            'titulo' => 'Ley 1712 de 2014',
        ]);

        $codigosVacios = array_column($this->transparencia->obligatoriasVacias(), 'codigo');
        self::assertNotContains('2.1', $codigosVacios);
        // Y su padre («2 Normativa») tampoco cuenta como vacío.
        self::assertNotContains('2', $codigosVacios);
    }

    public function testLosRecursosDeDatosAbiertosSonFormatosAbiertosYRespetanLaPublicacion(): void
    {
        $datasets = new DatasetRepository(self::$conexion);

        $id = $datasets->crear([
            'titulo' => 'Producción de servicios 2025',
            'descripcion' => 'Consolidado mensual de atenciones por servicio.',
            'publicado' => 0,
        ]);

        $recursoId = $datasets->crearRecurso([
            'dataset_id' => $id,
            'formato' => 'csv',
            'url_externa' => 'https://www.datos.gov.co/resource/ejemplo.csv',
        ]);

        // El dataset no está publicado: el recurso no existe para el público.
        self::assertNull($datasets->recursoPublico($recursoId));

        $datasets->actualizar($id, ['publicado' => 1]);

        $recurso = $datasets->recursoPublico($recursoId);
        self::assertNotNull($recurso);
        // Criterio 3: formato abierto, no PDF (el enum ni siquiera lo permite).
        self::assertSame('csv', $recurso['formato']);

        $datasets->contarDescarga($recursoId);
        self::assertSame(1, (int) self::$conexion->fetchOne('SELECT descargas FROM dataset_recursos WHERE id = ?', [$recursoId]));
    }

    // --- soporte ---

    private function sembrar(): void
    {
        $tester = new CommandTester(new SembrarTransparenciaCommand(
            self::$conexion,
            new AuditoriaService(self::$conexion),
            new EnteVigilanciaRepository(self::$conexion),
        ));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }
}
