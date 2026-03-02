<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282370 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft delete flag to donations and charities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity ADD is_hidden TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE donation ADD is_hidden TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP is_hidden');
        $this->addSql('ALTER TABLE charity DROP is_hidden');
    }
}
