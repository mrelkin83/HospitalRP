<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Indicadores\Repository\IndicadorRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Información estadística de la entidad: contratación, ocupación hospitalaria
 * y estadísticas vitales.
 *
 * Son cifras AGREGADAS que publica el hospital. No hay aquí ningún dato de un
 * paciente concreto, ni lo habrá: la regla §2.6 de CLAUDE.md no distingue
 * entre mostrar una historia clínica y dejar que se deduzca de una tabla
 * demasiado fina.
 *
 * Cada cifra sale acompañada de su periodo y su fuente. Un número de
 * ocupación sin decir a qué mes corresponde y de dónde salió no informa:
 * invita a que lo interpreten mal.
 */
final readonly class EstadisticasController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private IndicadorRepository $indicadores,
    ) {
    }

    public function mostrar(Request $peticion): Response
    {
        $conDatos = $this->indicadores->gruposConDatos();
        $grupos = [];

        foreach (IndicadorRepository::GRUPOS as $codigo => $titulo) {
            if (!in_array($codigo, $conDatos, true)) {
                continue;
            }

            $indicadores = $this->indicadores->ultimosPorGrupo($codigo);

            foreach ($indicadores as $i => $indicador) {
                $indicadores[$i]['serie'] = $this->indicadores->serie((string) $indicador['slug']);
            }

            $grupos[$codigo] = ['titulo' => $titulo, 'indicadores' => $indicadores];
        }

        return $this->layout->responder(
            titulo: 'Información estadística',
            descripcion: 'Indicadores de contratación, ocupación hospitalaria y estadísticas vitales '
                . 'de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/estadisticas', [
                'grupos' => $grupos,
            ]),
        );
    }
}
