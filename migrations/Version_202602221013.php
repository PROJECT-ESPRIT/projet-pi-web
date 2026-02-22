<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
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
        $conn = $this->connection;
        $db = method_exists($conn, 'getDatabase') ? $conn->getDatabase() : ($conn->getParams()['dbname'] ?? '');
        $exists = $conn->fetchOne(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'user' AND COLUMN_NAME = 'profile_image_url'",
            [$db],
            [ParameterType::STRING]
        );
        if (!$exists) {
            $this->addSql('ALTER TABLE user ADD profile_image_url VARCHAR(500) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP profile_image_url');
    }
}
