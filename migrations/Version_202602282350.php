<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image path to charity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity ADD image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity DROP image_path');
    }
}
