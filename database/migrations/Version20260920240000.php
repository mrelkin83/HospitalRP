<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catálogo de bancos para la cuenta de consignación del contratista.
 *
 * El banco se escribía a mano, y de ahí salían «Bancolombia», «BANCOLOMBIA
 * S.A.» y «bancolombia» para la misma entidad: tres formas distintas en la
 * autorización de consignación del formato GF-FR-002, que es un documento
 * con el que Tesorería paga.
 *
 * Va en tabla y no en un array del código porque la lista cambia —entran
 * entidades nuevas, otras se fusionan— y eso lo debe poder arreglar
 * Contratación desde el panel (CLAUDE.md §2.2), no un despliegue.
 *
 * La semilla son los establecimientos bancarios vigilados por la
 * Superintendencia Financiera de Colombia más usados para nómina; no
 * pretende ser exhaustiva y por eso la pantalla de parámetros permite
 * agregar y retirar.
 */
final class Version20260920240000 extends AbstractMigration
{
    private const array BANCOS = [
        'Bancolombia',
        'Banco de Bogotá',
        'Davivienda',
        'BBVA Colombia',
        'Banco de Occidente',
        'Banco Popular',
        'Banco Agrario de Colombia',
        'Banco Caja Social',
        'Banco AV Villas',
        'Scotiabank Colpatria',
        'Banco Itaú',
        'Banco GNB Sudameris',
        'Banco Pichincha',
        'Banco Falabella',
        'Banco Serfinanza',
        'Banco Finandina',
        'Banco W',
        'Bancamía',
        'Banco Mundo Mujer',
        'Banco Contactar',
        'Banco Cooperativo Coopcentral',
        'Banco Unión',
        'Lulo Bank',
        'Banco Credifinanciera (Ban100)',
        'Citibank Colombia',
        'Banco Santander de Negocios Colombia',
        'Banco BTG Pactual',
        'Confiar Cooperativa Financiera',
        'Cotrafa Cooperativa Financiera',
        'Cooperativa Financiera de Antioquia (CFA)',
        'Financiera Juriscoop',
        'Nequi',
        'Daviplata',
        'Movii',
        'RappiPay',
    ];

    public function getDescription(): string
    {
        return 'Cuentas de cobro: catálogo de bancos para la cuenta de consignación';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_bancos (
              id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre VARCHAR(120) NOT NULL,
              orden  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              activo TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_bancos_nombre (nombre)
            ) ENGINE=InnoDB
            SQL);

        $orden = 0;

        foreach (self::BANCOS as $nombre) {
            $this->addSql('INSERT INTO cc_bancos (nombre, orden) VALUES (?, ?)', [$nombre, ++$orden * 10]);
        }
    }

    public function down(Schema $schema): void
    {
        // El banco de cada contratista es texto en `cc_contratistas`, no una
        // clave foránea: quitar el catálogo no le borra el dato a nadie.
        $this->addSql('DROP TABLE cc_bancos');
    }
}
