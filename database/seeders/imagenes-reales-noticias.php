<?php

declare(strict_types=1);

/**
 * Cambia los marcadores genéricos de las noticias por fotografía real.
 *
 * Las 27 noticias traían imagen, pero era un `portada-*.jpg` de la semilla:
 * un rectángulo azul con un icono tenue. Se veía como un hueco de color, y
 * arrastraba también a «Nuestras instalaciones» de la portada, que toma sus
 * imágenes de las mismas noticias.
 *
 * La fotografía real ya está en la mediateca desde el Portafolio de Servicios
 * 2026. Este sembrador la reparte: primero por palabra clave del titular
 * —una noticia de urgencias se lleva la foto de urgencias— y lo que no casa,
 * por reparto cíclico, para que no se repita la misma foto seguida.
 *
 * Es idempotente: solo toca las noticias cuya imagen actual sea un marcador.
 * Lo que el hospital haya subido a mano se queda como está.
 */

$config = require __DIR__ . '/../../config/migraciones-bd.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
    $config['user'],
    $config['password'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

/** Palabra del titular => fragmento del nombre de la fotografía. */
const TEMAS = [
    'urgencia' => 'urgencias-24-horas',
    'vacun' => 'consulta-externa',
    'cirug' => 'cirugia',
    'quirúrg' => 'cirugia',
    'parto' => 'atencion-del-parto',
    'lactancia' => 'banco-de-leche-humana',
    'leche' => 'banco-de-leche-humana',
    'canguro' => 'programa-madre-canguro',
    'mental' => 'atencion-en-salud-mental',
    'imagen' => 'imagenologia',
    'resonancia' => 'resonancia-magnetica',
    'ecograf' => 'ecografia-obstetrica-3d-y-4d',
    'ambulancia' => 'transporte-asistencial',
    'traslado' => 'transporte-asistencial',
    'hospitaliza' => 'hospitalizacion',
    'cita' => 'consulta-externa',
    'consulta' => 'consulta-externa',
    'docencia' => 'pagina-docencia',
    'capacita' => 'pagina-docencia',
    'formación' => 'pagina-docencia',
];

// Catálogo de fotografía real: los webp del Portafolio.
$fotos = [];
foreach ($pdo->query('SELECT id, nombre_original FROM archivos WHERE nombre_original LIKE "%.webp" ORDER BY id') as $fila) {
    $fotos[(int) $fila['id']] = (string) $fila['nombre_original'];
}

if ($fotos === []) {
    exit("No hay fotografía del Portafolio en la mediateca. Nada que repartir.\n");
}

$idsFotos = array_keys($fotos);

// Solo las noticias que hoy llevan un marcador genérico.
$pendientes = $pdo->query(
    'SELECT n.id, n.titulo
       FROM noticias n
       JOIN archivos a ON a.id = n.imagen_id
      WHERE a.nombre_original LIKE "portada-%"
      ORDER BY n.id'
)->fetchAll(PDO::FETCH_ASSOC);

$asignar = $pdo->prepare('UPDATE noticias SET imagen_id = ? WHERE id = ?');
$porTema = 0;
$porTurno = 0;
$turno = 0;

foreach ($pendientes as $noticia) {
    $titulo = mb_strtolower($noticia['titulo']);
    $elegida = null;

    foreach (TEMAS as $palabra => $fragmento) {
        if (!str_contains($titulo, $palabra)) {
            continue;
        }

        foreach ($fotos as $id => $nombre) {
            if (str_contains($nombre, $fragmento)) {
                $elegida = $id;
                break 2;
            }
        }
    }

    if ($elegida === null) {
        $elegida = $idsFotos[$turno % count($idsFotos)];
        $turno++;
        $porTurno++;
    } else {
        $porTema++;
    }

    $asignar->execute([$elegida, $noticia['id']]);
    printf("  #%-3s %-46s -> %s\n", $noticia['id'], mb_substr($noticia['titulo'], 0, 44), $fotos[$elegida]);
}

printf("\n  %d noticias actualizadas (%d por tema, %d por reparto)\n", count($pendientes), $porTema, $porTurno);
