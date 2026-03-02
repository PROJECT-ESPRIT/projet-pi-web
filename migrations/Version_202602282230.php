<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add charity goal and anonymous donation flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity ADD goal_donations INT NOT NULL DEFAULT 10');
        $this->addSql('ALTER TABLE donation ADD is_anonymous TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP is_anonymous');
        $this->addSql('ALTER TABLE charity DROP goal_donations');
    }
}
