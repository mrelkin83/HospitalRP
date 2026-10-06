<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Política de derechos de autor (ITA 2.3.c).
 *
 * El numeral 2.3 del Anexo 2 exige cuatro políticas en el pie de TODO sitio de
 * sujeto obligado: términos y condiciones, privacidad y tratamiento de datos,
 * derechos de autor, y las demás que apliquen.
 *
 * El ENUM de `politicas` ya admitía `terminos` y `seguridad` —solo faltaba
 * sembrarlas y darles ruta—, pero `derechos_autor` no existía como tipo, así
 * que hace falta tocar el esquema.
 */
final class Version20261006130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tipo de política «derechos de autor» (ITA 2.3.c)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE politicas
              MODIFY tipo ENUM(
                'tratamiento_datos','aviso_privacidad','cookies','terminos',
                'accesibilidad','seguridad','derechos_autor'
              ) NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Las filas del tipo que desaparece se van primero: MySQL las
        // convertiría en cadena vacía al reducir el ENUM, dejando políticas
        // huérfanas que ninguna ruta sabe mostrar.
        $this->addSql("DELETE FROM politicas WHERE tipo = 'derechos_autor'");
        $this->addSql(<<<'SQL'
            ALTER TABLE politicas
              MODIFY tipo ENUM(
                'tratamiento_datos','aviso_privacidad','cookies','terminos',
                'accesibilidad','seguridad'
              ) NOT NULL
            SQL);
    }
}
