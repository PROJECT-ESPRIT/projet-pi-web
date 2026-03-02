<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename charity goal to monetary amount and add donation amount';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity CHANGE goal_donations goal_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE donation ADD amount INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP amount');
        $this->addSql('ALTER TABLE charity CHANGE goal_amount goal_donations INT NOT NULL DEFAULT 10');
    }
}
