<?php
// Las tres diapositivas del hero, como en la maqueta aprobada: fachada de
// urgencias con el equipo, consulta externa y atención del parto. Las fotos
// ya están en la mediateca (Portafolio 2026), así que esto solo las asigna.
$c = require 'config/migraciones-bd.php';
$pdo = new PDO("mysql:host={$c['host']};dbname={$c['dbname']};charset=utf8mb4", $c['user'], $c['password'] ?? '');

$slides = [
    [49, 'Atención humana', 'y segura',
     'Urgencias, hospitalización, cirugía y atención materno-infantil para Valledupar y el departamento del Cesar.',
     'Hospital público · Cesar', 'Solicitar cita', '/citas', 'Nuestros servicios', '/servicios', '/servicios/urgencias-24-horas'],
    [50, 'Consulta externa', 'con cita previa',
     'Medicina general y especializada en 21 consultorios, de lunes a viernes.',
     'Atención al usuario', 'Solicitar cita', '/citas', 'Ver horarios', '/contacto', '/servicios/consulta-externa'],
    [53, 'Atención del parto', 'y del recién nacido',
     'Somos el principal centro materno-infantil del departamento del Cesar, bajo la estrategia IAMII.',
     'Materno-infantil', 'Conocer el servicio', '/servicios/atencion-del-parto', 'Nuestros programas', '/programas', '/servicios/atencion-del-parto'],
];

$pdo->exec('DELETE FROM heroes');
$ins = $pdo->prepare(
    'INSERT INTO heroes (pildora, titulo, titulo_degradado, eslogan, boton1_texto, boton1_url,
         boton2_texto, boton2_url, imagen_id, enlace_imagen, mostrar_tarjeta, orden, visible, creado_en)
     VALUES (?,?,?,?,?,?,?,?,?,?,0,?,1,NOW())'
);

foreach ($slides as $i => $s) {
    [$img, $t1, $t2, $eslogan, $pildora, $b1t, $b1u, $b2t, $b2u, $enlace] = $s;
    $ins->execute([$pildora, $t1, $t2, $eslogan, $b1t, $b1u, $b2t, $b2u, $img, $enlace, $i]);
}

foreach ($pdo->query('SELECT orden, titulo, titulo_degradado, imagen_id, enlace_imagen FROM heroes ORDER BY orden') as $r) {
    echo "  {$r['orden']}. {$r['titulo']} {$r['titulo_degradado']}  img=#{$r['imagen_id']}  -> {$r['enlace_imagen']}\n";
}
