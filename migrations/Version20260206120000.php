<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status to user for artist approval flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD status VARCHAR(20) NOT NULL DEFAULT 'APPROVED'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP status');
    }
}
