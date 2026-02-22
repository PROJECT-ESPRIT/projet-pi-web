<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile image URL to user profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD profile_image_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP profile_image_url');
    }
}
