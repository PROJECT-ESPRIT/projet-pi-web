<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202603021200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename donation type Fourniture to Furniture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE type_don SET libelle = 'Furniture' WHERE libelle = 'Fourniture'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE type_don SET libelle = 'Fourniture' WHERE libelle = 'Furniture'");
    }
}
