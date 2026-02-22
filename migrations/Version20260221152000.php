<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add donation anonymous flag and allow nullable donor reference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation ADD is_anonymous TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE donation CHANGE donateur_id donateur_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP is_anonymous');
        $this->addSql('ALTER TABLE donation CHANGE donateur_id donateur_id INT NOT NULL');
    }
}
