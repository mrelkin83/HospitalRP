<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\ServidorPublicoRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directorio de servidores públicos (ITA 1.6 y 1.6.1).
 *
 * No se confunde con `/directorio`, que es el directorio médico. Este
 * responde a la obligación de transparencia: quién ocupa cada cargo, en qué
 * dependencia y con qué remuneración.
 */
final readonly class ServidoresPublicosController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ServidorPublicoRepository $servidores,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Directorio de servidores públicos',
            descripcion: 'Directorio de servidores públicos y contratistas de la E.S.E. Hospital '
                . 'Rosario Pumarejo de López, con cargo, dependencia y escala salarial.',
            contenido: $this->vistas->render('publico/servidores-publicos', [
                'porDependencia' => $this->servidores->publicadosPorDependencia(),
                'total' => $this->servidores->totalPublicados(),
            ]),
        );
    }
}
