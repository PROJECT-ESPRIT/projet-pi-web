<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202603021000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure default donation types exist';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO type_don (libelle) SELECT 'Furniture' WHERE NOT EXISTS (SELECT 1 FROM type_don WHERE libelle = 'Furniture')");
        $this->addSql("INSERT INTO type_don (libelle) SELECT 'Clothes' WHERE NOT EXISTS (SELECT 1 FROM type_don WHERE libelle = 'Clothes')");
        $this->addSql("INSERT INTO type_don (libelle) SELECT 'Money' WHERE NOT EXISTS (SELECT 1 FROM type_don WHERE libelle = 'Money')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM type_don WHERE libelle IN ('Furniture','Clothes','Money')");
    }
}
