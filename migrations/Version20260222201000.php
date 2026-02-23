<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename donation types (meubles->fourniture, argent->money) and add clothes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE type_don SET libelle = 'fourniture' WHERE LOWER(TRIM(libelle)) IN ('meuble', 'meubles')");
        $this->addSql("UPDATE type_don SET libelle = 'money' WHERE LOWER(TRIM(libelle)) IN ('argent')");
        $this->addSql("INSERT INTO type_don (libelle) SELECT 'clothes' WHERE NOT EXISTS (SELECT 1 FROM type_don WHERE LOWER(TRIM(libelle)) = 'clothes')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM type_don WHERE LOWER(TRIM(libelle)) = 'clothes'");
        $this->addSql("UPDATE type_don SET libelle = 'meubles' WHERE LOWER(TRIM(libelle)) = 'fourniture'");
        $this->addSql("UPDATE type_don SET libelle = 'argent' WHERE LOWER(TRIM(libelle)) = 'money'");
    }
}
