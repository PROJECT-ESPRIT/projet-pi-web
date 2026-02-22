<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scanned_at to reservation for ticket scan tracking';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $db = $conn->getDatabase();
        $exists = $conn->fetchOne(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'scanned_at'",
            [$db],
            [ParameterType::STRING]
        );
        if (!$exists) {
            $this->addSql('ALTER TABLE reservation ADD scanned_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP scanned_at');
    }
}
