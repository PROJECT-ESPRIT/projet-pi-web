<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loyalty points and loyalty level to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD points INT NOT NULL DEFAULT 0, ADD loyalty_level VARCHAR(20) NOT NULL DEFAULT 'BRONZE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP points, DROP loyalty_level');
    }
}
