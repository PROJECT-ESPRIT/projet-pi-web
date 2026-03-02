<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282360 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make charity goal optional and add donation image path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity MODIFY goal_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE donation ADD image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP image_path');
        $this->addSql('ALTER TABLE charity MODIFY goal_amount INT NOT NULL DEFAULT 10');
    }
}
