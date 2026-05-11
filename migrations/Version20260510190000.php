<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Symfony-only produit.image column. JavaFX schema does not have it; JavaFX ignores extra columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE produit
            ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE produit DROP COLUMN IF EXISTS image");
    }
}
