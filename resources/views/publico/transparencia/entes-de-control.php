<?php
/**
 * /transparencia/entes-de-control — Espera: $entes (list<array<string,mixed>>).
 *
 * ITA 3.13, siete literales por ente: nombre (a), dirección (b), teléfono (c),
 * correo (d), enlace al sitio (e), tipo de control (f) y mecanismos internos de
 * supervisión (g).
 *
 * Va como fichas y no como tabla: siete columnas no caben en un celular, y
 * CLAUDE.md §6 prohíbe tablas anchas en móvil. Cada ficha es una lista de
 * definiciones, que es lo que esto es de verdad.
 */
use App\Modules\Transparencia\Entity\TipoControl;

$soloDigitos = static fn (string $v): string => preg_replace('/\D+/', '', $v) ?? '';
?>
<div class="mx-auto max-w-5xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/transparencia">Transparencia</a>
    </nav>

    <h1 class="mt-4">Entes y autoridades que nos vigilan</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Estas son las autoridades que inspeccionan, vigilan y controlan a la
        E.S.E. Hospital Rosario Pumarejo de López. Si quieres presentar una
        queja sobre la entidad, puedes hacerlo ante cualquiera de ellas, además
        de nuestro canal de <a href="/atencion-ciudadania/pqrsdf">PQRSDF</a>.
    </p>

    <?php if ($entes === []) { ?>
        <p class="mt-8 texto-suave">Esta información está en actualización.</p>
    <?php } else { ?>
        <ul class="mt-8 flex flex-col gap-4">
            <?php foreach ($entes as $ente) {
                $tipo = TipoControl::tryFrom((string) $ente['tipo_control']); ?>
                <li>
                    <article class="tarjeta">
                        <h2 class="text-base">
                            <?php if (!empty($ente['url'])) { ?>
                                <a href="<?= $e((string) $ente['url']) ?>" target="_blank" rel="noopener"><?= $e((string) $ente['nombre']) ?></a>
                            <?php } else { ?>
                                <?= $e((string) $ente['nombre']) ?>
                            <?php } ?>
                        </h2>

                        <p class="mt-1 text-sm font-semibold">
                            <?= $e($tipo?->etiqueta() ?? 'Control') ?>
                            <?php if ($tipo !== null) { ?>
                                <span class="font-normal texto-suave">— <?= $e($tipo->descripcion()) ?></span>
                            <?php } ?>
                        </p>

                        <dl class="mt-3 flex flex-col gap-1 text-sm">
                            <?php if (!empty($ente['direccion'])) { ?>
                                <div>
                                    <dt class="inline font-semibold">Dirección:</dt>
                                    <dd class="inline"><?= $e((string) $ente['direccion']) ?></dd>
                                </div>
                            <?php } ?>
                            <?php if (!empty($ente['telefono'])) { ?>
                                <div>
                                    <dt class="inline font-semibold">Teléfono:</dt>
                                    <dd class="inline">
                                        <a href="tel:<?= $e($soloDigitos((string) $ente['telefono'])) ?>"><?= $e((string) $ente['telefono']) ?></a>
                                    </dd>
                                </div>
                            <?php } ?>
                            <?php if (!empty($ente['correo'])) { ?>
                                <div>
                                    <dt class="inline font-semibold">Correo:</dt>
                                    <dd class="inline">
                                        <a href="mailto:<?= $e((string) $ente['correo']) ?>"><?= $e((string) $ente['correo']) ?></a>
                                    </dd>
                                </div>
                            <?php } ?>
                            <?php if (!empty($ente['url'])) { ?>
                                <div>
                                    <dt class="inline font-semibold">Sitio web:</dt>
                                    <dd class="inline">
                                        <a href="<?= $e((string) $ente['url']) ?>" target="_blank" rel="noopener"><?= $e((string) $ente['url']) ?></a>
                                    </dd>
                                </div>
                            <?php } ?>
                            <?php if (!empty($ente['mecanismos'])) { ?>
                                <div class="mt-1">
                                    <dt class="font-semibold">Mecanismo de supervisión y notificación:</dt>
                                    <dd><?= $e((string) $ente['mecanismos']) ?></dd>
                                </div>
                            <?php } ?>
                        </dl>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <p class="mt-8 text-sm texto-suave">
        Publicado conforme al numeral 3.13 del Anexo 2 de la Resolución 1519 de 2020.
    </p>
</div>
