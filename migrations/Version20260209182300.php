<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209182300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_reponse DROP type_reponse');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_reponse ADD type_reponse VARCHAR(50) NOT NULL');
    }
}
