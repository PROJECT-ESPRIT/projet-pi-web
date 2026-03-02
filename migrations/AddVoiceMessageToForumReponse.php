<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class AddVoiceMessageToForumReponse extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add voice_message column to forum_reponse table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_reponse ADD voice_message VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_reponse DROP voice_message');
    }
}
