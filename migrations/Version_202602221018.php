<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add description field to charity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity DROP description');
    }
}
