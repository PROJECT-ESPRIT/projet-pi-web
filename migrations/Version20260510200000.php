<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Seed the donation_type table with 'Money' and 'Furniture' rows so Symfony's donation form whitelist (config/donation_types.json) can find them. Idempotent — only inserts if missing.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO donation_type (name, picture_path)
            SELECT 'Money', NULL
            WHERE NOT EXISTS (SELECT 1 FROM donation_type WHERE name = 'Money')");
        $this->addSql("INSERT INTO donation_type (name, picture_path)
            SELECT 'Furniture', NULL
            WHERE NOT EXISTS (SELECT 1 FROM donation_type WHERE name = 'Furniture')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM donation_type WHERE name IN ('Money', 'Furniture')");
    }
}
